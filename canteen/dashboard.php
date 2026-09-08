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
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/topbar.php';

?>

<div class="main-content">

<div class="page-body">

    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>

            <h4 class="page-header-title">
                <i class="fa-solid fa-gauge-high me-2"></i>
                Canteen Dashboard
            </h4>

            <p class="page-header-subtitle">
                Daily food receiving, serving and wastage summary.
            </p>

        </div>

        <div>

            <span class="badge badge-count p-2">

                <i class="fa-solid fa-calendar-days me-1"></i>

                <?= date('d-m-Y') ?>

            </span>

        </div>

    </div>


    <!-- ======================================================
         SUMMARY CARDS
    ======================================================= -->

    <div class="row g-3 mb-4">

        <!-- Received -->
        <div class="col-xl-3 col-md-6">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-truck"></i>
                </div>

                <div>
                    <div class="stat-label">Today's Received</div>
                    <div class="stat-value">
                        <?= number_format($todayReceived, 2) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Served -->
        <div class="col-xl-3 col-md-6">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <div>
                    <div class="stat-label">Today's Served</div>
                    <div class="stat-value">
                        <?= number_format($todayServed, 2) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Remaining -->
        <div class="col-xl-3 col-md-6">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div>
                    <div class="stat-label">Today's Remaining</div>
                    <div class="stat-value">
                        <?= number_format($todayRemaining, 2) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Wastage -->
        <div class="col-xl-3 col-md-6">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-red">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <div>
                    <div class="stat-label">Today's Wastage</div>
                    <div class="stat-value">
                        <?= number_format($todayWastage, 2) ?>
                    </div>
                </div>

            </div>

        </div>

    </div>


    <!-- ======================================================
         SECONDARY SUMMARY
    ======================================================= -->

    <div class="row g-3 mb-4">

        <!-- Pax -->
        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-purple">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <div class="stat-label">Today's Pax</div>
                    <div class="stat-value">
                        <?= number_format($todayPax) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Pending Receiving -->
        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-inbox"></i>
                </div>

                <div>
                    <div class="stat-label">Pending Food Receiving</div>
                    <div class="stat-value">
                        <?= number_format($pendingReceiving) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Date -->
        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>

                <div>
                    <div class="stat-label">Working Date</div>
                    <div class="stat-value" style="font-size: 20px;">
                        <?= date('d M Y') ?>
                    </div>
                </div>

            </div>

        </div>

    </div>


    <!-- ======================================================
         TODAY FOOD DETAILS
    ======================================================= -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <h5 class="mb-0">

                <i class="fa-solid fa-utensils me-2"></i>

                Today's Food Details

            </h5>

        </div>

        <div class="content-card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Food</th>

                            <th>Received</th>

                            <th>Served</th>

                            <th>Remaining</th>

                            <th>Wastage</th>

                            <th>Pax</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($todayFoodDetails)): ?>

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted py-4">

                                No food received today.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php $sl = 1; ?>

                        <?php foreach ($todayFoodDetails as $row): ?>

                            <?php

                            $remainingAfterWastage =
                                (float)$row['remaining_qty']
                                - (float)$row['wastage_qty'];

                            if ($remainingAfterWastage < 0) {
                                $remainingAfterWastage = 0;
                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $sl++ ?>
                                </td>

                                <td>

                                    <div class="food-cell">

                                        <span class="food-icon">
                                            <i class="fa-solid fa-utensils"></i>
                                        </span>

                                        <div>

                                            <strong>
                                                <?= e($row['food_name']) ?>
                                            </strong>

                                            <br>

                                            <small class="text-muted">
                                                <?= e($row['food_code']) ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <?= number_format(
                                        (float)$row['received_qty'],
                                        2
                                    ) ?>

                                    <?= e($row['unit']) ?>

                                </td>

                                <td>

                                    <?= number_format(
                                        (float)$row['served_qty'],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <span class="badge remaining-pill">

                                        <?= number_format(
                                            $remainingAfterWastage,
                                            2
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ((float)$row['wastage_qty'] > 0): ?>

                                        <span class="badge badge-disabled">

                                            <?= number_format(
                                                (float)$row['wastage_qty'],
                                                2
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-enable">
                                            0.00
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="badge badge-count">

                                        <?= number_format((int)$row['pax']) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ======================================================
         RECENT WASTAGE
    ======================================================= -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <h5 class="mb-0">

                <i class="fa-solid fa-trash-can me-2"></i>

                Recent Wastage

            </h5>

        </div>

        <div class="content-card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Date</th>

                            <th>Food</th>

                            <th>Quantity</th>

                            <th>Reason</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($recentWastage)): ?>

                        <tr>

                            <td colspan="5"
                                class="text-center text-muted py-4">

                                No wastage records found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php $sl = 1; ?>

                        <?php foreach ($recentWastage as $row): ?>

                            <tr>

                                <td>
                                    <?= $sl++ ?>
                                </td>

                                <td>
                                    <?= e($row['wastage_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['food_name']) ?>
                                </td>

                                <td>

                                    <span class="badge badge-disabled">

                                        <?= number_format(
                                            (float)$row['wastage_qty'],
                                            2
                                        ) ?>

                                        <?= e($row['unit']) ?>

                                    </span>

                                </td>

                                <td>

                                    <?= e($row['reason'] ?: '-') ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ======================================================
         QUICK ACTIONS
    ======================================================= -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <a href="food_receiving.php" class="stat-card quick-action-card">

                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-truck"></i>
                </div>

                <div>
                    <div class="quick-action-label">Food Receiving</div>
                    <div class="quick-action-sub">Receive from Kitchen</div>
                </div>

            </a>

        </div>

        <div class="col-md-4">

            <a href="food_serving.php" class="stat-card quick-action-card">

                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <div>
                    <div class="quick-action-label">Food Serving</div>
                    <div class="quick-action-sub">Update served qty &amp; pax</div>
                </div>

            </a>

        </div>

        <div class="col-md-4">

            <a href="wastage.php" class="stat-card quick-action-card">

                <div class="stat-icon stat-icon-red">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <div>
                    <div class="quick-action-label">Record Wastage</div>
                    <div class="quick-action-sub">Log food wastage</div>
                </div>

            </a>

        </div>

    </div>

</div>

</div>


<?php

require_once '../includes/footer.php';

?>