<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$role = $_SESSION['role_name'] ?? '';

$isAdmin = ($role === 'Super Admin');
$isStore = ($role === 'Store');
$isPurchase = ($role === 'Purchase');
$isKitchen = ($role === 'Kitchen');
$isCanteen = ($role === 'Canteen');

?>
<style>
/* Premium MNC Corporate Theme - Subtle Leaf, No Wave, High-End Active State */
/* Adjusted main content margin to match the new wider sidebar */
@media (min-width: 992px) {
    .main-content { margin-left: 245px !important; }
}

#sidebar {
    background-color: #082F63 !important; /* Exact color requested */
    width: 245px !important; /* Increased width to the right */
    /* Subtle Leaf Watermarks instead of wave */
    background-image: 
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' fill-opacity='0.03' d='M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z'/%3E%3C/svg%3E"),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' fill-opacity='0.04' d='M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z'/%3E%3C/svg%3E");
    background-position: top 5% right -30px, bottom 10% left -40px;
    background-repeat: no-repeat;
    background-size: 180px, 220px;
    border-right: none;
    color: #fff;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* --- PREMIUM BRAND LOGO SECTION --- */
#sidebar .brand { 
    padding: 25px 15px 20px; /* Reduced left padding to match menu */
    border-bottom: 1px solid rgba(255,255,255,0.06); 
    display: flex; 
    align-items: center; 
    gap: 14px; /* Better spacing between icon and text */
}
#sidebar .brand-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important; /* Rich gradient */
    color: #ffffff !important;
    border-radius: 14px; /* Softer radius */
    width: 48px; height: 48px; /* Slightly larger, premium feel */
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    /* 3D Glass Glow Effect */
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.4), inset 0 2px 4px rgba(255,255,255,0.25); 
    border: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
#sidebar .brand-title { color: #fff; font-weight: 800; font-size: 20px; letter-spacing: 0.5px; line-height: 1.2;}
#sidebar .brand small { color: #94a3b8; font-size: 11px; font-weight: 500; letter-spacing: 0.5px;}

/* Hide Ugly Scrollbar but keep it scrollable */
#sidebar {
    overflow-y: auto;
    scrollbar-width: none; /* Firefox */
}
#sidebar::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

/* --- LEFT ALIGNED MENU SECTION --- */
#sidebar .nav-label {
    color: #7b93af; font-size: 11px; font-weight: 800; letter-spacing: 1.5px;
    margin: 10px 14px 4px; /* Reduced gap, pushed further left */
    text-transform: uppercase;
    text-align: left; 
    display: block;
}

#sidebar .nav-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    text-align: left;
    color: #cbd5e1; 
    border-radius: 10px; 
    margin: 1px 10px; /* Pushed left and reduced top/bottom gap */
    padding: 9px 12px; /* Tighter padding for compact look */
    font-weight: 600; 
    font-size: 14.5px; 
    transition: all 0.3s ease;
    text-decoration: none;
    white-space: nowrap; 
}
#sidebar .nav-link i { 
    color: #94a3b8; 
    font-size: 16px; 
    width: 28px; /* Tighter spacing between icon and text */
    text-align: left;
    transition: all 0.3s ease;
}
#sidebar .nav-link:hover { 
    background: rgba(255,255,255,0.05); 
    color: #fff;
}
#sidebar .nav-link:hover i { color: #fff; transform: scale(1.05); }

/* EXACT Premium MNC Active State (Like your image) */
#sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.1) !important; /* Soft highlight box */
    color: #ffffff !important; 
    font-weight: 700;
    border-left: 5px solid #4ea8de; /* The thick rounded light blue left border */
    border-radius: 10px; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
#sidebar .nav-link.active i { color: #4ea8de !important; }
</style>

