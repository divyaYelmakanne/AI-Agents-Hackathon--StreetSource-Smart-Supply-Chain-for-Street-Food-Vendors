<?php
session_start();
header('Content-Type: application/json');
include 'db.php';
include 'email_service.php';

// Add logging for debugging
error_log("Reject order request received: " . json_encode($_POST));
error_log("Session data: " . json_encode($_SESSION));

try {
    // Check if user is logged in and is a supplier
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
        error_log("Access denied: user_id=" . ($_SESSION['user_id'] ?? 'null') . ", role=" . ($_SESSION['user_role'] ?? 'null'));
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $supplier_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $rejection_reason = $_POST['rejection_reason'] ?? 'Supplier unavailable';
    $custom_reason = $_POST['custom_reason'] ?? '';
    
    // Combine reasons if both are provided
    if ($custom_reason && $rejection_reason !== 'Other') {
        $rejection_reason = $rejection_reason . ': ' . $custom_reason;
    } elseif ($custom_reason && $rejection_reason === 'Other') {
        $rejection_reason = $custom_reason;
    }
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get order details with complete information
    $order_query = "SELECT 
                      o.*,
                      p.name as product_name,
                      p.price as product_price,
                      p.unit as product_unit,
                      p.image_url as product_image,
                      v.name as vendor_name,
                      v.email as vendor_email,
                      v.phone as vendor_phone,
                      v.address as vendor_address,
                      s.name as supplier_name,
                      s.email as supplier_email,
                      s.phone as supplier_phone,
                      s.address as supplier_address
                    FROM orders o
                    JOIN products p ON o.product_id = p.id
                    JOIN users v ON o.vendor_id = v.id
                    JOIN users s ON p.supplier_id = s.id
                    WHERE o.id = :order_id AND p.supplier_id = :supplier_id AND o.status = 'pending'";
    
    $order_stmt = $db->prepare($order_query);
    $order_stmt->execute([
        'order_id' => $order_id,
        'supplier_id' => $supplier_id
    ]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found or cannot be rejected']);
        exit;
    }
    
    // Update order status to cancelled
    $update_query = "UPDATE orders SET 
                       status = 'cancelled',
                       special_instructions = CONCAT(COALESCE(special_instructions, ''), '\n\nRejection Reason: ', :rejection_reason)
                     WHERE id = :order_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_result = $update_stmt->execute([
        'rejection_reason' => $rejection_reason,
        'order_id' => $order_id
    ]);
    
    if (!$update_result) {
        echo json_encode(['success' => false, 'error' => 'Failed to update order status']);
        exit;
    }
    
    // Send rejection email notification to vendor
    $emailService = new EmailService();
    $vendor_email = $order['vendor_email'];
    $vendor_name = $order['vendor_name'];
    $supplier_name = $order['supplier_name'];
    $product_name = $order['product_name'];
    
    $subject = "Order Rejected - StreetSource Order #" . $order_id;
    
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h2 style='color: #dc3545; margin: 0;'>😔 Order Rejected</h2>
            <p style='color: #666; margin: 5px 0;'>We're sorry, but your order cannot be fulfilled at this time</p>
        </div>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='color: #333; margin-top: 0; border-bottom: 2px solid #dc3545; padding-bottom: 10px;'>📦 Order Details</h3>
            <div style='display: flex; gap: 15px; margin-bottom: 15px;'>
                <div style='flex: 1;'>
                    <p style='margin: 8px 0;'><strong>Order ID:</strong> #" . $order_id . "</p>
                    <p style='margin: 8px 0;'><strong>Product:</strong> " . htmlspecialchars($product_name) . "</p>
                    <p style='margin: 8px 0;'><strong>Quantity:</strong> " . $order['quantity'] . " " . ($order['product_unit'] ?? 'units') . "</p>
                    <p style='margin: 8px 0;'><strong>Unit Price:</strong> ₹" . number_format($order['product_price'], 2) . "</p>
                    <p style='margin: 8px 0;'><strong>Total Amount:</strong> <span style='color: #dc3545; font-size: 1.2em; font-weight: bold;'>₹" . number_format($order['total_price'], 2) . "</span></p>
                    <p style='margin: 8px 0;'><strong>Payment Method:</strong> <span style='background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px;'>" . strtoupper($order['payment_method']) . "</span></p>
                    <p style='margin: 8px 0;'><strong>Order Date:</strong> " . date('F j, Y \a\t g:i A', strtotime($order['order_date'])) . "</p>
                </div>
            </div>
            " . ($order['special_instructions'] ? "
                <div style='background: #e2e3e5; padding: 15px; border-radius: 6px; border-left: 4px solid #6c757d;'>
                    <p style='margin: 0; color: #495057;'><strong>📝 Your Special Instructions:</strong></p>
                    <p style='margin: 5px 0 0 0; color: #495057; font-style: italic;'>" . htmlspecialchars($order['special_instructions']) . "</p>
                </div>
            " : "") . "
        </div>
        
        <div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;'>
            <h3 style='color: #721c24; margin-top: 0; border-bottom: 2px solid #dc3545; padding-bottom: 10px;'>❌ Rejection Information</h3>
            <div style='display: flex; gap: 20px;'>
                <div style='flex: 1;'>
                    <p style='margin: 8px 0;'><strong>Supplier:</strong> " . htmlspecialchars($supplier_name) . "</p>
                    <p style='margin: 8px 0;'><strong>📅 Rejection Date:</strong> " . date('l, F j, Y \a\t g:i A') . "</p>
                    <p style='margin: 8px 0;'><strong>🔍 Reason:</strong> " . htmlspecialchars($rejection_reason) . "</p>
                    " . ($order['supplier_phone'] ? "<p style='margin: 8px 0;'><strong>📞 Supplier Contact:</strong> " . htmlspecialchars($order['supplier_phone']) . "</p>" : "") . "
                    <p style='margin: 8px 0;'><strong>📧 Supplier Email:</strong> " . htmlspecialchars($order['supplier_email']) . "</p>
                </div>
            </div>
        </div>
        
        <div style='background: #d1ecf1; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #bee5eb;'>
            <h4 style='margin-top: 0; color: #0c5460;'>💡 What You Can Do Next:</h4>
            <ul style='margin: 10px 0; padding-left: 20px; color: #0c5460;'>
                <li>Try placing the order again later - the supplier might become available</li>
                <li>Contact the supplier directly to discuss availability</li>
                <li>Look for alternative suppliers offering similar products</li>
                <li>Adjust your order quantity or delivery requirements</li>
                <li>Check our marketplace for other vendors in your area</li>
            </ul>
        </div>
        
        <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;'>
            <p style='margin: 0; color: #856404; text-align: center;'>
                <strong>💳 Payment Status:</strong> " . (strtolower($order['payment_method']) === 'online' ? 'If any payment was processed, it will be refunded within 3-5 business days.' : 'No payment was processed for this Cash on Delivery order.') . "
            </p>
        </div>
        
        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <p style='color: #666; font-size: 16px; margin: 15px 0;'>
                <strong>We apologize for any inconvenience caused.</strong>
            </p>
            <div style='margin: 20px 0;'>
                <a href='http://localhost/StreetSource' style='display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;'>🏠 Go to Dashboard</a>
                <a href='http://localhost/StreetSource/vendor/marketplace.php' style='display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;'>🛒 Browse Marketplace</a>
            </div>
            <p style='color: #999; font-size: 12px; margin: 5px 0;'>
                StreetSource Team - Your Local Marketplace
            </p>
        </div>
    </div>";
    
    $email_sent = $emailService->sendEmail($vendor_email, $subject, $message, $vendor_name);
    
    // Log the email attempt
    error_log("Rejection email attempt - To: $vendor_email, Subject: $subject, Result: " . json_encode($email_sent));
    
    if ($email_sent['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Order rejected successfully and vendor has been notified via email'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Order rejected successfully but email notification failed: ' . $email_sent['error']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error rejecting order: ' . $e->getMessage()
    ]);
}
?>
