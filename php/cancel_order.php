<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['order_id']) || !is_numeric($data['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $order_id = (int)$data['order_id'];
    $vendor_id = $_SESSION['user_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if order exists and belongs to this vendor
    $stmt = $pdo->prepare("SELECT o.*, p.stock FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = :order_id AND o.vendor_id = :vendor_id");
    $stmt->execute(['order_id' => $order_id, 'vendor_id' => $vendor_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Check if order can be cancelled
    if ($order['status'] !== 'pending') {
        echo json_encode(['success' => false, 'error' => 'Only pending orders can be cancelled']);
        exit;
    }
    
    // Update order status to cancelled
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :order_id");
    $stmt->execute(['order_id' => $order_id]);
    
    // Restore product stock
    $new_stock = $order['stock'] + $order['quantity'];
    $stmt = $pdo->prepare("UPDATE products SET stock = :stock WHERE id = :product_id");
    $stmt->execute(['stock' => $new_stock, 'product_id' => $order['product_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
