<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Store Dashboard';

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

// Active materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
");

$totalMaterials = (int) $stmt->fetchColumn();


// Total current stock
$stmt = $con->query("
    SELECT COALESCE(SUM(current_stock), 0)
    FROM materials
    WHERE status = 'Enable'
");

$totalStock = (float) $stmt->fetchColumn();


// Low stock materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
      AND current_stock <= minimum_stock
");

$lowStockCount = (int) $stmt->fetchColumn();


// Pending purchase requests
$stmt = $con->query("
    SELECT COUNT(*)
    FROM purchase_requests
    WHERE status = 'Pending'
");

$pendingRequests = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| LOW STOCK MATERIALS
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
      AND current_stock <= minimum_stock
    ORDER BY material_name ASC
");

$lowStockMaterials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT STOCK TRANSACTIONS
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
    LIMIT 10
");

$recentTransactions = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$todayReceipts = 0.0;
$todayIssued = 0.0;
try 
{
    $q=$con->prepare("SELECT COALESCE(SUM(quantity),0) 
    FROM stock_transactions WHERE DATE(created_at)=? 
    AND transaction_type='PURCHASE'"); 
    $q->execute([date('Y-m-d')]); 
    $todayReceipts=(float)
    $q->fetchColumn(); 
} catch (Throwable $e) {}
try 
{ 
    $q=$con->prepare("SELECT COALESCE(SUM(quantity),0) 
    FROM stock_transactions WHERE DATE(created_at)=? 
    AND transaction_type='ISSUE_KITCHEN'"); 
    $q->execute([date('Y-m-d')]); 
    $todayIssued=(float)
    $q->fetchColumn(); 
} catch (Throwable $e) {}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="dashboard-hero">
            <div class="dashboard-heading">
            
            </div>
            <div class="hero-right">
                <div class="date-chip">
                    <i class="fa-regular fa-calendar-days"></i>
                    <div>
                        <strong><?= date('d-m-Y') ?></strong>
                        <span><?= date('l') ?></span>
                    </div>
                </div>
                <div class="motto-chip">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <div>
                        <strong>Right Stock Right Time</strong>
                        <span>Smart stock management</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="dash-stat">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Total Stock Items</div>
                        <div class="stat-value"><?= $totalMaterials ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ <?= number_format($totalStock,0) ?> 
                    total qty
                </div>
            </div>
            <div class="dash-stat stat-red">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Low Stock Items</div>
                        <div class="stat-value"><?= $lowStockCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="stat-trend down">↘ Needs attention</div>
            </div>
            <div class="dash-stat stat-purple">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Pending Purchase
                        </div>
                        <div class="stat-value">
                            <?= $pendingRequests ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    • Requests waiting
                </div>
            </div>
            <div class="dash-stat stat-green">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Today's Receipts
                        </div>
                        <div class="stat-value">
                            <?= number_format($todayReceipts,0) ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Received today
                </div>
            </div>
            <div class="dash-stat stat-cyan">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Total Issued</div>
                        <div class="stat-value"><?= number_format($todayIssued,0) ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Issued today
                </div>
            </div>
        </div>
        <div class="quick-card">
            <div class="section-title">
                <i class="fa-solid fa-bolt"></i> 
                Quick Actions
            </div>
            <div class="quick-actions">
                <a class="quick-action primary" href="purchase_requests.php">
                    <i class="fa-solid fa-cart-plus"></i>
                    Purchase Order
                </a>
                <a class="quick-action orange" href="stock.php">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    Stock Management
                </a>
                <a class="quick-action green" href="stock_inward.php">
                    <i class="fa-solid fa-box-open"></i>
                    Material Receive
                </a>
                <a class="quick-action purple" href="kitchen_requests.php">
                    <i class="fa-solid fa-utensils"></i>
                    Kitchen Requests
                </a>
            </div>
        </div>
        <div class="dashboard-table">
            <div class="table-header">
                <div class="section-title mb-0">
                    <i class="fa-solid fa-boxes-stacked"></i> 
                    Recent Stock Movements
                </div>
                <a href="item_history.php">View All ›</a>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentTransactions): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">No stock transactions found.</div>
                            </td>
                        </tr>
                        <?php else: foreach (array_slice($recentTransactions,0,5) as $tx): 
                            $type=$tx['transaction_type']; 
                            $cls=$type==='PURCHASE'?'status-success':
                            ($type==='ISSUE_KITCHEN'?'status-warning':'status-info'); 
                            $label=$type==='PURCHASE'?'Received':
                            ($type==='ISSUE_KITCHEN'?'Issued':ucwords(strtolower($type))); 
                        ?>
                        <tr>
                            <td>
                                <?= e(date('d-m-Y',strtotime($tx['created_at']))) ?>
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
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                
                </table>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
