<?php
// Modern Order Page with comprehensive features
include 'php/db.php';
requireRole('vendor');

$database = new Database();
$db = $database->getConnection();

$product_id = $_GET['product_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;

if (!$product_id || !$supplier_id) {
    $_SESSION['error'] = 'Invalid order parameters';
    header('Location: vendor/dashboard.php');
    exit();
}

// Get product details
try {
    $query = "SELECT p.*, u.name as supplier_name, u.business_name, u.phone, u.address 
              FROM products p 
              JOIN users u ON p.supplier_id = u.id 
              WHERE p.id = :product_id AND p.supplier_id = :supplier_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found or not available';
        header('Location: vendor/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error occurred';
    header('Location: vendor/dashboard.php');
    exit();
}

// Get user's profile for default address
try {
    $user_query = "SELECT name, phone, address FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user_profile = $user_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_profile = ['name' => '', 'phone' => '', 'address' => ''];
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity']);
    $delivery_address = trim($_POST['delivery_address']);
    $delivery_date = $_POST['delivery_date'];
    $delivery_time = $_POST['delivery_time'];
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes'] ?? '');
    $customer_phone = trim($_POST['customer_phone']);
    
    // Validation
    $errors = [];
    if ($quantity <= 0 || $quantity > $product['stock']) {
        $errors[] = 'Invalid quantity selected';
    }
    if (empty($delivery_address)) {
        $errors[] = 'Delivery address is required';
    }
    if (empty($delivery_date)) {
        $errors[] = 'Delivery date is required';
    }
    if (empty($delivery_time)) {
        $errors[] = 'Delivery time is required';
    }
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required';
    }
    if (empty($customer_phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // Check if delivery date is not in the past
    if (!empty($delivery_date) && strtotime($delivery_date) < strtotime('today')) {
        $errors[] = 'Delivery date cannot be in the past';
    }
    
    if (empty($errors)) {
        try {
            // Ensure orders table has all necessary columns
            $columns_to_check = [
                'delivery_address' => 'TEXT',
                'delivery_date' => 'DATE', 
                'delivery_time' => 'TIME',
                'payment_method' => 'VARCHAR(50)',
                'customer_phone' => 'VARCHAR(20)'
            ];
            
            foreach ($columns_to_check as $column => $type) {
                try {
                    // Check if column exists
                    $check_query = "SHOW COLUMNS FROM orders LIKE '$column'";
                    $result = $db->query($check_query);
                    
                    if ($result->rowCount() == 0) {
                        // Column doesn't exist, add it
                        $alter_query = "ALTER TABLE orders ADD COLUMN $column $type";
                        $db->exec($alter_query);
                    }
                } catch (PDOException $e) {
                    // Column might already exist or other error, continue
                    error_log("Database column check error: " . $e->getMessage());
                }
            }
            
            $total_price = $quantity * $product['price'];
            $vendor_id = $_SESSION['user_id'];
            $delivery_datetime = $delivery_date . ' ' . $delivery_time;
            
            $query = "INSERT INTO orders (vendor_id, supplier_id, product_id, quantity, total_price, notes, status, 
                                        delivery_address, delivery_date, delivery_time, payment_method, customer_phone, order_date) 
                      VALUES (:vendor_id, :supplier_id, :product_id, :quantity, :total_price, :notes, 'pending',
                              :delivery_address, :delivery_date, :delivery_time, :payment_method, :customer_phone, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':total_price', $total_price);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':delivery_address', $delivery_address);
            $stmt->bindParam(':delivery_date', $delivery_date);
            $stmt->bindParam(':delivery_time', $delivery_time);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':customer_phone', $customer_phone);
            
            if ($stmt->execute()) {
                // Send order confirmation email to supplier
                require_once 'php/email_service.php';
                $emailService = new EmailService();

                // Get supplier email
                $supplier_query = "SELECT email, name FROM users WHERE id = :supplier_id";
                $supplier_stmt = $db->prepare($supplier_query);
                $supplier_stmt->bindParam(':supplier_id', $supplier_id);
                $supplier_stmt->execute();
                $supplier = $supplier_stmt->fetch(PDO::FETCH_ASSOC);

                if ($supplier && !empty($supplier['email'])) {
                    $order_details = [
                        'Product' => $product['name'],
                        'Quantity' => $quantity . ' ' . $product['unit'],
                        'Total Price' => '₹' . number_format($total_price, 2),
                        'Payment Method' => ucfirst($payment_method),
                        'Delivery Date' => $delivery_date,
                        'Delivery Time' => $delivery_time,
                        'Delivery Address' => $delivery_address,
                        'Vendor Name' => $user_profile['name'] ?? '',
                        'Vendor Phone' => $customer_phone,
                        'Vendor Email' => $_SESSION['user_email'] ?? '',
                        'Special Notes' => $notes
                    ];
                    // Modern HTML email template
                    $order_table = '<table style="width:100%;border-collapse:collapse;font-size:16px;">';
                    foreach ($order_details as $key => $val) {
                        $order_table .= "<tr><td style='background:#f8f9fa;padding:10px 15px;border:1px solid #e9ecef;width:180px;font-weight:600;color:#333;'>$key</td><td style='padding:10px 15px;border:1px solid #e9ecef;color:#222;'>" . htmlspecialchars($val) . "</td></tr>";
                    }
                    $order_table .= '</table>';

                    $subject = '🍲 New Order Received - StreetSource';
                    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
                        . '<title>New Order Notification</title>'
                        . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f4f6f8;margin:0;padding:0;} .email-container{max-width:600px;margin:30px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.07);overflow:hidden;} .header{background:linear-gradient(90deg,#007bff 0%,#00c6ff 100%);color:#fff;padding:32px 24px;text-align:center;} .header h1{margin:0;font-size:2rem;} .content{padding:32px 24px;} .order-table{margin:24px 0;} .footer{background:#f8f9fa;color:#888;text-align:center;padding:18px 0;font-size:14px;}</style>'
                        . '</head><body>'
                        . '<div class="email-container">'
                        . '<div class="header">'
                        . '<h1>🍲 StreetSource</h1>'
                        . '<p style="margin:0;font-size:1.1rem;">You have received a new order from a vendor!</p>'
                        . '</div>'
                        . '<div class="content">'
                        . '<h2 style="color:#007bff;font-size:1.3rem;margin-top:0;">Order Details</h2>'
                        . '<div class="order-table">' . $order_table . '</div>'
                        . '<p style="margin:24px 0 0 0;">Please process this order as soon as possible. For any queries, contact the vendor directly using the details above.</p>'
                        . '</div>'
                        . '<div class="footer">&copy; 2025 StreetSource &mdash; Empowering Street Food Vendors</div>'
                        . '</div>'
                        . '</body></html>';
                    $emailService->sendEmail($supplier['email'], $subject, $message, $supplier['name']);
                }

                // Show popup and redirect using JS
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Order Placed</title>';
                echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
                echo '<script>setTimeout(function(){ window.location.href = "vendor/myorders.php"; }, 2000);</script>';
                echo '</head><body class="bg-light">';
                echo '<div class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,0.3);"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-check-circle"></i> Order Placed!</h5></div><div class="modal-body"><p>Your order was placed successfully.<br>You will be redirected to your orders in 2 seconds.</p></div></div></div></div>';
                echo '<script src="https://kit.fontawesome.com/7e2e4e2e2e.js" crossorigin="anonymous"></script>';
                echo '</body></html>';
                exit();
            } else {
                $errors[] = 'Failed to place order. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .product-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .order-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .price-display {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 15px 0;
        }
        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-option:hover, .payment-option.selected {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .btn-place-order {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .btn-place-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-store"></i> StreetSource</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="vendor/dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Product Information Card -->
                <div class="product-card">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <?php if ($product['image_url']): ?>
                                <img src="uploads/products/<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="img-fluid rounded-3 shadow" style="max-height: 200px;">
                            <?php else: ?>
                                <div class="bg-secondary rounded-3 d-flex align-items-center justify-content-center shadow" style="height: 200px; width: 100%;">
                                    <i class="fas fa-box text-white fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h3 class="mb-2"><i class="fas fa-shopping-basket text-primary"></i> <?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-muted mb-2"><i class="fas fa-store"></i> <strong>Supplier:</strong> <?php echo htmlspecialchars($product['business_name'] ?: $product['supplier_name']); ?></p>
                            <p class="text-success h4 mb-2"><i class="fas fa-tag"></i> ₹<?php echo number_format($product['price'], 2); ?> per <?php echo htmlspecialchars($product['unit']); ?></p>
                            <p class="text-info mb-2"><i class="fas fa-warehouse"></i> <strong>Available Stock:</strong> <?php echo $product['stock']; ?> <?php echo htmlspecialchars($product['unit']); ?></p>
                            <?php if ($product['description']): ?>
                                <p class="text-muted"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Form -->
                <div class="order-form">
                    <h4 class="text-center mb-4"><i class="fas fa-clipboard-list text-primary"></i> Complete Your Order</h4>
                    
                    <form method="POST" id="orderForm">
                        <!-- Quantity & Price Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-calculator text-primary"></i> Quantity & Pricing</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Quantity (<?php echo htmlspecialchars($product['unit']); ?>)</label>
                                    <input type="number" class="form-control form-control-lg" name="quantity" id="quantity"
                                           min="1" max="<?php echo $product['stock']; ?>" value="1" required>
                                    <small class="text-muted">Maximum: <?php echo $product['stock']; ?> <?php echo htmlspecialchars($product['unit']); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-receipt"></i> Total Amount</label>
                                    <div class="price-display">
                                        <h4 class="mb-0" id="totalPrice">₹<?php echo number_format($product['price'], 2); ?></h4>
                                        <small>Including all charges</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Information Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-truck text-primary"></i> Delivery Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-phone"></i> Your Phone Number</label>
                                    <input type="tel" class="form-control" name="customer_phone" 
                                           value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>" 
                                           placeholder="+91 XXXXX XXXXX" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Delivery Date</label>
                                    <input type="date" class="form-control" name="delivery_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-clock"></i> Preferred Time</label>
                                    <select class="form-control" name="delivery_time" required>
                                        <option value="">Select Time Slot</option>
                                        <option value="08:00">8:00 AM - 10:00 AM</option>
                                        <option value="10:00">10:00 AM - 12:00 PM</option>
                                        <option value="12:00">12:00 PM - 2:00 PM</option>
                                        <option value="14:00">2:00 PM - 4:00 PM</option>
                                        <option value="16:00">4:00 PM - 6:00 PM</option>
                                        <option value="18:00">6:00 PM - 8:00 PM</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Complete Delivery Address</label>
                                <textarea class="form-control" name="delivery_address" rows="3" required 
                                          placeholder="Enter complete address with landmarks, area, city, pincode..."><?php echo htmlspecialchars($user_profile['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Payment Method Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-credit-card text-primary"></i> Payment Method</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="payment-option" onclick="selectPayment('cod')">
                                        <input type="radio" name="payment_method" value="cod" id="cod" hidden>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-money-bill-wave fa-2x text-success me-3"></i>
                                            <div>
                                                <h6 class="mb-1">Cash on Delivery</h6>
                                                <small class="text-muted">Pay when you receive the order</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="payment-option" onclick="selectPayment('online')">
                                        <input type="radio" name="payment_method" value="online" id="online" hidden>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-mobile-alt fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-1">Online Payment</h6>
                                                <small class="text-muted">UPI / Net Banking / Cards</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Special Instructions Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-sticky-note text-primary"></i> Special Instructions (Optional)</h5>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="Any special requirements, quality preferences, delivery instructions, etc..."></textarea>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="text-center mt-4">
                            <a href="vendor/dashboard.php" class="btn btn-outline-secondary btn-lg me-3">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-place-order">
                                <i class="fas fa-shopping-cart"></i> Place Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update total price when quantity changes
        document.getElementById('quantity').addEventListener('input', function() {
            const quantity = parseInt(this.value) || 0;
            const price = <?php echo $product['price']; ?>;
            const total = quantity * price;
            document.getElementById('totalPrice').innerHTML = '₹' + total.toFixed(2);
        });

        // Payment method selection
        function selectPayment(method) {
            // Remove previous selection
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selection to clicked option
            event.currentTarget.classList.add('selected');
            
            // Set radio button
            document.getElementById(method).checked = true;
        }

        // Set minimum date to today
        document.querySelector('input[name="delivery_date"]').min = new Date().toISOString().split('T')[0];

        // Form validation
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
            
            const deliveryDate = document.querySelector('input[name="delivery_date"]').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (deliveryDate < today) {
                e.preventDefault();
                alert('Delivery date cannot be in the past');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
