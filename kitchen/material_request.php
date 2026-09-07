<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (
    !in_array(
        $_SESSION['role_name'] ?? '',
        ['Kitchen', 'Super Admin'],
        true
    )
) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Kitchen Material Request';

$success = '';
$error = '';


// =========================================================
// GENERATE KITCHEN REQUEST NUMBER
// =========================================================

function nextKitchenRequestNo(PDO $con): string
{
    $prefix = 'KR-' . date('Ymd') . '-';

    $stmt = $con->prepare(
        "SELECT request_no
         FROM kitchen_requests
         WHERE request_no LIKE ?
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([
        $prefix . '%'
    ]);

    $last = $stmt->fetchColumn();

    if ($last) {

        $number = (int) substr(
            (string) $last,
            -4
        );

        $number++;

    } else {

        $number = 1;
    }

    return $prefix .
        str_pad(
            (string) $number,
            4,
            '0',
            STR_PAD_LEFT
        );
}


// =========================================================
// SUBMIT REQUEST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'submit_request') {

            $materialIds =
                $_POST['material_id'] ?? [];

            $quantities =
                $_POST['requested_qty'] ?? [];

            $remarks =
                trim($_POST['cook_remarks'] ?? '');

            if (
                !is_array($materialIds) ||
                !is_array($quantities)
            ) {
                throw new RuntimeException(
                    'Please select at least one material.'
                );
            }

            $items = [];

            foreach ($materialIds as $index => $materialId) {

                $materialId =
                    (int) $materialId;

                $qty =
                    (float) (
                        $quantities[$index]
                        ?? 0
                    );

                if (
                    $materialId <= 0 ||
                    $qty <= 0
                ) {
                    continue;
                }

                $items[] = [
                    'material_id' => $materialId,
                    'qty' => $qty
                ];
            }

            if (!$items) {

                throw new RuntimeException(
                    'Please select material and enter quantity.'
                );
            }

            $con->beginTransaction();

            $requestNo =
                nextKitchenRequestNo($con);

            $stmt = $con->prepare(
                "INSERT INTO kitchen_requests
                (
                    request_no,
                    requested_by,
                    request_date,
                    status,
                    cook_remarks
                )
                VALUES
                (
                    ?,
                    ?,
                    CURDATE(),
                    'Submitted',
                    ?
                )"
            );

            $stmt->execute([
                $requestNo,
                $_SESSION['user_id'],
                $remarks ?: null
            ]);

            $requestId =
                (int) $con->lastInsertId();


            $itemStmt = $con->prepare(
                "INSERT INTO kitchen_request_items
                (
                    request_id,
                    material_id,
                    requested_qty,
                    approved_qty,
                    issued_qty
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    0,
                    0
                )"
            );


            foreach ($items as $item) {

                $itemStmt->execute([
                    $requestId,
                    $item['material_id'],
                    $item['qty']
                ]);
            }


            $con->commit();

            $success =
                'Kitchen request ' .
                $requestNo .
                ' submitted to Chef.';

        }

    } catch (Throwable $e) {

        if ($con->inTransaction()) {
            $con->rollBack();
        }

        $error =
            $e->getMessage();
    }
}


// =========================================================
// MATERIAL LIST
// =========================================================

$materials = $con
    ->query(
        "SELECT
            id,
            material_code,
            material_name,
            unit,
            current_stock
         FROM materials
         WHERE status = 'Enable'
         ORDER BY material_name"
    )
    ->fetchAll();


// =========================================================
// CURRENT USER REQUESTS
// =========================================================

$stmt = $con->prepare(
    "SELECT
        kr.*,
        u.employee_name
     FROM kitchen_requests kr
     JOIN users u
        ON u.id = kr.requested_by
     WHERE kr.requested_by = ?
     ORDER BY kr.id DESC"
);

$stmt->execute([
    $_SESSION['user_id']
]);

$requests =
    $stmt->fetchAll();


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<main class="main-content">

<?php
require_once __DIR__ . '/../includes/topbar.php';
?>

