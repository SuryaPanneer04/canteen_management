<?php

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Role Check
|--------------------------------------------------------------------------
*/
$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Canteen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Today's Food Received
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        COALESCE(SUM(received_qty), 0)
    FROM canteen_food_serving
    WHERE serving_date = ?
");

$stmt->execute([$today]);

$todayReceived = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Today's Food Served
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        COALESCE(SUM(served_qty), 0)
    FROM canteen_food_serving
    WHERE serving_date = ?
");

$stmt->execute([$today]);

$todayServed = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Today's Remaining
|--------------------------------------------------------------------------
*/
$todayRemaining = $todayReceived - $todayServed;

if ($todayRemaining < 0) {
    $todayRemaining = 0;
}


/*
|--------------------------------------------------------------------------
| Today's Wastage
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        COALESCE(SUM(wastage_qty), 0)
    FROM canteen_wastage
    WHERE wastage_date = ?
");

$stmt->execute([$today]);

$todayWastage = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Today's Pax
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        COALESCE(SUM(pax), 0)
    FROM canteen_food_serving
    WHERE serving_date = ?
");

$stmt->execute([$today]);

$todayPax = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Food Receiving
|--------------------------------------------------------------------------
*/
$stmt = $con->query("
    SELECT COUNT(*)
    FROM food_transfers
    WHERE status = 'Sent'
");

$pendingReceiving = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Today's Food Details
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        cfs.id,
        cfs.serving_date,
        cfs.received_qty,
        cfs.served_qty,
        cfs.remaining_qty,
        cfs.pax,

        fi.food_code,
        fi.food_name,
        fi.unit,

        COALESCE(
            (
                SELECT SUM(cw.wastage_qty)
                FROM canteen_wastage cw
                WHERE cw.serving_id = cfs.id
            ),
            0
        ) AS wastage_qty

    FROM canteen_food_serving cfs

    INNER JOIN food_items fi
        ON fi.id = cfs.food_id

    WHERE cfs.serving_date = ?

    ORDER BY cfs.id DESC
");

$stmt->execute([$today]);

$todayFoodDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Recent Wastage
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        cw.wastage_date,
        cw.wastage_qty,
        cw.reason,
        fi.food_name,
        fi.unit

    FROM canteen_wastage cw

    INNER JOIN food_items fi
        ON fi.id = cw.food_id

    ORDER BY cw.id DESC

    LIMIT 10
");

$stmt->execute();

$recentWastage = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Page Includes
|--------------------------------------------------------------------------
*/
$todayMeals = count($todayFoodDetails);
$foodPrepared = $todayReceived;
$servedToday = $todayServed;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="main-content">
<?php require_once '../includes/topbar.php'; ?>
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
                <i class="fa-solid fa-heart"></i>
                <div>
                    <strong>Healthy Food</strong>
                    <span>Healthy You</span>
                </div>
            </div>
        </div>
    </div>
    <div class="stats-grid">
        <div class="dash-stat">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Today's Meals</div>
                    <div class="stat-value"><?= $todayMeals ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-utensils"></i>
                </div>
            </div>
            <div class="stat-trend">↗ Food received</div>
        </div>
    <div class="dash-stat stat-red">
        <div class="stat-top">
            <div>
                <div class="stat-label">Food Prepared</div>
                <div class="stat-value">
                    <?= number_format($foodPrepared,0) ?>
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-kitchen-set"></i>
            </div>
        </div>
        <div class="stat-trend down">↘ Today's quantity</div>
    </div>
    <div class="dash-stat stat-cyan">
        <div class="stat-top">
            <div>
                <div class="stat-label">Pending Issue</div>
                <div class="stat-value">
                    <?= $pendingReceiving ?>
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>
        <div class="stat-trend down">↘ Awaiting receiving</div>
    </div>
    <div class="dash-stat stat-green">
        <div class="stat-top">
            <div>
                <div class="stat-label">Served Today</div>
                <div class="stat-value">
                    <?= number_format($servedToday,0) ?>
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-bowl-food"></i>
            </div>
        </div>
        <div class="stat-trend">↗ Meals served</div>
    </div>
    <div class="dash-stat stat-purple">
        <div class="stat-top">
            <div>
                <div class="stat-label">Customers</div>
                <div class="stat-value">
                    <?= number_format($todayPax) ?>
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="stat-trend">↗ Today's pax</div>
    </div>
 </div>
 <div class="quick-card">
    <div class="section-title">
        <i class="fa-solid fa-bolt"></i> 
        Quick Actions
    </div>
    <div class="quick-actions">
        <a class="quick-action primary" href="food_receiving.php">
            <i class="fa-solid fa-truck"></i>
            Meal Plan
        </a>
        <a class="quick-action orange" href="food_serving.php">
            <i class="fa-solid fa-utensils"></i>
            Food Preparation
        </a>
            <a class="quick-action green" href="food_serving.php">
                <i class="fa-solid fa-arrow-right"></i>
                Issue to Kitchen
            </a>
            <a class="quick-action purple" href="wastage.php">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Service History
            </a>
        </div>
    </div>
 <div class="dashboard-table">
    <div class="table-header">
        <div class="section-title mb-0">
            <i class="fa-solid fa-utensils"></i> 
            Today's Menu
        </div>
        <a href="food_receiving.php">View All ›</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Meal</th>
                    <th>Food Items</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$todayFoodDetails): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">No food received today.</div>
                        </td>
                    </tr>
                <?php else: $shown=0; 
                    foreach ($todayFoodDetails as $row): 
                    if($shown++>=5) 
                    break; 
                ?>
                    <tr>
                        <td>
                            <span class="meal-pill meal-lunch">Meal</span>
                        </td>
                        <td>
                            <strong><?= e($row['food_name']) ?></strong>
                        </td>
                        <td>
                            <?= number_format((float)$row['received_qty'],0) ?> 
                            <?= e($row['unit']) ?>
                        </td>
                        <td>
                            <span class="status-pill 
                                <?= (float)$row['served_qty']>0?'status-success':'status-warning' ?>">
                                <?= (float)$row['served_qty']>0?'Served':'Pending' ?>
                            </span>
                        </td>
                        <td>
                            <a class="view-btn" href="food_serving.php">
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
<?php require_once '../includes/footer.php'; ?>
