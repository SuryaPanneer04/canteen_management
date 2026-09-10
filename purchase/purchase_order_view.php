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
        
        // Role Check added here
        if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
            $error = 'Access Denied: Only Super Admin can approve purchase orders.';
        } else {
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
    }

    /*
    |--------------------------------------------------------------------------
    | MARK AS ORDERED
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'mark_ordered') {
        
        if (($_SESSION['role_name'] ?? '') !== 'Purchase') {
            $error = 'Access Denied: Only Purchase team can place the order.';
        } else {
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
    }
   /*
    |--------------------------------------------------------------------------
    | CANCEL PO
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'cancel_po') {
        
        // Role Check added here
        if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
            $error = 'Access Denied: Only Super Admin can cancel purchase orders.';
        } else {
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
            } elseif (in_array($po['status'], ['Received', 'Cancelled'], true)) {
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
    | ADD INVOICE (AUTOMATIC GENERATION + FILE UPLOAD)
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'add_invoice') {
        if (($_SESSION['role_name'] ?? '') !== 'Purchase') {
            $error = 'Access Denied: Only Purchase team can add invoices.';
        } else {
            $invNo = trim($_POST['invoice_no'] ?? '');
            $invDate = $_POST['invoice_date'] ?? date('Y-m-d');
            $taxAmt = (float)($_POST['tax_amount'] ?? 0);
            $poTotal = (float)($_POST['po_total'] ?? 0);
            $grandTotal = $poTotal + $taxAmt;

            // File Upload Logic
            $docPath = null;
            if (isset($_FILES['invoice_document']) && $_FILES['invoice_document']['error'] === UPLOAD_ERR_OK) {
                // Main Canteen Management folder-kulla uploads/invoices/ folder create pannanum
                $uploadDir = __DIR__ . '/../uploads/invoices/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . '_' . basename($_FILES['invoice_document']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['invoice_document']['tmp_name'], $targetPath)) {
                    $docPath = 'uploads/invoices/' . $fileName;
                }
            }

            $stmt = $con->prepare("
                INSERT INTO invoices (po_id, invoice_no, invoice_date, total_amount, tax_amount, document_path, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([$poId, $invNo, $invDate, $grandTotal, $taxAmt, $docPath, $_SESSION['user_id'] ?? null]);
            $success = 'Invoice generated successfully and sent to Admin for approval.';
        }
    }
    /*
    |--------------------------------------------------------------------------
    | MARK INVOICE AS PAID
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'mark_invoice_paid') {
        if (($_SESSION['role_name'] ?? '') !== 'Purchase') {
            $error = 'Access Denied: Only Purchase team can update payments.';
        } else {
            $stmt = $con->prepare("UPDATE invoices SET status = 'Paid' WHERE po_id = ? AND status = 'Approved'");
            $stmt->execute([$poId]);
            $success = 'Payment completed. Invoice marked as Paid.';
        }
    }
    elseif ($action === 'approve_invoice') {
        if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
            $error = 'Access Denied: Only Admin can approve invoices.';
        } else {
            $stmt = $con->prepare("UPDATE invoices SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE po_id = ? AND status = 'Pending'");
            $stmt->execute([$_SESSION['user_id'] ?? null, $poId]);
            $success = 'Invoice approved successfully. Purchase team can now process payment.';
        }
    }
    elseif ($action === 'reject_invoice') {
        if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
            $error = 'Access Denied: Only Admin can reject invoices.';
        } else {
            $stmt = $con->prepare("UPDATE invoices SET status = 'Rejected', approved_by = ?, approved_at = NOW() WHERE po_id = ? AND status = 'Pending'");
            $stmt->execute([$_SESSION['user_id'] ?? null, $poId]);
            $success = 'Invoice rejected.';
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
        'badge-disabled',

    'Approved' =>
        'badge-enable',

    'Ordered' =>
        'badge-enable',

    'Received' =>
        'badge-enable',

    'Cancelled' =>
        'badge-disabled',

    default =>
        'bg-secondary'
};

/*
|--------------------------------------------------------------------------
| CHECK IF INVOICE EXISTS
|--------------------------------------------------------------------------
*/
$invStmt = $con->prepare("SELECT invoice_no, status FROM invoices WHERE po_id = ? LIMIT 1");
$invStmt->execute([$poId]);
$invoice = $invStmt->fetch();

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

            <div class="content-card mb-4">

                <div class="content-card-header">

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


                <div class="content-card-body">

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

            <div class="content-card mb-4">

                <div class="content-card-header">

                    <strong>
                        <i class="fa-solid fa-boxes-stacked me-2"></i>
                        Purchase Order Items
                    </strong>

                </div>


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


            <!-- REMARKS -->

            <?php if (!empty($po['remarks'])): ?>

                <div class="content-card mb-4">

                    <div class="content-card-header">

                        <strong>
                            <i class="fa-solid fa-comment me-2"></i>
                            Remarks
                        </strong>

                    </div>

                    <div class="content-card-body">

                        <?= nl2br(e($po['remarks'])) ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- ACTIONS -->
            <?php if (in_array($_SESSION['role_name'] ?? '', ['Super Admin', 'Purchase'], true)): ?>
            
            <div class="content-card">
                <div class="content-card-header">
                    <strong>
                        <i class="fa-solid fa-gears me-2"></i>
                        Purchase Order Actions
                    </strong>
                </div>

                <div class="content-card-body">
                    <div class="d-flex flex-wrap gap-2">

                        <!-- APPROVE (SUPER ADMIN ONLY) -->
                        <?php if ($po['status'] === 'Pending' && ($_SESSION['role_name'] ?? '') === 'Super Admin'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="approve_po">
                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Approve this purchase order?');">
                                    <i class="fa-solid fa-check me-1"></i> Approve PO
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- MARK ORDERED (PURCHASE ONLY) -->
                        <?php if ($po['status'] === 'Approved' && ($_SESSION['role_name'] ?? '') === 'Purchase'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="mark_ordered">
                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this purchase order as Ordered?');">
                                    <i class="fa-solid fa-cart-shopping me-1"></i> Mark as Ordered
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- CANCEL (SUPER ADMIN ONLY) -->
                        <?php if (!in_array($po['status'], ['Received', 'Cancelled'], true) && ($_SESSION['role_name'] ?? '') === 'Super Admin'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="cancel_po">
                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this purchase order?');">
                                    <i class="fa-solid fa-xmark me-1"></i> Cancel PO
                                </button>
                            </form>
                        <?php endif; ?>

                       <!-- STATUS ALERTS & INVOICE BUTTON -->
                        <?php if ($po['status'] === 'Received'): ?>
                            <?php if (!empty($invoice)): ?>
                                <div class="alert alert-info mb-0 w-100 d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fa-solid fa-file-invoice-dollar me-2"></i> 
                                        Invoice <strong><?= e($invoice['invoice_no']) ?></strong> status: <strong><?= e($invoice['status']) ?></strong>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <!-- APPROVE/REJECT BUTTONS FOR ADMIN -->
                                        <?php if ($invoice['status'] === 'Pending' && ($_SESSION['role_name'] ?? '') === 'Super Admin'): ?>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="action" value="approve_invoice">
                                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this invoice for payment?');">
                                                    <i class="fa-solid fa-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="action" value="reject_invoice">
                                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this invoice?');">
                                                    <i class="fa-solid fa-xmark me-1"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- MARK AS PAID BUTTON FOR PURCHASE -->
                                        <?php if ($invoice['status'] === 'Approved' && ($_SESSION['role_name'] ?? '') === 'Purchase'): ?>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="action" value="mark_invoice_paid">
                                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm payment made to supplier? This will close the invoice.');">
                                                    <i class="fa-solid fa-money-bill-wave me-1"></i> Mark as Paid
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif (($_SESSION['role_name'] ?? '') === 'Purchase'): ?>
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                                    <i class="fa-solid fa-file-invoice me-1"></i> Generate Invoice
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success mb-0 w-100">
                                    <i class="fa-solid fa-circle-check me-2"></i> Materials Received. Waiting for Purchase team to generate invoice.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($po['status'] === 'Cancelled'): ?>
                            <div class="alert alert-danger mb-0 w-100">
                                <i class="fa-solid fa-ban me-2"></i> This purchase order has been cancelled.
                            </div>
                        <?php endif; ?>

                        <!-- NO ACTION MESSAGE (IF APPLICABLE) -->
                        <?php 
                        if (
                            ($po['status'] === 'Pending' && ($_SESSION['role_name'] ?? '') === 'Purchase') ||
                            ($po['status'] === 'Approved' && ($_SESSION['role_name'] ?? '') === 'Super Admin') ||
                            ($po['status'] === 'Ordered')
                        ): ?>
                            <span class="text-muted"><i class="fa-solid fa-hourglass-half me-1"></i> Waiting for next step in process.</span>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>

</div>
<!-- ADD INVOICE MODAL -->
<?php if ($po['status'] === 'Received' && empty($invoice) && ($_SESSION['role_name'] ?? '') === 'Purchase'): ?>
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Generate Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_invoice">
                    <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                    <input type="hidden" name="po_total" value="<?= $grandTotal ?>">

                    <div class="mb-3">
                        <label class="form-label">Supplier Bill / Invoice Number <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_no" class="form-control" required placeholder="Ex: INV-1001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tax Amount (₹)</label>
                        <input type="number" name="tax_amount" class="form-control" step="0.01" min="0" value="0">
                        <small class="text-muted">Enter tax amount if applicable. Leave 0 if none.</small>
                    </div>

                    <!-- NEW FIELD FOR FILE UPLOAD -->
                    <div class="mb-3">
                        <label class="form-label">Upload Bill Copy (Optional)</label>
                        <input type="file" name="invoice_document" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Upload scan/photo of the physical bill.</small>
                    </div>
                    
                    <div class="alert alert-light border mb-0">
                        <strong>Base PO Amount: </strong> ₹<?= number_format($grandTotal, 2) ?><br>
                        <small class="text-info">Total Invoice Amount = Base Amount + Tax Amount</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Generate this invoice? It will be sent to Admin for approval.');">Generate Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>