<aside class="sidebar" id="sidebar">

    <!-- =========================================================
         BRAND
    ========================================================== -->

    <div class="brand">

        <div class="brand-icon">
            <i class="fa-solid fa-store"></i> <!-- Exact Store Logo -->
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
                href="../admin/users.php"
            >

                <i class="fa-solid fa-users"></i>

                <span>
                    User Management
                </span>

            </a>

            <a 
        class="nav-link <?= $currentPage === 'price_master.php' ? 'active' : '' ?>" 
        href="../admin/price_master.php"
    >

        <i class="fa-solid fa-tags"></i>
        
        <span>
            Price Master
        </span>

    </a>
    <a 
        class="nav-link <?= $currentPage === 'invoice_approvals.php' ? 'active' : '' ?>" 
        href="../admin/invoice_approvals.php"
    >

        <i class="fa-solid fa-file-invoice-dollar"></i>
        
        <span>
            Invoice Approvals
        </span>

          <a 
        class="nav-link <?= $currentPage === 'canteen_report.php' ? 'active' : '' ?>" 
        href="../admin/canteen_report.php"
    >

        <i class="fa-solid fa-chart-pie"></i>
        
        <span>
            Canteen Report
        </span>

            <a
                class="nav-link <?= $currentPage === 'roles.php' ? 'active' : '' ?>"
                href="../admin/roles.php"
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
                <a href="../store/stock.php" class="nav-link">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Stock</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="../store/materials.php" class="nav-link">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Materials</span>
                </a>
            </li>

             <li class="nav-item">
                <a href="../store/categories.php" class="nav-link">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Categories</span>
                </a>
            </li>

           <!-- <li class="nav-item">
                <a href="../store/stock_inward.php" class="nav-link">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Stock Inward</span>
                </a>
            </li>-->

           <!-- <?php if (!$isAdmin): ?>
            <li class="nav-item">
                <a href="../store/stock_issue.php" class="nav-link">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Stock Issue</span>
                </a>
            </li> -->

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

        <?php endif; ?>


        <!-- =====================================================
             PURCHASE
        ====================================================== -->
 
       <?php if (in_array($_SESSION['role_name'] ?? '', ['Purchase', 'Super Admin'], true)): ?>

            <div class="nav-label">
                PURCHASE
            </div>

            <?php if (!$isAdmin): ?>
            <li class="nav-item">
                <!-- Added active class logic for requests.php -->
                <a href="../purchase/requests.php" class="nav-link <?= $currentPage === 'requests.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-circle-check"></i>
                    <span>Purchase Requests</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <!-- Added active class logic for suppliers.php -->
                <a href="../purchase/suppliers.php" class="nav-link <?= $currentPage === 'suppliers.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-truck-field"></i>
                    <span>Suppliers</span>
                </a>
            </li>

            <li class="nav-item">
                <!-- Added active class logic for purchase_orders.php and view page -->
                <a href="../purchase/purchase_orders.php" class="nav-link <?= ($currentPage === 'purchase_orders.php' || $currentPage === 'purchase_order_view.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>

        <?php endif; ?>


        <!-- =====================================================
             KITCHEN
        ====================================================== -->

        
 

        <?php if ($isAdmin || $isKitchen): ?>

            <div class="nav-label">
                KITCHEN
            </div>

           <!-- <li class="nav-item">

                <a
                    href="dashboard.php"
                    class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
                >
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Kitchen Dashboard</span>
                </a>

            </li> -->


            <!-- FOOD MENU & RECIPE -->

            <li class="nav-item">

                <a
                    href="../kitchen/food_menu.php"
                    class="nav-link <?= $currentPage === 'food_menu.php' ? 'active' : '' ?>"
                >
                    <i class="fa-solid fa-bowl-food"></i>
                    <span>Food Menu & Recipe</span>
                </a>

            </li>


            <?php if (!$isAdmin): ?>
            <li class="nav-item">
                <a href="daily_cooking_plan.php" class="nav-link <?= $currentPage === 'daily_cooking_plan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Daily Cooking Plan</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="chef_approval.php" class="nav-link <?= $currentPage === 'chef_approval.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Chef Approval</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="food_preparation.php" class="nav-link <?= $currentPage === 'food_preparation.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-fire-burner"></i>
                    <span>Food Preparation</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="issue_history.php" class="nav-link <?= $currentPage === 'issue_history.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Issue History</span>
                </a>
            </li>
        <?php endif; ?>

            <?php endif; ?>



        <!-- =====================================================
             CANTEEN
        ====================================================== -->

        <?php if ($isCanteen): ?>

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
                href="<?= $isAdmin ? '../canteen/food_receiving.php' : 'food_receiving.php' ?>"
            >

                <i class="fa-solid fa-store"></i>

                <span>
                    Food Receiving
                </span>

            </a>


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../canteen/food_serving.php' : 'food_serving.php' ?>"
            >

                <i class="fa-solid fa-bowl-food"></i>

                <span>
                    Food Serving
                </span>

            </a>


            <a
                class="nav-link"
                href="<?= $isAdmin ? '../canteen/wastage.php' : 'wastage.php' ?>"
            >

                <i class="fa-solid fa-receipt"></i>

                <span>
                    Wastage
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