<?php
// Clean place_order.php with proper error handling
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering and clean any previous output
ob_start();
session_start();
ob_end_clean();

// Set JSON headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Detect if this is a JSON request
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJsonRequest = strpos($contentType, 'application/json') !== false;

try {
    // Check if user is logged in and get vendor_id
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }
    
    $vendor_id = $_SESSION['user_id']; // Get actual vendor ID from session
    
    if ($isJsonRequest) {
        // Handle JSON request from order page
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
            exit;
        }
        
        $supplier_id = intval($data['supplier_id'] ?? 0);
        $product_id = intval($data['product_id'] ?? 0);
        $quantity = intval($data['quantity'] ?? 0);
        $delivery_option = trim($data['delivery_option'] ?? '');
        $delivery_date = trim($data['delivery_date'] ?? '');
        $delivery_time = trim($data['delivery_time'] ?? '');
        $payment_method = trim($data['payment_method'] ?? '');
        $special_instructions = trim($data['special_instructions'] ?? '');
        $total_amount = floatval($data['total_amount'] ?? 0);
        
    } else {
        // Handle form request from dashboard
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $delivery_latitude = floatval($_POST['vendor_latitude'] ?? 0);
        $delivery_longitude = floatval($_POST['vendor_longitude'] ?? 0);
        $delivery_option = 'asap';
        $delivery_date = date('Y-m-d');
        $delivery_time = date('H:i');
        $special_instructions = '';
        $total_amount = 0; // Will be calculated
    }
    
    // Validate required fields
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
        exit;
    }
    
    // Use Database class for consistent connection
    include_once 'db.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Ensure payment_method column exists (add if missing)
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
        if ($check_column->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cod'");
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_option VARCHAR(50) DEFAULT 'asap'");
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_datetime TIMESTAMP NULL");
            $pdo->exec("ALTER TABLE orders ADD COLUMN special_instructions TEXT NULL");
        }
    } catch (Exception $e) {
        // Ignore if columns already exist
    }
    
    // Get product details
    $query = "SELECT price, stock, name FROM products WHERE id = :product_id AND supplier_id = :supplier_id AND is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['product_id' => $product_id, 'supplier_id' => $supplier_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product['stock'] < $quantity) {
        echo json_encode(['success' => false, 'error' => 'Insufficient stock available']);
        exit;
    }
    
    // Calculate total price if not provided
    $item_total = $product['price'] * $quantity;
    $delivery_fee = 20; // Fixed delivery fee
    $calculated_total = $item_total + $delivery_fee;
    
    if ($total_amount <= 0) {
        $total_amount = $calculated_total;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Prepare delivery datetime
    $delivery_datetime = null;
    if ($delivery_option === 'custom' && $delivery_date && $delivery_time) {
        $delivery_datetime = $delivery_date . ' ' . $delivery_time . ':00';
    } elseif ($delivery_option === 'asap') {
        $delivery_datetime = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    } elseif ($delivery_option === 'today') {
        $delivery_datetime = date('Y-m-d 23:59:59');
    }
    
    // Insert order with enhanced fields - simplified approach
    $query = "INSERT INTO orders (vendor_id, supplier_id, product_id, quantity, total_price, payment_method, status, order_date) 
              VALUES (:vendor_id, :supplier_id, :product_id, :quantity, :total_price, :payment_method, 'pending', NOW())";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        'vendor_id' => $vendor_id,
        'supplier_id' => $supplier_id,
        'product_id' => $product_id,
        'quantity' => $quantity,
        'total_price' => $total_amount,
        'payment_method' => $payment_method
    ]);
    
    if (!$result) {
        throw new Exception('Failed to insert order');
    }
    
    $order_id = $pdo->lastInsertId();
    
    // Update product stock
    $new_stock = $product['stock'] - $quantity;
    $query = "UPDATE products SET stock = :stock WHERE id = :product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['stock' => $new_stock, 'product_id' => $product_id]);

    // Send email to supplier with order details
    include_once 'email_service.php';
    // Get supplier email and name
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :supplier_id LIMIT 1");
    $stmt->execute(['supplier_id' => $supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($supplier && !empty($supplier['email'])) {
        // Get vendor info
        $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = :vendor_id LIMIT 1");
        $stmt->execute(['vendor_id' => $vendor_id]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        $orderDetails = "<h2>New Order Received</h2>"
            . "<p><strong>Product:</strong> " . htmlspecialchars($product['name']) . "</p>"
            . "<p><strong>Quantity:</strong> " . intval($quantity) . "</p>"
            . "<p><strong>Total Price:</strong> ₹" . number_format($total_amount, 2) . "</p>"
            . "<p><strong>Payment Method:</strong> " . htmlspecialchars($payment_method) . "</p>"
            . "<p><strong>Vendor Name:</strong> " . htmlspecialchars($vendor['name'] ?? '') . "</p>"
            . "<p><strong>Vendor Email:</strong> " . htmlspecialchars($vendor['email'] ?? '') . "</p>"
            . "<p><strong>Vendor Phone:</strong> " . htmlspecialchars($vendor['phone'] ?? '') . "</p>"
            . "<p><strong>Order ID:</strong> #" . intval($order_id) . "</p>";
        $emailService = new EmailService();
        $emailService->sendEmail(
            $supplier['email'],
            'New Order Received on StreetSource',
            $orderDetails,
            $supplier['name']
        );
    }

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
    echo json_encode(['success' => false, 'error' => 'Failed to place order: ' . $e->getMessage()]);
}
?>
