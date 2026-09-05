<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Role Management';
$error = null;
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $roleName = trim($_POST['role_name'] ?? '');

        if ($roleName === '') {
            $error = 'Role name is required.';
        } else {
            $check = $con->prepare("SELECT COUNT(*) FROM roles WHERE role_name = ?");
            $check->execute([$roleName]);

            if ((int)$check->fetchColumn() > 0) {
                $error = 'Role already exists.';
            } else {
                $stmt = $con->prepare("INSERT INTO roles (role_name, status) VALUES (?, 'Enable')");
                $stmt->execute([$roleName]);

                flash('success', 'Role added successfully.');
                header('Location: roles.php');
                exit;
            }
        }
    }

    if ($action === 'toggle') {
        $roleId = (int)($_POST['role_id'] ?? 0);

        $countStmt = $con->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE role_id = ?
              AND status = 'Enable'
        ");
        $countStmt->execute([$roleId]);

        if ((int)$countStmt->fetchColumn() > 0) {
            $error = 'This role is assigned to active users. Reassign those users before disabling the role.';
        } else {
            $stmt = $con->prepare("
                UPDATE roles
                SET status = CASE
                    WHEN status = 'Enable' THEN 'Disabled'
                    ELSE 'Enable'
                END
                WHERE id = ?
            ");
            $stmt->execute([$roleId]);

            flash('success', 'Role status updated.');
            header('Location: roles.php');
            exit;
        }
    }
}

$roles = $con->query("
    SELECT
        r.id,
        r.role_name,
        r.status,
        COUNT(u.id) AS user_count
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    GROUP BY r.id, r.role_name, r.status
    ORDER BY r.id
")->fetchAll();

$totalRoles = count($roles);
$activeRoles = count(array_filter($roles, fn($r) => $r['status'] === 'Enable'));
$disabledRoles = $totalRoles - $activeRoles;
$assignedUsers = array_sum(array_column($roles, 'user_count'));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">

        <div class="mb-4">
            <h4 class="fw-bold mb-1">Role Management</h4>
            <div class="text-muted">Create and manage system access roles.</div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Roles</div>
                            <div class="stat-value"><?= $totalRoles ?></div>
                        </div>
                        <div class="stat-icon stat-icon-primary">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Active Roles</div>
                            <div class="stat-value"><?= $activeRoles ?></div>
                        </div>
                        <div class="stat-icon stat-icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Disabled Roles</div>
                            <div class="stat-value"><?= $disabledRoles ?></div>
                        </div>
                        <div class="stat-icon stat-icon-amber">
                            <i class="fa-solid fa-circle-minus"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Assigned Users</div>
                            <div class="stat-value"><?= $assignedUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-purple">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="content-card mb-4">
            <div class="content-card-header">
                <div>
                    <h6 class="fw-bold mb-1">Add Role</h6>
                    <small class="text-muted">Create roles for system access.</small>
                </div>
            </div>

            <div class="content-card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="add">

                    <div class="col-md-8">
                        <label class="form-label">Role Name *</label>
                        <input type="text" name="role_name" class="form-control"
                               placeholder="Example: Store Manager" required>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add Role
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-header">
                <div>
                    <h6 class="fw-bold mb-1">System Roles</h6>
                    <small class="text-muted">Roles used by the canteen application.</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Role Name</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php if (!$roles): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No roles found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($roles as $index => $role): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="row-avatar row-avatar-role">
                                        <i class="fa-solid fa-user-shield"></i>
                                    </div>
                                    <strong><?= e($role['role_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= (int)$role['user_count'] ?></td>
                            <td>
                                <?php if ($role['status'] === 'Enable'): ?>
                                    <span class="badge badge-enable">Enable</span>
                                <?php else: ?>
                                    <span class="badge badge-disabled">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <?= $role['status'] === 'Enable' ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>