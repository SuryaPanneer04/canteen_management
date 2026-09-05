<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Store Dashboard';

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

// Active materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
");

$totalMaterials = (int) $stmt->fetchColumn();


// Total current stock
$stmt = $con->query("
    SELECT COALESCE(SUM(current_stock), 0)
    FROM materials
    WHERE status = 'Enable'
");

$totalStock = (float) $stmt->fetchColumn();


// Low stock materials
$stmt = $con->query("
    SELECT COUNT(*)
    FROM materials
    WHERE status = 'Enable'
      AND current_stock <= minimum_stock
");

$lowStockCount = (int) $stmt->fetchColumn();


// Pending purchase requests
$stmt = $con->query("
    SELECT COUNT(*)
    FROM purchase_requests
    WHERE status = 'Pending'
");

$pendingRequests = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| LOW STOCK MATERIALS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        material_code,
        material_name,
        category,
        unit,
        current_stock,
        minimum_stock
    FROM materials
    WHERE status = 'Enable'
      AND current_stock <= minimum_stock
    ORDER BY material_name ASC
");

$lowStockMaterials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT STOCK TRANSACTIONS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        st.id,
        st.transaction_type,
        st.quantity,
        st.remarks,
        st.created_at,
        m.material_name,
        m.unit,
        u.employee_name
    FROM stock_transactions st

    INNER JOIN materials m
        ON m.id = st.material_id

    LEFT JOIN users u
        ON u.id = st.created_by

    ORDER BY st.id DESC
    LIMIT 10
");

$recentTransactions = $stmt->fetchAll();


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
                <h4 class="mb-1">Store Dashboard</h4>

                <div class="text-muted">
                    Monitor materials, stock and purchase requests.
                </div>
            </div>

            <a href="purchase_requests.php" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>
                Create Purchase Request
            </a>

        </div>


        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">

            <!-- Materials -->
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


            <!-- Stock -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="stat-label">
                                Current Stock Qty
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


            <!-- Low Stock -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="stat-label">
                                Low Stock Items
                            </div>

                            <div class="stat-value text-danger">
                                <?= $lowStockCount ?>
                            </div>
                        </div>

                        <div class="fs-2 text-danger">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- Purchase Requests -->
            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="stat-label">
                                Pending Requests
                            </div>

                            <div class="stat-value">
                                <?= $pendingRequests ?>
                            </div>
                        </div>

                        <div class="fs-2 text-warning">
                            <i class="fa-solid fa-file-circle-exclamation"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- LOW STOCK ALERT -->
        <div class="content-card mb-4">

            <div class="content-card-header d-flex justify-content-between align-items-center">

                <div>
                    <strong>
                        <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>
                        Low Stock Alert
                    </strong>
                </div>

                <a href="stock.php"
                   class="btn btn-sm btn-outline-secondary">

                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    View Stock

                </a>

            </div>


            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Material Code</th>

                            <th>Material</th>

                            <th>Category</th>

                            <th>Unit</th>

                            <th>Current Stock</th>

                            <th>Minimum Stock</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($lowStockMaterials)): ?>

                        <tr>

                            <td colspan="8"
                                class="text-center text-muted py-4">

                                <i class="fa-solid fa-circle-check text-success me-1"></i>

                                No low-stock materials.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($lowStockMaterials as $index => $material): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <?= e($material['material_code']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($material['material_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($material['category']) ?>
                                </td>

                                <td>
                                    <?= e($material['unit']) ?>
                                </td>

                                <td>
                                    <strong class="text-danger">
                                        <?= number_format(
                                            (float)$material['current_stock'],
                                            2
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$material['minimum_stock'],
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span class="badge bg-danger">
                                        Low Stock
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- RECENT STOCK TRANSACTIONS -->
        <div class="content-card">

            <div class="content-card-header d-flex justify-content-between align-items-center">

                <strong>
                    <i class="fa-solid fa-clock-rotate-left me-1"></i>
                    Recent Stock Transactions
                </strong>

                <a href="stock.php"
                   class="btn btn-sm btn-outline-secondary">

                    View Stock

                </a>

            </div>


            <div class="table-responsive">

                <table class="table table-hover mb-0">

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

                    <?php if (empty($recentTransactions)): ?>

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted py-4">

                                No stock transactions found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentTransactions as $index => $transaction): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <strong>
                                        <?= e($transaction['material_name']) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        <?= e($transaction['unit']) ?>
                                    </small>

                                </td>


                                <td>

                                    <?php

                                    $transactionType =
                                        $transaction['transaction_type'];

                                    if ($transactionType === 'PURCHASE') {

                                        $badgeClass = 'bg-success';
                                        $label = 'Purchase';

                                    } elseif ($transactionType === 'ISSUE_KITCHEN') {

                                        $badgeClass = 'bg-warning text-dark';
                                        $label = 'Kitchen Issue';

                                    } elseif ($transactionType === 'RETURN') {

                                        $badgeClass = 'bg-info text-dark';
                                        $label = 'Return';

                                    } else {

                                        $badgeClass = 'bg-secondary';
                                        $label = 'Adjustment';

                                    }

                                    ?>

                                    <span class="badge <?= $badgeClass ?>">
                                        <?= e($label) ?>
                                    </span>

                                </td>


                                <td>

                                    <strong>
                                        <?= number_format(
                                            (float)$transaction['quantity'],
                                            2
                                        ) ?>
                                    </strong>

                                    <?= e($transaction['unit']) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $transaction['remarks'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $transaction['employee_name']
                                        ?? 'System'
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

    </div>

</main>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>