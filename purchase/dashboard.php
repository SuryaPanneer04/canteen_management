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

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';

?>

<main class="main-content">

    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>


    <div class="page-body">

        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div class="mb-4">

            <h4 class="mb-1">
                Purchase Dashboard
            </h4>

            <p class="text-muted mb-0">
                Manage purchase requests and procurement activities.
            </p>

        </div>


        <!-- =====================================================
             SUMMARY CARDS
        ====================================================== -->

        <div class="row g-3 mb-4">


            <!-- TOTAL -->

            <div class="col-xl-3 col-md-6">

                <div class="dashboard-card">

                    <div class="dashboard-card-icon">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>

                    <div>

                        <div class="dashboard-card-label">
                            Total Requests
                        </div>

                        <div class="dashboard-card-value">
                            <?= $totalRequests ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- PENDING -->

            <div class="col-xl-3 col-md-6">

                <div class="dashboard-card">

                    <div class="dashboard-card-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>

                        <div class="dashboard-card-label">
                            Pending
                        </div>

                        <div class="dashboard-card-value">
                            <?= $pendingRequests ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- APPROVED -->

            <div class="col-xl-3 col-md-6">

                <div class="dashboard-card">

                    <div class="dashboard-card-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>

                        <div class="dashboard-card-label">
                            Approved
                        </div>

                        <div class="dashboard-card-value">
                            <?= $approvedRequests ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- REJECTED -->

            <div class="col-xl-3 col-md-6">

                <div class="dashboard-card">

                    <div class="dashboard-card-icon">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>

                    <div>

                        <div class="dashboard-card-label">
                            Rejected
                        </div>

                        <div class="dashboard-card-value">
                            <?= $rejectedRequests ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             QUICK ACTIONS
        ====================================================== -->

        <div class="content-card mb-4">

            <div class="content-card-header">

                <div>

                    <h5 class="mb-1">
                        Quick Actions
                    </h5>

                    <small class="text-muted">
                        Frequently used purchase functions.
                    </small>

                </div>

            </div>


            <div class="content-card-body">

                <div class="row g-3">


                    <div class="col-md-4">

                        <a
                            href="requests.php"
                            class="btn btn-primary w-100 py-3"
                        >

                            <i class="fa-solid fa-file-invoice me-2"></i>

                            View Purchase Requests

                        </a>

                    </div>


                    <div class="col-md-4">

                        <a
                            href="purchase_orders.php"
                            class="btn btn-outline-primary w-100 py-3"
                        >

                            <i class="fa-solid fa-cart-shopping me-2"></i>

                            Purchase Orders

                        </a>

                    </div>


                    <div class="col-md-4">

                        <a
                            href="suppliers.php"
                            class="btn btn-outline-secondary w-100 py-3"
                        >

                            <i class="fa-solid fa-truck-field me-2"></i>

                            Suppliers

                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             RECENT REQUESTS
        ====================================================== -->

        <div class="content-card">

            <div class="content-card-header">

                <div>

                    <h5 class="mb-1">
                        Recent Purchase Requests
                    </h5>

                    <small class="text-muted">
                        Latest requests submitted by departments.
                    </small>

                </div>


                <a
                    href="requests.php"
                    class="btn btn-sm btn-outline-primary"
                >

                    View All

                    <i class="fa-solid fa-arrow-right ms-1"></i>

                </a>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Request No.</th>

                            <th>Date</th>

                            <th>Requested By</th>

                            <th>Items</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentRequests)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >

                                <i class="fa-solid fa-inbox fa-2x mb-2"></i>

                                <div>
                                    No purchase requests found.
                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentRequests as $index => $request): ?>

                            <?php

                            $status = $request['status'];

                            switch ($status) {

                                case 'Pending':
                                    $badge = 'bg-warning text-dark';
                                    break;

                                case 'Approved':
                                    $badge = 'bg-success';
                                    break;

                                case 'Rejected':
                                    $badge = 'bg-danger';
                                    break;

                                case 'Completed':
                                    $badge = 'bg-primary';
                                    break;

                                default:
                                    $badge = 'bg-secondary';
                                    break;
                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($request['request_no']) ?>
                                    </strong>
                                </td>

                                <td>

                                    <?= e(
                                        date(
                                            'd-m-Y',
                                            strtotime($request['request_date'])
                                        )
                                    ) ?>

                                </td>

                                <td>
                                    <?= e($request['employee_name']) ?>
                                </td>

                                <td>
                                    <?= (int)$request['item_count'] ?>
                                </td>

                                <td>

                                    <span class="badge <?= $badge ?>">
                                        <?= e($status) ?>
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

</main>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>