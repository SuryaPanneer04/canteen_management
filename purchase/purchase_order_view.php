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

$pageTitle = 'Purchase Order Details';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| GET PO ID
|--------------------------------------------------------------------------
*/

$poId = (int)($_GET['id'] ?? $_POST['po_id'] ?? 0);

if ($poId <= 0) {
    header('Location: purchase_orders.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | APPROVE PO
    |--------------------------------------------------------------------------
    */

    if ($action === 'approve_po') {

        $stmt = $con->prepare("
            SELECT id, status
            FROM purchase_orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$poId]);

        $po = $stmt->fetch();

        if (!$po) {

            $error = 'Purchase order not found.';

        } elseif ($po['status'] !== 'Pending') {

            $error = 'Only Pending purchase orders can be approved.';

        } else {

            $stmt = $con->prepare("
                UPDATE purchase_orders
                SET status = 'Approved'
                WHERE id = ?
                AND status = 'Pending'
            ");

            $stmt->execute([$poId]);

            $success = 'Purchase order approved successfully.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MARK AS ORDERED
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'mark_ordered') {

        $stmt = $con->prepare("
            SELECT id, status
            FROM purchase_orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$poId]);

        $po = $stmt->fetch();

        if (!$po) {

            $error = 'Purchase order not found.';

        } elseif ($po['status'] !== 'Approved') {

            $error = 'Only Approved purchase orders can be marked as Ordered.';

        } else {

            $stmt = $con->prepare("
                UPDATE purchase_orders
                SET status = 'Ordered'
                WHERE id = ?
                AND status = 'Approved'
            ");

            $stmt->execute([$poId]);

            $success = 'Purchase order marked as Ordered.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL PO
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'cancel_po') {

        $stmt = $con->prepare("
            SELECT id, status
            FROM purchase_orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$poId]);

        $po = $stmt->fetch();

        if (!$po) {

            $error = 'Purchase order not found.';

        } elseif (in_array(
            $po['status'],
            ['Received', 'Cancelled'],
            true
        )) {

            $error = 'This purchase order cannot be cancelled.';

        } else {

            $stmt = $con->prepare("
                UPDATE purchase_orders
                SET status = 'Cancelled'
                WHERE id = ?
            ");

            $stmt->execute([$poId]);

            $success = 'Purchase order cancelled successfully.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH PURCHASE ORDER
|--------------------------------------------------------------------------
*/

$stmt = $con->prepare("
    SELECT
        po.id,
        po.po_no,
        po.request_id,
        po.supplier_id,
        po.po_date,
        po.expected_date,
        po.status,
        po.remarks,
        po.created_at,

        s.supplier_code,
        s.supplier_name,
        s.contact_person,
        s.phone,
        s.email,
        s.address,
        s.gst_number,

        pr.request_no,
        pr.request_date,

        u.employee_name AS created_by_name

    FROM purchase_orders po

    LEFT JOIN suppliers s
        ON s.id = po.supplier_id

    LEFT JOIN purchase_requests pr
        ON pr.id = po.request_id

    LEFT JOIN users u
        ON u.id = po.created_by

    WHERE po.id = ?

    LIMIT 1
");

$stmt->execute([$poId]);

$po = $stmt->fetch();

if (!$po) {
    die('Purchase order not found.');
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
        poi.unit_rate,
        poi.total_amount,

        m.material_code,
        m.material_name,
        m.unit

    FROM purchase_order_items poi

    LEFT JOIN materials m
        ON m.id = poi.material_id

    WHERE poi.po_id = ?

    ORDER BY poi.id ASC
");

$stmt->execute([$poId]);

$items = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CALCULATE GRAND TOTAL
|--------------------------------------------------------------------------
*/

$grandTotal = 0;

foreach ($items as $item) {
    $grandTotal += (float)$item['total_amount'];
}

/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

$statusClass = match ($po['status']) {

    'Draft' =>
        'bg-secondary',

    'Pending' =>
        'bg-warning text-dark',

    'Approved' =>
        'bg-success',

    'Ordered' =>
        'bg-primary',

    'Received' =>
        'bg-success',

    'Cancelled' =>
        'bg-danger',

    default =>
        'bg-secondary'
};

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

            <!-- PAGE HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h4 class="mb-1">

                        <i class="fa-solid fa-file-invoice me-2"></i>

                        Purchase Order Details

                    </h4>

                    <p class="text-muted mb-0">
                        View and process purchase order.
                    </p>

                </div>


                <div class="d-flex gap-2">

                    <a
                        href="purchase_orders.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="fa-solid fa-arrow-left me-1"></i>

                        Back

                    </a>

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


            <!-- PO HEADER -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <strong>
                                <?= e($po['po_no']) ?>
                            </strong>

                            <div class="small text-muted">
                                Purchase Order
                            </div>

                        </div>


                        <span class="badge <?= $statusClass ?> fs-6">

                            <?= e($po['status']) ?>

                        </span>

                    </div>

                </div>


                <div class="card-body">

                    <div class="row g-4">

                        <!-- PO DETAILS -->

                        <div class="col-md-6">

                            <h6 class="mb-3">
                                <i class="fa-solid fa-file-lines me-2"></i>
                                PO Information
                            </h6>

                            <table class="table table-sm">

                                <tr>
                                    <th width="40%">PO Number</th>
                                    <td><?= e($po['po_no']) ?></td>
                                </tr>

                                <tr>
                                    <th>PO Date</th>
                                    <td><?= e($po['po_date']) ?></td>
                                </tr>

                                <tr>
                                    <th>Expected Date</th>
                                    <td>
                                        <?= $po['expected_date']
                                            ? e($po['expected_date'])
                                            : '-' ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Request Number</th>
                                    <td><?= e($po['request_no']) ?></td>
                                </tr>

                                <tr>
                                    <th>Request Date</th>
                                    <td><?= e($po['request_date']) ?></td>
                                </tr>

                                <tr>
                                    <th>Created By</th>
                                    <td><?= e($po['created_by_name']) ?></td>
                                </tr>

                                <tr>
                                    <th>Created At</th>
                                    <td><?= e($po['created_at']) ?></td>
                                </tr>

                            </table>

                        </div>


                        <!-- SUPPLIER -->

                        <div class="col-md-6">

                            <h6 class="mb-3">
                                <i class="fa-solid fa-truck-field me-2"></i>
                                Supplier Information
                            </h6>

                            <table class="table table-sm">

                                <tr>
                                    <th width="40%">Supplier Code</th>
                                    <td>
                                        <?= e($po['supplier_code']) ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Supplier Name</th>
                                    <td>
                                        <strong>
                                            <?= e($po['supplier_name']) ?>
                                        </strong>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Contact Person</th>
                                    <td>
                                        <?= e($po['contact_person']) ?: '-' ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Phone</th>
                                    <td>
                                        <?= e($po['phone']) ?: '-' ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Email</th>
                                    <td>
                                        <?= e($po['email']) ?: '-' ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>GST Number</th>
                                    <td>
                                        <?= e($po['gst_number']) ?: '-' ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Address</th>
                                    <td>
                                        <?= nl2br(e($po['address'])) ?: '-' ?>
                                    </td>
                                </tr>

                            </table>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ITEMS -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <strong>
                        <i class="fa-solid fa-boxes-stacked me-2"></i>
                        Purchase Order Items
                    </strong>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>Material Code</th>

                                    <th>Material</th>

                                    <th>Unit</th>

                                    <th class="text-end">
                                        Quantity
                                    </th>

                                    <th class="text-end">
                                        Unit Rate
                                    </th>

                                    <th class="text-end">
                                        Total
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (!$items): ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="text-center text-muted py-4"
                                    >
                                        No items found.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($items as $index => $item): ?>

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

                                            ₹<?= number_format(
                                                (float)$item['total_amount'],
                                                2
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>


                            <tfoot>

                                <tr>

                                    <th
                                        colspan="6"
                                        class="text-end"
                                    >
                                        Grand Total
                                    </th>

                                    <th class="text-end">

                                        ₹<?= number_format(
                                            $grandTotal,
                                            2
                                        ) ?>

                                    </th>

                                </tr>

                            </tfoot>

                        </table>

                    </div>

                </div>

            </div>


            <!-- REMARKS -->

            <?php if (!empty($po['remarks'])): ?>

                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-header bg-white">

                        <strong>
                            <i class="fa-solid fa-comment me-2"></i>
                            Remarks
                        </strong>

                    </div>

                    <div class="card-body">

                        <?= nl2br(e($po['remarks'])) ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- ACTIONS -->

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white">

                    <strong>
                        <i class="fa-solid fa-gears me-2"></i>
                        Purchase Order Actions
                    </strong>

                </div>


                <div class="card-body">

                    <div class="d-flex flex-wrap gap-2">


                        <?php if ($po['status'] === 'Pending'): ?>

                            <!-- APPROVE -->

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="approve_po"
                                >

                                <input
                                    type="hidden"
                                    name="po_id"
                                    value="<?= (int)$po['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    onclick="return confirm('Approve this purchase order?');"
                                >

                                    <i class="fa-solid fa-check me-1"></i>

                                    Approve PO

                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if ($po['status'] === 'Approved'): ?>

                            <!-- MARK ORDERED -->

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="mark_ordered"
                                >

                                <input
                                    type="hidden"
                                    name="po_id"
                                    value="<?= (int)$po['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    onclick="return confirm('Mark this purchase order as Ordered?');"
                                >

                                    <i class="fa-solid fa-cart-shopping me-1"></i>

                                    Mark as Ordered

                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if (!in_array(
                            $po['status'],
                            ['Received', 'Cancelled'],
                            true
                        )): ?>

                            <!-- CANCEL -->

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="cancel_po"
                                >

                                <input
                                    type="hidden"
                                    name="po_id"
                                    value="<?= (int)$po['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to cancel this purchase order?');"
                                >

                                    <i class="fa-solid fa-xmark me-1"></i>

                                    Cancel PO

                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if ($po['status'] === 'Received'): ?>

                            <div class="alert alert-success mb-0">

                                <i class="fa-solid fa-circle-check me-2"></i>

                                This purchase order has been received.

                            </div>

                        <?php endif; ?>


                        <?php if ($po['status'] === 'Cancelled'): ?>

                            <div class="alert alert-danger mb-0">

                                <i class="fa-solid fa-ban me-2"></i>

                                This purchase order has been cancelled.

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>