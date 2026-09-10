<?php

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Canteen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$loginUserId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

if (!$loginUserId) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| Record Wastage (With Record Type Logic)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_wastage'])) {

    $servingId = (int)($_POST['serving_id'] ?? 0);
    $wastageQty = (float)($_POST['wastage_qty'] ?? 0);
    $wastageDate = trim($_POST['wastage_date'] ?? date('Y-m-d'));
    $remarks = trim($_POST['remarks'] ?? '');
    
    // NEW: Catch Record Type (Radio Button)
    $recordType = $_POST['record_type'] ?? 'Wastage';

    // Logic: If Staff Consumption, auto-assign values. Else, get from dropdown.
    if ($recordType === 'Staff Consumption') {
        $reason = 'Staff Consumption';
        $wasteType = 'N/A';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        $wasteType = trim($_POST['waste_type'] ?? '');
    }

    if ($servingId <= 0) {
        $message = 'Invalid food serving selected.';
        $messageType = 'danger';
    } elseif ($wastageQty <= 0) {
        $message = 'Quantity must be greater than zero.';
        $messageType = 'danger';
    } elseif (empty($wastageDate)) {
        $message = 'Please select date.';
        $messageType = 'danger';
    } elseif ($recordType === 'Wastage' && empty($reason)) {
        $message = 'Please select a wastage reason.';
        $messageType = 'danger';
    } elseif ($recordType === 'Wastage' && empty($wasteType)) {
        $message = 'Please select Waste Category (Wet/Dry).';
        $messageType = 'danger';
    } else {
        try {
            $con->beginTransaction();

            $stmt = $con->prepare("
                SELECT cfs.id, cfs.transfer_id, cfs.food_id, cfs.serving_date, cfs.received_qty, cfs.served_qty, cfs.remaining_qty, fi.food_name
                FROM canteen_food_serving cfs
                INNER JOIN food_items fi ON fi.id = cfs.food_id
                WHERE cfs.id = ? FOR UPDATE
            ");
            $stmt->execute([$servingId]);
            $serving = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serving) {
                throw new Exception('Food serving record not found.');
            }

            $stmt = $con->prepare("SELECT COALESCE(SUM(wastage_qty), 0) FROM canteen_wastage WHERE serving_id = ?");
            $stmt->execute([$servingId]);
            $alreadyWasted = (float)$stmt->fetchColumn();

            $remainingAfterServing = (float)$serving['remaining_qty'];
            $availableForWastage = $remainingAfterServing - $alreadyWasted;

            if ($availableForWastage < 0) {
                $availableForWastage = 0;
            }

            if ($wastageQty > $availableForWastage) {
                throw new Exception('Quantity cannot be greater than available remaining quantity. Available: ' . number_format($availableForWastage, 2));
            }

            $stmt = $con->prepare("
                INSERT INTO canteen_wastage 
                (food_id, serving_id, wastage_date, wastage_qty, reason, waste_type, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $serving['food_id'],
                $servingId,
                $wastageDate,
                $wastageQty,
                $reason !== '' ? $reason : null,
                $wasteType,
                $remarks !== '' ? $remarks : null,
                $loginUserId
            ]);

            $con->commit();
            $message = 'Record saved successfully.';
            $messageType = 'success';

        } catch (Throwable $e) {
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/* Fetch Serving Records Available */
$stmt = $con->query("
    SELECT cfs.id, cfs.transfer_id, cfs.food_id, cfs.serving_date, cfs.received_qty, cfs.served_qty, cfs.remaining_qty,
           fi.food_code, fi.food_name, fi.unit,
           COALESCE((SELECT SUM(cw.wastage_qty) FROM canteen_wastage cw WHERE cw.serving_id = cfs.id), 0) AS total_wastage,
           (cfs.remaining_qty - COALESCE((SELECT SUM(cw2.wastage_qty) FROM canteen_wastage cw2 WHERE cw2.serving_id = cfs.id), 0)) AS available_wastage
    FROM canteen_food_serving cfs
    INNER JOIN food_items fi ON fi.id = cfs.food_id
    WHERE cfs.status = 'Closed'
    ORDER BY cfs.serving_date DESC, cfs.id DESC
");
$servings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Wastage History */
$stmt = $con->query("
    SELECT cw.id, cw.wastage_date, cw.wastage_qty, cw.reason, cw.waste_type, cw.remarks, cw.created_at,
           fi.food_code, fi.food_name, fi.unit, cfs.serving_date, u.employee_name AS recorded_by_name
    FROM canteen_wastage cw
    INNER JOIN food_items fi ON fi.id = cw.food_id
    LEFT JOIN canteen_food_serving cfs ON cfs.id = cw.serving_id
    LEFT JOIN users u ON u.id = cw.recorded_by
    ORDER BY cw.wastage_date DESC, cw.id DESC
");
$wastageHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>