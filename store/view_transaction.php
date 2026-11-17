<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'View Transaction';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: item_history.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH TRANSACTION DETAILS (Without Supplier/Price info)
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        st.id,
        st.transaction_type,
        st.quantity,
        st.remarks,
        st.created_at,
        m.material_code,
        m.material_name,
        m.category,
        m.unit,
        u.employee_name
    FROM stock_transactions st
    INNER JOIN materials m ON m.id = st.material_id
    LEFT JOIN users u ON u.id = st.created_by
    WHERE st.id = ?
");
$stmt->execute([$id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    header('Location: item_history.php');
    exit;
}

$type = $tx['transaction_type'];
$cls = $type === 'PURCHASE' ? 'text-success' : ($type === 'ISSUE_KITCHEN' ? 'text-warning' : 'text-info');
$label = $type === 'PURCHASE' ? 'Received (GRN)' : ($type === 'ISSUE_KITCHEN' ? 'Issued to Kitchen' : ucwords(strtolower($type)));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
    <div class="page-body">
        
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="section-title mb-0">
                <i class="fa-solid fa-file-lines me-2 text-primary"></i> 
                Transaction Details
            </div>
            <a href="item_history.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to History
            </a>
        </div>

        <!-- Details Card -->
        <div class="content-card shadow-sm border-0 mb-4">
            <div class="content-card-body p-4">
                
                <div class="row g-4">
                    
                    <!-- Material Information -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-box-open me-2"></i>Material Information
                        </h6>
                        <table class="table table-borderless table-sm mt-3">
                            <tbody>
                                <tr>
                                    <th style="width: 160px;" class="text-muted">Material Code</th>
                                    <td><span class="badge bg-light text-dark border"><?= e($tx['material_code']) ?></span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Material Name</th>
                                    <td class="fs-6"><strong><?= e($tx['material_name']) ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Category</th>
                                    <td><?= e($tx['category']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Movement Details -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-truck-ramp-box me-2"></i>Movement Details
                        </h6>
                        <table class="table table-borderless table-sm mt-3">
                            <tbody>
                                <tr>
                                    <th style="width: 160px;" class="text-muted">Transaction Type</th>
                                    <td class="<?= $cls ?>"><strong><?= e($label) ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Quantity</th>
                                    <td>
                                        <span class="badge bg-light text-dark fs-6 border border-2">
                                            <?= number_format((float)$tx['quantity'], 2) ?> <?= e($tx['unit']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Date & Time</th>
                                    <td><?= e(date('d-m-Y h:i A', strtotime($tx['created_at']))) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Handled By</th>
                                    <td><i class="fa-solid fa-user-circle me-1 text-muted"></i> <?= e($tx['employee_name'] ?? 'System') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                </div>
                
                <!-- Remarks Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="text-muted mb-2 border-bottom pb-2">
                            <i class="fa-solid fa-comment-dots me-2"></i>Remarks / Reference
                        </h6>
                        <div class="p-3 mt-2 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0; color: #475569;">
                            <?= e($tx['remarks'] ?? 'No additional remarks provided.') ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>