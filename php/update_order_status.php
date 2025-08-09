<?php
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requireRole('supplier');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = intval($input['order_id']);
    $status = $input['status'];
    $supplier_id = $_SESSION['user_id'];
    
    if (!in_array($status, ['accepted', 'delivered', 'cancelled'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid status'
        ]);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Update order status
        $query = "UPDATE orders o 
                  JOIN products p ON o.product_id = p.id 
                  SET o.status = :status" . ($status === 'delivered' ? ", o.delivered_date = NOW()" : "") . "
                  WHERE o.id = :order_id AND p.supplier_id = :supplier_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':supplier_id', $supplier_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Order status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update order status'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
