<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if user is logged in and is a vendor
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        $vendor_id = $_SESSION['user_id'];
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $order_id = intval($_POST['order_id'] ?? 0);
        
        // Validate input
        if ($supplier_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
            exit;
        }
        
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
            exit;
        }
        
        if (empty($comment)) {
            echo json_encode(['success' => false, 'error' => 'Comment is required']);
            exit;
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if vendor has received a delivered order from this supplier
        $order_check_query = "SELECT COUNT(*) as delivered_orders 
                             FROM orders 
                             WHERE vendor_id = ? AND supplier_id = ? AND status = 'delivered'";
        $order_check_stmt = $db->prepare($order_check_query);
        $order_check_stmt->execute([$vendor_id, $supplier_id]);
        $order_result = $order_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order_result['delivered_orders'] == 0) {
            echo json_encode(['success' => false, 'error' => 'You can only review suppliers after receiving a delivered order from them']);
            exit;
        }
        
        // Check if vendor has already reviewed this supplier
        $check_query = "SELECT id FROM reviews WHERE vendor_id = ? AND supplier_id = ?";
        if ($order_id > 0) {
            $check_query .= " AND order_id = ?";
        }
        
        $check_stmt = $db->prepare($check_query);
        if ($order_id > 0) {
            $check_stmt->execute([$vendor_id, $supplier_id, $order_id]);
        } else {
            $check_stmt->execute([$vendor_id, $supplier_id]);
        }
        
        if ($check_stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You have already reviewed this supplier']);
            exit;
        }
        
        // Insert review
        $insert_query = "INSERT INTO reviews (vendor_id, supplier_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);
        $order_id_to_insert = $order_id > 0 ? $order_id : null;
        
        if ($insert_stmt->execute([$vendor_id, $supplier_id, $order_id_to_insert, $rating, $comment])) {
            echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to submit review']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
