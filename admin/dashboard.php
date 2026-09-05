<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';

$totalUsers = (int)$con->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = (int)$con->query("SELECT COUNT(*) FROM users WHERE status = 'Enable'")->fetchColumn();
$totalRoles = (int)$con->query("SELECT COUNT(*) FROM roles")->fetchColumn();

$today = date('Y-m-d');

$stmt = $con->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayUsers = (int)$stmt->fetchColumn();

$recentStmt = $con->query("
    SELECT u.id, u.employee_code, u.employee_name, u.email,
           r.role_name, u.status, u.created_at
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    ORDER BY u.id DESC
    LIMIT 8
");
$recentUsers = $recentStmt->fetchAll();

// --- Chart data: new users per day, last 7 days ---
$growthLabels = [];
$growthCounts = [];

$growthStmt = $con->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $growthStmt->execute([$day]);
    $growthLabels[] = date('D', strtotime($day));
    $growthCounts[] = (int)$growthStmt->fetchColumn();
}

// --- Chart data: users by role ---
$roleStmt = $con->query("
    SELECT r.role_name, COUNT(u.id) AS total
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    GROUP BY r.id, r.role_name
    ORDER BY total DESC
");
$roleRows = $roleStmt->fetchAll();
$roleLabels = array_map(fn($row) => $row['role_name'], $roleRows);
$roleCounts = array_map(fn($row) => (int)$row['total'], $roleRows);

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
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value"><?= $totalUsers ?></div>
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
                            <div class="stat-label">Active Users</div>
                            <div class="stat-value"><?= $activeUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Roles</div>
                            <div class="stat-value"><?= $totalRoles ?></div>
                        </div>
                        <div class="stat-icon stat-icon-purple">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Users Added Today</div>
                            <div class="stat-value"><?= $todayUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-amber">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3 mb-4">

            <div class="col-12 col-xl-8">
                <div class="content-card chart-card">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">User Growth</h6>
                            <small class="text-muted">New users over the last 7 days</small>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <canvas id="userGrowthChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="content-card chart-card">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Users by Role</h6>
                            <small class="text-muted">Current distribution</small>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <canvas id="roleChart" height="220"></canvas>
                        <div class="chart-legend" id="roleChartLegend"></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3">

            <div class="col-12 col-xl-8">
                <div class="content-card">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Recent Users</h6>
                            <small class="text-muted">Latest users in the system</small>
                        </div>
                        <a href="users.php" class="btn btn-sm btn-primary">
                            View All
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$recentUsers): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="row-avatar">
                                                <?= strtoupper(substr($user['employee_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= e($user['employee_name']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= e($user['employee_code']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($user['email']) ?></td>
                                    <td><?= e($user['role_name']) ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'Enable'): ?>
                                            <span class="badge badge-enable">Enable</span>
                                        <?php else: ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="content-card">
                    <div class="content-card-header">
                        <div>
                            <h6 class="fw-bold mb-1">Quick Actions</h6>
                            <small class="text-muted">Administration shortcuts</small>
                        </div>
                    </div>

                    <div class="content-card-body">

                        <a href="users.php?action=add"
                           class="btn btn-primary w-100 mb-2">
                            <i class="fa-solid fa-user-plus me-2"></i>
                            Add User
                        </a>

                        <a href="roles.php"
                           class="btn btn-outline-secondary w-100 mb-2">
                            <i class="fa-solid fa-user-shield me-2"></i>
                            Manage Roles
                        </a>

                        <a href="users.php"
                           class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-users me-2"></i>
                            Manage Users
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
                label: 'New Users',
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