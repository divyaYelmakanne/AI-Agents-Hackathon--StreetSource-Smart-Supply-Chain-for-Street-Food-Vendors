<?php
include '../php/db.php';
requireRole('supplier');

$database = new Database();
$db = $database->getConnection();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $supplier_id = $_SESSION['user_id'];
    
    if ($_POST['action'] === 'update_status') {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];
        
        if (in_array($status, ['accepted', 'delivered', 'cancelled'])) {
            try {
                // Update order status
                $query = "UPDATE orders o 
                          JOIN products p ON o.product_id = p.id 
                          SET o.status = :status" . ($status === 'delivered' ? ", o.delivered_date = NOW()" : "") . "
                          WHERE o.id = :order_id AND p.supplier_id = :supplier_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
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
            width: 80px;
            height: 80px;
            object-fit: cover;
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
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">🏪 StreetSource Supplier</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Products</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
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

                <!-- Order Statistics -->
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
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($orders); ?></div>
                            <div class="stats-label">Total Orders</div>
                        </div>
                    </div>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div style="font-size: 4rem; opacity: 0.3;">📋</div>
                            <h4 class="text-muted">No Orders Yet</h4>
                            <p class="text-muted">Orders from vendors will appear here. Make sure your products are active and well-stocked.</p>
                            <a href="dashboard.php" class="btn btn-primary">Manage Products</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Orders List -->
                    <div class="orders-container">
                        <?php foreach ($orders as $order): ?>
                            <div class="card order-card <?php echo $order['status']; ?> mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6 class="fw-bold"><?php echo htmlspecialchars($order['product_name']); ?></h6>
                                            <p class="mb-1"><strong>Quantity:</strong> <?php echo $order['quantity']; ?> units</p>
                                            <p class="mb-1"><strong>Total:</strong> ₹<?php echo number_format($order['total_price'], 2); ?></p>
                                            <p class="mb-1"><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></p>
                                            <p class="mb-1"><strong>Payment Method:</strong> 
                                                <span class="badge bg-success"><?php echo strtoupper($order['payment_method'] ?? 'COD'); ?></span>
                                            </p>
                                            <?php if ($order['special_instructions']): ?>
                                                <div class="mt-2 p-2 bg-light rounded">
                                                    <small><strong>Vendor Notes:</strong> 
                                                        <em><?php echo htmlspecialchars($order['special_instructions']); ?></em>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="vendor-info-card p-3 bg-light rounded">
                                                <h6 class="text-primary"><i class="bi bi-person-circle"></i> Vendor Details</h6>
                                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['vendor_name']); ?></p>
                                                <p class="mb-1">
                                                    <strong><i class="bi bi-telephone-fill text-success"></i> Mobile:</strong> 
                                                    <?php if ($order['vendor_phone']): ?>
                                                        <a href="tel:<?php echo $order['vendor_phone']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($order['vendor_phone']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong><i class="bi bi-envelope-fill text-primary"></i> Email:</strong> 
                                                    <a href="mailto:<?php echo $order['vendor_email']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($order['vendor_email']); ?>
                                                    </a>
                                                </p>
                                                <p class="mb-0">
                                                    <strong><i class="bi bi-geo-alt-fill text-danger"></i> Address:</strong> 
                                                    <?php echo htmlspecialchars($order['vendor_address'] ?? 'Not provided'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] === 'pending' ? 'warning' : 
                                                        ($order['status'] === 'accepted' ? 'success' : 
                                                        ($order['status'] === 'delivered' ? 'info' : 'danger')); 
                                                ?> fs-6 mb-3"><?php echo ucfirst($order['status']); ?></span>
                                                
                                                <?php if ($order['status'] === 'accepted' && $order['delivery_datetime']): ?>
                                                    <div class="delivery-info mb-3 p-2 bg-success bg-opacity-10 rounded">
                                                        <small class="d-block"><strong>Delivery Scheduled:</strong></small>
                                                        <small class="d-block text-success">
                                                            <?php echo date('M j, Y g:i A', strtotime($order['delivery_datetime'])); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <div class="d-grid gap-2">
                                                        <button type="button" class="btn btn-success btn-sm" 
                                                                onclick="acceptOrder(<?php echo $order['id']; ?>)">
                                                            ✅ Accept Order
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="showRejectModal(<?php echo $order['id']; ?>)">
                                                            ❌ Reject Order
                                                        </button>
                                                    </div>
                                                <?php elseif ($order['status'] === 'accepted'): ?>
                                                    <div class="d-grid gap-2">
                                                        <button type="button" class="btn btn-primary btn-sm" 
                                                                onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')">
                                                            🚚 Mark as Delivered
                                                        </button>
                                                    </div>
                                                <?php elseif ($order['status'] === 'delivered'): ?>
                                                    <button type="button" class="btn btn-success btn-sm" disabled>
                                                        ✓ Delivered Successfully
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                        Cancelled
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Contact Actions -->
                                                <div class="mt-3">
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('deliveryDate')) {
                document.getElementById('deliveryDate').min = today;
            }
        });
        
        function acceptOrder(orderId) {
            if (document.getElementById('acceptOrderModal')) {
                document.getElementById('acceptOrderId').value = orderId;
                new bootstrap.Modal(document.getElementById('acceptOrderModal')).show();
            } else {
                // Fallback for simple acceptance
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
                    window.location.reload(); // Reload to show updated status
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error accepting order');
            });
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
        
        function updateOrderStatus(orderId, status) {
            const confirmMessage = status === 'accepted' ? 'Accept this order?' : 
                                 status === 'delivered' ? 'Mark this order as delivered?' : 
                                 'Cancel this order?';
                                 
            if (!confirm(confirmMessage)) {
                return;
            }
            
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
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating order status');
            });
        }
    </script>
</body>
</html>
