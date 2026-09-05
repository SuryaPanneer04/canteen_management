<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$allowedRoles = ['Kitchen', 'Super Admin'];

if (!in_array($_SESSION['role_name'] ?? '', $allowedRoles, true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Material Request';

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| CREATE MATERIAL REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {

    $materialIds = $_POST['material_id'] ?? [];
    $quantities  = $_POST['quantity'] ?? [];
    $remarks     = trim($_POST['remarks'] ?? '');

    if (!is_array($materialIds) || !is_array($quantities)) {
        $error = 'Please add at least one material.';
    } else {

        $items = [];

        foreach ($materialIds as $index => $materialId) {

            $materialId = (int)$materialId;
            $quantity = isset($quantities[$index])
                ? (float)$quantities[$index]
                : 0;

            if ($materialId <= 0) {
                continue;
            }

            if ($quantity <= 0) {
                $error = 'Quantity must be greater than zero.';
                break;
            }

            $items[] = [
                'material_id' => $materialId,
                'quantity' => $quantity
            ];
        }

        if (!$error && empty($items)) {
            $error = 'Please add at least one material.';
        }

        if (!$error) {

            try {

                $con->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | CREATE REQUEST
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    INSERT INTO purchase_requests
                    (
                        requested_by,
                        status,
                        remarks,
                        created_at
                    )
                    VALUES
                    (
                        :requested_by,
                        'Pending',
                        :remarks,
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':requested_by' => $_SESSION['user_id'],
                    ':remarks' => $remarks !== '' ? $remarks : null
                ]);

                $requestId = (int)$con->lastInsertId();


                /*
                |--------------------------------------------------------------------------
                | ADD REQUEST ITEMS
                |--------------------------------------------------------------------------
                */

                $itemStmt = $con->prepare("
                    INSERT INTO purchase_request_items
                    (
                        request_id,
                        material_id,
                        requested_qty
                    )
                    VALUES
                    (
                        :request_id,
                        :material_id,
                        :requested_qty
                    )
                ");

                foreach ($items as $item) {

                    // Verify material exists and is enabled
                    $checkStmt = $con->prepare("
                        SELECT id
                        FROM materials
                        WHERE id = :id
                          AND status = 'Enable'
                        LIMIT 1
                    ");

                    $checkStmt->execute([
                        ':id' => $item['material_id']
                    ]);

                    if (!$checkStmt->fetch()) {
                        throw new RuntimeException(
                            'One of the selected materials is not available.'
                        );
                    }

                    $itemStmt->execute([
                        ':request_id' => $requestId,
                        ':material_id' => $item['material_id'],
                        ':requested_qty' => $item['quantity']
                    ]);
                }

                $con->commit();

                $success = 'Material request submitted successfully. Request No: #' . $requestId;

            } catch (Throwable $e) {

                if ($con->inTransaction()) {
                    $con->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FETCH MATERIALS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
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
");

$materials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH MY REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->prepare("
    SELECT
        pr.id,
        pr.status,
        pr.remarks,
        pr.created_at,
        COUNT(pri.id) AS item_count,
        COALESCE(SUM(pri.requested_qty), 0) AS total_qty
    FROM purchase_requests pr
    LEFT JOIN purchase_request_items pri
        ON pri.request_id = pr.id
    WHERE pr.requested_by = :requested_by
    GROUP BY
        pr.id,
        pr.status,
        pr.remarks,
        pr.created_at
    ORDER BY pr.id DESC
    LIMIT 20
");

$stmt->execute([
    ':requested_by' => $_SESSION['user_id']
]);

$myRequests = $stmt->fetchAll();


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<div class="main-content">

    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="mb-1">Material Request</h4>

            <p class="text-muted mb-0">
                Request materials from the Store department.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>

    </div>


    <!-- SUCCESS -->
    <?php if ($success): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="fa-solid fa-circle-check me-2"></i>

            <?= e($success) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>


    <!-- ERROR -->
    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="fa-solid fa-circle-exclamation me-2"></i>

            <?= e($error) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>


    <!-- REQUEST FORM -->
    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">
                <i class="fa-solid fa-cart-plus me-2"></i>
                Create Material Request
            </h5>

        </div>


        <div class="card-body">

            <form method="POST" id="requestForm">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="requestItems">

                        <thead class="table-light">

                            <tr>

                                <th style="width: 40%;">
                                    Material
                                </th>

                                <th style="width: 15%;">
                                    Unit
                                </th>

                                <th style="width: 20%;">
                                    Available Stock
                                </th>

                                <th style="width: 15%;">
                                    Required Qty
                                </th>

                                <th style="width: 10%;">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody id="itemRows">

                            <tr class="request-row">

                                <td>

                                    <select name="material_id[]"
                                            class="form-select material-select"
                                            required>

                                        <option value="">
                                            --- Select Material ---
                                        </option>

                                        <?php foreach ($materials as $material): ?>

                                            <option
                                                value="<?= (int)$material['id'] ?>"
                                                data-unit="<?= e($material['unit']) ?>"
                                                data-stock="<?= e((string)$material['current_stock']) ?>"
                                            >

                                                <?= e($material['material_name']) ?>
                                                -
                                                <?= e($material['material_code']) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <td>

                                    <span class="material-unit text-muted">
                                        -
                                    </span>

                                </td>


                                <td>

                                    <span class="material-stock text-muted">
                                        -
                                    </span>

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="quantity[]"
                                        class="form-control"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="0.00"
                                        required
                                    >

                                </td>


                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm remove-row"
                                        disabled
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                    </button>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="3"
                        placeholder="Enter any additional information..."
                    ></textarea>

                </div>


                <div class="d-flex justify-content-between">

                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="addRow"
                    >

                        <i class="fa-solid fa-plus me-1"></i>
                        Add Material

                    </button>


                    <button
                        type="submit"
                        name="create_request"
                        value="1"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-paper-plane me-1"></i>
                        Submit Request

                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- MY REQUESTS -->
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-0">
                        My Material Requests
                    </h5>

                    <small class="text-muted">
                        Recently submitted requests
                    </small>

                </div>

            </div>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>
                            <th>Request Date</th>
                            <th>Items</th>
                            <th>Total Qty</th>
                            <th>Remarks</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!$myRequests): ?>

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted py-4">

                                No material requests found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($myRequests as $request): ?>

                            <?php

                            $status = $request['status'];

                            $badgeClass = match ($status) {

                                'Pending'  => 'bg-warning text-dark',

                                'Approved' => 'bg-success',

                                'Rejected' => 'bg-danger',

                                default    => 'bg-secondary'

                            };

                            ?>

                            <tr>

                                <td>
                                    #<?= (int)$request['id'] ?>
                                </td>

                                <td>
                                    <?= e(
                                        date(
                                            'd-m-Y H:i',
                                            strtotime($request['created_at'])
                                        )
                                    ) ?>
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
                                    <?= e(
                                        $request['remarks'] ?: '-'
                                    ) ?>
                                </td>

                                <td>

                                    <span class="badge <?= $badgeClass ?>">
                                        <?= e($status) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const itemRows = document.getElementById('itemRows');
    const addRowButton = document.getElementById('addRow');


    /*
    |--------------------------------------------------------------------------
    | UPDATE MATERIAL INFORMATION
    |--------------------------------------------------------------------------
    */

    function updateMaterialInfo(row) {

        const select = row.querySelector('.material-select');

        const unitElement = row.querySelector('.material-unit');

        const stockElement = row.querySelector('.material-stock');

        const selectedOption =
            select.options[select.selectedIndex];


        if (!select.value) {

            unitElement.textContent = '-';

            stockElement.textContent = '-';

            return;
        }


        unitElement.textContent =
            selectedOption.dataset.unit || '-';


        stockElement.textContent =
            selectedOption.dataset.stock || '0';
    }


    /*
    |--------------------------------------------------------------------------
    | MATERIAL CHANGE
    |--------------------------------------------------------------------------
    */

    itemRows.addEventListener('change', function (event) {

        if (event.target.classList.contains('material-select')) {

            const row =
                event.target.closest('.request-row');

            updateMaterialInfo(row);

        }

    });


    /*
    |--------------------------------------------------------------------------
    | ADD NEW ROW
    |--------------------------------------------------------------------------
    */

    addRowButton.addEventListener('click', function () {

        const firstRow =
            itemRows.querySelector('.request-row');

        const newRow =
            firstRow.cloneNode(true);


        newRow.querySelector('.material-select').value = '';

        newRow.querySelector('.material-unit').textContent = '-';

        newRow.querySelector('.material-stock').textContent = '-';

        newRow.querySelector('input[name="quantity[]"]').value = '';


        const removeButton =
            newRow.querySelector('.remove-row');

        removeButton.disabled = false;


        itemRows.appendChild(newRow);


        updateRemoveButtons();

    });


    /*
    |--------------------------------------------------------------------------
    | REMOVE ROW
    |--------------------------------------------------------------------------
    */

    itemRows.addEventListener('click', function (event) {

        const removeButton =
            event.target.closest('.remove-row');

        if (!removeButton) {
            return;
        }


        const row =
            removeButton.closest('.request-row');


        if (itemRows.querySelectorAll('.request-row').length > 1) {

            row.remove();

        }


        updateRemoveButtons();

    });


    /*
    |--------------------------------------------------------------------------
    | ENABLE / DISABLE REMOVE BUTTONS
    |--------------------------------------------------------------------------
    */

    function updateRemoveButtons() {

        const rows =
            itemRows.querySelectorAll('.request-row');


        rows.forEach(function (row) {

            const button =
                row.querySelector('.remove-row');

            button.disabled =
                rows.length === 1;

        });

    }


    updateRemoveButtons();

});

</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>