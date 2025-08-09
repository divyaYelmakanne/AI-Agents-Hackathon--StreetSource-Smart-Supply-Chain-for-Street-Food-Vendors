<?php
// Simple, clean place_order.php for testing
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
ob_end_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }
    
    // Extract order data
    $product_id = intval($data['product_id'] ?? 0);
    $supplier_id = intval($data['supplier_id'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $total_amount = floatval($data['total_amount'] ?? 0);
    
    // Basic validation
    if ($product_id <= 0 || $supplier_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order data']);
        exit;
    }
    
    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=streetsource",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // Check product exists
    $stmt = $pdo->prepare("SELECT price, stock, name FROM products WHERE id = ? AND supplier_id = ? AND is_active = 1");
    $stmt->execute([$product_id, $supplier_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    if ($product['stock'] < $quantity) {
        echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            vendor_id, supplier_id, product_id, quantity, total_price,
            delivery_address, vendor_latitude, vendor_longitude,
            delivery_option, delivery_datetime, special_instructions,
            status, order_date
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            'pending', NOW()
        )
    ");
    
    $stmt->execute([
        1, // Test vendor ID
        $supplier_id,
        $product_id,
        $quantity,
        $total_amount,
        $data['delivery_address'] ?? '',
        $data['delivery_latitude'] ?? 0,
        $data['delivery_longitude'] ?? 0,
        $data['delivery_option'] ?? 'asap',
        $data['delivery_datetime'] ?? null,
        $data['special_instructions'] ?? ''
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Update stock
    $new_stock = $product['stock'] - $quantity;
    $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->execute([$new_stock, $product_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order placed successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
