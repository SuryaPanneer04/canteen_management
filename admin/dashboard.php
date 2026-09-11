<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';

$today = date('Y-m-d');
$activeUsers = (int)$con->query("SELECT COUNT(*) FROM users WHERE status = 'Enable'")->fetchColumn();

// 1. Pending Invoice Approvals
$pendingInvoices = 0;
try {
    $pendingInvoices = (int)$con->query("SELECT COUNT(*) FROM invoices WHERE status = 'Pending'")->fetchColumn();
} catch(Exception $e) {} 

// 2. Today's Total Served Food
$todayServed = 0;
try {
    $servedStmt = $con->prepare("SELECT SUM(received_qty - remaining_qty) FROM canteen_food_serving WHERE DATE(created_at) = ? AND status = 'Closed'");
    $servedStmt->execute([$today]);
    $todayServed = (int)$servedStmt->fetchColumn();
} catch(Exception $e) {}

// 3. Today's Wastage (Actual Wastage Only)
$todayWastage = 0;
try {
    $wastageStmt = $con->prepare("SELECT SUM(wastage_qty) FROM canteen_wastage WHERE DATE(created_at) = ? AND reason != 'Staff Consumption'");
    $wastageStmt->execute([$today]);
    $todayWastage = (int)$wastageStmt->fetchColumn();
} catch(Exception $e) {}

// --- Table: Pending Invoice Approvals ---
$pendingApprovals = [];
try {
    $recentStmt = $con->query("SELECT * FROM invoices WHERE status = 'Pending' ORDER BY id DESC LIMIT 5");
    $pendingApprovals = $recentStmt->fetchAll();
} catch(Exception $e) {}

// --- Chart data: Wastage Trend last 7 days ---
$growthLabels = [];
$growthCounts = [];
try {
    $trendStmt = $con->prepare("SELECT SUM(wastage_qty) FROM canteen_wastage WHERE DATE(created_at) = ? AND reason != 'Staff Consumption'");
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $trendStmt->execute([$day]);
        $growthLabels[] = date('D', strtotime($day));
        $val = (int)$trendStmt->fetchColumn();
        $growthCounts[] = $val > 0 ? $val : 0; 
    }
} catch(Exception $e) {}

// --- Chart data: Wastage vs Staff Consumption (Today) ---
$roleLabels = ['Actual Wastage', 'Staff Consumption'];
$staffCons = 0;
try {
    $staffConsStmt = $con->prepare("SELECT SUM(wastage_qty) FROM canteen_wastage WHERE DATE(created_at) = ? AND reason = 'Staff Consumption'");
    $staffConsStmt->execute([$today]);
    $staffCons = (int)$staffConsStmt->fetchColumn() ?: 0;
} catch(Exception $e) {}
$roleCounts = [$todayWastage, $staffCons];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">

        <div class="mb-4">
            <h4 class="fw-bold mb-1">Welcome, <?= e($_SESSION['employee_name']) ?></h4>
            <div class="text-muted">Monitor and manage the canteen system from here.</div>
        </div>

        <div class="row g-3 mb-4">
            
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Active Staff</div>
                            <div class="stat-value"><?= $activeUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-primary">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Pending Approvals</div>
                            <div class="stat-value"><?= $pendingInvoices ?></div>
                        </div>
                        <div class="stat-icon stat-icon-amber">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Food Served Today</div>
                            <div class="stat-value"><?= $todayServed ?></div>
                        </div>
                        <div class="stat-icon stat-icon-green">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Today's Wastage</div>
                            <div class="stat-value"><?= $todayWastage ?></div>
                        </div>
                        <div class="stat-icon stat-icon-purple">
                            <i class="fa-solid fa-trash-can"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3 mb-4">

            <div class="col-12 col-xl-8">
                <div class="content-card chart-card h-100">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Wastage Trend</h6>
                            <small class="text-muted">Actual wastage quantity over the last 7 days</small>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <canvas id="userGrowthChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="content-card chart-card h-100">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Wastage Breakdown</h6>
                            <small class="text-muted">Today's Wastage vs Staff Consumption</small>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <canvas id="roleChart" height="160"></canvas>
                        <div class="chart-legend" id="roleChartLegend"></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3">

            <div class="col-12 col-xl-8">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Pending Invoice Approvals</h6>
                            <small class="text-muted">Invoices waiting for Admin review</small>
                        </div>
                        <a href="invoice_approvals.php" class="btn btn-sm btn-primary">
                            View All
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$pendingApprovals): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        No pending invoices for approval.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($pendingApprovals as $inv): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($inv['invoice_no'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pending</span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Quick Actions</h6>
                            <small class="text-muted">Administration shortcuts</small>
                        </div>
                    </div>

                    <div class="content-card-body">

                        <a href="invoice_approvals.php"
                           class="btn btn-primary w-100 mb-2">
                            <i class="fa-solid fa-file-invoice-dollar me-2"></i>
                            Review Invoices
                        </a>

                        <a href="canteen_report.php"
                           class="btn btn-outline-secondary w-100 mb-2">
                            <i class="fa-solid fa-chart-pie me-2"></i>
                            Canteen Report
                        </a>

                        <a href="price_master.php"
                           class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-tags me-2"></i>
                            Price Master
                        </a>

                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    var css = getComputedStyle(document.documentElement);
    var primary = css.getPropertyValue('--primary').trim() || '#0e3b2e';
    var accent = css.getPropertyValue('--accent').trim() || '#f59e0b';
    var border = css.getPropertyValue('--border').trim() || '#e1e6e3';
    var muted = css.getPropertyValue('--muted').trim() || '#6b746f';

    var palette = [primary, accent, '#2563eb', '#7c3aed', '#dc2626', '#0891b2'];

    // User growth line chart
    var growthCtx = document.getElementById('userGrowthChart');
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($growthLabels) ?>,
            datasets: [{
                label: 'Wastage Qty',
                data: <?= json_encode($growthCounts) ?>,
                borderColor: primary,
                backgroundColor: primary + '22',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: primary,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: muted },
                    grid: { color: border }
                },
                x: {
                    ticks: { color: muted },
                    grid: { display: false }
                }
            }
        }
    });

    // Users by role pie chart
    var roleLabels = <?= json_encode($roleLabels) ?>;
    var roleCounts = <?= json_encode($roleCounts) ?>;
    var roleColors = roleLabels.map(function (_, i) { return palette[i % palette.length]; });

    var roleCtx = document.getElementById('roleChart');
    new Chart(roleCtx, {
        type: 'doughnut',
        data: {
            labels: roleLabels,
            datasets: [{
                data: roleCounts,
                backgroundColor: roleColors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: { legend: { display: false } }
        }
    });

    var legend = document.getElementById('roleChartLegend');
    roleLabels.forEach(function (label, i) {
        var item = document.createElement('div');
        item.className = 'chart-legend-item';
        item.innerHTML =
            '<span class="chart-legend-dot" style="background:' + roleColors[i] + '"></span>' +
            '<span>' + label + '</span>' +
            '<span class="chart-legend-value">' + roleCounts[i] + '</span>';
        legend.appendChild(item);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>