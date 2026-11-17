<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Purchase Dashboard';

$role = $_SESSION['role_name'] ?? '';

/*
|--------------------------------------------------------------------------
| ONLY PURCHASE / ADMIN
|--------------------------------------------------------------------------
*/

if ($role !== 'Purchase' && $role !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| PENDING REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT COUNT(*) 
    FROM purchase_requests
    WHERE status = 'Pending'
");

$pendingRequests = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| APPROVED REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT COUNT(*)
    FROM purchase_requests
    WHERE status = 'Approved'
");

$approvedRequests = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| REJECTED REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT COUNT(*)
    FROM purchase_requests
    WHERE status = 'Rejected'
");

$rejectedRequests = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT COUNT(*)
    FROM purchase_requests
");

$totalRequests = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| RECENT PURCHASE REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,
        u.employee_name,

        COUNT(pri.id) AS item_count

    FROM purchase_requests pr

    INNER JOIN users u
        ON u.id = pr.requested_by

    LEFT JOIN purchase_request_items pri
        ON pri.request_id = pr.id

    GROUP BY
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,
        u.employee_name

    ORDER BY pr.id DESC

    LIMIT 10
");

$recentRequests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$totalPO = 0; 
$receivedToday = 0; 
$totalSuppliers = 0; 
$totalValue = 0.0; 
$recentPOs = [];
try 
{ 
    $totalPO=(int)
    $con->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn(); 
} catch(Throwable $e) {}
try 
{ 
    $q=$con->prepare("SELECT COUNT(*) FROM purchase_orders WHERE status='Received' AND po_date=?"); 
    $q->execute([date('Y-m-d')]); 
    $receivedToday=(int)
    $q->fetchColumn(); 
} catch(Throwable $e) {}
try 
{ 
    $totalSuppliers=(int)
    $con->query("SELECT COUNT(*) FROM suppliers WHERE status='Enable'")->fetchColumn(); 
} catch(Throwable $e) {}
try 
{ 
    $totalValue=(float)
    $con->query("SELECT COALESCE(SUM(total_amount),0) FROM purchase_order_items")->fetchColumn(); 
} catch(Throwable $e) {}
try
{ 
    $recentPOs=$con->query("SELECT po.po_no, po.po_date, po.status, s.supplier_name, 
    COALESCE(SUM(poi.total_amount),0) total_amount 
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON s.id=po.supplier_id 
    LEFT JOIN purchase_order_items poi ON poi.po_id=po.id 
    GROUP BY po.id,po.po_no,po.po_date,po.status,s.supplier_name 
    ORDER BY po.id DESC LIMIT 5")->fetchAll(); 
} catch(Throwable $e) {}

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
                    <i class="fa-solid fa-basket-shopping"></i>
                    <div>
                        <strong>Better Purchase</strong>
                        <span>Better Business</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="dash-stat">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Total PO
                        </div>
                        <div class="stat-value">
                            <?= $totalPO ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ All purchase orders
                </div>
            </div>
            <div class="dash-stat stat-orange">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Pending PO
                        </div>
                        <div class="stat-value">
                            <?= $pendingRequests ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="stat-trend down">
                    ↘ Awaiting action
                </div>
            </div>
            <div class="dash-stat stat-green">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Received Today
                        </div>
                        <div class="stat-value">
                            <?= $receivedToday ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Completed receipts
                </div>
            </div>
            <div class="dash-stat stat-purple">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Total Suppliers
                        </div>
                        <div class="stat-value">
                            <?= $totalSuppliers ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    • Active suppliers
                </div>
            </div>
            <div class="dash-stat stat-cyan">
                <div class="stat-top">
                    <div><div class="stat-label">
                        Total Value
                    </div>
                    <div class="stat-value">
                        ₹<?= number_format($totalValue,0) ?>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="stat-trend">
                ↗ Order value
            </div>
        </div>
    </div>
    <div class="quick-card">
        <div class="section-title">
            <i class="fa-solid fa-bolt"></i> 
            Quick Actions
        </div>
        <div class="quick-actions">
            <a class="quick-action primary" href="purchase_orders.php">
                <i class="fa-solid fa-cart-plus"></i>
                Create PO
            </a>
            <a class="quick-action orange" href="suppliers.php">
                <i class="fa-solid fa-user-plus"></i>
                Supplier Master
            </a>
            <!--<a class="quick-action green" href="../store/purchase_order_receiving.php">
                <i class="fa-solid fa-box-open"></i>
                GRN
            </a>-->
            <a class="quick-action purple" href="requests.php">
                <i class="fa-solid fa-chart-column"></i>
                Purchase Report
            </a>
        </div>
    </div>
    <div class="dashboard-table">
        <div class="table-header">
            <div class="section-title mb-0">
                <i class="fa-solid fa-cart-shopping"></i> 
                Recent Purchase Orders
            </div>
            <a href="purchase_orders.php">
                View All ›
            </a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>PO No</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentPOs): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">No purchase orders found.</div>
                        </td>
                    </tr>
                    <?php else: foreach ($recentPOs as $po): 
                        $cls=$po['status']==='Received'?'status-success':
                        ($po['status']==='Pending'?'status-warning':
                        ($po['status']==='Cancelled'?'status-danger':'status-info')); 
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($po['po_no']) ?></strong>
                        </td>
                        <td>
                            <?= e($po['supplier_name']??'-') ?>
                        </td>
                        <td>
                            <?= e(date('d-m-Y',strtotime($po['po_date']))) ?>
                        </td>
                        <td>
                            <span class="status-pill <?= $cls ?>"><?= e($po['status']) ?></span>
                        </td>
                        <td>
                            ₹ <?= number_format((float)$po['total_amount'],2) ?>
                        </td>
                        <td>
                            <a class="view-btn" href="purchase_orders.php">
                                View
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
