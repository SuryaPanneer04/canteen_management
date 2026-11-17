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

$recentUsers = [];
try {
    $recentUsers = $con->query("
        SELECT
            u.employee_code,
            u.employee_name,
            u.email,
            u.role_id,
            COALESCE(r.role_name, 'User') AS role_name,
            u.status,
            u.created_at
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        ORDER BY u.id DESC
        LIMIT 5
    ")->fetchAll();
} catch (Throwable $e) {}
$departmentCount = 0;
try { $departmentCount = (int)$con->query("SELECT COUNT(DISTINCT role_id) FROM users")->fetchColumn(); } catch (Throwable $e) {}
$groupCount = 0;
try { $groupCount = (int)$con->query("SELECT COUNT(*) FROM roles")->fetchColumn(); } catch (Throwable $e) {}
$systemLogs = 0;
try { $systemLogs = (int)$con->query("SELECT COUNT(*) FROM stock_transactions")->fetchColumn(); } catch (Throwable $e) {}
$activeSessions = !empty($_SESSION['user_id']) ? 1 : 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="dashboard-hero">
            <div class="dashboard-heading">
                
                
            </div>
            <div class="hero-right">
                <div class="date-chip">
                    <i class="fa-regular fa-calendar-days"></i>
                    <div>
                        <strong><?= date('d-m-Y') ?></strong>
                        <span><?= date('l') ?></span>
                    </div>
                </div>
                <div class="motto-chip">
                    <i class="fa-solid fa-gears"></i>
                    <div>
                        <strong>Manage Control Grow</strong>
                        <span>System at a glance</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="dash-stat">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Total Users
                        </div>
                        <div class="stat-value">
                            <?= $activeUsers ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Active users
                </div>
            </div>
            <div class="dash-stat stat-orange">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Departments
                        </div>
                        <div class="stat-value">
                            <?= $departmentCount ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-building"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    • Current setup
                </div>
            </div>
            <div class="dash-stat stat-green">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Groups
                        </div>
                        <div class="stat-value">
                            <?= $groupCount ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Configured roles
                </div>
            </div>
            <div class="dash-stat stat-purple">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            System Logs
                        </div>
                        <div class="stat-value">
                            <?= $systemLogs ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                </div>
                <div class="stat-trend">
                    ↗ Stock activity
                </div>
            </div>
            <div class="dash-stat stat-cyan">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">
                            Active Sessions
                        </div>
                        <div class="stat-value">
                            <?= $activeSessions ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-trend neutral">
                    • Current login
                </div>
            </div>
        </div>
        <div class="quick-card">
            <div class="section-title">
                <i class="fa-solid fa-bolt"></i>
                Quick Actions
            </div>
            <div class="quick-actions">
                <a class="quick-action primary" href="users.php">
                    <i class="fa-solid fa-user-plus"></i>
                    Add User
                </a>
                <a class="quick-action orange" href="roles.php">
                    <i class="fa-solid fa-users-gear"></i>
                    Manage Roles
                </a>
                <a class="quick-action green" href="canteen_report.php">
                    <i class="fa-solid fa-chart-column"></i>
                    Reports
                </a>
                <a class="quick-action purple" href="price_master.php">
                    <i class="fa-solid fa-tags"></i>
                    Price Master
                </a>
            </div>
        </div>
        <div class="dashboard-table">
            <div class="table-header">
                <div class="section-title mb-0">
                    <i class="fa-solid fa-users"></i> 
                    Recent Users
                </div>
                <a href="users.php">
                    View All ›
                </a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentUsers): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    No users found.
                                </div>
                            </td>
                        </tr>
                        <?php else: foreach ($recentUsers as $u): 
                            $initial=strtoupper(substr(trim((string)$u['employee_name']),0,1)); 
                        ?>
                        <tr>
                            <td>
                                <span class="table-avatar"><?= e($initial) ?></span>
                                <strong><?= e($u['employee_name']) ?></strong>
                            </td>
                            <td>User</td>
                            <td>
                                <span class="status-pill 
                                    <?= ($u['status']??'')==='Enable'?'status-success':'status-danger' ?>">
                                    <?= e($u['status']??'') ?>
                                </span>
                            </td>
                            <td>
                                <?= e(date('d-m-Y',strtotime($u['created_at']))) ?>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reviewUserModal"
                                    data-employee-code="<?= e($u['employee_code'] ?? '') ?>"
                                    data-role="<?= e($u['role_name'] ?? 'User') ?>"
                                    data-employee-name="<?= e($u['employee_name'] ?? '') ?>"
                                    data-email="<?= e($u['email'] ?? '') ?>"
                                >
                                    <i class="fa-regular fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Review User Modal - view only, no editing -->
<div class="modal fade review-user-modal" id="reviewUserModal" tabindex="-1" aria-labelledby="reviewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="review-modal-header">
                <div class="review-modal-heading">
                    <div class="review-modal-icon"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <h5 class="modal-title" id="reviewUserModalLabel">User Details</h5>
                        <p>Review account information</p>
                    </div>
                </div>
                <button type="button" class="review-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="review-user-identity">
                <div class="review-user-avatar" id="reviewUserAvatar">U</div>
                <div>
                    <div class="review-user-name" id="reviewUserName">User</div>
                    <div class="review-user-role" id="reviewUserRole">User</div>
                </div>
            </div>

            <div class="review-user-body">
                <div class="review-section-title"><i class="fa-solid fa-circle-info"></i> ACCOUNT DETAILS</div>
                <div class="review-details-grid">
                    <div class="review-detail-card">
                        <span class="review-detail-label">Employee Code</span>
                        <strong id="reviewEmployeeCode">-</strong>
                    </div>
                    <div class="review-detail-card">
                        <span class="review-detail-label">Role</span>
                        <strong id="reviewRole">-</strong>
                    </div>
                    <div class="review-detail-card">
                        <span class="review-detail-label">Employee Name</span>
                        <strong id="reviewEmployeeName">-</strong>
                    </div>
                    <div class="review-detail-card">
                        <span class="review-detail-label">Email ID</span>
                        <strong id="reviewEmail">-</strong>
                    </div>
                </div>
            </div>

            <div class="review-modal-footer">
                <button type="button" class="review-close-btn" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reviewModal = document.getElementById('reviewUserModal');
    if (!reviewModal) return;

    reviewModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const employeeCode = button.getAttribute('data-employee-code') || '-';
        const role = button.getAttribute('data-role') || '-';
        const employeeName = button.getAttribute('data-employee-name') || '-';
        const email = button.getAttribute('data-email') || '-';

        document.getElementById('reviewEmployeeCode').textContent = employeeCode;
        document.getElementById('reviewRole').textContent = role;
        document.getElementById('reviewEmployeeName').textContent = employeeName;
        document.getElementById('reviewEmail').textContent = email;
        document.getElementById('reviewUserName').textContent = employeeName;
        document.getElementById('reviewUserRole').textContent = role;
        document.getElementById('reviewUserAvatar').textContent = employeeName.trim().charAt(0).toUpperCase() || 'U';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
