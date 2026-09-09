<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (isset($_POST['request_id'])) {
    
    $requestId = (int)$_POST['request_id'];
    
    try {
        // purchase_request_items and materials tables join pandrom
        $stmt = $con->prepare("
            SELECT 
                pri.material_id,
                pri.requested_qty,
                m.material_code,
                m.material_name,
                m.unit,
                m.unit_price
            FROM purchase_request_items pri
            INNER JOIN materials m ON m.id = pri.material_id
            WHERE pri.request_id = ?
        ");
        
        $stmt->execute([$requestId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $items]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
?>