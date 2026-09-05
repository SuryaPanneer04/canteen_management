<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $con->prepare("
            SELECT
                u.id,
                u.employee_code,
                u.employee_name,
                u.email,
                u.password,
                u.role_id,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.email = ?
              AND u.status = 'Enable'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['employee_code'] = $user['employee_code'];
            $_SESSION['employee_name'] = $user['employee_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            switch ($user['role_name']) {

                case 'Super Admin':
                    header('Location: admin/dashboard.php');
                    break;

                case 'Store':
                    header('Location: store/dashboard.php');
                    break;

                case 'Purchase':
                    header('Location: purchase/dashboard.php');
                    break;

                case 'Kitchen':
                    header('Location: kitchen/dashboard.php');
                    break;

                case 'Canteen':
                    header('Location: canteen/dashboard.php');
                    break;

                default:
                    header('Location: index.php');
                    break;
            }
            exit;
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Proton Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<div class="auth-page">

    <div class="auth-visual">
        <div class="auth-visual-overlay"></div>
        <div class="auth-visual-text">
            <h2>Efficient Management.<br>Exceptional Service.</h2>
            <p>Welcome to Proton Canteen, your comprehensive platform for managing daily operations.</p>
        </div>
    </div>

    <div class="auth-panel">
        <div class="auth-card">

            <div class="auth-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>

            <h1 class="auth-title">Proton Canteen</h1>
            <p class="auth-subtitle">Welcome Back. Please sign in to your account.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="auth-input-group">
                        <i class="fa-solid fa-envelope auth-input-icon"></i>
                        <input
                            type="email"
                            name="email"
                            class="form-control auth-input"
                            placeholder="admin@proton.com"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <div class="auth-row">
                        <label class="form-label">Password</label>
                        <a href="forgot-password.php" class="auth-link">Forgot Password?</a>
                    </div>
                    <div class="auth-input-group">
                        <i class="fa-solid fa-lock auth-input-icon"></i>
                        <input
                            type="password"
                            name="password"
                            id="authPassword"
                            class="form-control auth-input"
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" class="auth-input-toggle" id="authPasswordToggle" aria-label="Show password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                    <label class="form-check-label auth-remember" for="rememberMe">Remember me for 30 days</label>
                </div>

                <button class="btn w-100 auth-btn" type="submit">
                    Sign In <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>

            </form>

        </div>

        <div class="auth-footer">
            <div>&copy; 2026 CanteenPro Systems. All rights reserved.</div>
            <div class="auth-footer-links">
                <a href="privacy-policy.php">Privacy Policy</a>
                <span>&middot;</span>
                <a href="terms-of-service.php">Terms of Service</a>
                <span>&middot;</span>
                <a href="support.php">Support</a>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('authPasswordToggle').addEventListener('click', function () {
    var input = document.getElementById('authPassword');
    var icon = this.querySelector('i');
    var showing = input.type === 'text';

    input.type = showing ? 'password' : 'text';
    icon.classList.toggle('fa-eye', showing);
    icon.classList.toggle('fa-eye-slash', !showing);
    this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
});
</script>

</body>
</html>