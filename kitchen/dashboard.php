<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


// =========================================================
// ACCESS CONTROL
// =========================================================

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


// =========================================================
// DASHBOARD COUNTS
// =========================================================


// ---------------------------------------------------------
// ACTIVE MATERIALS
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
");

$totalMaterials = (int) $stmt->fetchColumn();


// ---------------------------------------------------------
// MATERIALS ISSUED TO KITCHEN TODAY
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM stock_transactions
    WHERE transaction_type = 'ISSUE_KITCHEN'
      AND DATE(created_at) = CURDATE()
");

$todayIssued = (float) $stmt->fetchColumn();


// ---------------------------------------------------------
// KITCHEN REQUESTS WAITING FOR CHEF
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COUNT(*)
    FROM kitchen_requests
    WHERE status = 'Submitted'
");

$pendingChefApproval = (int) $stmt->fetchColumn();


// ---------------------------------------------------------
// REQUESTS WAITING FOR STORE
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COUNT(*)
    FROM kitchen_requests
    WHERE status IN
    (
        'Chef Approved',
        'Sent to Store',
        'Partially Issued'
    )
");

$pendingStoreIssue = (int) $stmt->fetchColumn();


// ---------------------------------------------------------
// COMPLETED KITCHEN REQUESTS TODAY
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COUNT(*)
    FROM kitchen_requests
    WHERE status = 'Completed'
      AND request_date = CURDATE()
");

$completedRequests = (int) $stmt->fetchColumn();


// ---------------------------------------------------------
// TODAY FOOD PREPARATIONS
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COUNT(*)
    FROM food_preparations
    WHERE preparation_date = CURDATE()
");

$todayFoodPreparations = (int) $stmt->fetchColumn();


// ---------------------------------------------------------
// TODAY FOOD QUANTITY
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COALESCE(SUM(prepared_qty), 0)
    FROM food_preparations
    WHERE preparation_date = CURDATE()
");

$todayPreparedQty = (float) $stmt->fetchColumn();


// ---------------------------------------------------------
// FOOD SENT TO CANTEEN TODAY
// ---------------------------------------------------------

$stmt = $con->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM food_transfers
    WHERE transfer_date = CURDATE()
");

$todayFoodSent = (float) $stmt->fetchColumn();


// =========================================================
// RECENT KITCHEN REQUESTS
// =========================================================

