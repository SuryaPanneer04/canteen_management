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

$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$where = [
    "st.transaction_type = 'ISSUE_KITCHEN'"
];

$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            m.material_code LIKE :search
            OR m.material_name LIKE :search
            OR st.reference_no LIKE :search
            OR st.remarks LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| DATE FROM
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $where[] = "DATE(st.created_at) >= :date_from";

    $params[':date_from'] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| DATE TO
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $where[] = "DATE(st.created_at) <= :date_to";

    $params[':date_to'] = $dateTo;
}


$whereSql = implode(' AND ', $where);


/*
|--------------------------------------------------------------------------
| FETCH ISSUE HISTORY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        st.id,
        st.quantity,
        st.reference_no,
        st.reference_id,
        st.remarks,
        st.created_at,

        m.material_code,
        m.material_name,
        m.category,
        m.unit,

        u.employee_name

    FROM stock_transactions st

    INNER JOIN materials m
        ON m.id = st.material_id

    LEFT JOIN users u
        ON u.id = st.created_by

    WHERE {$whereSql}

    ORDER BY st.id DESC
";

$stmt = $con->prepare($sql);
$stmt->execute($params);

$issues = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TOTAL ISSUED QUANTITY
|--------------------------------------------------------------------------
*/

$totalQuantitySql = "
    SELECT COALESCE(SUM(st.quantity), 0)

    FROM stock_transactions st

    INNER JOIN materials m
        ON m.id = st.material_id

    WHERE {$whereSql}
";

$stmt = $con->prepare($totalQuantitySql);
$stmt->execute($params);

$totalQuantity = (float)$stmt->fetchColumn();


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>


<div class="main-content">

<div class="page-body">

    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Kitchen Issue History
            </h4>

            <p class="text-muted mb-0">
                View all materials issued to the kitchen.
            </p>

        </div>

        <a href="dashboard.php"
           class="btn btn-outline-secondary">

            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard

        </a>

    </div>


    <!-- FILTER CARD -->
    <div class="content-card mb-4">

        <div class="content-card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <!-- SEARCH -->

                    <div class="col-lg-4 col-md-6">

                        <label class="form-label">
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Material, code or reference..."
                        >

                    </div>


                    <!-- DATE FROM -->

                    <div class="col-lg-2 col-md-6">

                        <label class="form-label">
                            Date From
                        </label>

                        <input
                            type="date"
                            name="date_from"
                            class="form-control"
                            value="<?= e($dateFrom) ?>"
                        >

                    </div>


                    <!-- DATE TO -->

                    <div class="col-lg-2 col-md-6">

                        <label class="form-label">
                            Date To
                        </label>

                        <input
                            type="date"
                            name="date_to"
                            class="form-control"
                            value="<?= e($dateTo) ?>"
                        >

                    </div>


                    <!-- SEARCH BUTTON -->

                    <div class="col-lg-2 col-md-6">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >

                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Search

                        </button>

                    </div>


                    <!-- RESET -->

                    <div class="col-lg-2 col-md-6">

                        <a
                            href="issue_history.php"
                            class="btn btn-outline-secondary w-100"
                        >

                            <i class="fa-solid fa-rotate-left me-1"></i>
                            Reset

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- SUMMARY -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-right-left"></i>
                </div>

                <div>

                    <div class="stat-label">
                        Total Transactions
                    </div>

                    <div class="stat-value">
                        <?= count($issues) ?>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div>

                    <div class="stat-label">
                        Total Quantity Issued
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalQuantity, 2) ?>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-list"></i>
                </div>

                <div>

                    <div class="stat-label">
                        Current Records
                    </div>

                    <div class="stat-value">
                        <?= count($issues) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ISSUE TABLE -->

    <div class="content-card">

        <div class="content-card-header">

            <div>

                <h5 class="mb-1">
                    Issued Materials
                </h5>

                <small class="text-muted">
                    Kitchen stock issue transactions
                </small>

            </div>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Date</th>

                        <th>Material</th>

                        <th>Category</th>

                        <th>Quantity</th>

                        <th>Reference</th>

                        <th>Remarks</th>

                        <th>Issued By</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$issues): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="text-center text-muted py-5"
                        >

                            <i class="fa-solid fa-box-open fs-2 mb-2"></i>

                            <div>
                                No kitchen issue records found.
                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($issues as $index => $issue): ?>

                        <?php

                        $issuerInitial = strtoupper(
                            substr(
                                trim((string)($issue['employee_name'] ?? '-')),
                                0,
                                1
                            )
                        );

                        ?>

                        <tr>

                            <!-- NUMBER -->

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <!-- DATE -->

                            <td>

                                <?= e(
                                    date(
                                        'd-m-Y',
                                        strtotime($issue['created_at'])
                                    )
                                ) ?>

                                <br>

                                <small class="text-muted">

                                    <?= e(
                                        date(
                                            'H:i',
                                            strtotime($issue['created_at'])
                                        )
                                    ) ?>

                                </small>

                            </td>


                            <!-- MATERIAL -->

                            <td>

                                <strong>
                                    <?= e(
                                        $issue['material_name']
                                    ) ?>
                                </strong>

                                <br>

                                <small class="text-muted">

                                    <?= e(
                                        $issue['material_code']
                                    ) ?>

                                </small>

                            </td>


                            <!-- CATEGORY -->

                            <td>

                                <?= e(
                                    $issue['category'] ?? '-'
                                ) ?>

                            </td>


                            <!-- QUANTITY -->

                            <td>

                                <span class="badge bg-light text-dark">

                                    <?= number_format(
                                        (float)$issue['quantity'],
                                        2
                                    ) ?>

                                    <?= e(
                                        $issue['unit']
                                    ) ?>

                                </span>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <?= e(
                                    $issue['reference_no'] ?: '-'
                                ) ?>

                            </td>


                            <!-- REMARKS -->

                            <td>

                                <?= e(
                                    $issue['remarks'] ?: '-'
                                ) ?>

                            </td>


                            <!-- USER -->

                            <td>

                                <div class="d-flex align-items-center gap-2">

                                    <span class="row-avatar">
                                        <?= e($issuerInitial) ?>
                                    </span>

                                    <span>
                                        <?= e(
                                            $issue['employee_name'] ?? '-'
                                        ) ?>
                                    </span>

                                </div>

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