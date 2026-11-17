<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'Kitchen',
    'Super Admin'
];

if (
    !in_array(
        $_SESSION['role_name'] ?? '',
        $allowedRoles,
        true
    )
) {
    header('Location: ../index.php');
    exit;
}


$pageTitle = 'Kitchen Dashboard';
$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| DEFAULT COUNTS
|--------------------------------------------------------------------------
*/

$todayPlans = 0;
$pendingApproval = 0;
$waitingStore = 0;
$materialsIssued = 0;
$readyForPreparation = 0;
$sentToCanteen = 0;

$mealSummary = [];
$recentPlans = [];

$error = null;

/*
|--------------------------------------------------------------------------
| TODAY'S COOKING PLANS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $con->prepare("
        SELECT COUNT(*)
        FROM daily_cooking_plans
        WHERE cooking_date = ?
        AND status <> 'Cancelled'
    ");

    $stmt->execute([$today]);

    $todayPlans = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $error = 'Unable to load cooking plan summary.';
}


/*
|--------------------------------------------------------------------------
| PENDING CHEF APPROVAL
|--------------------------------------------------------------------------
*/

try {

    $stmt = $con->query("
        SELECT COUNT(*)
        FROM daily_cooking_plans
        WHERE status = 'Pending Approval'
    ");

    $pendingApproval = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $pendingApproval = 0;
}


/*
|--------------------------------------------------------------------------
| WAITING FOR STORE
|--------------------------------------------------------------------------
|
| Chef has approved and sent the material request to Store.
|
*/

try {

    $stmt = $con->query("
        SELECT COUNT(*)
        FROM kitchen_requests
        WHERE plan_id IS NOT NULL
        AND status IN ('Sent to Store', 'Partially Issued')
    ");

    $waitingStore = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $waitingStore = 0;
}


/*
|--------------------------------------------------------------------------
| COMPLETED STORE ISSUES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $con->query("
        SELECT COUNT(*)
        FROM kitchen_requests
        WHERE plan_id IS NOT NULL
        AND status = 'Completed'
    ");

    $materialsIssued = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $materialsIssued = 0;
}


/*
|--------------------------------------------------------------------------
| FOOD READY FOR PREPARATION
|--------------------------------------------------------------------------
|
| A cooking plan item is ready when:
|
| 1. Store request is completed
| 2. Food preparation has not yet been created
|
*/

try {

    $stmt = $con->query("
        SELECT COUNT(*)

        FROM daily_cooking_plan_items dpi

        INNER JOIN daily_cooking_plans dcp
            ON dcp.id = dpi.cooking_plan_id

        INNER JOIN kitchen_requests kr
            ON kr.plan_id = dcp.id
            AND kr.status = 'Completed'

        LEFT JOIN food_preparations fp
            ON fp.plan_item_id = dpi.id

        WHERE dcp.status IN ('Sent to Store', 'Completed')
        AND fp.id IS NULL
    ");

    $readyForPreparation = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $readyForPreparation = 0;
}


/*
|--------------------------------------------------------------------------
| SENT TO CANTEEN
|--------------------------------------------------------------------------
*/

try {

    $stmt = $con->query("
        SELECT COUNT(*)
        FROM food_transfers
        WHERE status = 'Sent'
    ");

    $sentToCanteen = (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $sentToCanteen = 0;
}


/*
|--------------------------------------------------------------------------
| TODAY MEAL SUMMARY
|--------------------------------------------------------------------------
*/

try {

    $stmt = $con->prepare("
        SELECT
            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,

            COUNT(dpi.id) AS food_count,

            COALESCE(
                SUM(dpi.required_plates),
                0
            ) AS total_plates

        FROM daily_cooking_plans dcp

        LEFT JOIN daily_cooking_plan_items dpi
            ON dpi.cooking_plan_id = dcp.id

        WHERE dcp.cooking_date = ?

        GROUP BY
            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status

        ORDER BY
            FIELD(
                dcp.meal_type,
                'Breakfast',
                'Lunch',
                'Snacks',
                'Dinner'
            )
    ");

    $stmt->execute([$today]);

    $mealSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $mealSummary = [];
}


/*
|--------------------------------------------------------------------------
| RECENT COOKING PLANS
|--------------------------------------------------------------------------
*/



try {

    $stmt = $con->query("
        SELECT
            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,
            dcp.created_at,

            COUNT(dpi.id) AS food_count,

            COALESCE(
                SUM(dpi.required_plates),
                0
            ) AS total_plates

        FROM daily_cooking_plans dcp

        LEFT JOIN daily_cooking_plan_items dpi
            ON dpi.cooking_plan_id = dcp.id

        WHERE dcp.status <> 'Cancelled'

        GROUP BY
            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,
            dcp.created_at

        ORDER BY
            dcp.cooking_date DESC,
            dcp.id DESC

        LIMIT 10
    ");

    $recentPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $recentPlans = [];
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function dashboardStatusClass(string $status): string
{
    return match ($status) {

        'Draft'
            => 'bg-secondary',

        'Pending Approval'
            => 'bg-warning text-dark',

        'Approved'
            => 'bg-info text-dark',

        'Sent to Store'
            => 'bg-primary',

        'Completed'
            => 'bg-success',

        'Cancelled'
            => 'bg-danger',

        default
            => 'bg-secondary'
    };
}


function dashboardMealClass(string $meal): string
{
    return match ($meal) {

        'Breakfast'
            => 'bg-warning text-dark',

        'Lunch'
            => 'bg-primary',

        'Snacks'
            => 'bg-info text-dark',

        'Dinner'
            => 'bg-dark',

        default
            => 'bg-secondary'
    };
}


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
                    <i class="fa-solid fa-utensils"></i>
                    <div>
                        <strong>Fresh Food</strong>
                        <span>Every Day</span>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3">
            <?= e($error) ?>
        </div>
        <?php endif; ?>
        <div class="stats-grid">
            <div class="dash-stat">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Today's Plans</div>
                            <div class="stat-value">
                                <?= $todayPlans ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                    </div>
                    <div class="stat-trend">↗ From yesterday</div>
                </div>
                <div class="dash-stat stat-orange">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Chef Approval</div>
                            <div class="stat-value">
                                <?= $pendingApproval ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-trend neutral">→ No change</div>
                </div>
                <div class="dash-stat stat-purple">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Store Pending</div>
                            <div class="stat-value"><?= $waitingStore ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                    </div>
                    <div class="stat-trend neutral">
                        → Materials waiting
                    </div>
                </div>
                <div class="dash-stat stat-green">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">
                                Materials Issued
                            </div>
                            <div class="stat-value">
                                <?= $materialsIssued ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        ↗ Completed
                    </div>
                </div>
                <div class="dash-stat stat-red">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">
                                Ready to Cook
                            </div>
                            <div class="stat-value">
                                <?= $readyForPreparation ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-fire-burner"></i>
                        </div>
                    </div>
                    <div class="stat-trend neutral">
                        → Ready items
                    </div>
                </div>
                <div class="dash-stat stat-cyan">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">
                                Sent to Canteen
                            </div>
                            <div class="stat-value">
                                <?= $sentToCanteen ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        ↗ Food transfers
                    </div>
                </div>
            </div>
            <div class="quick-card">
                <div class="section-title">
                    <i class="fa-solid fa-bolt"></i>
                     Quick Actions
                    </div>
                    <div class="quick-actions">
                        <a class="quick-action primary" href="daily_cooking_plan.php">
                            <i class="fa-solid fa-calendar-days"></i>
                            Daily Cooking Plan
                        </a>
                        <a class="quick-action orange" href="chef_approval.php">
                            <i class="fa-solid fa-user-check"></i>
                            Chef Approval
                        </a>
                        <a class="quick-action green" href="food_preparation.php">
                            <i class="fa-solid fa-kitchen-set"></i>
                            Food Preparation
                        </a>
                        <a class="quick-action purple" href="issue_history.php">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            Issue History
                        </a>
                    </div>
                </div>
                <div class="dashboard-table">
                    <div class="table-header">
                        <div class="section-title mb-0">
                            <i class="fa-solid fa-calendar-days"></i>
                             Today's Cooking Plan
                            </div>
                            <a href="daily_cooking_plan.php">
                                View All ›
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Meal</th>
                                        <th>Food Items</th>
                                        <th>Total Plates</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$mealSummary): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                No cooking plans found for today.
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: foreach ($mealSummary as $i=>$plan): $status=$plan['status']; 
                                    $cls=$status==='Completed'?'status-success':
                                    ($status==='Pending Approval'?'status-warning':
                                    ($status==='Cancelled'?'status-danger':'status-info')); 
                                    $meal=strtolower((string)$plan['meal_type']);
                                    $mealCls='meal-'.($meal==='breakfast'?'breakfast':
                                    ($meal==='lunch'?'lunch':($meal==='snacks'?'snacks':'dinner'))); ?>
                                    <tr>
                                        <td><?= $i+1 ?></td>
                                        <td>
                                            <span class="meal-pill <?= $mealCls ?>"><?= e($plan['meal_type']) ?></span>
                                        </td>
                                        <td>
                                            <?= (int)$plan['food_count'] ?> 
                                            food
                                        </td>
                                        <td>
                                            <?= number_format((float)$plan['total_plates'],0) ?>
                                            plates
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $cls ?>"><?= e($status) ?></span>
                                        </td>
                                        <td>
                                            <a class="view-btn" href="daily_cooking_plan.php?edit=<?= (int)$plan['id'] ?>">
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
