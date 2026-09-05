<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'User Management';
$error = null;
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $employeeCode = trim($_POST['employee_code'] ?? '');
        $employeeName = trim($_POST['employee_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($employeeName === '' || $email === '' || $password === '' || $roleId <= 0) {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must contain at least 6 characters.';
        } else {
            try {
                $check = $con->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $check->execute([$email]);

                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Email already exists.';
                } else {
                    $roleCheck = $con->prepare("SELECT COUNT(*) FROM roles WHERE id = ? AND status = 'Enable'");
                    $roleCheck->execute([$roleId]);

                    if ((int)$roleCheck->fetchColumn() === 0) {
                        $error = 'Selected role is invalid or disabled.';
                    } else {
                        $stmt = $con->prepare("
                            INSERT INTO users
                                (role_id, employee_code, employee_name, email, password, status)
                            VALUES (?, ?, ?, ?, ?, 'Enable')
                        ");

                        $stmt->execute([
                            $roleId,
                            $employeeCode !== '' ? $employeeCode : null,
                            $employeeName,
                            $email,
                            password_hash($password, PASSWORD_DEFAULT)
                        ]);

                        flash('success', 'User added successfully.');
                        header('Location: users.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $error = 'Unable to add user. Please check the entered data.';
            }
        }   
    }

    if ($action === 'edit') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $employeeName = trim($_POST['employee_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');

        if ($userId <= 0 || $employeeName === '' || $email === '' || $roleId <= 0) {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== '' && strlen($password) < 6) {
            $error = 'Password must contain at least 6 characters.';
        } else {
            try {
                // Check whether email belongs to another user
                $check = $con->prepare("
                    SELECT COUNT(*)
                    FROM users
                    WHERE email = ? AND id != ?
                ");
                $check->execute([$email, $userId]);

                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Email already exists.';
                } else {
                    // Check role
                    $roleCheck = $con->prepare("
                        SELECT COUNT(*)
                        FROM roles
                        WHERE id = ? AND status = 'Enable'
                    ");
                    $roleCheck->execute([$roleId]);

                if ((int)$roleCheck->fetchColumn() === 0) {
                    $error = 'Selected role is invalid or disabled.';
                } else {

                    if ($password !== '') {
                        $stmt = $con->prepare("
                            UPDATE users
                            SET employee_name = ?,
                                email = ?,
                                role_id = ?,
                                password = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $employeeName,
                            $email,
                            $roleId,
                            password_hash($password, PASSWORD_DEFAULT),
                            $userId
                        ]);
                    } else {
                        $stmt = $con->prepare("
                            UPDATE users
                            SET employee_name = ?,
                                email = ?,
                                role_id = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $employeeName,
                            $email,
                            $roleId,
                            $userId
                        ]);
                    }

                    flash('success', 'User updated successfully.');
                    header('Location: users.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Unable to update user. Please check the entered data.';
        }
    }
}

    if ($action === 'toggle') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot disable your own logged-in account.';
        } else {
            $stmt = $con->prepare("
                UPDATE users
                SET status = CASE
                    WHEN status = 'Enable' THEN 'Disabled'
                    ELSE 'Enable'
                END
                WHERE id = ?
            ");
            $stmt->execute([$userId]);

            flash('success', 'User status updated.');
            header('Location: users.php');
            exit;
        }
    }
}

$roles = $con->query("
    SELECT id, role_name
    FROM roles
    WHERE status = 'Enable'
    ORDER BY role_name
")->fetchAll();

$users = $con->query("
    SELECT u.id, u.employee_code, u.employee_name, u.email,
           r.role_name, u.status, u.created_at
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    ORDER BY u.id DESC
")->fetchAll();

$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['status'] === 'Enable'));
$disabledUsers = $totalUsers - $activeUsers;
$todayUsers = count(array_filter($users, fn($u) => date('Y-m-d', strtotime($u['created_at'])) === date('Y-m-d')));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">

        <div class="mb-4">
            <h4 class="fw-bold mb-1">User Management</h4>
            <div class="text-muted">Create login accounts and manage system access.</div>
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
                            <div class="stat-label">Disabled Users</div>
                            <div class="stat-value"><?= $disabledUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-amber">
                            <i class="fa-solid fa-user-slash"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Added Today</div>
                            <div class="stat-value"><?= $todayUsers ?></div>
                        </div>
                        <div class="stat-icon stat-icon-purple">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="content-card mb-4">
            <div class="content-card-header">
                <div>
                    <h6 class="fw-bold mb-1">Add User</h6>
                    <small class="text-muted">Create a login account and assign a system role.</small>
                </div>
            </div>

            <div class="content-card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add">

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Employee Code</label>
                            <input type="text" name="employee_code" class="form-control"
                                   placeholder="EMP001">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Employee Name *</label>
                            <input type="text" name="employee_name" class="form-control"
                                   required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control"
                                   required>
                        </div>

                        <div class="col-md-4"> 
                            <label class="form-label">Password *</label> 
                            <div class="input-group"> 
                                <input type="password" name="password" id="password" class="form-control" minlength="6" required> 
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword" onclick="togglePasswordVisibility()"> 
                                    <i class="fa-solid fa-eye" id="passwordIcon"></i> 
                                </button> 
                            </div> 
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Role *</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">--- Select Role ---</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>">
                                        <?= e($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="fa-solid fa-user-plus me-2"></i>
                                Add User
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-header">
                <div>
                    <h6 class="fw-bold mb-1">Users</h6>
                    <small class="text-muted"><?= count($users) ?> user(s) found.</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="row-avatar">
                                        <?= strtoupper(substr($user['employee_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($user['employee_name']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= e($user['employee_code'] ?? '') ?>
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
                            <td><?= e(date('d-m-Y H:i', strtotime($user['created_at']))) ?></td>
                            <td class="text-end">

                                <!-- Edit Button -->
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editUserModal<?= (int)$user['id'] ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    Edit
                                </button>

                                <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>

                                    <!-- Enable / Disable Button -->
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            <?= $user['status'] === 'Enable' ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <span class="text-muted small">Current user</span>

                                <?php endif; ?>

                            </td>
                        </tr>

                        <!-- Edit User Modal -->
                        <div class="modal fade edit-user-modal"
                            id="editUserModal<?= (int)$user['id'] ?>"
                            tabindex="-1"
                            aria-hidden="true">

                            <div class="modal-dialog modal-lg modal-dialog-centered">

                                <div class="modal-content">

                                    <div class="modal-header">
                                        <div>
                                            <div class="modal-title-text">
                                                <i class="fa-solid fa-user-pen me-2"></i>
                                                Edit User Account
                                            </div>
                                            <div class="modal-subtitle-text">
                                                Update account details and access role
                                            </div>
                                        </div>

                                        <button type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                                aria-label="Close">
                                        </button>
                                    </div>

                                    <form method="post">

                                        <div class="edit-user-identity">
                                            <div class="row-avatar">
                                                <?= strtoupper(substr($user['employee_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="edit-user-identity-name"><?= e($user['employee_name']) ?></div>
                                                <div class="edit-user-identity-meta">
                                                    <?= e($user['employee_code'] !== null && $user['employee_code'] !== '' ? $user['employee_code'] : 'No employee code') ?>
                                                    &middot;
                                                    <?php if ($user['status'] === 'Enable'): ?>
                                                        <span class="badge badge-enable">Enable</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-disabled">Disabled</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-body">

                                            <input type="hidden"
                                                name="action"
                                                value="edit">

                                            <input type="hidden"
                                                name="user_id"
                                                value="<?= (int)$user['id'] ?>">

                                            <div class="edit-user-section">
                                                <div class="edit-user-section-label">Account Details</div>

                                                <div class="row g-3">

                                                    <!-- Employee Code -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">
                                                            Employee Code
                                                        </label>

                                                        <input type="text"
                                                            class="form-control"
                                                            value="<?= e($user['employee_code'] ?? '') ?>"
                                                            readonly
                                                            disabled>
                                                    </div>

                                                    <!-- Role -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">
                                                            Role *
                                                        </label>

                                                        <select name="role_id"
                                                                class="form-select"
                                                                required>

                                                            <?php foreach ($roles as $role): ?>

                                                                <option
                                                                    value="<?= (int)$role['id'] ?>"
                                                                    <?= $role['role_name'] === $user['role_name'] ? 'selected' : '' ?>>

                                                                    <?= e($role['role_name']) ?>

                                                                </option>

                                                            <?php endforeach; ?>

                                                        </select>
                                                    </div>

                                                    <!-- Employee Name -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">
                                                            Employee Name *
                                                        </label>

                                                        <input type="text"
                                                            name="employee_name"
                                                            class="form-control"
                                                            value="<?= e($user['employee_name']) ?>"
                                                            required>
                                                    </div>

                                                    <!-- Email -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">
                                                            Email *
                                                        </label>

                                                        <input type="email"
                                                            name="email"
                                                            class="form-control"
                                                            value="<?= e($user['email']) ?>"
                                                            required>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="edit-user-section mb-0">
                                                <div class="edit-user-section-label">Security</div>

                                                <div class="edit-user-password-box">
                                                    <label class="form-label">
                                                        New Password
                                                    </label>

                                                    <div class="input-group">

                                                        <input type="password"
                                                            name="password"
                                                            id="editPassword<?= (int)$user['id'] ?>"
                                                            class="form-control"
                                                            minlength="6"
                                                            placeholder="Leave blank to keep current password">

                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                onclick="toggleEditPassword(<?= (int)$user['id'] ?>)">

                                                            <i class="fa-solid fa-eye"
                                                            id="editPasswordIcon<?= (int)$user['id'] ?>">
                                                            </i>

                                                        </button>

                                                    </div>

                                                    <div class="form-text text-muted mt-2">
                                                        <i class="fa-solid fa-circle-info me-1"></i>
                                                        Leave blank if you don't want to change the password.
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="modal-footer">

                                            <button type="button"
                                                    class="btn btn-secondary"
                                                    data-bs-dismiss="modal">
                                                Cancel
                                            </button>

                                            <button type="submit"
                                                    class="btn btn-primary">

                                                <i class="fa-solid fa-save me-2"></i>
                                                Save Changes

                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
function togglePasswordVisibility() {
    const password = document.getElementById('password');
    const icon = document.getElementById('passwordIcon');

    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
<script>
    function toggleEditPassword(userId) {

        const password = document.getElementById('editPassword' + userId);
        const icon = document.getElementById('editPasswordIcon' + userId);

        if (password.type === 'password') {

            password.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

        } else {

            password.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>