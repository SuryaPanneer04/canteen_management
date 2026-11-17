<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Item History';

/*
|--------------------------------------------------------------------------
| ALL STOCK TRANSACTIONS HISTORY
|--------------------------------------------------------------------------
*/
$stmt = $con->query("
    SELECT
        st.id,
        st.transaction_type,
        st.quantity,
        st.remarks,
        st.created_at,
        m.material_name,
        m.unit,
        u.employee_name
    FROM stock_transactions st
    INNER JOIN materials m
        ON m.id = st.material_id
    LEFT JOIN users u
        ON u.id = st.created_by
    ORDER BY st.id DESC
");

$allTransactions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-body">
        
        <div class="dashboard-table">
            <div class="table-header">
                <div class="section-title mb-0">
                    <i class="fa-solid fa-clock-rotate-left"></i> 
                    Item History
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Material</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Remarks</th>
                            <th class="text-center">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$allTransactions): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">No stock transactions found.</div>
                            </td>
                        </tr>
                        <?php else: foreach ($allTransactions as $tx): 
                            $type=$tx['transaction_type']; 
                            $cls=$type==='PURCHASE'?'status-success':
                            ($type==='ISSUE_KITCHEN'?'status-warning':'status-info'); 
                            $label=$type==='PURCHASE'?'Received':
                            ($type==='ISSUE_KITCHEN'?'Issued':ucwords(strtolower($type))); 
                        ?>
                        <tr>
                            <td>
                                <?= e(date('d-m-Y h:i A', strtotime($tx['created_at']))) ?>
                            </td>
                            <td>
                                <strong><?= e($tx['material_name']) ?></strong>
                            </td>
                            <td>
                                <span class="status-pill <?= $cls ?>"><?= e($label) ?></span>
                            </td>
                            <td>
                                <?= number_format((float)$tx['quantity'],2) ?> 
                                <?= e($tx['unit']) ?>
                            </td>
                            <td>
                                <?= e($tx['remarks']??'-') ?>
                            </td>
                            <td class="text-center">
                                <!-- View details button with Eye Icon -->
                                <a href="view_transaction.php?id=<?= $tx['id'] ?>" class="btn btn-sm btn-light border text-primary" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>