<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Purchase Requests';

$error = null;
$success = flash('success');

/*
|--------------------------------------------------------------------------
| CREATE PURCHASE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $materialIds = $_POST['material_id'] ?? [];
        $quantities  = $_POST['quantity'] ?? [];
        $remarks     = trim($_POST['remarks'] ?? '');

        if (!is_array($materialIds) || !is_array($quantities)) {
            throw new Exception('Invalid purchase request data.');
        }

        $items = [];

        foreach ($materialIds as $index => $materialId) {

            $materialId = (int)$materialId;
            $quantity   = (float)($quantities[$index] ?? 0);

            if ($materialId <= 0) {
                continue;
            }

            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than zero.');
            }

            $items[] = [
                'material_id' => $materialId,
                'quantity'    => $quantity
            ];
        }

        if (empty($items)) {
            throw new Exception('Please add at least one material.');
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK USER
        |--------------------------------------------------------------------------
        */

        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new Exception('User session expired. Please login again.');
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK MATERIALS
        |--------------------------------------------------------------------------
        */

        $materialCheck = $con->prepare("
            SELECT id, material_code, material_name, unit, current_stock
            FROM materials
            WHERE id = ?
              AND status = 'Enable'
        ");

        foreach ($items as $item) {

            $materialCheck->execute([
                $item['material_id']
            ]);

            $material = $materialCheck->fetch();

            if (!$material) {
                throw new Exception(
                    'One of the selected materials is invalid or disabled.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE REQUEST
        |--------------------------------------------------------------------------
        */

        $con->beginTransaction();

        $requestNo =
            'PR-' .
            date('YmdHis') .
            '-' .
            random_int(100, 999);

        $insertRequest = $con->prepare("
            INSERT INTO purchase_requests
            (
                request_no,
                requested_by,
                request_date,
                status,
                remarks
            )
            VALUES
            (
                ?,
                ?,
                CURDATE(),
                'Pending',
                ?
            )
        ");

        $insertRequest->execute([
            $requestNo,
            $userId,
            $remarks !== '' ? $remarks : null
        ]);

        $requestId = (int)$con->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | INSERT REQUEST ITEMS
        |--------------------------------------------------------------------------
        */

        $insertItem = $con->prepare("
            INSERT INTO purchase_request_items
            (
                request_id,
                material_id,
                requested_qty
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ");

        foreach ($items as $item) {

            $insertItem->execute([
                $requestId,
                $item['material_id'],
                $item['quantity']
            ]);
        }

        $con->commit();

        flash(
            'success',
            "Purchase Request {$requestNo} created successfully."
        );

        header('Location: purchase_requests.php');
        exit;

    } catch (Throwable $e) {

        if ($con->inTransaction()) {
            $con->rollBack();
        }

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH ENABLED MATERIALS
|--------------------------------------------------------------------------
*/

$materials = $con->query("
    SELECT
        id,
        material_code,
        material_name,
        category,
        unit,
        current_stock,
        minimum_stock
    FROM materials
    WHERE status = 'Enable'
    ORDER BY material_name ASC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH PURCHASE REQUEST HISTORY
|--------------------------------------------------------------------------
*/

$requests = $con->query("
    SELECT
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,
        u.employee_name,

        COUNT(i.id) AS item_count,

        COALESCE(
            SUM(i.requested_qty),
            0
        ) AS total_qty

    FROM purchase_requests pr

    INNER JOIN users u
        ON u.id = pr.requested_by

    LEFT JOIN purchase_request_items i
        ON i.request_id = pr.id

    GROUP BY
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,
        u.employee_name

    ORDER BY pr.id DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS (for the stat cards)
|--------------------------------------------------------------------------
*/

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$completedCount = 0;

foreach ($requests as $request) {

    switch ($request['status']) {

        case 'Pending':
            $pendingCount++;
            break;

        case 'Approved':
            $approvedCount++;
            break;

        case 'Rejected':
            $rejectedCount++;
            break;

        case 'Completed':
            $completedCount++;
            break;
    }

}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h4 class="mb-1">
                Purchase Requests
            </h4>

            <div class="text-muted">
                Request materials from the store and track approvals.
            </div>

        </div>


        <!-- SUCCESS MESSAGE -->
        <?php if ($success): ?>

            <div class="alert alert-success alert-dismissible fade show">
                <i class="fa-solid fa-circle-check me-2"></i>
                <?= e($success) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>
            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->
        <?php if ($error): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="fa-solid fa-triangle-exclamation me-2"></i>

                <?= e($error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-amber">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?= $pendingCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <div class="stat-label">Approved</div>
                        <div class="stat-value"><?= $approvedCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-purple">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>

                    <div>
                        <div class="stat-label">Rejected</div>
                        <div class="stat-value"><?= $rejectedCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-primary">
                        <i class="fa-solid fa-flag-checkered"></i>
                    </div>

                    <div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value"><?= $completedCount ?></div>
                    </div>

                </div>

            </div>

        </div>


        <!-- ==========================================================
             CREATE PURCHASE REQUEST
        =========================================================== -->

        <div class="content-card mb-4">

            <div class="content-card-header">

                <div>
                    <strong>
                        <i class="fa-solid fa-cart-plus me-2"></i>
                        Create Purchase Request
                    </strong>

                    <div class="text-muted small">
                        Select the materials and required quantities.
                    </div>
                </div>

            </div>


            <div class="content-card-body">

                <form method="POST" id="purchaseRequestForm">

                    <div id="requestItems">

                        <!-- FIRST ITEM -->

                        <div class="request-item border rounded p-3 mb-3">

                            <div class="row g-3 align-items-end">

                                <div class="col-md-7">

                                    <label class="form-label">
                                        Material
                                    </label>

                                    <select
                                        name="material_id[]"
                                        class="form-select material-select"
                                        required
                                    >

                                        <option value="">
                                            --- Select Material ---
                                        </option>

                                        <?php foreach ($materials as $material): ?>

                                            <option
                                                value="<?= (int)$material['id'] ?>"
                                            >

                                                <?= e($material['material_code']) ?>
                                                -
                                                <?= e($material['material_name']) ?>

                                                |
                                                Stock:
                                                <?= number_format(
                                                    (float)$material['current_stock'],
                                                    2
                                                ) ?>

                                                <?= e($material['unit']) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        Quantity
                                    </label>

                                    <input
                                        type="number"
                                        name="quantity[]"
                                        class="form-control"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="Enter quantity"
                                        required
                                    >

                                </div>


                                <div class="col-md-2">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger remove-item w-100"
                                    >
                                        <i class="fa-solid fa-trash me-1"></i>
                                        Remove
                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ADD MATERIAL -->

                    <button
                        type="button"
                        id="addItem"
                        class="btn btn-outline-secondary mb-3"
                    >

                        <i class="fa-solid fa-plus me-1"></i>
                        Add Material

                    </button>


                    <!-- REMARKS -->

                    <div class="mb-3">

                        <label class="form-label">
                            Reason / Requirement
                        </label>

                        <textarea
                            name="remarks"
                            class="form-control"
                            rows="3"
                            placeholder="Enter reason or requirement..."
                        ></textarea>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-paper-plane me-1"></i>
                        Submit Purchase Request

                    </button>

                </form>

            </div>

        </div>


        <!-- ==========================================================
             REQUEST HISTORY
        =========================================================== -->

        <div class="content-card">

            <div class="content-card-header">

                <div>
                    <strong>
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>
                        Purchase Request History
                    </strong>

                    <div class="text-muted small">
                        View previously submitted purchase requests.
                    </div>
                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Request No.</th>

                            <th>Date</th>

                            <th>Requested By</th>

                            <th>Items</th>

                            <th>Total Qty</th>

                            <th>Status</th>

                            <th>Remarks</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($requests)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >

                                    <i class="fa-solid fa-inbox fa-2x mb-2"></i>

                                    <div>
                                        No purchase requests found.
                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($requests as $index => $request): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= e($request['request_no']) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <?= e(
                                            date(
                                                'd-m-Y',
                                                strtotime($request['request_date'])
                                            )
                                        ) ?>

                                    </td>


                                    <td>
                                        <?= e($request['employee_name']) ?>
                                    </td>


                                    <td>
                                        <?= (int)$request['item_count'] ?>
                                    </td>


                                    <td>
                                        <?= number_format(
                                            (float)$request['total_qty'],
                                            2
                                        ) ?>
                                    </td>


                                    <td>

                                        <?php

                                        $status = $request['status'];

                                        if ($status === 'Pending') {
                                            $badge = 'bg-warning text-dark';
                                        } elseif ($status === 'Approved') {
                                            $badge = 'badge-enable';
                                        } elseif ($status === 'Rejected') {
                                            $badge = 'badge-disabled';
                                        } elseif ($status === 'Completed') {
                                            $badge = 'bg-primary';
                                        } else {
                                            $badge = 'bg-secondary';
                                        }

                                        ?>

                                        <span class="badge <?= $badge ?>">

                                            <?= e($status) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if (!empty($request['remarks'])): ?>

                                            <span
                                                title="<?= e($request['remarks']) ?>"
                                            >
                                                <?= e(
                                                    mb_strimwidth(
                                                        $request['remarks'],
                                                        0,
                                                        35,
                                                        '...'
                                                    )
                                                ) ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| ADD MATERIAL ROW
|--------------------------------------------------------------------------
*/

document.getElementById('addItem').addEventListener('click', function () {

    const container = document.getElementById('requestItems');

    const firstItem = container.querySelector('.request-item');

    const newItem = firstItem.cloneNode(true);

    newItem.querySelector('select').value = '';
    newItem.querySelector('input').value = '';

    container.appendChild(newItem);

});


/*
|--------------------------------------------------------------------------
| REMOVE MATERIAL ROW
|--------------------------------------------------------------------------
*/

document.getElementById('requestItems').addEventListener(
    'click',
    function (event) {

        const button = event.target.closest('.remove-item');

        if (!button) {
            return;
        }

        const items =
            document.querySelectorAll('.request-item');

        /*
        | Keep at least one row
        */

        if (items.length <= 1) {

            alert('At least one material is required.');

            return;
        }

        button.closest('.request-item').remove();

    }
);


/*
|--------------------------------------------------------------------------
| PREVENT DUPLICATE MATERIAL SELECTION
|--------------------------------------------------------------------------
*/

document.getElementById('requestItems').addEventListener(
    'change',
    function (event) {

        if (!event.target.classList.contains('material-select')) {
            return;
        }

        const selects =
            document.querySelectorAll('.material-select');

        const selectedValues = [];

        selects.forEach(function (select) {

            if (select.value !== '') {

                if (selectedValues.includes(select.value)) {

                    alert(
                        'This material has already been selected.'
                    );

                    select.value = '';

                } else {

                    selectedValues.push(select.value);

                }

            }

        });

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>