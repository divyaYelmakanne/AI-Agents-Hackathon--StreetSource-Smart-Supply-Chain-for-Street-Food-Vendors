<?php
include '../php/db.php';
requireRole('supplier');

$database = new Database();
$db = $database->getConnection();

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = $_SESSION['user_id'];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $unit = trim($_POST['unit']);
                $description = trim($_POST['description']);
                $image_name = null;
                
                // Handle file upload
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/products/';
                    $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $image_name = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $image_name;
                        
                        if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                            $_SESSION['error'] = 'Failed to upload image.';
                            break;
                        }
                    } else {
                        $_SESSION['error'] = 'Invalid image format. Please use JPG, PNG, or GIF.';
                        break;
                    }
                }
                
                if (empty($name) || $price <= 0 || $stock < 0) {
                    $_SESSION['error'] = 'Please fill in all required fields with valid values.';
                } else {
                    try {
                        $query = "INSERT INTO products (supplier_id, name, price, stock, unit, description, image_url) 
                                  VALUES (:supplier_id, :name, :price, :stock, :unit, :description, :image_url)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':supplier_id', $supplier_id);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':price', $price);
                        $stmt->bindParam(':stock', $stock);
                        $stmt->bindParam(':unit', $unit);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':image_url', $image_name);
                        
                        if ($stmt->execute()) {
                            $_SESSION['success'] = 'Product added successfully!';
                        } else {
                            $_SESSION['error'] = 'Failed to add product.';
                        }
                    } catch (PDOException $e) {
                        $_SESSION['error'] = 'Failed to add product.';
                    }
                }
                break;
                
            case 'update_product':
                $product_id = intval($_POST['product_id']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $description = trim($_POST['description']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $query = "UPDATE products SET price = :price, stock = :stock, description = :description, is_active = :is_active 
                              WHERE id = :product_id AND supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':stock', $stock);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':is_active', $is_active);
                    $stmt->bindParam(':product_id', $product_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Product updated successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to update product.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Failed to update product.';
                }
                break;
                
            case 'update_stock':
                $product_id = intval($_POST['product_id']);
                $new_stock = intval($_POST['new_stock']);
                $status = trim($_POST['status']);
                
                try {
                    $is_active = ($status === 'active') ? 1 : 0;
                    $query = "UPDATE products SET stock = :stock, is_active = :is_active 
                              WHERE id = :product_id AND supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':stock', $new_stock);
                    $stmt->bindParam(':is_active', $is_active);
                    $stmt->bindParam(':product_id', $product_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Product stock and status updated successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to update product.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Failed to update product.';
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                try {
                    // First, get the image name to delete the file
                    $query = "SELECT image_url FROM products WHERE id = :product_id AND supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':product_id', $product_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    $stmt->execute();
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete the product
                    $query = "DELETE FROM products WHERE id = :product_id AND supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':product_id', $product_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    
                    if ($stmt->execute()) {
                        // Delete the image file if it exists
                        if ($product && $product['image_url']) {
                            $image_path = '../uploads/products/' . $product['image_url'];
                            if (file_exists($image_path)) {
                                unlink($image_path);
                            }
                        }
                        $_SESSION['success'] = 'Product deleted successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to delete product.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Failed to delete product.';
                }
                break;
                
            case 'accept_order':
                $order_id = intval($_POST['order_id']);
                $delivery_date = $_POST['delivery_date'];
                $delivery_time = $_POST['delivery_time'];
                $delivery_notes = trim($_POST['delivery_notes']);
                
                try {
                    // Update order with delivery details
                    $query = "UPDATE orders o
                              JOIN products p ON o.product_id = p.id 
                              SET 
                                o.status = 'accepted',
                                o.delivery_datetime = CONCAT(:delivery_date, ' ', :delivery_time),
                                o.special_instructions = CONCAT(COALESCE(o.special_instructions, ''), '\n\nSupplier Notes: ', :delivery_notes),
                                o.updated_at = NOW()
                              WHERE o.id = :order_id AND p.supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':delivery_date', $delivery_date);
                    $stmt->bindParam(':delivery_time', $delivery_time);
                    $stmt->bindParam(':delivery_notes', $delivery_notes);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    
                    if ($stmt->execute()) {
                        // Send email notification to vendor
                        require_once '../php/email_service.php';
                        
                        // Get order and vendor details
                        $query = "SELECT o.*, p.name as product_name, u.name as vendor_name, u.email as vendor_email,
                                         o.delivery_address, o.vendor_latitude, o.vendor_longitude
                                  FROM orders o
                                  JOIN products p ON o.product_id = p.id
                                  JOIN users u ON o.vendor_id = u.id
                                  WHERE o.id = :order_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':order_id', $order_id);
                        $stmt->execute();
                        $order_details = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($order_details) {
                            $email_service = new EmailService();
                            $subject = "Order Accepted - Delivery Scheduled";
                            $delivery_datetime = date('M j, Y', strtotime($delivery_date)) . ' at ' . date('g:i A', strtotime($delivery_time));
                            
                            $message = "
                                <h2>🎉 Great News! Your Order Has Been Accepted</h2>
                                <p>Hello {$order_details['vendor_name']},</p>
                                <p>Your order has been accepted and scheduled for delivery:</p>
                                
                                <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <h3>📦 Order Details:</h3>
                                    <p><strong>Product:</strong> {$order_details['product_name']}</p>
                                    <p><strong>Quantity:</strong> {$order_details['quantity']} units</p>
                                    <p><strong>Total:</strong> $" . number_format($order_details['total_price'], 2) . "</p>
                                </div>
                                
                                <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <h3>🚚 Delivery Schedule:</h3>
                                    <p><strong>Date & Time:</strong> {$delivery_datetime}</p>
                                    <p><strong>Location:</strong> {$order_details['delivery_address']}</p>
                                    " . ($delivery_notes ? "<p><strong>Notes:</strong> {$delivery_notes}</p>" : "") . "
                                </div>
                                
                                <p>Please be available at the scheduled time and location. Our supplier will contact you if there are any changes.</p>
                                <p>Thank you for using StreetSource!</p>
                            ";
                            
                            $email_service->sendEmail($order_details['vendor_email'], $subject, $message);
                        }
                        
                        $_SESSION['success'] = 'Order accepted and vendor notified via email!';
                    } else {
                        $_SESSION['error'] = 'Failed to accept order.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Failed to accept order.';
                }
                break;
                
            case 'update_order_status':
                $order_id = intval($_POST['order_id']);
                $new_status = trim($_POST['new_status']);
                
                try {
                    $query = "UPDATE orders SET status = :status WHERE id = :order_id AND supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':status', $new_status);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Order status updated successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to update order status.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Failed to update order status.';
                }
                break;
        }
    }
}

