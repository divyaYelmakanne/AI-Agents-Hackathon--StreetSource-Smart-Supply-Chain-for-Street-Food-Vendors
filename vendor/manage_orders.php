<?php
include '../php/db.php';
requireRole('vendor');

$database = new Database();
$db = $database->getConnection();

$vendor_id = $_SESSION['user_id'];

try {
    $query = "SELECT o.*, p.name as product_name, p.unit, u.name as supplier_name, u.business_name as supplier_business, u.phone as supplier_phone, u.email as supplier_email, u.address as supplier_address
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.supplier_id = u.id
              WHERE o.vendor_id = :vendor_id
              ORDER BY 
                CASE 
                    WHEN o.status = 'pending' THEN 1
                    WHEN o.status = 'accepted' THEN 2
                    WHEN o.status = 'delivered' THEN 3
                    WHEN o.status = 'cancelled' THEN 4
                END,
                o.order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Calculate total spend for delivered orders
    $delivered_orders = array_filter($orders, function($o) { return $o['status'] === 'delivered'; });
    $total_spend = array_reduce($delivered_orders, function($sum, $o) {
        return $sum + floatval($o['total_price']);
    }, 0);
} catch (PDOException $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .order-card { border-left: 4px solid #007bff; transition: all 0.3s ease; }
        .order-card.pending { border-left-color: #ffc107; }
        .order-card.accepted { border-left-color: #28a745; }
        .order-card.delivered { border-left-color: #6c757d; }
        .order-card.cancelled { border-left-color: #dc3545; }
        .supplier-info { background: #f8f9fa; border-radius: 8px; padding: 15px; }
        .status-badge { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">🍲 StreetSource</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🗂️ Manage Your Orders</h2>
            <div class="card bg-success text-white" style="min-width:200px;">
                <div class="card-body p-2">
                    <div class="fw-bold">Total Spend</div>
                    <div class="fs-4">₹<?php echo number_format($total_spend, 2); ?></div>
                </div>
            </div>
        </div>
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">No orders found.</div>
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
                                <div class="supplier-info">
                                    <div class="mb-2">
                                        <strong><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($order['supplier_name']); ?></strong>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-telephone-fill text-success"></i> <strong><?php echo $order['supplier_phone'] ?: 'Not provided'; ?></strong>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-envelope-fill text-primary"></i> <small><?php echo htmlspecialchars($order['supplier_email']); ?></small>
                                    </div>
                                    <div>
                                        <i class="bi bi-geo-alt-fill text-danger"></i> <small><?php echo htmlspecialchars($order['supplier_address'] ?: 'Not provided'); ?></small>
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
                                    <?php if (!empty($order['delivery_address'])): ?>
                                    <div class="mb-2">
                                        <strong>Delivery Address:</strong><br>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['notes'])): ?>
                                    <div class="mb-2">
                                        <strong>Notes:</strong><br>
                                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($order['notes']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'accepted' ? 'success' : ($order['status'] === 'delivered' ? 'secondary' : 'danger')); ?> fs-6 mb-3 text-uppercase"><?php echo $order['status']; ?></span>
                                <h5 class="text-primary">₹<?php echo number_format($order['total_price'], 2); ?></h5>
                                <small class="text-muted">Qty: <?php echo $order['quantity']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
