<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role_name'] ?? '';
    
    switch ($role) {
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
            session_destroy();
            header('Location: index.php');
            break;
    }
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
    <!-- Linking our new custom external CSS -->
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>

<div class="auth-page">

    <!-- Left Visual Side -->
    <div class="auth-visual">
        <div class="auth-visual-overlay"></div>
        <div class="auth-visual-text">
            <div class="visual-logo">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div>
                <h2 class="visual-title">Proton Canteen</h2>
                <p class="visual-subtitle">Canteen Management System</p>
                <p class="visual-desc">Efficient Management. Exceptional Service.</p>
            </div>
        </div>
    </div>

    <!-- Right Panel Side -->
    <div class="auth-panel">
        
        <div class="top-right-decor">
            GOOD FOOD<br>
            GREAT PEOPLE<br>
            <span class="highlight">BRIGHTER TOMORROW</span>
        </div>

        <div class="auth-card">
            
            <div class="card-logo">
                <i class="fa-solid fa-utensils"></i>
            </div>

            <h1 class="auth-title">Proton Canteen</h1>
            <p class="auth-subtitle">Canteen Management System</p>

            <div class="greeting-title">Welcome Back!</div>
            <div class="greeting-sub">Please sign in to your account.</div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 mb-3" style="font-size: 13px;">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                
                <div class="auth-input-group">
                    <i class="fa-regular fa-envelope auth-input-icon"></i>
                    <input
                        type="email"
                        name="email"
                        class="form-control auth-input"
                        placeholder="admin@proton.com"
                        required
                        autofocus
                    >
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

                <div class="auth-actions">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" checked>
                        <label class="form-check-label" for="rememberMe">Remember me for 30 days</label>
                    </div>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>

                <button class="btn w-100 auth-btn" type="submit">
                    Sign In <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
                
                <div class="divider">Need help logging in?</div>

                <div class="text-center mt-3 mb-2" style="font-size: 13.5px; font-weight: 600; color: #475569;">
                    <i class="fa-solid fa-headset me-2" style="color: #00c652; font-size: 15px;"></i> 
                    Contact with Administration
                </div>

                <div class="bottom-quote">
                    "Good Food Fuels Great Work"
                    <div class="quote-line"></div>
                </div>

            </form>

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