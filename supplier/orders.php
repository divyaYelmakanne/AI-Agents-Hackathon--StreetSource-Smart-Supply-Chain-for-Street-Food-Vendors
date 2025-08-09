
<?php
include '../php/db.php';
requireRole('supplier');

$database = new Database();
$db = $database->getConnection();

// Ensure 'read' column exists in orders table
try {
    $check_query = "SHOW COLUMNS FROM orders LIKE 'read'";
    $result = $db->query($check_query);
    if ($result->rowCount() == 0) {
        $db->exec("ALTER TABLE orders ADD COLUMN `read` TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    // Ignore if already exists
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $supplier_id = $_SESSION['user_id'];
    
    if ($_POST['action'] === 'update_status') {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];
        
        if (in_array($status, ['accepted', 'delivered', 'cancelled', 'read'])) {
            try {
                // Update order status
                if ($status === 'read') {
                    $query = "UPDATE orders o 
                              JOIN products p ON o.product_id = p.id 
                              SET o.read = 1
                              WHERE o.id = :order_id AND p.supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                } else {
                    $query = "UPDATE orders o 
                              JOIN products p ON o.product_id = p.id 
                              SET o.status = :status" . ($status === 'delivered' ? ", o.delivered_date = NOW()" : "") . "
                              WHERE o.id = :order_id AND p.supplier_id = :supplier_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                }
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Order status updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update order status.';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Failed to update order status.';
            }
        } else {
            $_SESSION['error'] = 'Invalid status.';
        }
    }
}

try {
    $supplier_id = $_SESSION['user_id'];
    
    // Get all orders for this supplier
    $query = "SELECT o.*, p.name as product_name, p.unit, u.name as vendor_name, u.phone as vendor_phone, 
              u.email as vendor_email, u.address as vendor_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.vendor_id = u.id
              WHERE p.supplier_id = :supplier_id
              ORDER BY 
                CASE 
                    WHEN o.status = 'pending' THEN 1
                    WHEN o.status = 'accepted' THEN 2
                    WHEN o.status = 'delivered' THEN 3
                    WHEN o.status = 'cancelled' THEN 4
                END,
                o.order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $pending_orders = array_filter($orders, function($o) { return $o['status'] === 'pending'; });
    $accepted_orders = array_filter($orders, function($o) { return $o['status'] === 'accepted'; });
    $delivered_orders = array_filter($orders, function($o) { return $o['status'] === 'delivered'; });
    $cancelled_orders = array_filter($orders, function($o) { return $o['status'] === 'cancelled'; });
    // Calculate total revenue from delivered orders
    $total_revenue = array_reduce($delivered_orders, function($sum, $o) {
        return $sum + floatval($o['total_price']);
    }, 0);
} catch (PDOException $e) {
    $orders = [];
    $pending_orders = [];
    $accepted_orders = [];
    $delivered_orders = [];
    $cancelled_orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Orders - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .order-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .order-card.pending {
            border-left-color: #ffc107;
        }
        .order-card.accepted {
            border-left-color: #28a745;
        }
        .order-card.delivered {
            border-left-color: #6c757d;
        }
        .order-card.cancelled {
            border-left-color: #dc3545;
        }
        .vendor-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .product-image {
            /* removed product-image style as image is no longer shown */
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        .action-buttons .btn {
            margin: 2px;
        }
        .contact-info {
            font-size: 0.9rem;
            padding: 4px 0;
        }
        .contact-info i {
            width: 16px;
            margin-right: 8px;
        }
        .order-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
        }
        .contact-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .contact-row i {
            width: 20px;
        }
        .vendor-contact-info {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #dee2e6;
        }
        
        /* Dropdown menu styling */
        .dropdown-menu {
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
    </style>
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
                        <a class="nav-link active" href="orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../php/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>📋 Order Management</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">← Back to Dashboard</a>
                </div>

                <!-- Order Statistics (PHP) -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($pending_orders); ?></div>
                            <div class="stats-label">Pending Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($accepted_orders); ?></div>
                            <div class="stats-label">Accepted Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($delivered_orders); ?></div>
                            <div class="stats-label">Delivered Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-success text-white">
                            <div class="stats-number">₹<?php echo number_format($total_revenue, 2); ?></div>
                            <div class="stats-label">Total Revenue</div>
                        </div>
                    </div>
                </div>

                <!-- PHP-rendered order list -->
                <div class="mt-4">
                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info">No orders found for this supplier.</div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="card order-card <?php echo $order['status']; ?> mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-2 text-center">
                                            <small class="d-block mt-1 text-muted">Order #<?php echo $order['id']; ?></small>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($order['product_name']); ?></h6>
                                            <div class="vendor-info">
                                                <div class="mb-2">
                                                    <strong><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($order['vendor_name']); ?></strong>
                                                </div>
                                                <div class="contact-info mb-2">
                                                    <i class="bi bi-telephone-fill text-success"></i> 
                                                    <strong><?php echo $order['vendor_phone'] ?: 'Not provided'; ?></strong>
                                                </div>
                                                <div class="contact-info mb-2">
                                                    <i class="bi bi-envelope-fill text-primary"></i> 
                                                    <small><?php echo htmlspecialchars($order['vendor_email']); ?></small>
                                                </div>
                                                <div class="contact-info">
                                                    <i class="bi bi-geo-alt-fill text-danger"></i> 
                                                    <small><?php echo htmlspecialchars($order['vendor_address'] ?: 'Not provided'); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="order-details">
                                                <div class="mb-2">
                                                    <strong>Order Date:</strong><br>
                                                    <span class="badge bg-info"><?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Payment Method:</strong><br>
                                                    <span class="badge bg-success"><?php echo strtoupper($order['payment_method'] ?? 'COD'); ?></span>
                                                </div>
                                                <?php if (!empty($order['delivery_option'])): ?>
                                                <div class="mb-2">
                                                    <strong>Delivery Type:</strong><br>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($order['delivery_option']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($order['special_instructions'])): ?>
                                                <div class="mb-2">
                                                    <strong>Notes:</strong><br>
                                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($order['special_instructions']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'accepted' ? 'success' : ($order['status'] === 'delivered' ? 'secondary' : 'danger')); ?> fs-6 mb-3 text-uppercase"><?php echo $order['status']; ?></span>
                                            <?php if (isset($order['read']) && $order['read'] == 0): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="status" value="read">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2" title="Mark as Read">
                                                        <i class="bi bi-envelope-open"></i> Mark as Read
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($order['status'] === 'accepted'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="status" value="delivered">
                                                    <button type="submit" class="btn btn-warning btn-sm mt-2" title="Mark as Delivered">
                                                        <i class="bi bi-truck"></i> Mark as Delivered
                                                    </button>
                                                </form>
                                            <?php elseif ($order['status'] === 'delivered'): ?>
                                                <button class="btn btn-secondary btn-sm mt-2" disabled title="Order Delivered">
                                                    <i class="bi bi-check2-circle"></i> Delivered
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Order Details Modal (JS-driven) -->
                <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="orderDetailsContent">
                                <!-- Order details will be loaded here by JS -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                <form id="rejectOrderForm">
                    <div class="modal-body">
                        <input type="hidden" id="rejectOrderId" name="order_id">
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3" required placeholder="Please provide a reason for rejecting this order"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Order details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                <form id="acceptOrderForm">
                    <div class="modal-body">
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Accept Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrders = [];
        
        // Load orders on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadOrders();
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('deliveryDate')) {
                document.getElementById('deliveryDate').min = today;
            }
            
            // Filter buttons
            document.querySelectorAll('input[name="statusFilter"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    filterOrders(this.value);
                });
            });
        });
        
        function loadOrders() {
            fetch('../php/get_supplier_orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentOrders = data.orders;
                        updateStatistics(currentOrders);
                        displayOrders(currentOrders);
                        if (currentOrders.length === 0) {
                            showError('No orders found for this supplier.');
                        }
                    } else {
                        showError('Error: ' + (data.error || 'No orders found'));
                    }
                })
                .catch(error => {
                    console.error('Error loading orders:', error);
                    showError('AJAX Error: ' + error);
                });
        }
        
        function updateStatistics(orders) {
            const pending = orders.filter(o => o.status === 'pending').length;
            const accepted = orders.filter(o => o.status === 'accepted').length;
            const delivered = orders.filter(o => o.status === 'delivered').length;
            const revenue = orders.filter(o => o.status === 'delivered')
                                 .reduce((sum, o) => sum + parseFloat(o.total_price), 0);
            
            if (document.getElementById('pendingCount')) {
                document.getElementById('pendingCount').textContent = pending;
                document.getElementById('acceptedCount').textContent = accepted;
                document.getElementById('deliveredCount').textContent = delivered;
                document.getElementById('totalRevenue').textContent = '₹' + revenue.toFixed(2);
            }
        }
        
        function displayOrders(orders) {
            const container = document.getElementById('ordersContainer');
            if (!container) return;
            
            if (orders.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No orders found</div>';
                return;
            }
            
            const ordersHtml = orders.map(order => `
                <div class="card order-card ${order.status} mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 text-center">
                                <img src="../${order.product_image || 'assets/images/no-image.png'}" 
                                     alt="${order.product_name}" class="product-image">
                                <small class="d-block mt-1 text-muted">Order #${order.id}</small>
                            </div>
                            <div class="col-md-4">
                                <h6 class="mb-2">${order.product_name}</h6>
                                <div class="vendor-info">
                                    <div class="mb-2">
                                        <strong><i class="bi bi-person-circle"></i> ${order.vendor_name}</strong>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="contact-info mb-2">
                                                <i class="bi bi-telephone-fill text-success"></i> 
                                                <strong>${order.vendor_phone || 'Not provided'}</strong>
                                            </div>
                                            <div class="contact-info mb-2">
                                                <i class="bi bi-envelope-fill text-primary"></i> 
                                                <small>${order.vendor_email}</small>
                                            </div>
                                            <div class="contact-info">
                                                <i class="bi bi-geo-alt-fill text-danger"></i> 
                                                <small>${order.vendor_address || 'Not provided'}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="order-details">
                                    <div class="mb-2">
                                        <strong>Order Date:</strong><br>
                                        <span class="badge bg-info">${new Date(order.order_date).toLocaleDateString()} ${new Date(order.order_date).toLocaleTimeString()}</span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Payment Method:</strong><br>
                                        <span class="badge bg-success">${order.payment_method?.toUpperCase() || 'COD'}</span>
                                    </div>
                                    ${order.delivery_option ? `
                                        <div class="mb-2">
                                            <strong>Delivery Type:</strong><br>
                                            <span class="badge bg-secondary">${order.delivery_option?.toUpperCase()}</span>
                                        </div>
                                    ` : ''}
                                    ${order.delivery_datetime ? `
                                        <div class="mb-2">
                                            <strong>Needed By:</strong><br>
                                            <span class="badge bg-warning text-dark">${new Date(order.delivery_datetime).toLocaleDateString()} ${new Date(order.delivery_datetime).toLocaleTimeString()}</span>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="col-md-1 text-center">
                                <h5 class="text-primary">₹${parseFloat(order.total_price).toFixed(2)}</h5>
                                <small class="text-muted">Qty: ${order.quantity}</small><br>
                                <span class="badge status-badge bg-${getStatusColor(order.status)}">${order.status.toUpperCase()}</span>
                            </div>
                            <div class="col-md-2 text-center action-buttons">
                                <div class="d-grid gap-1">
                                    ${order.read == 0 ? `
                                        <button class="btn btn-outline-primary btn-sm" onclick="markAsRead(${order.id})" title="Mark as Read">
                                            <i class="bi bi-envelope-open"></i> Mark as Read
                                        </button>
                                    ` : ''}
        function markAsRead(orderId) {
            updateOrderStatus(orderId, 'read');
        }
                                    <button class="btn btn-info btn-sm" onclick="viewOrderDetails(${order.id})" title="View All Details Filled by Vendor">
                                        <i class="bi bi-file-text"></i> Details
                                    </button>
                                    <button class="btn btn-success btn-sm" onclick="acceptOrder(${order.id})" title="Accept Order" ${order.status !== 'pending' ? 'disabled' : ''}>
                                        <i class="bi bi-check-circle"></i> Accept
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectOrder(${order.id})" title="Reject Order" ${order.status !== 'pending' ? 'disabled' : ''}>
                                        <i class="bi bi-x-circle"></i> Reject
                                    </button>
                                    ${order.status === 'accepted' ? `
                                        <button class="btn btn-warning btn-sm" onclick="markDelivered(${order.id})" title="Mark as Delivered">
                                            <i class="bi bi-truck"></i> Mark as Delivered
                                        </button>
                                    ` : ''}
                                    ${order.status === 'delivered' ? `
                                        <button class="btn btn-secondary btn-sm" disabled title="Order Delivered">
                                            <i class="bi bi-check2-circle"></i> Delivered
                                        </button>
                                    ` : ''}
                                    ${order.vendor_phone ? `
                                        <a href="tel:${order.vendor_phone}" class="btn btn-outline-success btn-sm" title="Call Vendor">
                                            <i class="bi bi-telephone"></i> Call
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        ${order.special_instructions ? `
                            <div class="mt-2 p-2 bg-light rounded">
                                <small><strong>Vendor Instructions:</strong> ${order.special_instructions}</small>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = ordersHtml;
        }
        
        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'accepted': 'success',
                'delivered': 'info',
                'cancelled': 'danger'
            };
            return colors[status] || 'secondary';
        }
        
        function filterOrders(status) {
            if (status === 'all') {
                displayOrders(currentOrders);
            } else {
                const filtered = currentOrders.filter(order => order.status === status);
                displayOrders(filtered);
            }
        }
        
        function viewOrderDetails(orderId) {
            const order = currentOrders.find(o => o.id == orderId);
            if (!order) return;
            
            const modalContent = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-box-seam"></i> Product Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="../${order.product_image || 'assets/images/no-image.png'}" 
                                         alt="${order.product_name}" style="width: 100px; height: 100px; object-fit: cover;" class="rounded me-3">
                                    <div>
                                        <h5>${order.product_name}</h5>
                                        <p class="mb-1"><strong>Price:</strong> ₹${parseFloat(order.product_price || (order.total_price / order.quantity)).toFixed(2)}/${order.unit || 'unit'}</p>
                                        <p class="mb-1"><strong>Quantity:</strong> ${order.quantity} ${order.unit || 'units'}</p>
                                        <p class="mb-0"><strong>Total:</strong> <span class="text-success fw-bold">₹${parseFloat(order.total_price).toFixed(2)}</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-receipt"></i> Order Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <p><strong>Order ID:</strong><br><span class="badge bg-dark">#${order.id}</span></p>
                                        <p><strong>Order Date:</strong><br>${new Date(order.order_date).toLocaleDateString()} at ${new Date(order.order_date).toLocaleTimeString()}</p>
                                        <p><strong>Status:</strong><br><span class="badge bg-${getStatusColor(order.status)}">${order.status.toUpperCase()}</span></p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>Payment Method:</strong><br><span class="badge bg-success">${order.payment_method?.toUpperCase() || 'CASH ON DELIVERY'}</span></p>
                                        ${order.delivery_date ? `<p><strong>Delivery Date:</strong><br><span class="text-primary">${order.delivery_date}</span></p>` : ''}
                                        ${order.delivery_time ? `<p><strong>Delivery Time:</strong><br><span class="text-primary">${order.delivery_time}</span></p>` : ''}
                                    </div>
                                </div>
                                ${order.notes ? `
                                    <div class="alert alert-light border">
                                        <strong><i class="bi bi-chat-left-text"></i> Special Instructions:</strong><br>
                                        ${order.notes}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-person-circle"></i> Vendor Information</h6>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">${order.vendor_name}</h6>
                                <div class="vendor-contact-info">
                                    <div class="contact-row mb-2 p-2 bg-light rounded">
                                        <i class="bi bi-envelope-fill text-primary"></i> 
                                        <strong>Email:</strong> <a href="mailto:${order.vendor_email}">${order.vendor_email}</a>
                                    </div>
                                    <div class="contact-row mb-2 p-2 bg-light rounded">
                                        <i class="bi bi-telephone-fill text-success"></i> 
                                        <strong>Phone:</strong> ${order.vendor_phone ? `<a href="tel:${order.vendor_phone}">${order.vendor_phone}</a>` : 'Not provided'}
                                    </div>
                                    <div class="contact-row mb-2 p-2 bg-light rounded">
                                        <i class="bi bi-geo-alt-fill text-danger"></i> 
                                        <strong>Address:</strong> ${order.vendor_address || 'Not provided'}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="mailto:${order.vendor_email}" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-envelope"></i> Send Email
                                    </a>
                                    ${order.vendor_phone ? `
                                        <a href="tel:${order.vendor_phone}" class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-telephone"></i> Call Now
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        ${order.delivery_address || order.customer_phone ? `
                            <div class="card mb-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Delivery Details</h6>
                                </div>
                                <div class="card-body">
                                    ${order.delivery_address ? `
                                        <div class="mb-3">
                                            <strong><i class="bi bi-house-door"></i> Delivery Address:</strong><br>
                                            <div class="p-2 bg-light rounded">${order.delivery_address}</div>
                                        </div>
                                    ` : ''}
                                    ${order.customer_phone ? `
                                        <div class="mb-3">
                                            <strong><i class="bi bi-person-check"></i> Customer Contact:</strong><br>
                                            <a href="tel:${order.customer_phone}" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-telephone"></i> ${order.customer_phone}
                                            </a>
                                        </div>
                                    ` : ''}
                                    ${order.delivery_date && order.delivery_time ? `
                                        <div class="alert alert-warning">
                                            <strong><i class="bi bi-clock"></i> Requested Delivery:</strong><br>
                                            ${order.delivery_date} at ${order.delivery_time}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${order.status === 'pending' ? `
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="acceptOrder(${order.id}); bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();">
                                            <i class="bi bi-check-circle"></i> Accept This Order
                                        </button>
                                        <button class="btn btn-danger" onclick="rejectOrder(${order.id}); bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();">
                                            <i class="bi bi-x-circle"></i> Reject This Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ` : order.status === 'accepted' ? `
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Delivery Action</h6>
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-info w-100" onclick="markDelivered(${order.id}); bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();">
                                        <i class="bi bi-check2-square"></i> Mark as Delivered
                                    </button>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            if (document.getElementById('orderDetailsContent')) {
                document.getElementById('orderDetailsContent').innerHTML = modalContent;
                new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
            } else {
                alert('Order Details: ' + JSON.stringify(order, null, 2));
            }
        }
        
        function acceptOrder(orderId) {
            if (document.getElementById('acceptOrderModal')) {
                document.getElementById('acceptOrderId').value = orderId;
                new bootstrap.Modal(document.getElementById('acceptOrderModal')).show();
            } else {
                // Fallback for existing interface
                if (confirm('Accept this order?')) {
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
                    alert('Order accepted successfully! Vendor has been notified via email.');
                    if (document.getElementById('acceptOrderModal')) {
                        bootstrap.Modal.getInstance(document.getElementById('acceptOrderModal')).hide();
                    }
                    loadOrders(); // Reload orders
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error accepting order');
            });
        }
        
        function rejectOrder(orderId) {
            showRejectModal(orderId);
        }
        
        function showRejectModal(orderId) {
            if (document.getElementById('rejectOrderModal')) {
                document.getElementById('rejectOrderId').value = orderId;
                new bootstrap.Modal(document.getElementById('rejectOrderModal')).show();
            } else {
                // Fallback to prompt if modal not available
                const reason = prompt('Please provide a reason for rejection:', 'Unable to fulfill this order at the moment');
                if (reason !== null) { // User didn't cancel the prompt
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
                                window.location.reload(); // Reload to show updated status
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
                    window.location.reload(); // Reload to show updated status
                } else {
                    alert('❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error rejecting order');
            });
        }
        
        function markDelivered(orderId) {
            if (confirm('Mark this order as delivered?')) {
                updateOrderStatus(orderId, 'delivered');
            }
        }
        
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
                    alert('Order status updated successfully!');
                    loadOrders();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating order status');
            });
        }
        
        function showError(message) {
            const container = document.getElementById('ordersContainer');
            if (container) {
                container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
            }
            // Also log to console for debugging
            console.error('Order Page Error:', message);
        }
    </script>
    <script src="../assets/js/main.js"></script>
    <script>
        async function updateOrderStatus(orderId, status) {
            if (!confirm('Are you sure you want to ' + status + ' this order?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('order_id', orderId);
                formData.append('status', status);
                
                const response = await fetch('orders.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Reload the page to see updated status
                window.location.reload();
            } catch (error) {
                alert('Error updating order status: ' + error.message);
            }
        }
    </script>
    
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
