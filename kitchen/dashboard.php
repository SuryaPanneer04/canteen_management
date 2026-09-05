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

$pageTitle = 'Kitchen Dashboard';

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

// Total active materials
$stmt = $con->query("
    SELECT COUNT(*) 
    FROM materials
    WHERE status = 'Enable'
");
$totalMaterials = (int)$stmt->fetchColumn();


// Total stock
$stmt = $con->query("
    SELECT COALESCE(SUM(current_stock), 0)
    FROM materials
    WHERE status = 'Enable'
");
$totalStock = (float)$stmt->fetchColumn();


// Low stock materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
      AND current_stock <= minimum_stock
");
$lowStockCount = (int)$stmt->fetchColumn();


// Today's kitchen issues
$stmt = $con->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM stock_transactions
    WHERE transaction_type = 'ISSUE_KITCHEN'
      AND DATE(created_at) = CURDATE()
");
$todayIssued = (float)$stmt->fetchColumn();


// Recent kitchen issues
$stmt = $con->query("
    SELECT
        st.id,
        st.quantity,
        st.reference_no,
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
    WHERE st.transaction_type = 'ISSUE_KITCHEN'
    ORDER BY st.id DESC
    LIMIT 10
");
$recentIssues = $stmt->fetchAll();


// Low stock materials
$stmt = $con->query("
    SELECT
        material_code,
        material_name,
        category,
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

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Kitchen Dashboard</h4>
            <p class="text-muted mb-0">
                Monitor kitchen stock and material issues.
            </p>
        </div>

        <div>
            <a href="material_request.php" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>
                Material Request
            </a>
        </div>
    </div>


    <!-- Dashboard Cards -->
    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">

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


        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="text-muted small">
                                Available Stock
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= number_format($totalStock, 2) ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-success">
                            <i class="fa-solid fa-warehouse"></i>
                        </div>

                    </div>

                </div>
            </div>
        </div>


        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="text-muted small">
                                Low Stock Items
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= $lowStockCount ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-danger">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                    </div>

                </div>
            </div>
        </div>


        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="text-muted small">
                                Today's Issue
                            </div>

                            <h3 class="mb-0 mt-2">
                                <?= number_format($todayIssued, 2) ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-warning">
                            <i class="fa-solid fa-utensils"></i>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>


    <!-- Main Content -->
    <div class="row g-4">

        <!-- Recent Issues -->
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h5 class="mb-0">
                                Recent Kitchen Issues
                            </h5>

                            <small class="text-muted">
                                Recently issued materials
                            </small>
                        </div>

                        <a href="issue_history.php"
                           class="btn btn-sm btn-outline-primary">
                            View All
                        </a>

                    </div>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead class="table-light">

                                <tr>
                                    <th>#</th>
                                    <th>Material</th>
                                    <th>Quantity</th>
                                    <th>Reference</th>
                                    <th>Issued By</th>
                                    <th>Date</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if (!$recentIssues): ?>

                                <tr>
                                    <td colspan="6"
                                        class="text-center text-muted py-4">
                                        No kitchen issues found.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($recentIssues as $index => $issue): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($issue['material_name']) ?>
                                            </strong>

                                            <br>

                                            <small class="text-muted">
                                                <?= e($issue['material_code']) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= number_format(
                                                    (float)$issue['quantity'],
                                                    2
                                                ) ?>
                                                <?= e($issue['unit']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= e(
                                                $issue['reference_no'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $issue['employee_name'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                date(
                                                    'd-m-Y H:i',
                                                    strtotime($issue['created_at'])
                                                )
                                            ) ?>
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


        <!-- Low Stock -->
        <div class="col-lg-4">

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">
                        Low Stock
                    </h5>

                    <small class="text-muted">
                        Materials requiring attention
                    </small>

                </div>


                <div class="card-body p-0">

                    <?php if (!$lowStockMaterials): ?>

                        <div class="text-center text-muted py-4">
                            <i class="fa-solid fa-circle-check fs-3 mb-2"></i>

                            <div>
                                No low stock materials.
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($lowStockMaterials as $material): ?>

                                <div class="list-group-item">

                                    <div class="d-flex justify-content-between">

                                        <div>

                                            <strong>
                                                <?= e(
                                                    $material['material_name']
                                                ) ?>
                                            </strong>

                                            <br>

                                            <small class="text-muted">
                                                <?= e(
                                                    $material['material_code']
                                                ) ?>
                                            </small>

                                        </div>

                                        <div class="text-end">

                                            <span class="badge bg-danger">
                                                <?= number_format(
                                                    (float)$material['current_stock'],
                                                    2
                                                ) ?>
                                                <?= e($material['unit']) ?>
                                            </span>

                                            <br>

                                            <small class="text-muted">
                                                Min:
                                                <?= number_format(
                                                    (float)$material['minimum_stock'],
                                                    2
                                                ) ?>
                                            </small>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>