<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Kitchen Dashboard';

/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
*/

$allowedRoles = ['Kitchen', 'Super Admin'];

if (
    !isset($_SESSION['userrole']) ||
    !in_array($_SESSION['userrole'], $allowedRoles, true)
) {
    header('Location: ../index.php');
    exit;
}

$today = date('Y-m-d');

$error = null;

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

$recentPlans = [];

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
require_once __DIR__ . '/../includes/topbar.php';

?>

<div class="main-content">

<!--
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
-->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h4 class="mb-1">
            <i class="fa-solid fa-kitchen-set me-2"></i>
            Kitchen Dashboard
        </h4>

        <p class="text-muted mb-0">
            Cooking plan, material issue and food preparation overview.
        </p>

    </div>

    <div>

        <span class="badge bg-light text-dark border">
            <?= date('d-m-Y') ?>
        </span>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
-->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <i class="fa-solid fa-circle-exclamation me-2"></i>

        <?= e($error) ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
-->

<div class="row g-3 mb-4">


    <!-- TODAY PLANS -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Today's Plans
                        </small>

                        <h3 class="mb-0">
                            <?= $todayPlans ?>
                        </h3>

                    </div>

                    <div class="text-primary fs-3">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- PENDING APPROVAL -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Chef Approval
                        </small>

                        <h3 class="mb-0 text-warning">
                            <?= $pendingApproval ?>
                        </h3>

                    </div>

                    <div class="text-warning fs-3">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- WAITING STORE -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Store Pending
                        </small>

                        <h3 class="mb-0 text-primary">
                            <?= $waitingStore ?>
                        </h3>

                    </div>

                    <div class="text-primary fs-3">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- MATERIALS ISSUED -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Materials Issued
                        </small>

                        <h3 class="mb-0 text-success">
                            <?= $materialsIssued ?>
                        </h3>

                    </div>

                    <div class="text-success fs-3">
                        <i class="fa-solid fa-box-open"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- READY TO COOK -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Ready to Cook
                        </small>

                        <h3 class="mb-0 text-danger">
                            <?= $readyForPreparation ?>
                        </h3>

                    </div>

                    <div class="text-danger fs-3">
                        <i class="fa-solid fa-fire-burner"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- SENT TO CANTEEN -->

    <div class="col-xl-2 col-md-4 col-sm-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Sent to Canteen
                        </small>

                        <h3 class="mb-0 text-info">
                            <?= $sentToCanteen ?>
                        </h3>

                    </div>

                    <div class="text-info fs-3">
                        <i class="fa-solid fa-truck"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| QUICK ACTIONS
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">
            <i class="fa-solid fa-bolt me-2"></i>
            Quick Actions
        </h5>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-3">

                <a
                    href="daily_cooking_plan.php"
                    class="btn btn-outline-primary w-100 py-3"
                >

                    <i class="fa-solid fa-calendar-plus fa-lg me-2"></i>

                    Daily Cooking Plan

                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="chef_approval.php"
                    class="btn btn-outline-warning w-100 py-3"
                >

                    <i class="fa-solid fa-user-check fa-lg me-2"></i>

                    Chef Approval

                    <?php if ($pendingApproval > 0): ?>

                        <span class="badge bg-danger ms-1">
                            <?= $pendingApproval ?>
                        </span>

                    <?php endif; ?>

                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="food_preparation.php"
                    class="btn btn-outline-success w-100 py-3"
                >

                    <i class="fa-solid fa-utensils fa-lg me-2"></i>

                    Food Preparation

                    <?php if ($readyForPreparation > 0): ?>

                        <span class="badge bg-danger ms-1">
                            <?= $readyForPreparation ?>
                        </span>

                    <?php endif; ?>

                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="issue_history.php"
                    class="btn btn-outline-secondary w-100 py-3"
                >

                    <i class="fa-solid fa-clock-rotate-left fa-lg me-2"></i>

                    Issue History

                </a>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| TODAY'S COOKING PLAN
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">

            <i class="fa-solid fa-calendar-day me-2"></i>

            Today's Cooking Plan

        </h5>

    </div>

    <div class="card-body">

        <?php if (empty($mealSummary)): ?>

            <div class="text-center py-4">

                <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3"></i>

                <h5>
                    No cooking plan for today
                </h5>

                <p class="text-muted mb-3">
                    Create a daily cooking plan for today's meals.
                </p>

                <a
                    href="daily_cooking_plan.php"
                    class="btn btn-primary"
                >
                    <i class="fa-solid fa-plus me-1"></i>
                    Create Cooking Plan
                </a>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

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

                    <?php foreach ($mealSummary as $index => $plan): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>

                                <span class="badge <?= dashboardMealClass(
                                    (string)$plan['meal_type']
                                ) ?>">

                                    <?= e($plan['meal_type']) ?>

                                </span>

                            </td>

                            <td>

                                <?= (int)$plan['food_count'] ?>

                                <?= (int)$plan['food_count'] === 1
                                    ? 'food'
                                    : 'foods' ?>

                            </td>

                            <td>

                                <strong>

                                    <?= number_format(
                                        (float)$plan['total_plates'],
                                        0
                                    ) ?>

                                </strong>

                                plates

                            </td>

                            <td>

                                <span class="badge <?= dashboardStatusClass(
                                    (string)$plan['status']
                                ) ?>">

                                    <?= e($plan['status']) ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="daily_cooking_plan.php?edit=<?= (int)$plan['id'] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >

                                    <i class="fa-solid fa-eye"></i>

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| RECENT COOKING PLANS
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-header">

        <h5 class="mb-0">

            <i class="fa-solid fa-clock-rotate-left me-2"></i>

            Recent Cooking Plans

        </h5>

    </div>

    <div class="card-body">

        <?php if (empty($recentPlans)): ?>

            <div class="text-center text-muted py-4">

                No cooking plans found.

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Date</th>

                            <th>Meal</th>

                            <th>Foods</th>

                            <th>Plates</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($recentPlans as $index => $plan): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>

                                <?= e(
                                    date(
                                        'd-m-Y',
                                        strtotime(
                                            $plan['cooking_date']
                                        )
                                    )
                                ) ?>

                            </td>

                            <td>

                                <span class="badge <?= dashboardMealClass(
                                    (string)$plan['meal_type']
                                ) ?>">

                                    <?= e($plan['meal_type']) ?>

                                </span>

                            </td>

                            <td>

                                <?= (int)$plan['food_count'] ?>

                            </td>

                            <td>

                                <?= number_format(
                                    (float)$plan['total_plates'],
                                    0
                                ) ?>

                            </td>

                            <td>

                                <span class="badge <?= dashboardStatusClass(
                                    (string)$plan['status']
                                ) ?>">

                                    <?= e($plan['status']) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
