<?php
// Ensure no output before JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// For testing, allow access without authentication
// Comment out this block when authentication is working properly
$testing_mode = true;

if (!$testing_mode) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
}

if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

try {
    // Direct database connection without including db.php
    $host = 'localhost';
    $db_name = 'streetsource';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name",
        $username,
        $password,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    $product_id = (int)$_GET['product_id'];
    
    // Get product details with supplier information
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
    
    // Separate product and supplier data
    $productData = [
        'id' => $product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'unit' => $product['unit'],
        'stock' => $product['stock'],
        'image_url' => $product['image_url'],
        'category' => $product['category'],
        'is_active' => $product['is_active']
    ];
    
    $supplierData = [
        'id' => $product['supplier_id'],
        'name' => $product['supplier_name'],
        'phone' => $product['phone'],
        'latitude' => $product['supplier_lat'],
        'longitude' => $product['supplier_lng']
    ];
    
    echo json_encode([
        'success' => true,
        'product' => $productData,
        'supplier' => $supplierData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
