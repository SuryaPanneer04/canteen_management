<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Stock Report';


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS FILTER
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'All',
    'In Stock',
    'Low Stock',
    'Out of Stock',
    'Disabled'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {

    $statusFilter = 'All';

}


/*
|--------------------------------------------------------------------------
| BUILD MATERIAL QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            material_code LIKE :search
            OR material_name LIKE :search
            OR category LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';

}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'Disabled') {

    $where[] = "
        status = 'Disabled'
    ";

} elseif ($statusFilter === 'In Stock') {

    $where[] = "
        status = 'Enable'
        AND current_stock > minimum_stock
    ";

} elseif ($statusFilter === 'Low Stock') {

    $where[] = "
        status = 'Enable'
        AND current_stock > 0
        AND current_stock <= minimum_stock
    ";

} elseif ($statusFilter === 'Out of Stock') {

    $where[] = "
        status = 'Enable'
        AND current_stock <= 0
    ";

}


/*
|--------------------------------------------------------------------------
| WHERE CLAUSE
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(' AND ', $where);

}


/*
|--------------------------------------------------------------------------
| FETCH STOCK
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        material_code,
        material_name,
        category,
        unit,
        minimum_stock,
        current_stock,
        status,
        created_at
    FROM materials
    $whereSql
    ORDER BY material_name ASC
";

$stmt = $con->prepare($sql);
$stmt->execute($params);

$materials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

// Total active materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
");

$totalMaterials = (int)$stmt->fetchColumn();


// Total active stock quantity
$stmt = $con->query("
    SELECT COALESCE(SUM(current_stock), 0)
    FROM materials
    WHERE status = 'Enable'
");

$totalStock = (float)$stmt->fetchColumn();


// Low stock
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
      AND current_stock > 0
      AND current_stock <= minimum_stock
");

$lowStockCount = (int)$stmt->fetchColumn();


// Out of stock
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
      AND current_stock <= 0
");

$outOfStockCount = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| RECENT TRANSACTIONS
|--------------------------------------------------------------------------
*/

$transactionMaterialId = (int)(
    $_GET['material_id'] ?? 0
);

$transactions = [];

