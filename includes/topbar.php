<header class="topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
            <div class="topbar-subtitle">Canteen</div>
        </div>
    </div>

    <div class="dropdown">
        <button class="btn user-menu dropdown-toggle" data-bs-toggle="dropdown" type="button">
            <span class="avatar"><?= e(strtoupper(substr($_SESSION['employee_name'] ?? 'U', 0, 1))) ?></span>
            <span class="d-none d-sm-inline">
                <?= e($_SESSION['employee_name'] ?? 'User') ?>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
                <span class="dropdown-item-text">
                    <strong><?= e($_SESSION['employee_name'] ?? '') ?></strong><br>
                    <small class="text-muted"><?= e($_SESSION['role_name'] ?? '') ?></small>
                </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="../logout.php">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                </a>
            </li>
        </ul>
    </div>
</header>
