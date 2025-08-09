<?php
header('Content-Type: application/json');
include 'db.php';

try {
    $supplier_id = intval($_GET['supplier_id'] ?? 0);
    
    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get supplier basic information
    $supplier_query = "SELECT id, name, email, phone, address, created_at FROM users WHERE id = ? AND role = 'supplier'";
    $supplier_stmt = $db->prepare($supplier_query);
    $supplier_stmt->execute([$supplier_id]);
    $supplier = $supplier_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        exit;
    }
    
    // Get delivery statistics
    $delivery_query = "SELECT COUNT(*) as total_deliveries FROM orders WHERE supplier_id = ? AND status = 'delivered'";
    $delivery_stmt = $db->prepare($delivery_query);
    $delivery_stmt->execute([$supplier_id]);
    $delivery_stats = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get product count
    $product_query = "SELECT COUNT(*) as product_count FROM products WHERE supplier_id = ? AND is_active = 1";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute([$supplier_id]);
    $product_stats = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get real reviews from the database
    $reviews_query = "SELECT r.rating, r.comment, r.created_at, u.name as vendor_name 
                     FROM reviews r 
                     JOIN users u ON r.vendor_id = u.id 
                     WHERE r.supplier_id = ? 
                     ORDER BY r.created_at DESC 
                     LIMIT 10";
    $reviews_stmt = $db->prepare($reviews_query);
    $reviews_stmt->execute([$supplier_id]);
    $reviews_data = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format reviews for response
    $reviews = [];
    foreach ($reviews_data as $review) {
        $reviews[] = [
            'vendor_name' => $review['vendor_name'],
            'rating' => intval($review['rating']),
            'comment' => $review['comment'],
            'date' => date('Y-m-d', strtotime($review['created_at']))
        ];
    }
    
    // Calculate average rating from real reviews
    if (count($reviews) > 0) {
        $total_rating = array_sum(array_column($reviews, 'rating'));
        $average_rating = $total_rating / count($reviews);
    } else {
        // If no reviews, use a default rating
        $average_rating = 4.0;
    }
    
    $profile_data = [
        'id' => $supplier['id'],
        'name' => $supplier['name'],
        'email' => $supplier['email'],
        'phone' => $supplier['phone'],
        'address' => $supplier['address'],
        'member_since' => $supplier['created_at'],
        'total_deliveries' => $delivery_stats['total_deliveries'],
        'product_count' => $product_stats['product_count'],
        'average_rating' => $average_rating,
        'review_count' => count($reviews),
        'reviews' => $reviews
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $profile_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching supplier profile: ' . $e->getMessage()
    ]);
}
?>