if ($transactionMaterialId > 0) {

    $stmt = $con->prepare("
        SELECT
            st.id,
            st.transaction_type,
            st.quantity,
            st.remarks,
            st.created_at,
            m.material_code,
            m.material_name,
            m.unit,
            u.employee_name
        FROM stock_transactions st

        INNER JOIN materials m
            ON m.id = st.material_id

        LEFT JOIN users u
            ON u.id = st.created_by

        WHERE st.material_id = :material_id

        ORDER BY st.id DESC

        LIMIT 50
    ");

    $stmt->execute([
        ':material_id' => $transactionMaterialId
    ]);

    $transactions = $stmt->fetchAll();

}


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

        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h4 class="mb-1">
                    Stock Report
                </h4>

                <div class="text-muted">
                    View current stock and material-wise stock status.
                </div>

            </div>


            <div>

                <a
                    href="stock_inward.php"
                    class="btn btn-success me-1">

                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>

                    Stock Inward

                </a>


                <a
                    href="stock_issue.php"
                    class="btn btn-warning">

                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>

                    Issue to Kitchen

                </a>

            </div>

        </div>


        <!-- SUMMARY CARDS -->
        <div class="row g-3 mb-4">

            <!-- ACTIVE MATERIALS -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="stat-label">
                                Active Materials
                            </div>

                            <div class="stat-value">
                                <?= $totalMaterials ?>
                            </div>

                        </div>

                        <div class="fs-2 text-primary">

                            <i class="fa-solid fa-boxes-stacked"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- TOTAL STOCK -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="stat-label">
                                Total Stock Qty
                            </div>

                            <div class="stat-value">
                                <?= number_format($totalStock, 2) ?>
                            </div>

                        </div>

                        <div class="fs-2 text-success">

                            <i class="fa-solid fa-warehouse"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- LOW STOCK -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="stat-label">
                                Low Stock
                            </div>

                            <div class="stat-value text-warning">
                                <?= $lowStockCount ?>
                            </div>

                        </div>

                        <div class="fs-2 text-warning">

                            <i class="fa-solid fa-triangle-exclamation"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- OUT OF STOCK -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="stat-label">
                                Out of Stock
                            </div>

                            <div class="stat-value text-danger">
                                <?= $outOfStockCount ?>
                            </div>

                        </div>

                        <div class="fs-2 text-danger">

                            <i class="fa-solid fa-circle-xmark"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- FILTER -->
        <div class="content-card mb-4">

            <div class="content-card-header">

                <strong>

                    <i class="fa-solid fa-filter me-1"></i>

                    Stock Filter

                </strong>

            </div>


            <div class="p-4">

                <form method="GET">

                    <div class="row g-3 align-items-end">

                        <!-- SEARCH -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Search Material
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                value="<?= e($search) ?>"
                                placeholder="Code, material name or category">

                        </div>


                        <!-- STATUS -->
                        <div class="col-md-4">

                            <label class="form-label">
                                Stock Status
                            </label>

                            <select
                                name="status"
                                class="form-select">

                                <option
                                    value="All"
                                    <?= $statusFilter === 'All'
                                        ? 'selected'
                                        : '' ?>>

                                    All

                                </option>

                                <option
                                    value="In Stock"
                                    <?= $statusFilter === 'In Stock'
                                        ? 'selected'
                                        : '' ?>>

                                    In Stock

                                </option>

                                <option
                                    value="Low Stock"
                                    <?= $statusFilter === 'Low Stock'
                                        ? 'selected'
                                        : '' ?>>

                                    Low Stock

                                </option>

                                <option
                                    value="Out of Stock"
                                    <?= $statusFilter === 'Out of Stock'
                                        ? 'selected'
                                        : '' ?>>

                                    Out of Stock

                                </option>

                                <option
                                    value="Disabled"
                                    <?= $statusFilter === 'Disabled'
                                        ? 'selected'
                                        : '' ?>>

                                    Disabled

                                </option>

                            </select>

                        </div>


                        <!-- BUTTON -->
                        <div class="col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                <i class="fa-solid fa-magnifying-glass me-1"></i>

                                Search

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- STOCK TABLE -->
        <div class="content-card mb-4">

            <div class="content-card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <strong>

                        <i class="fa-solid fa-warehouse me-1"></i>

                        Current Stock

                    </strong>

                    <span class="text-muted small">

                        <?= count($materials) ?> material(s)

                    </span>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Code</th>

                            <th>Material</th>

                            <th>Category</th>

                            <th>Unit</th>

                            <th>Minimum</th>

                            <th>Current Stock</th>

                            <th>Status</th>

                            <th class="text-center">Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($materials)): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="text-center text-muted py-5">

                                <i class="fa-solid fa-box-open fs-2 d-block mb-2"></i>

                                No stock records found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($materials as $index => $material): ?>

                            <?php

                            $currentStock =
                                (float)$material['current_stock'];

                            $minimumStock =
                                (float)$material['minimum_stock'];

                            if ($material['status'] === 'Disabled') {

                                $stockStatus = 'Disabled';
                                $badgeClass = 'bg-secondary';

                            } elseif ($currentStock <= 0) {

                                $stockStatus = 'Out of Stock';
                                $badgeClass = 'bg-danger';

                            } elseif ($currentStock <= $minimumStock) {

                                $stockStatus = 'Low Stock';
                                $badgeClass = 'bg-warning text-dark';

                            } else {

                                $stockStatus = 'In Stock';
                                $badgeClass = 'bg-success';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <span class="fw-semibold">

                                        <?= e(
                                            $material['material_code']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <strong>

                                        <?= e(
                                            $material['material_name']
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= e(
                                        $material['category']
                                        ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <span class="badge bg-light text-dark border">

                                        <?= e(
                                            $material['unit']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= number_format(
                                        $minimumStock,
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <strong
                                        class="<?= $stockStatus === 'Out of Stock'
                                            ? 'text-danger'
                                            : (
                                                $stockStatus === 'Low Stock'
                                                ? 'text-warning'
                                                : 'text-success'
                                            )
                                        ?>">

                                        <?= number_format(
                                            $currentStock,
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <span class="badge <?= $badgeClass ?>">

                                        <?= e($stockStatus) ?>

                                    </span>

                                </td>


                                <td class="text-center">

                                    <a
                                        href="?material_id=<?= (int)$material['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View Transactions">

                                        <i class="fa-solid fa-clock-rotate-left"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- TRANSACTION HISTORY -->
        <?php if ($transactionMaterialId > 0): ?>

            <div class="content-card">

                <div class="content-card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <strong>

                            <i class="fa-solid fa-clock-rotate-left me-1"></i>

                            Stock Transaction History

                        </strong>


                        <a
                            href="stock.php"
                            class="btn btn-sm btn-outline-secondary">

                            <i class="fa-solid fa-xmark me-1"></i>

                            Close

                        </a>

                    </div>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Material</th>

                                <th>Transaction</th>

                                <th>Quantity</th>

                                <th>Remarks</th>

                                <th>Created By</th>

                                <th>Date</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (empty($transactions)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center text-muted py-4">

                                    No transactions found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($transactions as $index => $transaction): ?>

                                <?php

                                $transactionType =
                                    $transaction['transaction_type'];

                                if ($transactionType === 'PURCHASE') {

                                    $label = 'Stock Inward';
                                    $badge = 'bg-success';
                                    $prefix = '+';

                                } elseif (
                                    $transactionType === 'ISSUE_KITCHEN'
                                ) {

                                    $label = 'Kitchen Issue';
                                    $badge = 'bg-warning text-dark';
                                    $prefix = '-';

                                } elseif ($transactionType === 'RETURN') {

                                    $label = 'Return';
                                    $badge = 'bg-info text-dark';
                                    $prefix = '+';

                                } else {

                                    $label = 'Adjustment';
                                    $badge = 'bg-secondary';
                                    $prefix = '';

                                }

                                ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>

                                            <?= e(
                                                $transaction['material_name']
                                            ) ?>

                                        </strong>

                                        <br>

                                        <small class="text-muted">

                                            <?= e(
                                                $transaction['material_code']
                                            ) ?>

                                        </small>

                                    </td>


                                    <td>

                                        <span class="badge <?= $badge ?>">

                                            <?= e($label) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= $prefix ?>

                                            <?= number_format(
                                                (float)$transaction['quantity'],
                                                2
                                            ) ?>

                                            <?= e(
                                                $transaction['unit']
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= e(
                                            $transaction['remarks']
                                            ?: '-'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            $transaction['employee_name']
                                            ?: 'System'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            $transaction['created_at']
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endif; ?>

    </div>

</main>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>