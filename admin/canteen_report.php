<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only Admin can view reports
if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Canteen Daily Report';

/*
|--------------------------------------------------------------------------
| DATE FILTERS
|--------------------------------------------------------------------------
*/
$fromDate = $_GET['from_date'] ?? date('Y-m-01'); // Default to 1st of current month
$toDate   = $_GET['to_date'] ?? date('Y-m-t');  // Default to end of current month

/*
|--------------------------------------------------------------------------
| FETCH REPORT DATA
|--------------------------------------------------------------------------
*/
$stmt = $con->prepare("
    SELECT
        cfs.serving_date,
        fi.food_name,
        fi.unit,
        cfs.received_qty,
        cfs.served_qty,
        cfs.pax,
        
        -- Calculate Staff Consumption
        (
            SELECT COALESCE(SUM(cw.wastage_qty), 0) 
            FROM canteen_wastage cw 
            WHERE cw.serving_id = cfs.id AND cw.reason = 'Staff Consumption'
        ) as staff_consumption,
        
        -- Calculate Actual Wastage (Everything else)
        (
            SELECT COALESCE(SUM(cw.wastage_qty), 0) 
            FROM canteen_wastage cw 
            WHERE cw.serving_id = cfs.id AND cw.reason != 'Staff Consumption'
        ) as actual_wastage

    FROM canteen_food_serving cfs
    INNER JOIN food_items fi ON fi.id = cfs.food_id
    WHERE cfs.status = 'Closed'
      AND cfs.serving_date >= :from_date 
      AND cfs.serving_date <= :to_date
    ORDER BY cfs.serving_date DESC, cfs.id DESC
");

$stmt->execute([
    ':from_date' => $fromDate,
    ':to_date'   => $toDate
]);

$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="page-body">
            <!-- PAGE HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="fa-solid fa-chart-pie me-2"></i>
                        Canteen Daily Report
                    </h4>
                    <p class="text-muted mb-0">
                        View food serving, staff consumption, and actual wastage.
                    </p>
                </div>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Report
                </button>
            </div>

            <!-- FILTER FORM -->
            <div class="content-card mb-4">
                <div class="content-card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-filter me-1"></i> Filter Report
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="canteen_report.php" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- REPORT TABLE -->
            <div class="content-card">
                <div class="content-card-header">
                    <strong><i class="fa-solid fa-table me-2"></i> Report Data</strong>
                    <span class="badge badge-count"><?= count($reportData) ?> Records</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0 text-nowrap">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Date</th>
                                <th>Food Item</th>
                                <th>Received (Kitchen)</th>
                                <th>Served (Users)</th>
                                <th>Total Pax</th>
                                <th class="text-primary">Staff Consumption</th>
                                <th class="text-danger">Actual Wastage</th>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            <?php if (empty($reportData)): ?>
                                <tr>
                                    <td colspan="7" class="text-muted py-5">No records found for the selected dates.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td><strong><?= date('d-m-Y', strtotime($row['serving_date'])) ?></strong></td>
                                        <td class="text-start"><strong><?= e($row['food_name']) ?></strong></td>
                                        <td><?= number_format((float)$row['received_qty'], 2) ?> <?= e($row['unit']) ?></td>
                                        <td><?= number_format((float)$row['served_qty'], 2) ?> <?= e($row['unit']) ?></td>
                                        <td><strong><?= (int)$row['pax'] ?></strong></td>
                                        
                                        <!-- Staff Consumption -->
                                        <td class="text-primary fw-bold">
                                            <?= number_format((float)$row['staff_consumption'], 2) ?> <?= e($row['unit']) ?>
                                        </td>
                                        
                                        <!-- Actual Wastage -->
                                        <td class="text-danger fw-bold">
                                            <?= number_format((float)$row['actual_wastage'], 2) ?> <?= e($row['unit']) ?>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>