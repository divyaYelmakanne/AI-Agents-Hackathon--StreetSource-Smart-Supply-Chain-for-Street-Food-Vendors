<?php
include '../php/db.php';
requireRole('vendor');

// Get vendor's orders
$database = new Database();
$db = $database->getConnection();

try {
    $vendor_id = $_SESSION['user_id'];
    
    $query = "SELECT o.*, p.name as product_name, p.unit, p.image_url, u.name as supplier_name, u.phone as supplier_phone
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.supplier_id = u.id
              WHERE o.vendor_id = :vendor_id
              ORDER BY o.order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - StreetSource</title>
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="myorders.php">My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="review.php">Reviews</a>
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
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>📦 My Orders</h2>
                    <a href="dashboard.php" class="btn btn-primary">+ Place New Order</a>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div style="font-size: 4rem; opacity: 0.3;">📦</div>
                            <h4 class="text-muted">No Orders Yet</h4>
                            <p class="text-muted">Start ordering from nearby suppliers to see your order history here.</p>
                            <a href="dashboard.php" class="btn btn-primary">Browse Suppliers</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Orders List -->
                    <div class="row g-4">
                        <?php foreach ($orders as $order): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span class="fw-bold">Order #<?php echo $order['id']; ?></span>
                                        <span class="order-status status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($order['image_url']): ?>
                                        <img src="../uploads/products/<?php echo htmlspecialchars($order['image_url']); ?>" 
                                             class="card-img-top" style="height: 150px; object-fit: cover;" 
                                             alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($order['product_name']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">Supplier:</small><br>
                                            <strong><?php echo htmlspecialchars($order['supplier_name']); ?></strong><br>
                                            <small class="text-muted">📞 <?php echo htmlspecialchars($order['supplier_phone']); ?></small>
                                        </p>
                                        
                                        <!-- Delivery Information -->
                                        <?php if (!empty($order['delivery_option'])): ?>
                                            <div class="mb-3 p-2 bg-light rounded">
                                                <small class="text-muted">🚚 Delivery:</small><br>
                                                <small class="fw-bold">
                                                    <?php 
                                                    switch($order['delivery_option']) {
                                                        case 'asap':
                                                            echo '⚡ ASAP (within 30 minutes)';
                                                            break;
                                                        case 'today':
                                                            echo '📅 Same day delivery';
                                                            break;
                                                        case 'custom':
                                                            if (!empty($order['delivery_datetime'])) {
                                                                echo '📆 Scheduled: ' . date('M j, Y g:i A', strtotime($order['delivery_datetime']));
                                                            } else {
                                                                echo 'Scheduled delivery';
                                                            }
                                                            break;
                                                        default:
                                                            echo ucfirst($order['delivery_option']);
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Address -->
                                        <?php if (!empty($order['delivery_address'])): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">📍 Address:</small><br>
                                                <small><?php echo htmlspecialchars($order['delivery_address']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Special Instructions -->
                                        <?php if (!empty($order['special_instructions'])): ?>
                                            <div class="mb-3 p-2 bg-warning bg-opacity-10 rounded">
                                                <small class="text-muted">💬 Instructions:</small><br>
                                                <small><?php echo htmlspecialchars($order['special_instructions']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="row text-center border-top pt-3">
                                            <div class="col-4">
                                                <div class="small text-muted">Quantity</div>
                                                <div class="fw-bold"><?php echo $order['quantity'] . ' ' . $order['unit']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Total</div>
                                                <div class="fw-bold text-success"><?php echo formatCurrency($order['total_price']); ?></div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Date</div>
                                                <div class="fw-bold small"><?php echo date('M j', strtotime($order['order_date'])); ?></div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($order['notes']): ?>
                                            <div class="mt-3 p-2 bg-light rounded">
                                                <small class="text-muted">Notes:</small><br>
                                                <small><?php echo htmlspecialchars($order['notes']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
                                            </small>
                                            <div>
                                                <?php if ($order['status'] === 'delivered'): ?>
                                                    <a href="review.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                        ⭐ Review
                                                    </a>
                                                <?php elseif ($order['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="confirmCancelOrder(<?php echo $order['id']; ?>)">
                                                        ❌ Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order Statistics -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <h4 class="mb-3">📊 Order Summary</h4>
                        </div>
                        <?php
                        $total_orders = count($orders);
                        $pending_orders = count(array_filter($orders, function($o) { return $o['status'] === 'pending'; }));
                        $delivered_orders = count(array_filter($orders, function($o) { return $o['status'] === 'delivered'; }));
                        $total_spent = array_sum(array_column(array_filter($orders, function($o) { return $o['status'] === 'delivered'; }), 'total_price'));
                        ?>
                        
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $total_orders; ?></div>
                                <div class="stats-label">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $pending_orders; ?></div>
                                <div class="stats-label">Pending</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $delivered_orders; ?></div>
                                <div class="stats-label">Delivered</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo formatCurrency($total_spent); ?></div>
                                <div class="stats-label">Total Spent</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function confirmCancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                cancelOrder(orderId);
            }
        }
        
        async function cancelOrder(orderId) {
            try {
                const response = await fetch('../php/cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order_id: orderId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Order cancelled successfully');
                    location.reload();
                } else {
                    alert('❌ Error: ' + (result.error || 'Failed to cancel order'));
                }
            } catch (error) {
                alert('❌ Error cancelling order: ' + error.message);
            }
        }
    </script>
</body>
</html>
