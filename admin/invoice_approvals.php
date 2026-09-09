<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL (SUPER ADMIN ONLY)
|--------------------------------------------------------------------------
*/
if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Invoice Approvals';
$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE APPROVE / REJECT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    if ($invoiceId > 0) {
        if ($action === 'approve') {
            $stmt = $con->prepare("UPDATE invoices SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'Pending'");
            $stmt->execute([$_SESSION['user_id'], $invoiceId]);
            $success = 'Invoice approved for payment successfully.';
        } elseif ($action === 'reject') {
            $stmt = $con->prepare("UPDATE invoices SET status = 'Rejected', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'Pending'");
            $stmt->execute([$_SESSION['user_id'], $invoiceId]);
            $success = 'Invoice rejected.';
        }
    } else {
        $error = 'Invalid Invoice ID.';
    }
}

/*
|--------------------------------------------------------------------------
| FETCH INVOICES
|--------------------------------------------------------------------------
*/
$stmt = $con->query("
    SELECT 
        i.*, 
        po.po_no, 
        s.supplier_name,
        u.employee_name AS created_by_name
    FROM invoices i
    INNER JOIN purchase_orders po ON i.po_id = po.id
    INNER JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON i.created_by = u.id
    ORDER BY i.created_at DESC
");
$invoices = $stmt->fetchAll();

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
                    <h4 class="mb-1"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Invoice Approvals</h4>
                    <p class="text-muted mb-0">Review and approve supplier invoices for payment.</p>
                </div>
            </div>

            <!-- ALERTS -->
            <?php if ($success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i> <?= e($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- INVOICE LIST -->
            <div class="content-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No & Date</th>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Uploaded By</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-center">Document</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$invoices): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No invoices found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($inv['invoice_no']) ?></strong><br>
                                            <small class="text-muted"><?= e($inv['invoice_date']) ?></small>
                                        </td>
                                        <td><a href="../purchase/purchase_order_view.php?id=<?= (int)$inv['po_id'] ?>"><?= e($inv['po_no']) ?></a></td>
                                        <td><?= e($inv['supplier_name']) ?></td>
                                        <td><?= e($inv['created_by_name'] ?? 'System') ?></td>
                                        <td class="text-end">
                                            <strong>₹<?= number_format((float)$inv['total_amount'], 2) ?></strong><br>
                                            <small class="text-muted">Tax: ₹<?= number_format((float)$inv['tax_amount'], 2) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($inv['document_path'])): ?>
                                                <a href="../<?= e($inv['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View Document">
                                                    <i class="fa-solid fa-file-pdf"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">No File</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $badgeClass = match($inv['status']) {
                                                    'Pending' => 'bg-warning text-dark',
                                                    'Approved' => 'bg-primary',
                                                    'Paid' => 'bg-success',
                                                    'Rejected' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= e($inv['status']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($inv['status'] === 'Pending'): ?>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Approve" onclick="return confirm('Approve this invoice for payment?');">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Reject this invoice?');">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>