$stmt = $con->query("
    SELECT
        kr.id,
        kr.request_no,
        kr.request_date,
        kr.status,
        kr.cook_remarks,
        kr.chef_remarks,

        u.employee_name

    FROM kitchen_requests kr

    LEFT JOIN users u
        ON u.id = kr.requested_by

    ORDER BY kr.id DESC

    LIMIT 10
");

$recentRequests = $stmt->fetchAll();


// =========================================================
// RECENT FOOD PREPARATION
// =========================================================

$stmt = $con->query("
    SELECT

        fp.id,
        fp.preparation_no,
        fp.preparation_date,
        fp.prepared_qty,
        fp.status,

        fi.food_name,
        fi.unit

    FROM food_preparations fp

    INNER JOIN food_items fi
        ON fi.id = fp.food_id

    ORDER BY fp.id DESC

    LIMIT 10
");

$recentFood = $stmt->fetchAll();


// =========================================================
// LOW STOCK MATERIALS
// =========================================================

$stmt = $con->query("
    SELECT

        material_code,
        material_name,
        unit,
        current_stock,
        minimum_stock

    FROM materials

    WHERE status = 'Enable'

      AND current_stock <= minimum_stock

    ORDER BY current_stock ASC

    LIMIT 10
");

$lowStockMaterials = $stmt->fetchAll();


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';

require_once __DIR__ . '/../includes/topbar.php';

?>

<div class="main-content">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Kitchen Dashboard
            </h4>

            <p class="text-muted mb-0">
                Manage Cook Requests, Chef Approval,
                Food Preparation and Canteen Transfer.
            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="material_request.php"
                class="btn btn-primary"
            >

                <i class="fa-solid fa-cart-plus me-1"></i>

                Material Request

            </a>


            <a
                href="food_preparation.php"
                class="btn btn-success"
            >

                <i class="fa-solid fa-utensils me-1"></i>

                Food Preparation

            </a>

        </div>

    </div>



    <!-- =====================================================
         MAIN STATISTICS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <!-- MATERIALS -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div>

                            <div class="text-muted small">
                                Active Materials
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $totalMaterials ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-primary">

                            <i class="fa-solid fa-boxes-stacked"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- CHEF APPROVAL -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div>

                            <div class="text-muted small">
                                Waiting for Chef
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $pendingChefApproval ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-warning">

                            <i class="fa-solid fa-user-check"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- STORE ISSUE -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div>

                            <div class="text-muted small">
                                Waiting for Store
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $pendingStoreIssue ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-danger">

                            <i class="fa-solid fa-box-open"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- TODAY ISSUE -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div>

                            <div class="text-muted small">
                                Materials Issued Today
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= number_format(
                                    $todayIssued,
                                    2
                                ) ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-success">

                            <i class="fa-solid fa-arrow-right"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         FOOD STATISTICS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <!-- COMPLETED REQUESTS -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Requests Completed Today
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $completedRequests ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-success">

                            <i class="fa-solid fa-circle-check"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- FOOD PREPARATIONS -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Food Preparations Today
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $todayFoodPreparations ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-danger">

                            <i class="fa-solid fa-fire-burner"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- PREPARED QUANTITY -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Food Prepared Today
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= number_format(
                                    $todayPreparedQty,
                                    2
                                ) ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-primary">

                            <i class="fa-solid fa-bowl-food"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- SENT TO CANTEEN -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Food Sent to Canteen
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= number_format(
                                    $todayFoodSent,
                                    2
                                ) ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-info">

                            <i class="fa-solid fa-truck"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         WORKFLOW QUICK LINKS
    ====================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <h5 class="mb-3">
                Kitchen Workflow
            </h5>


            <div class="row g-3">


                <!-- COOK -->

                <div class="col-lg-3 col-md-6">

                    <a
                        href="material_request.php"
                        class="text-decoration-none"
                    >

                        <div
                            class="border rounded p-3 h-100"
                        >

                            <div class="fs-2 text-primary mb-2">

                                <i class="fa-solid fa-user-chef"></i>

                            </div>

                            <h6>
                                1. Cook Request
                            </h6>

                            <p class="text-muted small mb-0">

                                Cook selects cooking materials
                                and sends the request to Chef.

                            </p>

                        </div>

                    </a>

                </div>



                <!-- CHEF -->

                <div class="col-lg-3 col-md-6">

                    <a
                        href="chef_approval.php"
                        class="text-decoration-none"
                    >

                        <div
                            class="border rounded p-3 h-100"
                        >

                            <div class="fs-2 text-warning mb-2">

                                <i class="fa-solid fa-user-check"></i>

                            </div>

                            <h6>
                                2. Chef Approval
                            </h6>

                            <p class="text-muted small mb-0">

                                Chef reviews the request,
                                adds or changes materials,
                                then approves it.

                            </p>

                        </div>

                    </a>

                </div>



                <!-- STORE -->

                <div class="col-lg-3 col-md-6">

                    <a
                        href="../store/kitchen_requests.php"
                        class="text-decoration-none"
                    >

                        <div
                            class="border rounded p-3 h-100"
                        >

                            <div class="fs-2 text-success mb-2">

                                <i class="fa-solid fa-box-open"></i>

                            </div>

                            <h6>
                                3. Store Issue
                            </h6>

                            <p class="text-muted small mb-0">

                                Store issues the approved
                                cooking materials to Kitchen.

                            </p>

                        </div>

                    </a>

                </div>



                <!-- FOOD -->

                <div class="col-lg-3 col-md-6">

                    <a
                        href="food_preparation.php"
                        class="text-decoration-none"
                    >

                        <div
                            class="border rounded p-3 h-100"
                        >

                            <div class="fs-2 text-danger mb-2">

                                <i class="fa-solid fa-fire-burner"></i>

                            </div>

                            <h6>
                                4. Food Preparation
                            </h6>

                            <p class="text-muted small mb-0">

                                Record prepared food and
                                send food to Canteen.

                            </p>

                        </div>

                    </a>

                </div>


            </div>

        </div>

    </div>



    <!-- =====================================================
         RECENT REQUESTS + LOW STOCK
    ====================================================== -->

    <div class="row g-4">


        <!-- RECENT REQUESTS -->

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-header bg-white py-3">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <div>

                            <h5 class="mb-0">
                                Recent Kitchen Requests
                            </h5>

                            <small class="text-muted">
                                Cook and Chef request history
                            </small>

                        </div>


                        <a
                            href="material_request.php"
                            class="btn btn-sm btn-outline-primary"
                        >
                            View Requests
                        </a>

                    </div>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Request No
                                    </th>

                                    <th>
                                        Cook
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php if (
                                empty($recentRequests)
                            ): ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-center text-muted py-4"
                                    >

                                        No kitchen requests found.

                                    </td>

                                </tr>

                            <?php else: ?>


                                <?php foreach (
                                    $recentRequests
                                    as $request
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= e(
                                                    $request['request_no']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= e(
                                                $request['employee_name']
                                                ?? '-'
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                $request['request_date']
                                            ) ?>

                                        </td>


                                        <td>


                                        <?php

                                        $status =
                                            $request['status'];

                                        $badgeClass =
                                            'bg-secondary';


                                        if (
                                            $status ===
                                            'Submitted'
                                        ) {

                                            $badgeClass =
                                                'bg-warning text-dark';

                                        } elseif (
                                            $status ===
                                            'Chef Approved'
                                        ) {

                                            $badgeClass =
                                                'bg-primary';

                                        } elseif (
                                            $status ===
                                            'Sent to Store'
                                        ) {

                                            $badgeClass =
                                                'bg-info text-dark';

                                        } elseif (
                                            $status ===
                                            'Partially Issued'
                                        ) {

                                            $badgeClass =
                                                'bg-warning text-dark';

                                        } elseif (
                                            $status ===
                                            'Completed'
                                        ) {

                                            $badgeClass =
                                                'bg-success';

                                        } elseif (
                                            $status ===
                                            'Rejected'
                                        ) {

                                            $badgeClass =
                                                'bg-danger';

                                        }

                                        ?>


                                            <span
                                                class="badge <?= $badgeClass ?>"
                                            >

                                                <?= e(
                                                    $status
                                                ) ?>

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

        </div>



        <!-- LOW STOCK -->

        <div class="col-lg-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">
                        Low Stock
                    </h5>

                    <small class="text-muted">
                        Materials below minimum level
                    </small>

                </div>


                <div class="card-body p-0">

                    <div class="list-group list-group-flush">


                    <?php if (
                        empty($lowStockMaterials)
                    ): ?>


                        <div
                            class="text-center text-muted py-4"
                        >

                            <i
                                class="fa-solid fa-circle-check fs-3 text-success mb-2"
                            ></i>

                            <br>

                            No low stock materials.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $lowStockMaterials
                            as $material
                        ): ?>

                            <div
                                class="list-group-item"
                            >

                                <div
                                    class="d-flex justify-content-between"
                                >

                                    <div>

                                        <strong>

                                            <?= e(
                                                $material['material_name']
                                            ) ?>

                                        </strong>

                                        <br>

                                        <small
                                            class="text-muted"
                                        >

                                            <?= e(
                                                $material['material_code']
                                            ) ?>

                                        </small>

                                    </div>


                                    <div
                                        class="text-end"
                                    >

                                        <span
                                            class="badge bg-danger"
                                        >

                                            <?= number_format(
                                                (float)
                                                $material['current_stock'],
                                                2
                                            ) ?>

                                            <?= e(
                                                $material['unit']
                                            ) ?>

                                        </span>

                                        <br>

                                        <small
                                            class="text-muted"
                                        >

                                            Min:
                                            <?= number_format(
                                                (float)
                                                $material['minimum_stock'],
                                                2
                                            ) ?>

                                        </small>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         RECENT FOOD PREPARATION
    ====================================================== -->

    <div class="card border-0 shadow-sm mt-4">

        <div class="card-header bg-white py-3">

            <div
                class="d-flex justify-content-between align-items-center"
            >

                <div>

                    <h5 class="mb-0">
                        Recent Food Preparation
                    </h5>

                    <small class="text-muted">
                        Food prepared by Kitchen
                    </small>

                </div>


                <a
                    href="food_preparation.php"
                    class="btn btn-sm btn-outline-success"
                >

                    Food Preparation

                </a>

            </div>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>
                                Preparation No
                            </th>

                            <th>
                                Food
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty($recentFood)
                    ): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="text-center text-muted py-4"
                            >

                                No food preparation records found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $recentFood
                            as $food
                        ): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= e(
                                            $food['preparation_no']
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= e(
                                        $food['food_name']
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="badge bg-success"
                                    >

                                        <?= number_format(
                                            (float)
                                            $food['prepared_qty'],
                                            2
                                        ) ?>

                                        <?= e(
                                            $food['unit']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= e(
                                        $food['preparation_date']
                                    ) ?>

                                </td>


                                <td>

                                    <?php

                                    $foodStatus =
                                        $food['status'];

                                    $foodBadge =
                                        'bg-secondary';


                                    if (
                                        $foodStatus ===
                                        'Prepared'
                                    ) {

                                        $foodBadge =
                                            'bg-warning text-dark';

                                    } elseif (
                                        $foodStatus ===
                                        'Sent to Canteen'
                                    ) {

                                        $foodBadge =
                                            'bg-info text-dark';

                                    } elseif (
                                        $foodStatus ===
                                        'Completed'
                                    ) {

                                        $foodBadge =
                                            'bg-success';
                                    }

                                    ?>


                                    <span
                                        class="badge <?= $foodBadge ?>"
                                    >

                                        <?= e(
                                            $foodStatus
                                        ) ?>

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


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>      