<div class="page-body">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="mb-1">Kitchen Material Request</h4>

            <p class="text-muted mb-0">
                Cook requests cooking materials from Chef.
            </p>
        </div>

    </div>


    <?php if ($success): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="fa-solid fa-circle-check me-2"></i>

            <?= e($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="fa-solid fa-triangle-exclamation me-2"></i>

            <?= e($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         REQUEST FORM
    ====================================================== -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <div>
                <h5 class="mb-1">
                    Create Material Request
                </h5>

                <small class="text-muted">
                    Select materials and quantities to send to the Chef.
                </small>
            </div>

        </div>

        <div class="content-card-body">

            <form method="post">

                <input
                    type="hidden"
                    name="action"
                    value="submit_request"
                >


                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>

                            <tr>

                                <th width="35%">
                                    Material
                                </th>

                                <th width="20%">
                                    Current Stock
                                </th>

                                <th width="20%">
                                    Quantity Required
                                </th>

                                <th width="15%">
                                    Unit
                                </th>

                                <th width="10%">
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody id="requestRows">

                            <tr>

                                <td>

                                    <select
                                        name="material_id[]"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select Material
                                        </option>

                                        <?php foreach (
                                            $materials
                                            as $material
                                        ): ?>

                                            <option
                                                value="<?= $material['id'] ?>"
                                            >
                                                <?= e(
                                                    $material['material_name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <td>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="-"
                                        readonly
                                    >

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="requested_qty[]"
                                        class="form-control"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    >

                                </td>


                                <td>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="-"
                                        readonly
                                    >

                                </td>


                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="removeRow(this)"
                                    >
                                        Remove
                                    </button>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <button
                    type="button"
                    class="btn btn-outline-secondary mb-3"
                    onclick="addRow()"
                >
                    <i class="fa-solid fa-plus me-1"></i>
                    Add Material
                </button>


                <div class="mb-3">

                    <label class="form-label">
                        Cook Remarks
                    </label>

                    <textarea
                        name="cook_remarks"
                        class="form-control"
                        rows="3"
                        placeholder="Enter cooking requirements..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="fa-solid fa-paper-plane me-1"></i>
                    Submit Request to Chef
                </button>

            </form>

        </div>

    </div>


    <!-- =====================================================
         REQUEST HISTORY
    ====================================================== -->

    <div class="content-card">

        <div class="content-card-header">

            <div>
                <h5 class="mb-1">
                    My Kitchen Requests
                </h5>

                <small class="text-muted">
                    <?= count($requests) ?> request(s) submitted by you
                </small>
            </div>

        </div>

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>Request No</th>

                        <th>Date</th>

                        <th>Status</th>

                        <th>Remarks</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $requests
                    as $request
                ): ?>

                    <?php

                    $status = $request['status'];

                    $badgeClass = 'bg-secondary';

                    if ($status === 'Submitted') {

                        $badgeClass = 'bg-warning text-dark';

                    } elseif ($status === 'Chef Approved') {

                        $badgeClass = 'bg-primary';

                    } elseif ($status === 'Sent to Store') {

                        $badgeClass = 'bg-info text-dark';

                    } elseif ($status === 'Partially Issued') {

                        $badgeClass = 'bg-warning text-dark';

                    } elseif ($status === 'Completed') {

                        $badgeClass = 'badge-enable';

                    } elseif ($status === 'Rejected') {

                        $badgeClass = 'badge-disabled';
                    }

                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e(
                                    $request['request_no']
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $request['request_date']
                            ) ?>
                        </td>

                        <td>

                            <span class="badge <?= $badgeClass ?>">

                                <?= e(
                                    $status
                                ) ?>

                            </span>

                        </td>

                        <td>
                            <?= e(
                                $request['cook_remarks']
                                ?? ''
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$requests): ?>

                    <tr>

                        <td
                            colspan="4"
                            class="text-center text-muted py-4"
                        >
                            No requests found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</main>


<script>

function addRow()
{
    const tbody =
        document.getElementById('requestRows');

    const firstRow =
        tbody.querySelector('tr');

    const newRow =
        firstRow.cloneNode(true);

    newRow
        .querySelectorAll('input')
        .forEach(function(input)
        {
            if (
                input.name ===
                'requested_qty[]'
            ) {
                input.value = '';
            }
        });

    newRow
        .querySelectorAll('select')
        .forEach(function(select)
        {
            select.value = '';
        });

    tbody.appendChild(newRow);
}


function removeRow(button)
{
    const tbody =
        document.getElementById('requestRows');

    if (tbody.children.length > 1)
    {
        button
            .closest('tr')
            .remove();
    }
}

</script>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>