<?php
// Simple version without authentication for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get product ID
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=streetsource",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // Get product details
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name as supplier_name,
            u.phone,
            u.latitude as supplier_lat,
            u.longitude as supplier_lng
        FROM products p
        JOIN users u ON p.supplier_id = u.id
        WHERE p.id = :product_id AND p.is_active = 1
    ");
    
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    // Return structured data
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'unit' => $product['unit'],
            'stock' => $product['stock'],
            'image_url' => $product['image_url'],
            'category' => $product['category']
        ],
        'supplier' => [
            'id' => $product['supplier_id'],
            'name' => $product['supplier_name'],
            'phone' => $product['phone'],
            'latitude' => $product['supplier_lat'],
            'longitude' => $product['supplier_lng']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
