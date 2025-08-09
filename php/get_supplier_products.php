<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    // Get supplier ID from request
    $supplier_id = $_GET['supplier_id'] ?? null;
    
    if (!$supplier_id) {
        throw new Exception('Supplier ID is required');
    }
    
    // Validate supplier ID
    if (!is_numeric($supplier_id)) {
        throw new Exception('Invalid supplier ID');
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get products for the specific supplier
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.stock,
            p.unit,
            p.image_url,
            p.is_active,
            p.created_at,
            u.business_name as supplier_name
        FROM products p 
        JOIN users u ON p.supplier_id = u.id 
        WHERE p.supplier_id = ? 
        AND p.is_active = 1
        ORDER BY p.name ASC
    ");
    
    $stmt->execute([$supplier_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'supplier_id' => $supplier_id
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_supplier_products.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'products' => []
    ]);
}
?>
