<?php
header('Content-Type: application/json');
include 'db.php';
include 'email_service.php';

try {
    // Check if user is logged in and is a supplier
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $supplier_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $delivery_date = $_POST['delivery_date'] ?? '';
    $delivery_time = $_POST['delivery_time'] ?? '';
    $delivery_location = $_POST['delivery_location'] ?? '';
    $delivery_instructions = $_POST['delivery_instructions'] ?? '';
    $notes = $delivery_instructions; // Use delivery_instructions as notes
    
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
        echo json_encode(['success' => false, 'error' => 'Order not found or cannot be accepted']);
        exit;
    }
    
    // Update order status and delivery details
    $delivery_datetime = $delivery_date . ' ' . $delivery_time;
    
    $update_query = "UPDATE orders SET 
                       status = 'accepted',
                       delivery_datetime = :delivery_datetime,
                       special_instructions = CONCAT(COALESCE(special_instructions, ''), '\n\nSupplier Notes: ', :notes)
                     WHERE id = :order_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_result = $update_stmt->execute([
        'delivery_datetime' => $delivery_datetime,
        'notes' => $notes,
        'order_id' => $order_id
    ]);
    
    if (!$update_result) {
        echo json_encode(['success' => false, 'error' => 'Failed to update order status']);
        exit;
    }
    
    // Send email notification to vendor
    $emailService = new EmailService();
    $vendor_email = $order['vendor_email'];
    $vendor_name = $order['vendor_name'];
    $supplier_name = $order['supplier_name'];
    $product_name = $order['product_name'];
    
    // Debug log to verify data
    error_log("Order data for email: " . json_encode($order));
    error_log("Delivery details: Date=$delivery_date, Time=$delivery_time, Location=$delivery_location, Notes=$notes");
    
    $subject = "Order Accepted - StreetSource Order #" . $order_id;
    
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h2 style='color: #28a745; margin: 0;'>🎉 Order Accepted!</h2>
            <p style='color: #666; margin: 5px 0;'>Your order has been confirmed and is being prepared</p>
        </div>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='color: #333; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;'>📦 Order Details</h3>
            <div style='margin-bottom: 15px;'>
                <p style='margin: 8px 0;'><strong>Order ID:</strong> #" . $order_id . "</p>
                <p style='margin: 8px 0;'><strong>Product:</strong> " . htmlspecialchars($product_name) . "</p>
                <p style='margin: 8px 0;'><strong>Quantity:</strong> " . $order['quantity'] . " " . ($order['product_unit'] ?? 'units') . "</p>
                <p style='margin: 8px 0;'><strong>Unit Price:</strong> ₹" . number_format($order['product_price'], 2) . "</p>
                <p style='margin: 8px 0;'><strong>Total Amount:</strong> <span style='color: #28a745; font-size: 1.2em; font-weight: bold;'>₹" . number_format($order['total_price'], 2) . "</span></p>
                <p style='margin: 8px 0;'><strong>Payment Method:</strong> <span style='background: #28a745; color: white; padding: 4px 8px; border-radius: 4px;'>" . strtoupper($order['payment_method'] ?? 'COD') . "</span></p>
                <p style='margin: 8px 0;'><strong>Order Date:</strong> " . date('F j, Y \a\t g:i A', strtotime($order['order_date'])) . "</p>
            </div>";
            
    // Add special instructions if they exist
    if (!empty($order['special_instructions'])) {
        $message .= "
            <div style='background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107; margin-top: 15px;'>
                <p style='margin: 0; color: #856404;'><strong>📝 Your Special Instructions:</strong></p>
                <p style='margin: 5px 0 0 0; color: #856404; font-style: italic;'>" . htmlspecialchars($order['special_instructions']) . "</p>
            </div>";
    }
    
    $message .= "
        </div>
        
        <div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;'>🚚 Delivery Information</h3>
            <div style='margin-bottom: 15px;'>
                <p style='margin: 8px 0;'><strong>📅 Delivery Date:</strong> " . date('l, F j, Y', strtotime($delivery_date)) . "</p>
                <p style='margin: 8px 0;'><strong>🕒 Delivery Time:</strong> " . date('g:i A', strtotime($delivery_time)) . "</p>
                <p style='margin: 8px 0;'><strong>📍 Delivery Location:</strong> " . htmlspecialchars($delivery_location) . "</p>";
                
    // Add delivery notes if provided
    if (!empty($notes)) {
        $message .= "<p style='margin: 8px 0;'><strong>📋 Delivery Notes:</strong> " . htmlspecialchars($notes) . "</p>";
    }
    
    $message .= "
            </div>
        </div>
        
        <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='color: #1976d2; margin-top: 0; border-bottom: 2px solid #1976d2; padding-bottom: 10px;'>🏪 Supplier Information</h3>
            <div style='margin-bottom: 15px;'>
                <p style='margin: 8px 0;'><strong>Business Name:</strong> " . htmlspecialchars($supplier_name) . "</p>";
                
    // Add supplier contact info if available
    if (!empty($order['supplier_phone'])) {
        $message .= "<p style='margin: 8px 0;'><strong>📞 Contact:</strong> " . htmlspecialchars($order['supplier_phone']) . "</p>";
    }
    if (!empty($order['supplier_email'])) {
        $message .= "<p style='margin: 8px 0;'><strong>📧 Email:</strong> " . htmlspecialchars($order['supplier_email']) . "</p>";
    }
    if (!empty($order['supplier_address'])) {
        $message .= "<p style='margin: 8px 0;'><strong>📍 Address:</strong> " . htmlspecialchars($order['supplier_address']) . "</p>";
    }
    
    $message .= "
            </div>
        </div>
        
        <div style='background: #f0f8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;'>
            <h3 style='color: #155724; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;'>🏠 Your Information</h3>
            <div style='margin-bottom: 15px;'>
                <p style='margin: 8px 0;'><strong>Vendor Name:</strong> " . htmlspecialchars($vendor_name) . "</p>";
                
    if (!empty($order['vendor_phone'])) {
        $message .= "<p style='margin: 8px 0;'><strong>📞 Your Phone:</strong> " . htmlspecialchars($order['vendor_phone']) . "</p>";
    }
    if (!empty($order['vendor_address'])) {
        $message .= "<p style='margin: 8px 0;'><strong>📍 Your Address:</strong> " . htmlspecialchars($order['vendor_address']) . "</p>";
    }
    
    $message .= "
            </div>
        </div>
        
        <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;'>
            <h4 style='margin-top: 0; color: #856404;'>📋 Next Steps:</h4>
            <ul style='margin: 10px 0; padding-left: 20px; color: #856404;'>
                <li>Please be available at the specified delivery location on <strong>" . date('l, F j, Y', strtotime($delivery_date)) . " at " . date('g:i A', strtotime($delivery_time)) . "</strong></li>
                <li>Keep your payment ready (<strong>" . strtoupper($order['payment_method'] ?? 'COD') . "</strong>)</li>
                <li>Contact the supplier if you have any questions</li>
                <li>You will receive another notification when the order is out for delivery</li>
            </ul>
        </div>
        
        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <p style='color: #666; font-size: 16px; margin: 15px 0;'>
                <strong>Thank you for choosing StreetSource!</strong>
            </p>
            <div style='margin: 20px 0;'>
                <a href='http://localhost/StreetSource' style='display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;'>Visit Your Dashboard</a>
            </div>
            <p style='color: #999; font-size: 12px; margin: 5px 0;'>
                Order placed on " . date('F j, Y \a\t g:i A', strtotime($order['order_date'])) . "<br>
                Accepted on " . date('F j, Y \a\t g:i A') . "
            </p>
        </div>
    </div>";
    
    $email_sent = $emailService->sendEmail($vendor_email, $subject, $message, $vendor_name);
    
    // Log the email attempt
    error_log("Email attempt - To: $vendor_email, Subject: $subject, Result: " . json_encode($email_sent));
    
    if ($email_sent['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Order accepted successfully and vendor has been notified via email'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Order accepted successfully but email notification failed: ' . $email_sent['error']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error accepting order: ' . $e->getMessage()
    ]);
}
?>
