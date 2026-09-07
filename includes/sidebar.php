<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$role = $_SESSION['role_name'] ?? '';

$isAdmin = ($role === 'Super Admin');
$isStore = ($role === 'Store');
$isPurchase = ($role === 'Purchase');
$isKitchen = ($role === 'Kitchen');
$isCanteen = ($role === 'Canteen');

?>

<aside class="sidebar" id="sidebar">

    <!-- =========================================================
         BRAND
    ========================================================== -->

    <div class="brand">

        <div class="brand-icon">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <div>
            <div class="brand-title">
                Canteen
            </div>

            <small>
                Management System
            </small>
        </div>

    </div>


    <nav class="sidebar-nav">


        <!-- =====================================================
             MAIN
        ====================================================== -->

        <div class="nav-label">
            MAIN
        </div>


        <!-- DASHBOARD
             Everyone can see Dashboard
        -->

        <a
            class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
            href="dashboard.php"
        >

            <i class="fa-solid fa-gauge-high"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- =====================================================
             ADMINISTRATION
             SUPER ADMIN ONLY
        ====================================================== -->

        <?php if ($isAdmin): ?>

            <div class="nav-label">
                ADMINISTRATION
            </div>


            <a
                class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>"
                href="users.php"
            >

                <i class="fa-solid fa-users"></i>

                <span>
                    User Management
                </span>

            </a>


            <a
                class="nav-link <?= $currentPage === 'roles.php' ? 'active' : '' ?>"
                href="roles.php"
            >

                <i class="fa-solid fa-user-shield"></i>

                <span>
                    Role Management
                </span>

            </a>

        <?php endif; ?>


        <!-- =====================================================
             STORE
        ====================================================== -->

        <?php if (in_array($_SESSION['role_name'] ?? '', ['Store', 'Super Admin'], true)): ?>

            <div class="nav-label">
                STORE
            </div>

            <!-- <li class="nav-item">
                <a href="../store/dashboard.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Store Dashboard</span>
                </a>
            </li> -->

            <li class="nav-item">
                <a href="../store/materials.php" class="nav-link">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Materials</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/stock_inward.php" class="nav-link">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Stock Inward</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/stock_issue.php" class="nav-link">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Stock Issue</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/stock.php" class="nav-link">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Stock</span>
                </a>
            </li>

            <li>
                <a href="../store/kitchen_requests.php" class="nav-link">
                    <i class="fa-solid fa-utensils"></i>
                    <span>Kitchen Requests</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/purchase_requests.php" class="nav-link">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    <span>Purchase Requests</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/purchase_order_receiving.php" class="nav-link">
                    <i class="fa-solid fa-box-open"></i>
                    <span>PO Receiving</span>
                </a>
            </li>

        <?php endif; ?>


        <!-- =====================================================
             PURCHASE
        ====================================================== -->
 
       <?php if (in_array($_SESSION['role_name'] ?? '', ['Purchase', 'Super Admin'], true)): ?>

            <div class="nav-label">
                PURCHASE
            </div>

           <!-- <li class="nav-item">
                <a href="../purchase/dashboard.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Purchase Dashboard</span>
                </a>
            </li> -->

            <li class="nav-item">
                <a href="../purchase/requests.php" class="nav-link">
                    <i class="fa-solid fa-file-circle-check"></i>
                    <span>Purchase Requests</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../purchase/suppliers.php" class="nav-link">
                    <i class="fa-solid fa-truck-field"></i>
                    <span>Suppliers</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../purchase/purchase_orders.php" class="nav-link">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>

        <?php endif; ?>


        <!-- =====================================================
             KITCHEN
        ====================================================== -->

        <div class="nav-label">
                KITCHEN
            </div>


        <?php if ($isAdmin || $isKitchen): ?>

            

           <!-- <a
                class="nav-link"
                href="<?= $isAdmin ? '../kitchen/dashboard.php' : 'dashboard.php' ?>"
            >

                <i class="fa-solid fa-fire-burner"></i>

                <span>
                    Kitchen Dashboard
                </span>

            </a> -->


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../kitchen/material_request.php' : 'material_request.php' ?>"
            >

                <i class="fa-solid fa-cart-plus"></i>

                <span>
                    Material Request
                </span>

            </a>


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../kitchen/issue_history.php' : 'issue_history.php' ?>"
            >

                <i class="fa-solid fa-clock-rotate-left"></i>

                <span>
                    Issue History
                </span>

            </a>

            <a
                class="nav-link"
                href="<?= $isAdmin ? '../kitchen/food_preparation.php' : 'food_preparation.php' ?>"
            >

                <i class="fa-solid fa-fire-burner"></i>

                <span>
                   Food Preparation
                </span>

            </a>

        <?php endif; ?>


        <!-- =====================================================
             CANTEEN
        ====================================================== -->

        <?php if ($isAdmin || $isCanteen): ?>

            <div class="nav-label">
                CANTEEN
            </div>


           <!-- <a
                class="nav-link"
                href="<?= $isAdmin ? '../canteen/dashboard.php' : 'dashboard.php' ?>"
            >

                <i class="fa-solid fa-store"></i>

                <span>
                    Canteen Dashboard
                </span>

            </a> -->


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../canteen/menu.php' : 'menu.php' ?>"
            >

                <i class="fa-solid fa-bowl-food"></i>

                <span>
                    Menu
                </span>

            </a>


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../canteen/sales.php' : 'sales.php' ?>"
            >

                <i class="fa-solid fa-receipt"></i>

                <span>
                    Sales
                </span>

            </a>

        <?php endif; ?>


        <!-- =====================================================
             SYSTEM
        ====================================================== -->

        <div class="nav-label">
            SYSTEM
        </div>


        <a
            class="nav-link"
            href="<?= $isAdmin ? '../logout.php' : '../logout.php' ?>"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>
                Logout
            </span>

        </a>

    </nav>

</aside>