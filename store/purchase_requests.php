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

        /*
        |--------------------------------------------------------------------------
        | GET POST DATA
        |--------------------------------------------------------------------------
        */

        $materialIds = $_POST['material_id'] ?? [];
        $quantities  = $_POST['quantity'] ?? [];
        $remarks     = trim($_POST['remarks'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | VALIDATE ARRAYS
        |--------------------------------------------------------------------------
        */

        if (!is_array($materialIds) || !is_array($quantities)) {
            throw new Exception('Invalid purchase request data.');
        }

        /*
        |--------------------------------------------------------------------------
        | PREPARE ITEMS
        |--------------------------------------------------------------------------
        */

        $items = [];
        $selectedMaterialIds = [];

        foreach ($materialIds as $index => $materialId) {

            $materialId = (int)$materialId;
            $quantity   = (float)($quantities[$index] ?? 0);

            /*
            | Skip empty material rows
            */
            if ($materialId <= 0) {
                continue;
            }

            /*
            | Quantity validation
            */
            if ($quantity <= 0) {
                throw new Exception(
                    'Quantity must be greater than zero.'
                );
            }

            /*
            | Prevent duplicate materials
            */
            if (in_array($materialId, $selectedMaterialIds, true)) {
                throw new Exception(
                    'The same material cannot be added more than once.'
                );
            }

            $selectedMaterialIds[] = $materialId;

            $items[] = [
                'material_id' => $materialId,
                'quantity'    => $quantity
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK ITEMS
        |--------------------------------------------------------------------------
        */

        if (empty($items)) {
            throw new Exception(
                'Please select at least one material.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK LOGGED-IN USER
        |--------------------------------------------------------------------------
        */

        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new Exception(
                'User session expired. Please login again.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK MATERIALS
        |--------------------------------------------------------------------------
        */

        $materialCheck = $con->prepare("
            SELECT
                id,
                material_code,
                material_name,
                category,
                unit,
                current_stock,
                minimum_stock
            FROM materials
            WHERE id = ?
              AND status = 'Enable'
            LIMIT 1
        ");

        foreach ($items as $item) {

            $materialCheck->execute([
                $item['material_id']
            ]);

            $material = $materialCheck->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                throw new Exception(
                    'One of the selected materials is invalid or disabled.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        $con->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | GENERATE UNIQUE REQUEST NUMBER
        |
        | Example:
        | PR-20260908143025-583
        |--------------------------------------------------------------------------
        */

        $requestNo = null;

        for ($attempt = 1; $attempt <= 10; $attempt++) {

            $candidate =
                'PR-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);

            $checkRequestNo = $con->prepare("
                SELECT id
                FROM purchase_requests
                WHERE request_no = ?
                LIMIT 1
            ");

            $checkRequestNo->execute([
                $candidate
            ]);

            if (!$checkRequestNo->fetch()) {

                $requestNo = $candidate;
                break;
            }
        }

        if ($requestNo === null) {

            /*
            | Extra fallback in the very unlikely case
            */
            $requestNo =
                'PR-' .
                date('YmdHis') .
                '-' .
                random_int(1000, 9999);

            $checkRequestNo = $con->prepare("
                SELECT id
                FROM purchase_requests
                WHERE request_no = ?
                LIMIT 1
            ");

            $checkRequestNo->execute([
                $requestNo
            ]);

            if ($checkRequestNo->fetch()) {
                throw new Exception(
                    'Unable to generate a unique purchase request number. Please try again.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT PURCHASE REQUEST
        |--------------------------------------------------------------------------
        */

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

        if ($requestId <= 0) {
            throw new Exception(
                'Purchase request could not be created.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT PURCHASE REQUEST ITEMS
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

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |
        | DO NOT UPDATE MATERIAL STOCK HERE.
        |
        | Stock will be updated only through:
        | - Stock Inward
        | - Stock Issue
        |--------------------------------------------------------------------------
        */

        $con->commit();

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            "Purchase Request {$requestNo} created successfully."
        );

        header('Location: purchase_requests.php');
        exit;

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        */

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
")->fetchAll(PDO::FETCH_ASSOC);


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
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FETCH ALL PURCHASE REQUEST ITEMS FOR MODAL VIEW
|--------------------------------------------------------------------------
*/

$requestItemsData = $con->query("
    SELECT 
        i.request_id,
        m.material_code,
        m.material_name,
        m.unit,
        i.requested_qty
    FROM purchase_request_items i
    INNER JOIN materials m ON m.id = i.material_id
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$pendingCount   = 0;
$approvedCount  = 0;
$rejectedCount  = 0;
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

        <div class="dashboard-hero">

            <div class="dashboard-heading">

                

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

        <div class="stats-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">

            <!-- Pending -->

            <div class="dash-stat stat-orange">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?= $pendingCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">Awaiting approval</div>
            </div>


            <!-- Approved -->

            <div class="dash-stat stat-green">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Approved</div>
                        <div class="stat-value"><?= $approvedCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="stat-trend">Ready for PO</div>
            </div>


            <!-- Rejected -->

            <div class="dash-stat stat-red">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Rejected</div>
                        <div class="stat-value"><?= $rejectedCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
                <div class="stat-trend down">Not approved</div>
            </div>


            <!-- Completed -->

            <div class="dash-stat stat-cyan">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value"><?= $completedCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-flag-checkered"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">Fully processed</div>
            </div>

        </div>


        <!-- ==========================================================
             CREATE PURCHASE REQUEST
        =========================================================== -->

        <div class="quick-card">

            <div class="section-title">

                <i class="fa-solid fa-cart-plus"></i>

                Create Purchase Request

            </div>

            <div class="text-muted small mb-3">
                Select the materials and enter the required quantity.
            </div>


            <form
                method="POST"
                id="purchaseRequestForm"
            >

                <div id="requestItems">


                    <!-- FIRST ITEM -->

                    <div class="request-item border rounded p-3 mb-3">

                        <div class="row g-3 align-items-end">


                            <!-- MATERIAL -->

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


                            <!-- QUANTITY -->

                            <div class="col-md-3">

                                <label class="form-label">
                                    Required Quantity
                                </label>

                                <input
                                    type="number"
                                    name="quantity[]"
                                    class="form-control quantity-input"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Enter quantity"
                                    required
                                >

                            </div>


                            <!-- REMOVE -->

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


                <!-- ADD MATERIAL / EXCEL UPLOAD -->

                <div class="d-flex flex-wrap gap-2 mb-3">

                    <button
                        type="button"
                        id="addItem"
                        class="btn btn-outline-primary"
                    >
                        <i class="fa-solid fa-plus me-1"></i>
                        Add Material
                    </button>

                    <button
                        type="button"
                        id="uploadExcelBtn"
                        class="btn btn-outline-success"
                    >
                        <i class="fa-solid fa-file-excel me-1"></i>
                        Upload Excel
                    </button>

                    <input
                        type="file"
                        id="excelFile"
                        accept=".xlsx,.xls"
                        style="display:none;"
                    >

                </div>

                <div class="text-muted small mb-3">

                    <i class="fa-solid fa-circle-info me-1"></i>

                    Excel format:

                    <strong>Material Code</strong>,
                    <strong>Material Name</strong>,
                    <strong>Quantity</strong>

                    <br>

                    Example:
                    <strong>MAT-001 | Rice | 10</strong>

                </div>


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
                    id="submitRequestBtn"
                >

                    <i class="fa-solid fa-paper-plane me-1"></i>

                    Submit Purchase Request

                </button>

            </form>

        </div>


        <!-- ==========================================================
             PURCHASE REQUEST HISTORY
        =========================================================== -->

        <div class="dashboard-table">

            <div class="table-header">

                <div>

                    <strong>

                        <i class="fa-solid fa-clock-rotate-left me-2"></i>

                        Purchase Request History

                    </strong>

                    <div class="text-muted small">

                        View previously submitted purchase requests.

                    </div>

                </div>

                <span class="fs-6 fw-small"><?= count($requests) ?> request(s)</span>

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

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($requests)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="empty-state"
                                >

                                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>

                                    <div>
                                        No purchase requests found.
                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($requests as $index => $request): ?>

                                <tr>

                                    <!-- NUMBER -->

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <!-- REQUEST NUMBER -->

                                    <td>

                                        <strong>

                                            <?= e(
                                                $request['request_no']
                                            ) ?>

                                        </strong>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?= e(
                                            date(
                                                'd-m-Y',
                                                strtotime(
                                                    $request['request_date']
                                                )
                                            )
                                        ) ?>

                                    </td>


                                    <!-- REQUESTED BY -->

                                    <td>

                                        <div class="food-cell">

                                            <span class="table-avatar">
                                                <?= e(
                                                    strtoupper(
                                                        substr(
                                                            (string)$request['employee_name'],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                            <span>
                                                <?= e(
                                                    $request['employee_name']
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- ITEMS -->

                                    <td>

                                        <?= (int)$request['item_count'] ?>

                                    </td>


                                    <!-- TOTAL QUANTITY -->

                                    <td>

                                        <?= number_format(
                                            (float)$request['total_qty'],
                                            2
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php

                                        $status = $request['status'];

                                        if ($status === 'Pending') {

                                            $badge =
                                                'status-warning';

                                        } elseif ($status === 'Approved') {

                                            $badge =
                                                'status-success';

                                        } elseif ($status === 'Rejected') {

                                            $badge =
                                                'status-danger';

                                        } elseif ($status === 'Completed') {

                                            $badge =
                                                'status-info';

                                        } else {

                                            $badge =
                                                'status-neutral';
                                        }

                                        ?>

                                        <span
                                            class="status-pill <?= $badge ?>"
                                        >

                                            <?= e($status) ?>

                                        </span>

                                    </td>


                                    <!-- REMARKS -->

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

                                    <!-- ACTIONS -->
                                    <td>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-primary view-request-btn"
                                            data-request-id="<?= (int)$request['id'] ?>"
                                            data-request-no="<?= e($request['request_no']) ?>"
                                            data-request-date="<?= e($request['request_date']) ?>"
                                            data-status="<?= e($request['status']) ?>"
                                            data-remarks="<?= e($request['remarks']) ?>"
                                        >
                                            <i class="fa-solid fa-eye me-1"></i> View
                                        </button>
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


<!-- VIEW REQUEST DETAILS MODAL -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <!-- MODAL HEADER -->
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i> 
                    Purchase Request: <span id="modalRequestNo" class="text-secondary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body p-4">
                
                <!-- METADATA CARD PANEL -->
                <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Request Date</div>
                            <div id="modalRequestDate" class="fw-semibold text-dark">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Status</div>
                            <div><span id="modalRequestStatus" class="status-pill status-neutral">-</span></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Remarks / Reason</div>
                            <div id="modalRequestRemarks" class="fw-semibold text-dark text-truncate" title="">-</div>
                        </div>
                    </div>
                </div>

                <!-- ITEMS TABLE HEADER -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-secondary mb-0">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Requested Materials
                    </h6>
                </div>

                <!-- ITEMS TABLE -->
                <div class="table-responsive border rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase fs-7 text-muted">
                            <tr>
                                <th class="py-3 ps-3" style="width: 60px;">#</th>
                                <th class="py-3">Material Code</th>
                                <th class="py-3">Material Name</th>
                                <th class="py-3 text-end pe-3">Requested Qty</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- MODAL FOOTER -->
            <div class="modal-footer bg-light border-top px-4 py-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const allRequestItems = <?= json_encode($requestItemsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    document.querySelectorAll('.view-request-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const requestId = this.getAttribute('data-request-id');
            const requestNo = this.getAttribute('data-request-no');
            const requestDate = this.getAttribute('data-request-date');
            const status = this.getAttribute('data-status');
            const remarks = this.getAttribute('data-remarks');

            // Set header info
            document.getElementById('modalRequestNo').textContent = requestNo;
            document.getElementById('modalRequestDate').textContent = requestDate;
            
            // Set dynamic status badge inside modal
            const statusSpan = document.getElementById('modalRequestStatus');
            statusSpan.textContent = status;
            statusSpan.className = 'status-pill ';
            if (status === 'Pending') statusSpan.classList.add('status-warning');
            else if (status === 'Approved') statusSpan.classList.add('status-success');
            else if (status === 'Rejected') statusSpan.classList.add('status-danger');
            else if (status === 'Completed') statusSpan.classList.add('status-info');
            else statusSpan.classList.add('status-neutral');

            // Set remarks with title attribute for hover capability
            const remarksEl = document.getElementById('modalRequestRemarks');
            remarksEl.textContent = remarks ? remarks : '-';
            remarksEl.title = remarks ? remarks : '';

            // Filter items for this request
            const tbody = document.getElementById('modalItemsTableBody');
            tbody.innerHTML = '';

            const matchedItems = allRequestItems.filter(item => String(item.request_id) === String(requestId));

            if (matchedItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No materials found for this request.</td></tr>';
            } else {
                matchedItems.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="ps-3 text-muted">${index + 1}</td>
                        <td class="fw-semibold">${item.material_code}</td>
                        <td>${item.material_name}</td>
                        <td class="text-end pe-3 fw-bold text-primary">${parseFloat(item.requested_qty).toFixed(2)} <span class="text-muted fw-normal">${item.unit}</span></td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Show Bootstrap Modal
            const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
            modal.show();
        });
    });
});
</script>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>


<!-- SheetJS Excel Library -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script>

/*
|--------------------------------------------------------------------------
| ADD MATERIAL ROW
|--------------------------------------------------------------------------
*/

document.getElementById('addItem').addEventListener(
    'click',
    function () {

        const container =
            document.getElementById('requestItems');

        const firstItem =
            container.querySelector('.request-item');

        const newItem =
            firstItem.cloneNode(true);


        /*
        | Clear material
        */

        const select =
            newItem.querySelector('.material-select');

        select.value = '';


        /*
        | Clear quantity
        */

        const quantity =
            newItem.querySelector('.quantity-input');

        quantity.value = '';


        container.appendChild(newItem);
    }
);


/*
|--------------------------------------------------------------------------
| REMOVE MATERIAL ROW
|--------------------------------------------------------------------------
*/

document.getElementById('requestItems').addEventListener(
    'click',
    function (event) {

        const button =
            event.target.closest('.remove-item');

        if (!button) {
            return;
        }


        const items =
            document.querySelectorAll('.request-item');


        /*
        | Keep at least one row
        */

        if (items.length <= 1) {

            alert(
                'At least one material is required.'
            );

            return;
        }


        button
            .closest('.request-item')
            .remove();

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

        if (
            !event.target.classList.contains(
                'material-select'
            )
        ) {
            return;
        }


        const selects =
            document.querySelectorAll(
                '.material-select'
            );


        const selectedValues = [];


        selects.forEach(function (select) {

            if (select.value === '') {
                return;
            }


            if (
                selectedValues.includes(
                    select.value
                )
            ) {

                alert(
                    'This material has already been selected.'
                );

                select.value = '';

            } else {

                selectedValues.push(
                    select.value
                );

            }

        });

    }
);


/*
|--------------------------------------------------------------------------
| EXCEL UPLOAD BUTTON
|--------------------------------------------------------------------------
*/

document.getElementById(
    'uploadExcelBtn'
).addEventListener(
    'click',
    function () {

        document.getElementById(
            'excelFile'
        ).click();

    }
);


/*
|--------------------------------------------------------------------------
| EXCEL FILE UPLOAD
|--------------------------------------------------------------------------
*/

document.getElementById(
    'excelFile'
).addEventListener(
    'change',
    function (event) {

        const file =
            event.target.files[0];


        if (!file) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK EXCEL LIBRARY
        |--------------------------------------------------------------------------
        */

        if (
            typeof XLSX === 'undefined'
        ) {

            alert(
                'Excel library could not be loaded. Please check your internet connection.'
            );

            event.target.value = '';

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | READ EXCEL FILE
        |--------------------------------------------------------------------------
        */

        const reader =
            new FileReader();


        reader.onload =
            function (e) {

                try {

                    const data =
                        new Uint8Array(
                            e.target.result
                        );


                    const workbook =
                        XLSX.read(
                            data,
                            {
                                type: 'array'
                            }
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | FIRST SHEET
                    |--------------------------------------------------------------------------
                    */

                    const sheetName =
                        workbook.SheetNames[0];


                    const worksheet =
                        workbook.Sheets[
                            sheetName
                        ];


                    /*
                    |--------------------------------------------------------------------------
                    | CONVERT SHEET TO JSON
                    |--------------------------------------------------------------------------
                    */

                    const rows =
                        XLSX.utils.sheet_to_json(
                            worksheet,
                            {
                                defval: ''
                            }
                        );


                    if (!rows.length) {

                        alert(
                            'The Excel file is empty.'
                        );

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | GET DATABASE MATERIALS
                    |
                    | PHP materials array is converted to JavaScript.
                    |--------------------------------------------------------------------------
                    */

                    const materials =
                        <?= json_encode(
                            $materials,
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        ) ?>;


                    /*
                    |--------------------------------------------------------------------------
                    | NORMALIZE TEXT
                    |--------------------------------------------------------------------------
                    */

                    function normalizeText(value) {

                        return String(
                            value ?? ''
                        )
                        .trim()
                        .toLowerCase();

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | NORMALIZE EXCEL HEADER
                    |--------------------------------------------------------------------------
                    */

                    function getColumn(
                        row,
                        possibleNames
                    ) {

                        const keys =
                            Object.keys(row);


                        for (
                            const key of keys
                        ) {

                            const normalizedKey =
                                normalizeText(
                                    key
                                );


                            for (
                                const possibleName
                                of possibleNames
                            ) {

                                if (
                                    normalizedKey ===
                                    normalizeText(
                                        possibleName
                                    )
                                ) {

                                    return row[key];

                                }

                            }

                        }


                        return '';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CURRENTLY SELECTED MATERIALS
                    |--------------------------------------------------------------------------
                    */

                    const existingSelections =
                        new Set();


                    document
                        .querySelectorAll(
                            '.material-select'
                        )
                        .forEach(function (select) {

                            if (select.value !== '') {

                                existingSelections.add(
                                    select.value
                                );

                            }

                        });


                    /*
                    |--------------------------------------------------------------------------
                    | RESULT COUNTERS
                    |--------------------------------------------------------------------------
                    */

                    let addedCount = 0;

                    let skippedCount = 0;

                    const errors = [];


                    /*
                    |--------------------------------------------------------------------------
                    | PROCESS EACH EXCEL ROW
                    |--------------------------------------------------------------------------
                    */

                    rows.forEach(
                        function (
                            row,
                            rowIndex
                        ) {

                            const excelRowNumber =
                                rowIndex + 2;


                            /*
                            | Read columns
                            */

                            const materialCode =
                                String(
                                    getColumn(
                                        row,
                                        [
                                            'Material Code',
                                            'material_code',
                                            'Code'
                                        ]
                                    )
                                ).trim();


                            const materialName =
                                String(
                                    getColumn(
                                        row,
                                        [
                                            'Material Name',
                                            'material_name',
                                            'Name'
                                        ]
                                    )
                                ).trim();


                            const quantityValue =
                                getColumn(
                                    row,
                                    [
                                        'Quantity',
                                        'quantity',
                                        'Required Quantity',
                                        'required_qty'
                                    ]
                                );


                            const quantity =
                                parseFloat(
                                    quantityValue
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | VALIDATE EMPTY VALUES
                            |--------------------------------------------------------------------------
                            */

                            if (
                                materialCode === '' &&
                                materialName === '' &&
                                (
                                    isNaN(quantity) ||
                                    quantity <= 0
                                )
                            ) {

                                return;

                            }


                            if (
                                materialCode === ''
                            ) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Material Code is missing.'
                                );

                                return;

                            }


                            if (
                                materialName === ''
                            ) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Material Name is missing.'
                                );

                                return;

                            }


                            if (
                                isNaN(quantity) ||
                                quantity <= 0
                            ) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Quantity must be greater than zero.'
                                );

                                return;

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | FIND MATERIAL BY CODE
                            |--------------------------------------------------------------------------
                            */

                            const material =
                                materials.find(
                                    function (
                                        item
                                    ) {

                                        return (
                                            normalizeText(
                                                item.material_code
                                            ) ===
                                            normalizeText(
                                                materialCode
                                            )
                                        );

                                    }
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | MATERIAL CODE NOT FOUND
                            |--------------------------------------------------------------------------
                            */

                            if (!material) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Material Code "' +
                                    materialCode +
                                    '" was not found in Material Master.'
                                );

                                return;

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | VALIDATE MATERIAL NAME
                            |--------------------------------------------------------------------------
                            */

                            if (
                                normalizeText(
                                    material.material_name
                                ) !==
                                normalizeText(
                                    materialName
                                )
                            ) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Material Name "' +
                                    materialName +
                                    '" does not match Material Code "' +
                                    materialCode +
                                    '". Database name is "' +
                                    material.material_name +
                                    '".'
                                );

                                return;

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | CHECK DUPLICATE
                            |--------------------------------------------------------------------------
                            */

                            const materialId =
                                String(
                                    material.id
                                );


                            if (
                                existingSelections.has(
                                    materialId
                                )
                            ) {

                                skippedCount++;

                                errors.push(
                                    'Row ' +
                                    excelRowNumber +
                                    ': Material "' +
                                    materialCode +
                                    '" is already selected.'
                                );

                                return;

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | ADD MATERIAL TO EXISTING FORM
                            |--------------------------------------------------------------------------
                            */

                            const container =
                                document.getElementById(
                                    'requestItems'
                                );


                            let targetItem = null;


                            /*
                            | Find an empty row first
                            */

                            const requestItems =
                                container.querySelectorAll(
                                    '.request-item'
                                );


                            requestItems.forEach(
                                function (
                                    item
                                ) {

                                    if (
                                        targetItem !== null
                                    ) {
                                        return;
                                    }


                                    const select =
                                        item.querySelector(
                                            '.material-select'
                                        );

                                    const quantityInput =
                                        item.querySelector(
                                            '.quantity-input'
                                        );


                                    if (
                                        select.value === '' &&
                                        quantityInput.value === ''
                                    ) {

                                        targetItem =
                                            item;

                                    }

                                }
                            );


                            /*
                            | If no empty row exists,
                            | create a new row.
                            */

                            if (
                                targetItem === null
                            ) {

                                const firstItem =
                                    container.querySelector(
                                        '.request-item'
                                    );


                                targetItem =
                                    firstItem.cloneNode(
                                        true
                                    );


                                targetItem
                                    .querySelector(
                                        '.material-select'
                                    )
                                    .value = '';


                                targetItem
                                    .querySelector(
                                        '.quantity-input'
                                    )
                                    .value = '';


                                container.appendChild(
                                    targetItem
                                );

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | SET MATERIAL
                            |--------------------------------------------------------------------------
                            */

                            const select =
                                targetItem.querySelector(
                                    '.material-select'
                                );


                            const quantityInput =
                                targetItem.querySelector(
                                    '.quantity-input'
                                );


                            select.value =
                                materialId;


                            quantityInput.value =
                                quantity;


                            /*
                            | Mark as selected
                            */

                            existingSelections.add(
                                materialId
                            );


                            addedCount++;

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | RESET FILE INPUT
                    |--------------------------------------------------------------------------
                    */

                    event.target.value = '';


                    /*
                    |--------------------------------------------------------------------------
                    | SHOW RESULT
                    |--------------------------------------------------------------------------
                    */

                    let message =
                        'Excel upload completed.\n\n' +
                        'Materials added: ' +
                        addedCount +
                        '\n' +
                        'Rows skipped: ' +
                        skippedCount;


                    /*
                    |--------------------------------------------------------------------------
                    | SHOW ERRORS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        errors.length > 0
                    ) {

                        message +=
                            '\n\nDetails:\n' +
                            errors.join(
                                '\n'
                            );

                    }


                    alert(message);

                } catch (error) {

                    console.error(
                        error
                    );


                    alert(
                        'Unable to read the Excel file. Please check the file format.'
                    );

                }

            };


        reader.readAsArrayBuffer(
            file
        );

    }
);


/*
|--------------------------------------------------------------------------
| FORM VALIDATION
|--------------------------------------------------------------------------
*/

document.getElementById(
    'purchaseRequestForm'
).addEventListener(
    'submit',
    function (event) {

        const selects =
            document.querySelectorAll(
                '.material-select'
            );

        const quantities =
            document.querySelectorAll(
                '.quantity-input'
            );


        let validItems = 0;

        const selectedValues = [];


        for (
            let i = 0;
            i < selects.length;
            i++
        ) {

            const materialId =
                selects[i].value;

            const quantity =
                parseFloat(
                    quantities[i].value
                );


            /*
            |--------------------------------------------------------------------------
            | Ignore completely empty rows
            |--------------------------------------------------------------------------
            */

            if (
                materialId === '' &&
                (
                    isNaN(quantity) ||
                    quantity <= 0
                )
            ) {

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | Material required
            |--------------------------------------------------------------------------
            */

            if (
                materialId === ''
            ) {

                event.preventDefault();

                alert(
                    'Please select a material.'
                );

                selects[i].focus();

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Quantity required
            |--------------------------------------------------------------------------
            */

            if (
                isNaN(quantity) ||
                quantity <= 0
            ) {

                event.preventDefault();

                alert(
                    'Please enter a valid quantity.'
                );

                quantities[i].focus();

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate check
            |--------------------------------------------------------------------------
            */

            if (
                selectedValues.includes(
                    materialId
                )
            ) {

                event.preventDefault();

                alert(
                    'The same material cannot be added more than once.'
                );

                return;

            }


            selectedValues.push(
                materialId
            );

            validItems++;

        }


        /*
        |--------------------------------------------------------------------------
        | AT LEAST ONE MATERIAL
        |--------------------------------------------------------------------------
        */

        if (
            validItems === 0
        ) {

            event.preventDefault();

            alert(
                'Please add at least one material.'
            );

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | CONFIRMATION
        |--------------------------------------------------------------------------
        */

        const confirmed =
            confirm(
                'Are you sure you want to submit this Purchase Request?'
            );


        if (!confirmed) {

            event.preventDefault();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE CLICK
        |--------------------------------------------------------------------------
        */

        const submitButton =
            document.getElementById(
                'submitRequestBtn'
            );


        submitButton.disabled =
            true;


        submitButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-1"></i> Submitting...';

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>