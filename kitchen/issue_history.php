<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$allowedRoles = ['Kitchen', 'Super Admin'];

if (!in_array($_SESSION['role_name'] ?? '', $allowedRoles, true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Kitchen Issue History';


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$statusFilter = trim($_GET['status'] ?? 'All');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedStatuses = [
    'All',
    'Sent to Store',
    'Partially Issued',
    'Completed',
    'Rejected'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

/*
|--------------------------------------------------------------------------
| BUILD REQUEST QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

/*
|--------------------------------------------------------------------------
| ONLY NEW KITCHEN FLOW REQUESTS
|--------------------------------------------------------------------------
|
| plan_id is populated by Chef Approval.
|
*/

$where[] = "kr.plan_id IS NOT NULL";

/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($statusFilter !== 'All') {

    $where[] = "kr.status = :status";

    $params[':status'] = $statusFilter;
}

/*
|--------------------------------------------------------------------------
| DATE FROM
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $where[] = "kr.request_date >= :date_from";

    $params[':date_from'] = $dateFrom;
}

/*
|--------------------------------------------------------------------------
| DATE TO
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $where[] = "kr.request_date <= :date_to";

    $params[':date_to'] = $dateTo;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            kr.request_no LIKE :search
            OR dcp.plan_no LIKE :search
            OR kr.meal_type LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| FETCH REQUESTS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            kr.id,
            kr.request_no,
            kr.request_date,
            kr.status,
            kr.meal_type,
            kr.plan_id,
            kr.created_at,
            kr.updated_at,

            dcp.plan_no,
            dcp.plan_date,
            dcp.meal_type AS plan_meal_type,

            u.employee_name,
            u.employee_code,

            COUNT(kri.id) AS item_count,

            COALESCE(
                SUM(kri.requested_qty),
                0
            ) AS total_requested,

            COALESCE(
                SUM(kri.approved_qty),
                0
            ) AS total_approved,

            COALESCE(
                SUM(kri.issued_qty),
                0
            ) AS total_issued

        FROM kitchen_requests kr

        LEFT JOIN daily_cooking_plans dcp
            ON dcp.id = kr.plan_id

        LEFT JOIN users u
            ON u.id = kr.requested_by

        LEFT JOIN kitchen_request_items kri
            ON kri.request_id = kr.id

        WHERE {$whereSql}

        GROUP BY
            kr.id,
            kr.request_no,
            kr.request_date,
            kr.status,
            kr.meal_type,
            kr.plan_id,
            kr.created_at,
            kr.updated_at,
            dcp.plan_no,
            dcp.plan_date,
            dcp.meal_type,
            u.employee_name,
            u.employee_code

        ORDER BY kr.id DESC
    ";

    $stmt = $con->prepare($sql);
    $stmt->execute($params);

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $requests = [];

    $error = 'Unable to load material issue history.';
}

/*
|--------------------------------------------------------------------------
| FETCH REQUEST ITEMS
|--------------------------------------------------------------------------
*/

$requestItems = [];

if (!empty($requests)) {

    $requestIds = array_map(
        static fn(array $row): int => (int)$row['id'],
        $requests
    );

    /*
    |--------------------------------------------------------------------------
    | SAFE PLACEHOLDERS
    |--------------------------------------------------------------------------
    */

    $placeholders = implode(
        ',',
        array_fill(0, count($requestIds), '?')
    );

    try {

        $stmt = $con->prepare("
            SELECT
                kri.id,
                kri.request_id,
                kri.material_id,
                kri.requested_qty,
                kri.approved_qty,
                kri.issued_qty,
                kri.remarks,

                m.material_code,
                m.material_name,
                m.category,
                m.unit

            FROM kitchen_request_items kri

            INNER JOIN materials m
                ON m.id = kri.material_id

            WHERE kri.request_id IN ({$placeholders})

            ORDER BY
                m.material_name ASC
        ");

        $stmt->execute($requestIds);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {

            $requestId = (int)$item['request_id'];

            if (!isset($requestItems[$requestId])) {
                $requestItems[$requestId] = [];
            }

            $requestItems[$requestId][] = $item;
        }

    } catch (Throwable $e) {

        $requestItems = [];
    }
}

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$totalRequests = count($requests);

$totalRequested = 0;
$totalApproved = 0;
$totalIssued = 0;

$completedRequests = 0;
$partialRequests = 0;
$pendingRequests = 0;

foreach ($requests as $request) {

    $totalRequested += (float)$request['total_requested'];
    $totalApproved += (float)$request['total_approved'];
    $totalIssued += (float)$request['total_issued'];

    if ($request['status'] === 'Completed') {

        $completedRequests++;

    } elseif ($request['status'] === 'Partially Issued') {

        $partialRequests++;

    } elseif (
        in_array(
            $request['status'],
            ['Sent to Store'],
            true
        )
    ) {

        $pendingRequests++;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';

?>

<div class="main-content">

<!--
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
-->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h4 class="mb-1">
            <i class="fa-solid fa-boxes-stacked me-2"></i>
            Material Issue History
        </h4>

        <p class="text-muted mb-0">
            View materials issued by Store for Kitchen cooking plans.
        </p>

    </div>

    <a
        href="dashboard.php"
        class="btn btn-outline-secondary"
    >
        <i class="fa-solid fa-arrow-left me-1"></i>
        Dashboard
    </a>

</div>


<!--
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
-->

<?php if ($error): ?>

    <div class="alert alert-danger alert-dismissible fade show">

        <i class="fa-solid fa-circle-exclamation me-2"></i>

        <?= e($error) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
-->

<div class="row g-3 mb-4">

    <div class="col-md-3">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Requests
                        </small>

                        <h3 class="mb-0">
                            <?= $totalRequests ?>
                        </h3>

                    </div>

                    <div class="text-primary fs-2">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Completed
                        </small>

                        <h3 class="mb-0 text-success">
                            <?= $completedRequests ?>
                        </h3>

                    </div>

                    <div class="text-success fs-2">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Partially Issued
                        </small>

                        <h3 class="mb-0 text-warning">
                            <?= $partialRequests ?>
                        </h3>

                    </div>

                    <div class="text-warning fs-2">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Waiting Store
                        </small>

                        <h3 class="mb-0 text-danger">
                            <?= $pendingRequests ?>
                        </h3>

                    </div>

                    <div class="text-danger fs-2">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">
            <i class="fa-solid fa-filter me-2"></i>
            Filter Material Issues
        </h5>

    </div>

    <div class="card-body">

        <form method="GET">

            <div class="row g-3">

                <div class="col-md-3">

                    <label class="form-label">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Request No / Plan No / Meal"
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="col-md-2">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

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


                <div class="col-md-2">

                    <label class="form-label">
                        From Date
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="<?= e($dateFrom) ?>"
                    >

                </div>


                <div class="col-md-2">

                    <label class="form-label">
                        To Date
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="<?= e($dateTo) ?>"
                    >

                </div>


                <div class="col-md-3 d-flex align-items-end gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Search
                    </button>

                    <a
                        href="issue_history.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i>
                        Reset
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| REQUEST HISTORY
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-header">

        <h5 class="mb-0">
            <i class="fa-solid fa-clock-rotate-left me-2"></i>
            Material Requests & Issues
        </h5>

    </div>

    <div class="card-body">

        <?php if (empty($requests)): ?>

            <div class="text-center py-5">

                <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>

                <h5>
                    No material issue records found
                </h5>

                <p class="text-muted mb-0">
                    Material requests created from Chef Approval will
                    appear here after they are sent to Store.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Request No</th>

                            <th>Cooking Plan</th>

                            <th>Date</th>

                            <th>Meal</th>

                            <th>Materials</th>

                            <th>Requested</th>

                            <th>Approved</th>

                            <th>Issued</th>

                            <th>Status</th>

                            <th width="100">
                                Details
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($requests as $index => $request): ?>

                        <?php

                        $status = $request['status'];

                        $statusClass = match ($status) {

                            'Completed'
                                => 'bg-success',

                            'Partially Issued'
                                => 'bg-warning text-dark',

                            'Sent to Store'
                                => 'bg-primary',

                            'Rejected'
                                => 'bg-danger',

                            default
                                => 'bg-secondary'
                        };

                        $collapseId = 'issueDetails' . (int)$request['id'];

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

                                <?php if (!empty($request['plan_no'])): ?>

                                    <strong>
                                        <?= e($request['plan_no']) ?>
                                    </strong>

                                <?php else: ?>

                                    <span class="text-muted">
                                        -
                                    </span>

                                <?php endif; ?>

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

                                <span class="badge bg-info text-dark">

                                    <?= e(
                                        $request['meal_type']
                                        ?: ($request['plan_meal_type'] ?? '-')
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="badge bg-secondary">

                                    <?= (int)$request['item_count'] ?>

                                    <?= (int)$request['item_count'] === 1
                                        ? 'item'
                                        : 'items' ?>

                                </span>

                            </td>


                            <td>

                                <?= number_format(
                                    (float)$request['total_requested'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    (float)$request['total_approved'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= number_format(
                                        (float)$request['total_issued'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <span class="badge <?= $statusClass ?>">

                                    <?= e($status) ?>

                                </span>

                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?= $collapseId ?>"
                                    aria-expanded="false"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </td>

                        </tr>


                        <!--
                        |--------------------------------------------------------------------------
                        | MATERIAL DETAILS
                        |--------------------------------------------------------------------------
                        -->

                        <tr>

                            <td
                                colspan="11"
                                class="p-0 border-top-0"
                            >

                                <div
                                    class="collapse"
                                    id="<?= $collapseId ?>"
                                >

                                    <div class="p-3 bg-light">

                                        <div class="row mb-3">

                                            <div class="col-md-3">

                                                <small class="text-muted">
                                                    Request No
                                                </small>

                                                <div>
                                                    <strong>
                                                        <?= e(
                                                            $request['request_no']
                                                        ) ?>
                                                    </strong>
                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <small class="text-muted">
                                                    Cooking Plan
                                                </small>

                                                <div>
                                                    <?= e(
                                                        $request['plan_no']
                                                        ?? '-'
                                                    ) ?>
                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <small class="text-muted">
                                                    Requested By
                                                </small>

                                                <div>
                                                    <?= e(
                                                        $request['employee_name']
                                                        ?? '-'
                                                    ) ?>
                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <small class="text-muted">
                                                    Plan Date
                                                </small>

                                                <div>

                                                    <?php if (!empty($request['plan_date'])): ?>

                                                        <?= e(
                                                            date(
                                                                'd-m-Y',
                                                                strtotime(
                                                                    $request['plan_date']
                                                                )
                                                            )
                                                        ) ?>

                                                    <?php else: ?>

                                                        -

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </div>


                                        <?php
                                        $itemsForRequest =
                                            $requestItems[(int)$request['id']]
                                            ?? [];
                                        ?>


                                        <?php if (empty($itemsForRequest)): ?>

                                            <div class="alert alert-secondary mb-0">

                                                No material items found.

                                            </div>

                                        <?php else: ?>

                                            <div class="table-responsive">

                                                <table class="table table-sm table-bordered bg-white mb-0">

                                                    <thead class="table-light">

                                                        <tr>

                                                            <th>
                                                                Material
                                                            </th>

                                                            <th>
                                                                Category
                                                            </th>

                                                            <th>
                                                                Unit
                                                            </th>

                                                            <th>
                                                                Requested
                                                            </th>

                                                            <th>
                                                                Approved
                                                            </th>

                                                            <th>
                                                                Issued
                                                            </th>

                                                            <th>
                                                                Balance
                                                            </th>

                                                            <th>
                                                                Remarks
                                                            </th>

                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                    <?php foreach (
                                                        $itemsForRequest
                                                        as $item
                                                    ): ?>

                                                        <?php

                                                        $requested =
                                                            (float)$item[
                                                                'requested_qty'
                                                            ];

                                                        $approved =
                                                            (float)$item[
                                                                'approved_qty'
                                                            ];

                                                        $issued =
                                                            (float)$item[
                                                                'issued_qty'
                                                            ];

                                                        $balance =
                                                            max(
                                                                0,
                                                                $approved - $issued
                                                            );

                                                        ?>

                                                        <tr>

                                                            <td>

                                                                <strong>
                                                                    <?= e(
                                                                        $item[
                                                                            'material_name'
                                                                        ]
                                                                    ) ?>
                                                                </strong>

                                                                <br>

                                                                <small class="text-muted">
                                                                    <?= e(
                                                                        $item[
                                                                            'material_code'
                                                                        ]
                                                                        ?? ''
                                                                    ) ?>
                                                                </small>

                                                            </td>


                                                            <td>

                                                                <?= e(
                                                                    $item[
                                                                        'category'
                                                                    ]
                                                                    ?? '-'
                                                                ) ?>

                                                            </td>


                                                            <td>

                                                                <?= e(
                                                                    $item[
                                                                        'unit'
                                                                    ]
                                                                ) ?>

                                                            </td>


                                                            <td>

                                                                <?= number_format(
                                                                    $requested,
                                                                    2
                                                                ) ?>

                                                            </td>


                                                            <td>

                                                                <?= number_format(
                                                                    $approved,
                                                                    2
                                                                ) ?>

                                                            </td>


                                                            <td>

                                                                <?php if ($issued > 0): ?>

                                                                    <span class="text-success fw-bold">

                                                                        <?= number_format(
                                                                            $issued,
                                                                            2
                                                                        ) ?>

                                                                    </span>

                                                                <?php else: ?>

                                                                    <span class="text-muted">
                                                                        0.00
                                                                    </span>

                                                                <?php endif; ?>

                                                            </td>


                                                            <td>

                                                                <?php if ($balance > 0): ?>

                                                                    <span class="text-danger">

                                                                        <?= number_format(
                                                                            $balance,
                                                                            2
                                                                        ) ?>

                                                                    </span>

                                                                <?php else: ?>

                                                                    <span class="text-success">

                                                                        0.00

                                                                    </span>

                                                                <?php endif; ?>

                                                            </td>


                                                            <td>

                                                                <?= e(
                                                                    $item[
                                                                        'remarks'
                                                                    ]
                                                                    ?? '-'
                                                                ) ?>

                                                            </td>

                                                        </tr>

                                                    <?php endforeach; ?>

                                                    </tbody>

                                                </table>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

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