try {
    $supplier_id = $_SESSION['user_id'];
    
    // Get supplier information including delivery details
    $supplier_query = "SELECT name, email, phone, address, delivery_address, delivery_radius FROM users WHERE id = :supplier_id";
    $supplier_stmt = $db->prepare($supplier_query);
    $supplier_stmt->bindParam(':supplier_id', $supplier_id);
    $supplier_stmt->execute();
    $supplier_info = $supplier_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get supplier statistics
    $query = "SELECT 
                COUNT(DISTINCT p.id) as total_products,
                SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_products,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total_price ELSE 0 END) as total_revenue,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(r.id) as total_reviews
              FROM products p
              LEFT JOIN orders o ON p.id = o.product_id
              LEFT JOIN reviews r ON p.supplier_id = r.supplier_id
              WHERE p.supplier_id = :supplier_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get products
    $query = "SELECT * FROM products WHERE supplier_id = :supplier_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending orders that need immediate attention
    $query = "SELECT o.*, p.name as product_name, u.name as vendor_name, u.phone as vendor_phone, 
              u.email as vendor_email, u.address as vendor_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.vendor_id = u.id
              WHERE p.supplier_id = :supplier_id AND o.status = 'pending'
              ORDER BY o.order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending orders that need action
    $query = "SELECT o.*, p.name as product_name, u.name as vendor_name, u.phone as vendor_phone, 
              u.email as vendor_email, u.address as vendor_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.vendor_id = u.id
              WHERE p.supplier_id = :supplier_id AND o.status = 'pending'
              ORDER BY o.order_date ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all orders for management
    $query = "SELECT o.*, p.name as product_name, u.name as vendor_name, u.phone as vendor_phone, 
              u.email as vendor_email, u.address as vendor_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.vendor_id = u.id
              WHERE p.supplier_id = :supplier_id
              ORDER BY o.order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user profile for profile modal
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $supplier_id);
    $stmt->execute();
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['total_products' => 0, 'active_products' => 0, 'total_orders' => 0, 'pending_orders' => 0, 'total_revenue' => 0, 'avg_rating' => 0, 'total_reviews' => 0];
    $products = [];
    $recent_orders = [];
    $user_profile = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">🍲 StreetSource</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../php/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <section class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 🚚</h1>
                    <p class="mb-0">Manage your products and orders to serve street food vendors</p>
                </div>
                <div class="col-md-3">
                    <div class="rating text-white">
                        <?php 
                        // Generate star rating
                        $rating = $stats['avg_rating'];
                        $stars = '';
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                $stars .= '⭐';
                            } else {
                                $stars .= '☆';
                            }
                        }
                        echo $stars . ' ' . number_format($rating, 1) . ' (' . $stats['total_reviews'] . ' reviews)';
                        ?>
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <button type="button" class="btn btn-light btn-sm mb-2" id="locationBtn" onclick="updateSupplierLocation()">
                        📍 Set Store Location
                    </button>
                    <br>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        📦 Add Product
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_products']; ?></div>
                    <div class="stats-label">Total Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stats-label">Pending Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                    <div class="stats-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo round($stats['avg_rating'], 1); ?> ⭐</div>
                    <div class="stats-label">Average Rating</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Products Management -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📦 My Products</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            + Add Product
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="text-center py-4">
                                <div style="font-size: 4rem; opacity: 0.3;">📦</div>
                                <h5 class="text-muted">No Products Added</h5>
                                <p class="text-muted">Start by adding products that vendors can order from you.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                    Add Your First Product
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" 
                                                                 class="me-3">
                                                        <?php else: ?>
                                                            <div class="bg-light d-flex align-items-center justify-content-center me-3" 
                                                                 style="width: 50px; height: 50px; border-radius: 8px;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                            <?php if ($product['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo formatCurrency($product['price']) . '/' . $product['unit']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2 <?php echo $product['stock'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                            <?php echo $product['stock'] . ' ' . $product['unit']; ?>
                                                        </span>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                onclick="openStockModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                            📊 Manage
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge <?php echo $product['is_active'] ? 'bg-success' : ($product['stock'] > 0 ? 'bg-secondary' : 'bg-danger'); ?> me-2">
                                                            <?php 
                                                            if ($product['is_active']) {
                                                                echo $product['stock'] > 0 ? 'Active' : 'Sold Out';
                                                            } else {
                                                                echo 'Inactive';
                                                            }
                                                            ?>
                                                        </span>
                                                        <div class="btn-group" role="group">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="update_stock">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <input type="hidden" name="new_stock" value="<?php echo $product['stock']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $product['is_active'] ? 'inactive' : 'active'; ?>">
                                                                <button type="submit" class="btn btn-outline-<?php echo $product['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                                    <?php echo $product['is_active'] ? '⏸️' : '▶️'; ?>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="delete_product">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders & Quick Actions -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="quick-actions mb-4">
                    <h5 class="mb-3">⚡ Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="orders.php" class="btn btn-outline-primary">
                            📋 View All Orders
                        </a>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            📦 Add New Product
                        </button>
                    </div>
                </div>

                <!-- Pending Orders -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">⏳ Pending Orders (Need Action)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-muted small">No pending orders. All caught up! 🎉</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="card order-card pending mb-3" style="border-left: 4px solid #ffc107;">
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="fw-bold text-primary"><?php echo htmlspecialchars($order['product_name']); ?></h6>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="mb-1"><strong>Quantity:</strong> <?php echo $order['quantity']; ?> units</p>
                                                        <p class="mb-1"><strong>Total:</strong> ₹<?php echo number_format($order['total_price'], 2); ?></p>
                                                        <p class="mb-1"><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></p>
                                                        <p class="mb-1"><strong>Payment:</strong> 
                                                            <span class="badge bg-success"><?php echo strtoupper($order['payment_method'] ?? 'COD'); ?></span>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="vendor-info-card p-2 bg-light rounded">
                                                            <h6 class="text-primary mb-2"><i class="bi bi-person-circle"></i> Vendor</h6>
                                                            <p class="mb-1 small"><strong>Name:</strong> <?php echo htmlspecialchars($order['vendor_name']); ?></p>
                                                            <p class="mb-1 small">
                                                                <strong><i class="bi bi-telephone-fill text-success"></i></strong> 
                                                                <?php if ($order['vendor_phone']): ?>
                                                                    <a href="tel:<?php echo $order['vendor_phone']; ?>" class="text-decoration-none">
                                                                        <?php echo htmlspecialchars($order['vendor_phone']); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not provided</span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <p class="mb-1 small">
                                                                <strong><i class="bi bi-envelope-fill text-primary"></i></strong> 
                                                                <a href="mailto:<?php echo $order['vendor_email']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($order['vendor_email']); ?>
                                                                </a>
                                                            </p>
                                                            <p class="mb-0 small">
                                                                <strong><i class="bi bi-geo-alt-fill text-danger"></i></strong> 
                                                                <?php echo htmlspecialchars($order['vendor_address'] ?? 'Not provided'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($order['special_instructions']): ?>
                                                    <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded">
                                                        <small><strong>📝 Vendor Notes:</strong> 
                                                            <em><?php echo htmlspecialchars($order['special_instructions']); ?></em>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <span class="badge bg-warning fs-6 mb-3">⏳ Pending</span>
                                                
                                                <div class="d-grid gap-2 mb-3">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="acceptOrder(<?php echo $order['id']; ?>)">
                                                        ✅ Accept Order
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="showRejectModal(<?php echo $order['id']; ?>)">
                                                        ❌ Reject Order
                                                    </button>
                                                </div>
                                                
                                                <!-- Contact Actions -->
                                                <div class="contact-actions">
                                                    <?php if ($order['vendor_phone']): ?>
                                                        <a href="tel:<?php echo $order['vendor_phone']; ?>" 
                                                           class="btn btn-outline-success btn-sm me-1" title="Call Vendor">
                                                            <i class="bi bi-telephone"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="mailto:<?php echo $order['vendor_email']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Email Vendor">
                                                        <i class="bi bi-envelope"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All Orders</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Management Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📊 Manage Stock & Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" id="stockProductId" name="product_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Product Name:</label>
                                <p id="stockProductName" class="fw-bold"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Current Price:</label>
                                <p id="stockProductPrice" class="fw-bold"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="newStock" class="form-label">Stock Quantity:</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustStock(-1)">-</button>
                                    <input type="number" id="newStock" name="new_stock" class="form-control text-center" min="0" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustStock(1)">+</button>
                                </div>
                                <small class="text-muted">Current: <span id="currentStock"></span></small>
                            </div>
                            <div class="col-md-6">
                                <label for="productStatus" class="form-label">Status:</label>
                                <select id="productStatus" name="status" class="form-select" required>
                                    <option value="active">🟢 Active</option>
                                    <option value="inactive">🔴 Sold Out / Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Quick Actions:</strong>
                            <div class="btn-group mt-2 w-100" role="group">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="setStock(50)">Set 50</button>
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="setStock(0)">Sold Out</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="addStock(10)">+10 Stock</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">💾 Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_image" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                            <div class="form-text">Upload JPG, PNG, or GIF. Max size: 5MB</div>
                            <div class="mt-2" id="imagePreview" style="display: none;">
                                <img id="previewImg" src="" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 5px;">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price *</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">Unit</label>
                                    <select class="form-select" id="unit" name="unit">
                                        <option value="kg">kg</option>
                                        <option value="gram">gram</option>
                                        <option value="liter">liter</option>
                                        <option value="piece">piece</option>
                                        <option value="dozen">dozen</option>
                                        <option value="packet">packet</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="stock" class="form-label">Initial Stock *</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" id="editProductId" name="product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="editProductName" readonly>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editPrice" class="form-label">Price *</label>
                                    <input type="number" class="form-control" id="editPrice" name="price" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editStock" class="form-label">Stock *</label>
                                    <input type="number" class="form-control" id="editStock" name="stock" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                                <label class="form-check-label" for="editIsActive">
                                    Product is active (visible to vendors)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editPrice').value = product.price;
            document.getElementById('editStock').value = product.stock;
            document.getElementById('editDescription').value = product.description || '';
            document.getElementById('editIsActive').checked = product.is_active == 1;
            
            const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        }
        
        // Supplier Location Tracking
        async function updateSupplierLocation() {
            const btn = document.getElementById('locationBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Getting location...';
            btn.disabled = true;
            
            try {
                const position = await getCurrentLocation();
                
                // Update location in database
                const response = await fetch('../php/update_location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        latitude: position.latitude,
                        longitude: position.longitude
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    btn.innerHTML = '✅ Location Set';
                    btn.className = 'btn btn-success btn-sm mb-2';
                    
                    // Show success message
                    showAlert('success', `Store location updated successfully! Lat: ${position.latitude.toFixed(6)}, Lng: ${position.longitude.toFixed(6)}`);
                } else {
                    throw new Error(result.error || 'Failed to update location');
                }
            } catch (error) {
                btn.innerHTML = '❌ Location Error';
                btn.className = 'btn btn-danger btn-sm mb-2';
                showAlert('danger', 'Error updating location: ' + error.message);
            } finally {
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.className = 'btn btn-light btn-sm mb-2';
                    btn.disabled = false;
                }, 3000);
            }
        }
        
        // Image Preview Function
        document.getElementById('product_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                // Check file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    showAlert('danger', 'File size must be less than 5MB');
                    e.target.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showAlert('danger', 'Please select a valid image file (JPG, PNG, or GIF)');
                    e.target.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Show alert function
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Insert alert at the top of the container
            const container = document.querySelector('.container.py-4');
            container.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
        
        // Get current location (helper function)
        function getCurrentLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation is not supported by this browser.'));
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        });
                    },
                    (error) => {
                        let errorMessage;
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = "Location access denied. Please allow location access and try again.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage = "Location information is unavailable.";
                                break;
                            case error.TIMEOUT:
                                errorMessage = "Location request timed out.";
                                break;
                            default:
                                errorMessage = "An unknown error occurred.";
                                break;
                        }
                        reject(new Error(errorMessage));
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000
                    }
                );
            });
        }
        
        // Stock Management Functions
        function openStockModal(product) {
            document.getElementById('stockProductId').value = product.id;
            document.getElementById('stockProductName').textContent = product.name;
            document.getElementById('stockProductPrice').textContent = '$' + parseFloat(product.price).toFixed(2) + '/' + product.unit;
            document.getElementById('newStock').value = product.stock;
            document.getElementById('currentStock').textContent = product.stock + ' ' + product.unit;
            document.getElementById('productStatus').value = product.is_active == 1 ? 'active' : 'inactive';
            
            const stockModal = new bootstrap.Modal(document.getElementById('stockModal'));
            stockModal.show();
        }
        
        function adjustStock(change) {
            const stockInput = document.getElementById('newStock');
            let currentValue = parseInt(stockInput.value) || 0;
            let newValue = Math.max(0, currentValue + change);
            stockInput.value = newValue;
            
            // Auto-update status based on stock
            const statusSelect = document.getElementById('productStatus');
            if (newValue === 0) {
                statusSelect.value = 'inactive';
            }
        }
        
        function setStock(amount) {
            const stockInput = document.getElementById('newStock');
            const statusSelect = document.getElementById('productStatus');
            
            stockInput.value = amount;
            
            if (amount === 0) {
                statusSelect.value = 'inactive';
                showAlert('warning', 'Stock set to 0 - Status changed to Sold Out');
            } else {
                statusSelect.value = 'active';
                showAlert('info', `Stock set to ${amount} - Status changed to Active`);
            }
        }
        
        function addStock(amount) {
            const stockInput = document.getElementById('newStock');
            const currentValue = parseInt(stockInput.value) || 0;
            const newValue = currentValue + amount;
            stockInput.value = newValue;
            
            // Auto-activate if adding stock
            const statusSelect = document.getElementById('productStatus');
            statusSelect.value = 'active';
            showAlert('success', `Added ${amount} to stock. Total: ${newValue}`);
        }
        
        // Auto-refresh page every 30 seconds to check for new orders
        setInterval(function() {
            // Check for new pending orders
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const pendingOrdersSection = doc.querySelector('.card.border-warning');
                    const currentPendingSection = document.querySelector('.card.border-warning');
                    
                    if (pendingOrdersSection && !currentPendingSection) {
                        // New pending order arrived
                        showAlert('info', '📦 New order received! Please check the pending orders section.');
                        // Optionally reload the page
                        setTimeout(() => window.location.reload(), 2000);
                    }
                });
        }, 30000); // Check every 30 seconds
        
        // Order Management Functions
        function acceptOrder(orderId) {
            if (document.getElementById('acceptOrderModal')) {
                document.getElementById('acceptOrderId').value = orderId;
                new bootstrap.Modal(document.getElementById('acceptOrderModal')).show();
            } else {
                // Fallback for simple acceptance
                if (confirm('Accept this order? You can set delivery details in the full order management page.')) {
                    updateOrderStatus(orderId, 'accepted');
                }
            }
        }
        
        function submitAcceptOrder() {
            const formData = new FormData(document.getElementById('acceptOrderForm'));
            
            fetch('../php/accept_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    if (document.getElementById('acceptOrderModal')) {
                        bootstrap.Modal.getInstance(document.getElementById('acceptOrderModal')).hide();
                    }
                    location.reload(); // Reload to show updated status
                } else {
                    alert('❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error accepting order');
            });
        }
        
        function showRejectModal(orderId) {
            if (document.getElementById('rejectOrderModal')) {
                document.getElementById('rejectOrderId').value = orderId;
                new bootstrap.Modal(document.getElementById('rejectOrderModal')).show();
            } else {
                // Fallback to prompt
                const reason = prompt('Please provide a reason for rejection:', 'Unable to fulfill at this time');
                if (reason !== null) {
                    if (confirm('Are you sure you want to reject this order?')) {
                        const formData = new FormData();
                        formData.append('order_id', orderId);
                        formData.append('rejection_reason', reason || 'Supplier unavailable');
                        
                        fetch('../php/reject_order.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('✅ ' + data.message);
                                location.reload();
                            } else {
                                alert('❌ Error: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('❌ Error rejecting order');
                        });
                    }
                }
            }
        }
        
        function submitRejectOrder() {
            const formData = new FormData(document.getElementById('rejectOrderForm'));
            const reason = formData.get('rejection_reason');
            const customReason = formData.get('custom_reason');
            
            // Combine reason and custom reason if needed
            let finalReason = reason;
            if (reason === 'Other' && customReason) {
                finalReason = customReason;
            } else if (reason && customReason) {
                finalReason = reason + ': ' + customReason;
            }
            
            // Update the form data with the final reason
            formData.set('rejection_reason', finalReason);
            
            fetch('../php/reject_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    if (document.getElementById('rejectOrderModal')) {
                        bootstrap.Modal.getInstance(document.getElementById('rejectOrderModal')).hide();
                    }
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error rejecting order');
            });
        }
        
        // Quick Order Management Functions (Legacy - keeping for compatibility)
        function acceptQuickOrder(orderId) {
            acceptOrder(orderId); // Use the new modal function
        }
        
        function rejectQuickOrder(orderId) {
            showRejectModal(orderId); // Use the new modal function
        }
        
        // Set minimum date to today for delivery date picker
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('deliveryDate')) {
                document.getElementById('deliveryDate').min = today;
            }
        });
        
        function updateOrderStatus(orderId, status) {
            fetch('../php/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Order status updated successfully!');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert('danger', 'Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error updating order status');
            });
        }

        // Profile Management Functions
        function openProfileTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        function submitProfile() {
            const formData = new FormData(document.getElementById('profileForm'));
            formData.append('action', 'update_profile');
            
            fetch('../php/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error updating profile');
            });
        }

        function submitPassword() {
            const formData = new FormData(document.getElementById('passwordForm'));
            formData.append('action', 'change_password');
            
            fetch('../php/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    document.getElementById('passwordForm').reset();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error changing password');
            });
        }

        function previewProfilePhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePhotoPreview').src = e.target.result;
                    document.getElementById('photoPreviewContainer').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Show profile tab on modal open
        document.addEventListener('DOMContentLoaded', function() {
            const profileModal = document.getElementById('profileModal');
            if (profileModal) {
                profileModal.addEventListener('shown.bs.modal', function() {
                    const firstTab = document.querySelector('.tab-link');
                    if (firstTab) firstTab.click();
                });
            }
        });
    </script>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tab Navigation -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary tab-link" onclick="openProfileTab(event, 'profileTab')">
                                    👤 Profile Information
                                </button>
                                <button type="button" class="btn btn-outline-primary tab-link" onclick="openProfileTab(event, 'passwordTab')">
                                    🔒 Change Password
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information Tab -->
                    <div id="profileTab" class="tab-content">
                        <!-- Profile Display Section -->
                        <div class="row mb-4">
                            <div class="col-md-4 text-center">
                                <!-- Current Profile Photo -->
                                <div class="profile-photo-container mb-3">
                                    <?php 
                                    $profile_photo = '';
                                    if (!empty($user_profile['shop_logo'])) {
                                        // Check if file exists in profiles directory first
                                        if (file_exists("../uploads/profiles/" . $user_profile['shop_logo'])) {
                                            $profile_photo = "../uploads/profiles/" . $user_profile['shop_logo'];
                                        } 
                                        // Check in shop_logos directory (for legacy/registration uploads)
                                        elseif (file_exists("../uploads/shop_logos/" . $user_profile['shop_logo'])) {
                                            $profile_photo = "../uploads/shop_logos/" . $user_profile['shop_logo'];
                                        }
                                    }
                                    
                                    if ($profile_photo): ?>
                                        <img src="<?php echo htmlspecialchars($profile_photo); ?>" 
                                             alt="Profile Photo" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 120px; height: 120px; font-size: 48px; color: #6c757d;">
                                            🏪
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($user_profile['name'] ?? ''); ?></h5>
                                <p class="text-muted mb-0"><?php echo ucfirst($user_profile['role'] ?? 'supplier'); ?></p>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Email:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($user_profile['email'] ?? 'Not provided'); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Phone:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($user_profile['phone'] ?? 'Not provided'); ?></span>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <strong>Business Name:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($user_profile['business_name'] ?? 'Not provided'); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>City:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($user_profile['city'] ?? 'Not provided'); ?></span>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <strong>Address:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($user_profile['address'] ?? 'Not provided'); ?></span>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <strong>Member Since:</strong><br>
                                        <span class="text-muted"><?php echo date('F j, Y', strtotime($user_profile['created_at'] ?? '')); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Status:</strong><br>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Edit Profile</h6>
                        
                        <form id="profileForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" 
                                               value="<?php echo htmlspecialchars($user_profile['name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" name="profile_photo" 
                                       accept="image/*" onchange="previewProfilePhoto(this)">
                                <small class="text-muted">Accepted formats: JPG, PNG, GIF (Max 5MB)</small>
                                
                                <!-- Preview Container -->
                                <div id="photoPreviewContainer" class="mt-2" style="display: none;">
                                    <img id="profilePhotoPreview" class="img-thumbnail" style="max-width: 100px;" alt="Preview">
                                    <small class="d-block text-muted">New photo preview</small>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="button" class="btn btn-primary" onclick="submitProfile()">
                                    💾 Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="button" class="btn btn-primary" onclick="submitProfile()">
                                    💾 Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Tab -->
                    <div id="passwordTab" class="tab-content" style="display: none;">
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" name="current_password" required>
                                <small class="text-muted">Enter your current password for verification</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                <small class="text-muted">Must be at least 6 characters long</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                            
                            <div class="text-end">
                                <button type="button" class="btn btn-primary" onclick="submitPassword()">
                                    🔒 Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Accept Order Modal -->
    <div class="modal fade" id="acceptOrderModal" tabindex="-1" aria-labelledby="acceptOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="acceptOrderModalLabel">Accept Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="acceptOrderForm">
                        <input type="hidden" id="acceptOrderId" name="order_id">
                        
                        <div class="mb-3">
                            <label for="deliveryDate" class="form-label">Delivery Date</label>
                            <input type="date" class="form-control" id="deliveryDate" name="delivery_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deliveryTime" class="form-label">Delivery Time</label>
                            <input type="time" class="form-control" id="deliveryTime" name="delivery_time" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deliveryLocation" class="form-label">Delivery Location</label>
                            <textarea class="form-control" id="deliveryLocation" name="delivery_location" rows="3" 
                                      placeholder="Enter pickup/delivery location details" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deliveryInstructions" class="form-label">Additional Instructions (Optional)</label>
                            <textarea class="form-control" id="deliveryInstructions" name="delivery_instructions" rows="2" 
                                      placeholder="Any special instructions for the vendor"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitAcceptOrder()">Accept Order</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reject Order Modal -->
    <div class="modal fade" id="rejectOrderModal" tabindex="-1" aria-labelledby="rejectOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectOrderModalLabel">Reject Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectOrderForm">
                        <input type="hidden" id="rejectOrderId" name="order_id">
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Warning:</strong> Rejecting this order will send an email notification to the vendor with your reason.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label">Reason for Rejection</label>
                            <select class="form-control" id="rejectionReason" name="rejection_reason" required>
                                <option value="">Select a reason...</option>
                                <option value="Out of stock">Out of stock</option>
                                <option value="Unable to deliver to location">Unable to deliver to location</option>
                                <option value="Quantity too large">Quantity too large</option>
                                <option value="Temporarily unavailable">Temporarily unavailable</option>
                                <option value="Payment method not supported">Payment method not supported</option>
                                <option value="Other">Other (please specify below)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="customReason" class="form-label">Additional Details (Optional)</label>
                            <textarea class="form-control" id="customReason" name="custom_reason" rows="3" 
                                      placeholder="Provide additional details about why you cannot fulfill this order"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitRejectOrder()">Reject Order</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
