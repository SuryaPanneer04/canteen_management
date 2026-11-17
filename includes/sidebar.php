<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role_name'] ?? '';
$isAdmin = ($role === 'Super Admin');
$isStore = ($role === 'Store');
$isPurchase = ($role === 'Purchase');
$isKitchen = ($role === 'Kitchen');
$isCanteen = ($role === 'Canteen');

$menus = [];
if ($isKitchen) {
    $menus = [
        ['label'=>'Food Menu & Recipe','icon'=>'fa-utensils','href'=>'../kitchen/food_menu.php'],
        ['label'=>'Daily Cooking Plan','icon'=>'fa-calendar-days','href'=>'../kitchen/daily_cooking_plan.php'],
        ['label'=>'Chef Approval','icon'=>'fa-circle-check','href'=>'../kitchen/chef_approval.php'],
        ['label'=>'Food Preparation','icon'=>'fa-kitchen-set','href'=>'../kitchen/food_preparation.php'],
        ['label'=>'Issue History','icon'=>'fa-clock-rotate-left','href'=>'../kitchen/issue_history.php'],
    ];
} elseif ($isStore) {
    $menus = [
        ['label'=>'Stock','icon'=>'fa-chart-column','href'=>'../store/stock.php'],
        ['label'=>'Items','icon'=>'fa-box-open','href'=>'../store/materials.php'],
        ['label'=>'Categories','icon'=>'fa-solid fa-layer-group','href'=>'../store/categories.php'],
        ['label'=>'Kitchen Requests','icon'=>'fa-solid fa-utensils','href'=>'../store/kitchen_requests.php'],
        ['label'=>'Purchase Requests','icon'=>'fa-solid fa-cart-plus','href'=>'../store/purchase_requests.php'],
        ['label'=>'Item Receive','icon'=>'fa-solid fa-box-open','href'=>'../store/purchase_order_receiving.php'],
        ['label'=>'Item History','icon'=>'fa-clock-rotate-left','href'=>'../store/item_history.php'],
    ];
} elseif ($isPurchase) {
    $menus = [
        ['label'=>'Purchase Order','icon'=>'fa-cart-shopping','href'=>'../purchase/purchase_orders.php'],
        ['label'=>'Supplier Master','icon'=>'fa-users','href'=>'../purchase/suppliers.php'],
        //['label'=>'GRN','icon'=>'fa-box-open','href'=>'../store/purchase_order_receiving.php'],
        ['label'=>'Purchase Report','icon'=>'fa-chart-line','href'=>'../purchase/requests.php'],
    ];
} elseif ($isCanteen) {
    $menus = [
        ['label'=>'Food Receiving','icon'=>'fa-truck','href'=>'../canteen/food_receiving.php'],
        ['label'=>'Food Serving','icon'=>'fa-utensils','href'=>'../canteen/food_serving.php'],
        ['label'=>'Wastage','icon'=>'fa-trash-can','href'=>'../canteen/wastage.php'],
    ];
} elseif ($isAdmin) {
    $menus = [
        ['label'=>'User Management','icon'=>'fa-users','href'=>'../admin/users.php'],
        ['label'=>'Role Management','icon'=>'fa-user-shield','href'=>'../admin/roles.php'],
        ['label'=>'Price Master','icon'=>'fa-tags','href'=>'../admin/price_master.php'],
        ['label'=>'Invoice Approvals','icon'=>'fa-file-invoice-dollar','href'=>'../admin/invoice_approvals.php'],
        ['label'=>'Canteen Report','icon'=>'fa-chart-pie','href'=>'../admin/canteen_report.php'],
        ['label'=>'PO Approvals','icon'=>'fa-clipboard-check','href'=>'../purchase/purchase_orders.php'],
    ];
}

$brandIcon = $isAdmin ? 'fa-shield-halved' : ($isStore ? 'fa-warehouse' : ($isPurchase ? 'fa-cart-shopping' : ($isCanteen ? 'fa-utensils' : 'fa-kitchen-set')));
$roleLabel = $isAdmin ? 'Admin' : ($role ?: 'Kitchen');
$tagline = $isAdmin ? 'Manage Control Grow' : ($isStore ? 'Stock Today / Service Tomorrow' : ($isPurchase ? 'Better Purchase / Better Business' : ($isCanteen ? 'Healthy Food / Healthy You' : 'Good Food & Happy People')));
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="fa-solid <?= e($brandIcon) ?>"></i></div>
        <div class="brand-copy">
            <div class="brand-title"><?= e($roleLabel) ?></div>
            <small>Management System</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">MAIN</div>
        <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a>

        <?php if ($menus): ?>
            <div class="nav-label"><?= e($roleLabel) ?></div>
            <?php foreach ($menus as $menu): ?>
                <a class="nav-link <?= $currentPage === basename($menu['href']) ? 'active' : '' ?>" href="<?= e($menu['href']) ?>">
                    <i class="fa-solid <?= e($menu['icon']) ?>"></i><span><?= e($menu['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="nav-divider"></div>
        <div class="nav-label">SYSTEM</div>
        <a class="nav-link" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-icon"><i class="fa-solid <?= e($brandIcon) ?>"></i></div>
        <?php foreach (explode("\n", $tagline) as $line): ?><div><?= e($line) ?></div><?php endforeach; ?>
    </div>
</aside>
