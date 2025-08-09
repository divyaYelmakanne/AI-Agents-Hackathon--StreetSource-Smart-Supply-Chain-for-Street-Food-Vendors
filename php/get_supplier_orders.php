<?php
header('Content-Type: application/json');
include 'db.php';

try {
    // Check if user is logged in and is a supplier
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $supplier_id = $_SESSION['user_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get orders with vendor and product details
    $query = "SELECT 
                o.id,
                o.quantity,
                o.total_price,
                o.status,
                o.order_date,
                o.payment_method,
                o.delivery_option,
                o.delivery_datetime,
                o.special_instructions,
                o.notes,
                p.name as product_name,
                p.price as product_price,
                p.unit as product_unit,
                p.image_url as product_image,
                v.name as vendor_name,
                v.email as vendor_email,
                v.phone as vendor_phone,
                v.address as vendor_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users v ON o.vendor_id = v.id
              WHERE p.supplier_id = :supplier_id
              ORDER BY o.order_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching orders: ' . $e->getMessage()
    ]);
}
?>
