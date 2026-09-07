<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (!in_array($_SESSION['role_name'] ?? '', ['Purchase', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Purchase Orders';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| GENERATE PO NUMBER
|--------------------------------------------------------------------------
*/

function generatePONumber(PDO $con): string
{
    $prefix = 'PO-' . date('Ym') . '-';

    $stmt = $con->prepare("
        SELECT po_no
        FROM purchase_orders
        WHERE po_no LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$prefix . '%']);

    $lastPO = $stmt->fetchColumn();

    if ($lastPO) {

        $lastNumber = (int)substr(
            (string)$lastPO,
            strrpos((string)$lastPO, '-') + 1
        );

        $nextNumber = $lastNumber + 1;

    } else {

        $nextNumber = 1;
    }

    return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| CREATE PURCHASE ORDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'create_po') {

        $requestId = (int)($_POST['request_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $poDate = $_POST['po_date'] ?? date('Y-m-d');
        $expectedDate = $_POST['expected_date'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        $materialIds = $_POST['material_id'] ?? [];
        $quantities = $_POST['ordered_qty'] ?? [];
        $rates = $_POST['unit_rate'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($requestId <= 0) {

            $error = 'Please select a purchase request.';

        } elseif ($supplierId <= 0) {

            $error = 'Please select a supplier.';

        } elseif (empty($materialIds)) {

            $error = 'Please add at least one material.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK REQUEST
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT id, status
                FROM purchase_requests
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$requestId]);

            $request = $stmt->fetch();

            if (!$request) {

                $error = 'Purchase request not found.';

            } elseif ($request['status'] !== 'Approved') {

                $error = 'Only approved purchase requests can be converted into a purchase order.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK SUPPLIER
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT id, supplier_name
                    FROM suppliers
                    WHERE id = ?
                    AND status = 'Enable'
                    LIMIT 1
                ");

                $stmt->execute([$supplierId]);

                $supplier = $stmt->fetch();

                if (!$supplier) {

                    $error = 'Selected supplier is not available.';

                } else {

                    try {

                        $con->beginTransaction();

                        /*
                        |--------------------------------------------------------------------------
                        | CHECK WHETHER PO ALREADY EXISTS
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $con->prepare("
                            SELECT id
                            FROM purchase_orders
                            WHERE request_id = ?
                            AND status != 'Cancelled'
                            LIMIT 1
                        ");

                        $stmt->execute([$requestId]);

                        if ($stmt->fetch()) {

                            throw new Exception(
                                'A purchase order already exists for this request.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | GENERATE PO NUMBER
                        |--------------------------------------------------------------------------
                        */

                        $poNo = generatePONumber($con);

                        /*
                        |--------------------------------------------------------------------------
                        | INSERT PO
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $con->prepare("
                            INSERT INTO purchase_orders
                            (
                                po_no,
                                request_id,
                                supplier_id,
                                po_date,
                                expected_date,
                                status,
                                remarks,
                                created_by
                            )
                            VALUES
                            (?, ?, ?, ?, ?, 'Pending', ?, ?)
                        ");

                        $stmt->execute([
                            $poNo,
                            $requestId,
                            $supplierId,
                            $poDate,
                            $expectedDate !== '' ? $expectedDate : null,
                            $remarks !== '' ? $remarks : null,
                            $_SESSION['user_id'] ?? null
                        ]);

                        $poId = (int)$con->lastInsertId();

                        /*
                        |--------------------------------------------------------------------------
                        | INSERT ITEMS
                        |--------------------------------------------------------------------------
                        */

                        $itemStmt = $con->prepare("
                            INSERT INTO purchase_order_items
                            (
                                po_id,
                                material_id,
                                ordered_qty,
                                unit_rate,
                                total_amount
                            )
                            VALUES
                            (?, ?, ?, ?, ?)
                        ");

                        foreach ($materialIds as $index => $materialId) {

                            $materialId = (int)$materialId;

                            $qty = isset($quantities[$index])
                                ? (float)$quantities[$index]
                                : 0;

                            $rate = isset($rates[$index])
                                ? (float)$rates[$index]
                                : 0;

                            if ($materialId <= 0 || $qty <= 0) {
                                continue;
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | CHECK MATERIAL
                            |--------------------------------------------------------------------------
                            */

                            $materialStmt = $con->prepare("
                                SELECT id
                                FROM materials
                                WHERE id = ?
                                AND status = 'Enable'
                                LIMIT 1
                            ");

                            $materialStmt->execute([$materialId]);

                            if (!$materialStmt->fetch()) {

                                throw new Exception(
                                    'One of the selected materials is invalid.'
                                );
                            }

                            $totalAmount = $qty * $rate;

                            $itemStmt->execute([
                                $poId,
                                $materialId,
                                $qty,
                                $rate,
                                $totalAmount
                            ]);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE REQUEST
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $con->prepare("
                            UPDATE purchase_requests
                            SET status = 'Approved'
                            WHERE id = ?
                        ");

                        $stmt->execute([$requestId]);

                        $con->commit();

                        $success = 'Purchase Order ' . $poNo . ' created successfully.';

                    } catch (Throwable $e) {

                        if ($con->inTransaction()) {
                            $con->rollBack();
                        }

                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH APPROVED REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        u.employee_name AS requested_by_name
    FROM purchase_requests pr
    LEFT JOIN users u
        ON u.id = pr.requested_by
    WHERE pr.status = 'Approved'
    AND NOT EXISTS (
        SELECT 1
        FROM purchase_orders po
        WHERE po.request_id = pr.id
        AND po.status != 'Cancelled'
    )
    ORDER BY pr.id DESC
");

$approvedRequests = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| FETCH SUPPLIERS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        supplier_code,
        supplier_name
    FROM suppliers
    WHERE status = 'Enable'
    ORDER BY supplier_name ASC
");

$suppliers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| FETCH PURCHASE ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        po.id,
        po.po_no,
        po.po_date,
        po.expected_date,
        po.status,
        po.remarks,
        s.supplier_name,
        pr.request_no,
        u.employee_name AS created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s
        ON s.id = po.supplier_id
    LEFT JOIN purchase_requests pr
        ON pr.id = po.request_id
    LEFT JOIN users u
        ON u.id = po.created_by
    ORDER BY po.id DESC
");

$purchaseOrders = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">

        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="page-body">

            <!-- HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>
                    <h4 class="mb-1">
                        <i class="fa-solid fa-file-invoice me-2"></i>
                        Purchase Orders
                    </h4>

                    <p class="text-muted mb-0">
                        Create and manage purchase orders.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#createPOModal"
                >
                    <i class="fa-solid fa-plus me-2"></i>
                    Create Purchase Order
                </button>

            </div>


            <!-- ALERT -->

            <?php if ($success !== ''): ?>

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


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger alert-dismissible fade show">

                    <i class="fa-solid fa-circle-exclamation me-2"></i>

                    <?= e($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- PO LIST -->

            <div class="content-card">

                <div class="content-card-header">

                    <div>
                        <h5 class="mb-1">
                            Purchase Order List
                        </h5>

                        <small class="text-muted">
                            <?= count($purchaseOrders) ?> PO(s) found
                        </small>
                    </div>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>PO Number</th>

                                <th>Request No.</th>

                                <th>Supplier</th>

                                <th>PO Date</th>

                                <th>Expected Date</th>

                                <th>Status</th>

                                <th>Created By</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (!$purchaseOrders): ?>

                            <tr>

                                <td
                                    colspan="9"
                                    class="text-center text-muted py-5"
                                >

                                    <i class="fa-solid fa-file-invoice fa-2x mb-3"></i>

                                    <div>
                                        No purchase orders found.
                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($purchaseOrders as $index => $po): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= e($po['po_no']) ?>
                                        </strong>

                                    </td>

                                    <td>
                                        <?= e($po['request_no']) ?>
                                    </td>

                                    <td>
                                        <?= e($po['supplier_name']) ?>
                                    </td>

                                    <td>
                                        <?= e($po['po_date']) ?>
                                    </td>

                                    <td>
                                        <?= e($po['expected_date']) ?>
                                    </td>

                                    <td>

                                        <?php

                                        $badgeClass = match ($po['status']) {
                                            'Draft' => 'bg-secondary',
                                            'Pending' => 'badge-disabled',
                                            'Approved' => 'badge-enable',
                                            'Ordered' => 'badge-enable',
                                            'Received' => 'badge-enable',
                                            'Cancelled' => 'badge-disabled',
                                            default => 'bg-secondary'
                                        };

                                        ?>

                                        <span class="badge <?= $badgeClass ?>">
                                            <?= e($po['status']) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= e($po['created_by_name']) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="purchase_order_view.php?id=<?= (int)$po['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Purchase Order"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </a>

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

</div>


<!-- CREATE PO MODAL -->

<div
    class="modal fade edit-user-modal"
    id="createPOModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <form method="post">

                <div class="modal-header">

                    <div>

                        <div class="modal-title-text">
                            <i class="fa-solid fa-file-invoice me-2"></i>
                            Create Purchase Order
                        </div>

                        <div class="modal-subtitle-text">
                            Convert an approved request into an order with a supplier.
                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body pt-4">

                    <input
                        type="hidden"
                        name="action"
                        value="create_po"
                    >


                    <!-- BASIC DETAILS -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-4">

                            <label class="form-label">
                                Approved Request
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="request_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    --- Select Request ---
                                </option>

                                <?php foreach ($approvedRequests as $request): ?>

                                    <option
                                        value="<?= (int)$request['id'] ?>"
                                    >
                                        <?= e($request['request_no']) ?>
                                        -
                                        <?= e($request['requested_by_name']) ?>
                                        -
                                        <?= e($request['request_date']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Supplier
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="supplier_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    --- Select Supplier ---
                                </option>

                                <?php foreach ($suppliers as $supplier): ?>

                                    <option
                                        value="<?= (int)$supplier['id'] ?>"
                                    >
                                        <?= e($supplier['supplier_code']) ?>
                                        -
                                        <?= e($supplier['supplier_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                PO Date
                            </label>

                            <input
                                type="date"
                                name="po_date"
                                class="form-control"
                                value="<?= date('Y-m-d') ?>"
                                required
                            >

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                Expected Date
                            </label>

                            <input
                                type="date"
                                name="expected_date"
                                class="form-control"
                            >

                        </div>

                    </div>


                    <!-- ITEMS -->

                    <div class="d-flex justify-content-between align-items-center mb-2">

                        <h6 class="mb-0">
                            Purchase Order Items
                        </h6>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="addPOItem"
                        >
                            <i class="fa-solid fa-plus me-1"></i>
                            Add Item
                        </button>

                    </div>


                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead class="table-light">

                                <tr>

                                    <th width="45%">
                                        Material
                                    </th>

                                    <th width="15%">
                                        Quantity
                                    </th>

                                    <th width="15%">
                                        Unit Rate
                                    </th>

                                    <th width="15%">
                                        Total
                                    </th>

                                    <th width="10%">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="poItemsBody">

                                <tr class="po-item-row">

                                    <td>

                                        <select
                                            name="material_id[]"
                                            class="form-select material-select"
                                            required
                                        >

                                            <option value="">
                                                --- Select Material ---
                                            </option>

                                            <?php

                                            $materialStmt = $con->query("
                                                SELECT
                                                    id,
                                                    material_code,
                                                    material_name,
                                                    unit
                                                FROM materials
                                                WHERE status = 'Enable'
                                                ORDER BY material_name ASC
                                            ");

                                            $materials = $materialStmt->fetchAll();

                                            ?>

                                            <?php foreach ($materials as $material): ?>

                                                <option
                                                    value="<?= (int)$material['id'] ?>"
                                                >
                                                    <?= e($material['material_code']) ?>
                                                    -
                                                    <?= e($material['material_name']) ?>
                                                    (<?= e($material['unit']) ?>)
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="ordered_qty[]"
                                            class="form-control qty-input"
                                            min="0.01"
                                            step="0.01"
                                            value="1"
                                            required
                                        >

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="unit_rate[]"
                                            class="form-control rate-input"
                                            min="0"
                                            step="0.01"
                                            value="0"
                                            required
                                        >

                                    </td>


                                    <td>

                                        <input
                                            type="text"
                                            class="form-control total-input"
                                            value="0.00"
                                            readonly
                                        >

                                    </td>


                                    <td>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger remove-item"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>

                                    </td>

                                </tr>

                            </tbody>


                            <tfoot>

                                <tr>

                                    <th colspan="3" class="text-end">
                                        Grand Total
                                    </th>

                                    <th>

                                        <input
                                            type="text"
                                            id="grandTotal"
                                            class="form-control fw-bold"
                                            value="0.00"
                                            readonly
                                        >

                                    </th>

                                    <th></th>

                                </tr>

                            </tfoot>

                        </table>

                    </div>


                    <!-- REMARKS -->

                    <div class="mt-3">

                        <label class="form-label">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            class="form-control"
                            rows="3"
                            placeholder="Enter remarks if required..."
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-save me-1"></i>

                        Create Purchase Order

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const itemsBody = document.getElementById('poItemsBody');
    const addButton = document.getElementById('addPOItem');
    const grandTotal = document.getElementById('grandTotal');


    /*
    |--------------------------------------------------------------------------
    | CALCULATE ROW TOTAL
    |--------------------------------------------------------------------------
    */

    function calculateRow(row) {

        const qty = parseFloat(
            row.querySelector('.qty-input')?.value || 0
        );

        const rate = parseFloat(
            row.querySelector('.rate-input')?.value || 0
        );

        const total = qty * rate;

        const totalInput = row.querySelector('.total-input');

        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }

        calculateGrandTotal();
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE GRAND TOTAL
    |--------------------------------------------------------------------------
    */

    function calculateGrandTotal() {

        let total = 0;

        document.querySelectorAll('.po-item-row').forEach(function (row) {

            const qty = parseFloat(
                row.querySelector('.qty-input')?.value || 0
            );

            const rate = parseFloat(
                row.querySelector('.rate-input')?.value || 0
            );

            total += qty * rate;

        });

        grandTotal.value = total.toFixed(2);
    }


    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */

    addButton.addEventListener('click', function () {

        const firstRow = document.querySelector('.po-item-row');

        const newRow = firstRow.cloneNode(true);

        newRow.querySelector('.material-select').value = '';

        newRow.querySelector('.qty-input').value = '1';

        newRow.querySelector('.rate-input').value = '0';

        newRow.querySelector('.total-input').value = '0.00';

        itemsBody.appendChild(newRow);

    });


    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    itemsBody.addEventListener('click', function (event) {

        const button = event.target.closest('.remove-item');

        if (!button) {
            return;
        }

        const rows = document.querySelectorAll('.po-item-row');

        if (rows.length <= 1) {

            alert('At least one item is required.');

            return;
        }

        button.closest('.po-item-row').remove();

        calculateGrandTotal();

    });


    /*
    |--------------------------------------------------------------------------
    | QUANTITY / RATE CHANGE
    |--------------------------------------------------------------------------
    */

    itemsBody.addEventListener('input', function (event) {

        if (
            event.target.classList.contains('qty-input') ||
            event.target.classList.contains('rate-input')
        ) {

            calculateRow(
                event.target.closest('.po-item-row')
            );

        }

    });


    /*
    |--------------------------------------------------------------------------
    | INITIAL CALCULATION
    |--------------------------------------------------------------------------
    */

    calculateGrandTotal();

});

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>