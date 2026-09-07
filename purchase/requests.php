<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Purchase Requests';

$role = $_SESSION['role_name'] ?? '';

/*
|--------------------------------------------------------------------------
| ALLOW PURCHASE + SUPER ADMIN
|--------------------------------------------------------------------------
*/

if ($role !== 'Purchase' && $role !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}

$error = null;
$success = flash('success');


/*
|--------------------------------------------------------------------------
| APPROVE / REJECT REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $error = 'Invalid purchase request.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CHECK REQUEST
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT
                    id,
                    request_no,
                    status
                FROM purchase_requests
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$requestId]);

            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Purchase request not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | ONLY PENDING REQUESTS CAN BE PROCESSED
            |--------------------------------------------------------------------------
            */

            if ($request['status'] !== 'Pending') {
                throw new Exception(
                    'This purchase request has already been processed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS
            |--------------------------------------------------------------------------
            */

            $newStatus =
                $action === 'approve'
                    ? 'Approved'
                    : 'Rejected';


            $stmt = $con->prepare("
                UPDATE purchase_requests
                SET status = ?
                WHERE id = ?
                  AND status = 'Pending'
            ");

            $stmt->execute([
                $newStatus,
                $requestId
            ]);


            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'Unable to update the purchase request.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            if ($newStatus === 'Approved') {

                flash(
                    'success',
                    "Purchase request {$request['request_no']} approved successfully."
                );

            } else {

                flash(
                    'success',
                    "Purchase request {$request['request_no']} rejected successfully."
                );

            }

            header('Location: requests.php');
            exit;

        } catch (Throwable $e) {

            $error = $e->getMessage();

        }
    }
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? '';

$allowedStatuses = [
    'Pending',
    'Approved',
    'Rejected',
    'Completed'
];

$where = '';
$params = [];

if (
    $statusFilter !== '' &&
    in_array($statusFilter, $allowedStatuses, true)
) {

    $where = "WHERE pr.status = ?";
    $params[] = $statusFilter;

}


/*
|--------------------------------------------------------------------------
| FETCH REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,

        u.employee_name,

        COUNT(pri.id) AS item_count,

        COALESCE(
            SUM(pri.requested_qty),
            0
        ) AS total_qty

    FROM purchase_requests pr

    INNER JOIN users u
        ON u.id = pr.requested_by

    LEFT JOIN purchase_request_items pri
        ON pri.request_id = pr.id

    {$where}

    GROUP BY
        pr.id,
        pr.request_no,
        pr.request_date,
        pr.status,
        pr.remarks,
        u.employee_name

    ORDER BY pr.id DESC
";

$stmt = $con->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll();

?>


<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


<main class="main-content">

    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>


    <div class="page-body">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h4 class="mb-1">
                    Purchase Requests
                </h4>

                <p class="text-muted mb-0">
                    Review and process purchase requests submitted by Store.
                </p>

            </div>

        </div>


        <!-- =====================================================
             ALERTS
        ====================================================== -->

        <?php if ($success): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="fa-solid fa-circle-check me-2"></i>

                <?= e($success) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="fa-solid fa-triangle-exclamation me-2"></i>

                <?= e($error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="content-card mb-4">

            <div class="content-card-body">

                <form
                    method="GET"
                    class="row g-3 align-items-end"
                >

                    <div class="col-md-4">

                        <label class="form-label">
                            Request Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All Requests
                            </option>

                            <?php foreach ($allowedStatuses as $status): ?>

                                <option
                                    value="<?= e($status) ?>"
                                    <?= $statusFilter === $status ? 'selected' : '' ?>
                                >
                                    <?= e($status) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-md-auto">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-filter me-1"></i>

                            Filter

                        </button>

                    </div>


                    <div class="col-md-auto">

                        <a
                            href="requests.php"
                            class="btn btn-outline-secondary"
                        >

                            <i class="fa-solid fa-rotate-left me-1"></i>

                            Reset

                        </a>

                    </div>

                </form>

            </div>

        </div>


        <!-- =====================================================
             REQUEST TABLE
        ====================================================== -->

        <div class="content-card">

            <div class="content-card-header">

                <div>

                    <h5 class="mb-1">
                        Request List
                    </h5>

                    <small class="text-muted">
                        <?= count($requests) ?> request(s) found
                    </small>

                </div>

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

                            <th>Total Qty</th>

                            <th>Status</th>

                            <th>Remarks</th>

                            <th class="text-end">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($requests)): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="text-center text-muted py-5"
                            >

                                <i
                                    class="fa-solid fa-inbox fa-2x mb-2"
                                ></i>

                                <div>
                                    No purchase requests found.
                                </div>

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($requests as $index => $request): ?>

                            <?php

                            $status = $request['status'];

                            switch ($status) {

                                case 'Pending':
                                    $badge = 'badge-disabled';
                                    break;

                                case 'Approved':
                                    $badge = 'badge-enable';
                                    break;

                                case 'Rejected':
                                    $badge = 'badge-disabled';
                                    break;

                                case 'Completed':
                                    $badge = 'badge-enable';
                                    break;

                                default:
                                    $badge = 'bg-secondary';
                                    break;

                            }

                            $initial = strtoupper(
                                substr(trim((string)$request['employee_name']), 0, 1)
                            );

                            ?>


                            <tr>

                                <!-- NUMBER -->

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <!-- REQUEST NUMBER -->

                                <td>

                                    <strong>
                                        <?= e($request['request_no']) ?>
                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= e(
                                        date(
                                            'd-m-Y',
                                            strtotime($request['request_date'])
                                        )
                                    ) ?>

                                </td>


                                <!-- USER -->

                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <span class="row-avatar">
                                            <?= e($initial) ?>
                                        </span>

                                        <span>
                                            <?= e($request['employee_name']) ?>
                                        </span>

                                    </div>

                                </td>


                                <!-- ITEMS -->

                                <td>

                                    <span class="badge bg-light text-dark">

                                        <?= (int)$request['item_count'] ?>

                                        item(s)

                                    </span>

                                </td>


                                <!-- QUANTITY -->

                                <td>

                                    <?= number_format(
                                        (float)$request['total_qty'],
                                        2
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span class="badge <?= $badge ?>">

                                        <?= e($status) ?>

                                    </span>

                                </td>


                                <!-- REMARKS -->

                                <td>

                                    <?php if (!empty($request['remarks'])): ?>

                                        <span
                                            title="<?= e($request['remarks']) ?>"
                                        >

                                            <?= e(
                                                mb_strimwidth(
                                                    $request['remarks'],
                                                    0,
                                                    30,
                                                    '...'
                                                )
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->

                                <td class="text-end">


                                    <?php if ($status === 'Pending'): ?>


                                        <!-- APPROVE -->

                                        <form
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Approve this purchase request?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int)$request['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-success"
                                                title="Approve"
                                            >

                                                <i class="fa-solid fa-check"></i>

                                            </button>

                                        </form>


                                        <!-- REJECT -->

                                        <form
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Reject this purchase request?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int)$request['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="Reject"
                                            >

                                                <i class="fa-solid fa-xmark"></i>

                                            </button>

                                        </form>


                                    <?php else: ?>

                                        <span class="text-muted">
                                            No action
                                        </span>

                                    <?php endif; ?>


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


<?php require_once __DIR__ . '/../includes/footer.php'; ?>