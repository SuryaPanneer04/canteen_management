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

if (!in_array($_SESSION['role_name'] ?? '', ['Store', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Purchase Order Receiving';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| RECEIVE PURCHASE ORDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'receive_po') {

        $poId = (int)($_POST['po_id'] ?? 0);

        $materialIds = $_POST['material_id'] ?? [];
        $receivedQtys = $_POST['received_qty'] ?? [];

        if ($poId <= 0) {

            $error = 'Invalid purchase order.';

        } elseif (empty($materialIds)) {

            $error = 'No purchase order items found.';

        } else {

            try {

                $con->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | LOCK PURCHASE ORDER
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT
                        id,
                        po_no,
                        status
                    FROM purchase_orders
                    WHERE id = ?
                    FOR UPDATE
                ");

                $stmt->execute([$poId]);

                $po = $stmt->fetch();

                if (!$po) {

                    throw new Exception(
                        'Purchase order not found.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ONLY ORDERED PO CAN BE RECEIVED
                |--------------------------------------------------------------------------
                */

                if ($po['status'] !== 'Ordered') {

                    throw new Exception(
                        'Only Ordered purchase orders can be received.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | FETCH PO ITEMS
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT
                        poi.id,
                        poi.material_id,
                        poi.ordered_qty,
                        m.material_code,
                        m.material_name,
                        m.unit
                    FROM purchase_order_items poi
                    INNER JOIN materials m
                        ON m.id = poi.material_id
                    WHERE poi.po_id = ?
                    ORDER BY poi.id ASC
                ");

                $stmt->execute([$poId]);

                $poItems = $stmt->fetchAll();

                if (!$poItems) {

                    throw new Exception(
                        'This purchase order has no items.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RECEIVE EACH ITEM
                |--------------------------------------------------------------------------
                */

                $receivedAnything = false;

                foreach ($poItems as $item) {

                    $itemId = (int)$item['id'];
                    $materialId = (int)$item['material_id'];

                    /*
                    | The form uses item ID as the key.
                    */

                    $receivedQty = isset($receivedQtys[$itemId])
                        ? (float)$receivedQtys[$itemId]
                        : 0;

                    if ($receivedQty < 0) {

                        throw new Exception(
                            'Received quantity cannot be negative.'
                        );
                    }

                    /*
                    | Skip zero quantity.
                    */

                    if ($receivedQty == 0) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | PREVENT RECEIVING MORE THAN ORDERED
                    |--------------------------------------------------------------------------
                    */

                    $orderedQty = (float)$item['ordered_qty'];

                    if ($receivedQty > $orderedQty) {

                        throw new Exception(
                            'Received quantity for ' .
                            $item['material_name'] .
                            ' cannot be greater than ordered quantity.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK MATERIAL
                    |--------------------------------------------------------------------------
                    */

                    $materialStmt = $con->prepare("
                        SELECT
                            id,
                            current_stock
                        FROM materials
                        WHERE id = ?
                        FOR UPDATE
                    ");

                    $materialStmt->execute([$materialId]);

                    $material = $materialStmt->fetch();

                    if (!$material) {

                        throw new Exception(
                            'Material not found.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE STOCK
                    |--------------------------------------------------------------------------
                    */

                    $newStock =
                        (float)$material['current_stock']
                        + $receivedQty;

                    $updateStock = $con->prepare("
                        UPDATE materials
                        SET current_stock = ?
                        WHERE id = ?
                    ");

                    $updateStock->execute([
                        $newStock,
                        $materialId
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | GET UNIT RATE
                    |--------------------------------------------------------------------------
                    */

                    $rateStmt = $con->prepare("
                        SELECT unit_rate
                        FROM purchase_order_items
                        WHERE id = ?
                        AND po_id = ?
                        LIMIT 1
                    ");

                    $rateStmt->execute([
                        $itemId,
                        $poId
                    ]);

                    $unitRate = (float)(
                        $rateStmt->fetchColumn() ?? 0
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT STOCK TRANSACTION
                    |--------------------------------------------------------------------------
                    */

                    $transactionStmt = $con->prepare("
                        INSERT INTO stock_transactions
                        (
                            material_id,
                            transaction_type,
                            quantity,
                            reference_no,
                            remarks,
                            created_by
                        )
                        VALUES
                        (?, 'PURCHASE', ?, ?, ?, ?)
                    ");

                    $transactionStmt->execute([
                        $materialId,
                        $receivedQty,
                        $po['po_no'],
                        'Purchase Order Receiving',
                        $_SESSION['user_id'] ?? null
                    ]);

                    $receivedAnything = true;
                }

                if (!$receivedAnything) {

                    throw new Exception(
                        'Please enter received quantity for at least one item.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | UPDATE PO STATUS
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    UPDATE purchase_orders
                    SET status = 'Received'
                    WHERE id = ?
                    AND status = 'Ordered'
                ");

                $stmt->execute([$poId]);

                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $con->commit();

                $success =
                    'Purchase Order ' .
                    $po['po_no'] .
                    ' received successfully. Stock has been updated.';

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
| FETCH ORDERED PURCHASE ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        po.id,
        po.po_no,
        po.po_date,
        po.expected_date,
        po.status,

        s.supplier_name,

        pr.request_no

    FROM purchase_orders po

    LEFT JOIN suppliers s
        ON s.id = po.supplier_id

    LEFT JOIN purchase_requests pr
        ON pr.id = po.request_id

    WHERE po.status = 'Ordered'

    ORDER BY po.id DESC
");

$orderedPOs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| SELECT PO FOR RECEIVING
|--------------------------------------------------------------------------
*/

$selectedPO = null;
$selectedItems = [];

$selectedPoId = (int)($_GET['po_id'] ?? 0);

if ($selectedPoId > 0) {

    $stmt = $con->prepare("
        SELECT
            po.id,
            po.po_no,
            po.po_date,
            po.expected_date,
            po.status,

            s.supplier_code,
            s.supplier_name,
            s.contact_person,
            s.phone,
            s.email,

            pr.request_no

        FROM purchase_orders po

        LEFT JOIN suppliers s
            ON s.id = po.supplier_id

        LEFT JOIN purchase_requests pr
            ON pr.id = po.request_id

        WHERE po.id = ?

        LIMIT 1
    ");

    $stmt->execute([$selectedPoId]);

    $selectedPO = $stmt->fetch();

    if ($selectedPO) {

        $stmt = $con->prepare("
            SELECT
                poi.id,
                poi.material_id,
                poi.ordered_qty,
                poi.unit_rate,
                poi.total_amount,

                m.material_code,
                m.material_name,
                m.unit,
                m.current_stock

            FROM purchase_order_items poi

            INNER JOIN materials m
                ON m.id = poi.material_id

            WHERE poi.po_id = ?

            ORDER BY poi.id ASC
        ");

        $stmt->execute([$selectedPoId]);

        $selectedItems = $stmt->fetchAll();
    }
}

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

        <div class="container-fluid py-4">

            <!-- HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h4 class="mb-1">

                        <i class="fa-solid fa-box-open me-2"></i>

                        Purchase Order Receiving

                    </h4>

                    <p class="text-muted mb-0">

                        Receive purchased materials and update stock.

                    </p>

                </div>

            </div>


            <!-- ALERTS -->

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


            <!-- ORDERED PO LIST -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <strong>

                        <i class="fa-solid fa-truck-ramp-box me-2"></i>

                        Orders Waiting for Receiving

                    </strong>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>PO Number</th>

                                    <th>Request No.</th>

                                    <th>Supplier</th>

                                    <th>PO Date</th>

                                    <th>Expected Date</th>

                                    <th>Action</th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (!$orderedPOs): ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="text-center text-muted py-5"
                                    >

                                        <i class="fa-solid fa-box-open fa-2x mb-3"></i>

                                        <div>
                                            No purchase orders waiting for receiving.
                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($orderedPOs as $index => $po): ?>

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

                                            <?= $po['expected_date']
                                                ? e($po['expected_date'])
                                                : '-' ?>

                                        </td>

                                        <td>

                                            <a
                                                href="purchase_order_receiving.php?po_id=<?= (int)$po['id'] ?>"
                                                class="btn btn-sm btn-primary"
                                            >

                                                <i class="fa-solid fa-box-open me-1"></i>

                                                Receive

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


            <!-- RECEIVING FORM -->

            <?php if ($selectedPO): ?>

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <strong>

                                    Receive:
                                    <?= e($selectedPO['po_no']) ?>

                                </strong>

                                <div class="small text-muted">

                                    <?= e($selectedPO['supplier_name']) ?>

                                </div>

                            </div>

                            <span class="badge bg-primary">

                                <?= e($selectedPO['status']) ?>

                            </span>

                        </div>

                    </div>


                    <div class="card-body">

                        <?php if ($selectedPO['status'] !== 'Ordered'): ?>

                            <div class="alert alert-warning">

                                This purchase order is no longer available
                                for receiving.

                            </div>

                        <?php else: ?>

                            <div class="row mb-4">

                                <div class="col-md-4">

                                    <strong>PO Number</strong>

                                    <div>
                                        <?= e($selectedPO['po_no']) ?>
                                    </div>

                                </div>

                                <div class="col-md-4">

                                    <strong>Supplier</strong>

                                    <div>
                                        <?= e($selectedPO['supplier_name']) ?>
                                    </div>

                                </div>

                                <div class="col-md-4">

                                    <strong>Request Number</strong>

                                    <div>
                                        <?= e($selectedPO['request_no']) ?>
                                    </div>

                                </div>

                            </div>


                            <form method="post">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="receive_po"
                                >

                                <input
                                    type="hidden"
                                    name="po_id"
                                    value="<?= (int)$selectedPO['id'] ?>"
                                >


                                <div class="table-responsive">

                                    <table class="table table-bordered align-middle">

                                        <thead class="table-light">

                                            <tr>

                                                <th>#</th>

                                                <th>Material Code</th>

                                                <th>Material</th>

                                                <th>Unit</th>

                                                <th class="text-end">
                                                    Ordered Qty
                                                </th>

                                                <th class="text-end">
                                                    Unit Rate
                                                </th>

                                                <th class="text-end">
                                                    Current Stock
                                                </th>

                                                <th width="180">
                                                    Received Qty
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                        <?php foreach ($selectedItems as $index => $item): ?>

                                            <tr>

                                                <td>
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td>
                                                    <?= e($item['material_code']) ?>
                                                </td>

                                                <td>

                                                    <strong>
                                                        <?= e($item['material_name']) ?>
                                                    </strong>

                                                </td>

                                                <td>
                                                    <?= e($item['unit']) ?>
                                                </td>

                                                <td class="text-end">

                                                    <?= number_format(
                                                        (float)$item['ordered_qty'],
                                                        2
                                                    ) ?>

                                                </td>

                                                <td class="text-end">

                                                    ₹<?= number_format(
                                                        (float)$item['unit_rate'],
                                                        2
                                                    ) ?>

                                                </td>

                                                <td class="text-end">

                                                    <span class="badge bg-secondary">

                                                        <?= number_format(
                                                            (float)$item['current_stock'],
                                                            2
                                                        ) ?>

                                                    </span>

                                                </td>

                                                <td>

                                                    <input
                                                        type="hidden"
                                                        name="material_id[<?= (int)$item['id'] ?>]"
                                                        value="<?= (int)$item['material_id'] ?>"
                                                    >

                                                    <input
                                                        type="number"
                                                        name="received_qty[<?= (int)$item['id'] ?>]"
                                                        class="form-control"
                                                        min="0"
                                                        max="<?= e((string)$item['ordered_qty']) ?>"
                                                        step="0.01"
                                                        value="<?= e((string)$item['ordered_qty']) ?>"
                                                    >

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>


                                <div class="alert alert-info">

                                    <i class="fa-solid fa-circle-info me-2"></i>

                                    Enter the actual quantity received.
                                    The received quantity cannot be greater
                                    than the ordered quantity.

                                </div>


                                <div class="d-flex justify-content-end gap-2">

                                    <a
                                        href="purchase_order_receiving.php"
                                        class="btn btn-secondary"
                                    >

                                        Cancel

                                    </a>


                                    <button
                                        type="submit"
                                        class="btn btn-success"
                                        onclick="return confirm('Confirm receiving this purchase order? Stock will be updated immediately.');"
                                    >

                                        <i class="fa-solid fa-box-open me-1"></i>

                                        Receive & Update Stock

                                    </button>

                                </div>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>