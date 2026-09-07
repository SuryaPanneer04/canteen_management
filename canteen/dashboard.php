<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$role = $_SESSION['role_name'] ?? '';

$isAdmin = ($role === 'Super Admin');
$isStore = ($role === 'Store');
$isPurchase = ($role === 'Purchase');
$isKitchen = ($role === 'Kitchen');
$isCanteen = ($role === 'Canteen');

?>

<body>
    <h1>THIS IS THE CANTEEN PAGE </h1>
    <a
            class="nav-link"
            href="<?= $isAdmin ? '../logout.php' : '../logout.php' ?>"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>
                Logout
            </span>

        </a><br>

    <a href="food_receiving.php">Food receiving </a>
</body>
</html>