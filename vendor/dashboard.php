<?php
session_start();

// Include database connection
include '../php/db.php';

// Simple role check function (if not defined elsewhere)
if (!function_exists('requireRole')) {
    function requireRole($role) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header('Location: ../login.php');
            exit();
        }
        if ($_SESSION['role'] !== $role) {
            header('Location: ../unauthorized.php');
            exit();
        }
    }
}

// Simple currency format function (if not defined elsewhere)
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₹' . number_format($amount, 2);
    }
}

requireRole('vendor');

// Get vendor statistics
$database = new Database();
$db = $database->getConnection();

try {
    // Get order statistics
    $vendor_id = $_SESSION['user_id'];
    
    $query = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status = 'delivered' THEN total_price ELSE 0 END) as total_spent
              FROM orders WHERE vendor_id = :vendor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent orders
    $query = "SELECT o.*, p.name as product_name, u.name as supplier_name, p.unit
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.supplier_id = u.id
              WHERE o.vendor_id = :vendor_id
              ORDER BY o.order_date DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user profile for profile modal
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $vendor_id);
    $stmt->execute();
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'delivered_orders' => 0, 'total_spent' => 0];
    $recent_orders = [];
    $user_profile = null;
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <title>Vendor Dashboard - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .nearby-supplier {
            border-left: 4px solid #28a745 !important;
            background-color: #f8fff8;
        }
        .supplier-card {
            transition: all 0.3s ease;
        }
        .supplier-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .badge.bg-success {
            font-size: 0.75em;
        }
        .products-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .product-card {
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .product-card .card-title {
            color: #495057;
            font-size: 0.95rem;
        }
        .product-card .btn {
            font-size: 0.8rem;
        }
        
        /* Enhanced Modal Styles */
        .modal-header.bg-primary {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
        }
        .modal-header.bg-info {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
        }
        
        /* Enhanced Product Cards in Modal */
        .modal .card {
            transition: all 0.2s ease;
        }
        .modal .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Toast Styling */
        .toast-container .toast {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Enhanced Button Styles */
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .btn-info:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }
        
        /* Icon styling */
        .fas, .fab {
            margin-right: 5px;
        }
        
        /* Enhanced card styling */
        .card.shadow-sm {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        .card.shadow-sm:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }

        /* Stats card styling */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
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

        /* Dashboard header */
        .dashboard-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        /* Order status styling */
        .order-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d1ecf1; color: #0c5460; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">Manage Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="review.php">Reviews</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            👤 <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Vendor'); ?>
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
                <div class="col-md-8">
                    <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Vendor'); ?>! 👋</h1>
                    <p class="mb-0">Find trusted suppliers and order fresh ingredients for your business</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-light" id="locationBtn" onclick="updateVendorLocation()">
                        📍 Update Location
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">
        <!-- Alert Container -->
        <div id="alertContainer"></div>
        
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
                    <div class="stats-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stats-label">Total Orders</div>
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
                    <div class="stats-number"><?php echo $stats['delivered_orders']; ?></div>
                    <div class="stats-label">Completed Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo formatCurrency($stats['total_spent']); ?></div>
                    <div class="stats-label">Total Spent</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Suppliers Section -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🏪 Available Suppliers</h5>
                        <div>
                            <select id="radiusSelect" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="10">10 km</option>
                                <option value="25" selected>25 km</option>
                                <option value="50">50 km</option>
                                <option value="100">All suppliers</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Location Controls -->
                        <div class="p-3 border-bottom bg-light">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <button class="btn btn-primary btn-sm w-100" onclick="trackCurrentLocation()">
                                        📍 Track My Location
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-success btn-sm w-100" onclick="findSuppliersOnMap()" id="findSuppliersBtn">
                                        🔍 Find Suppliers
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-info btn-sm w-100" onclick="showManualLocation()">
                                        📍 Set Location
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Location Status -->
                            <div id="locationStatus" class="mt-2" style="display: none;">
                                <div class="alert alert-info mb-0 py-2">
                                    <small id="locationText">Getting location...</small>
                                </div>
                            </div>
                            
                            <!-- Manual Location Panel -->
                            <div id="manualLocationPanel" class="mt-3" style="display: none;">
                                <div class="card">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">📍 Set Your Location Manually</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <input type="number" id="manualLat" class="form-control form-control-sm" 
                                                       placeholder="Latitude" step="any">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" id="manualLng" class="form-control form-control-sm" 
                                                       placeholder="Longitude" step="any">
                                            </div>
                                            <div class="col-md-4">
                                                <button class="btn btn-success btn-sm" onclick="setManualLocation()">
                                                    Use This Location
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Quick locations:</small>
                                            <button class="btn btn-outline-secondary btn-sm ms-1" onclick="setQuickLocation(28.6139, 77.2090, 'Delhi')">Delhi</button>
                                            <button class="btn btn-outline-secondary btn-sm ms-1" onclick="setQuickLocation(19.0760, 72.8777, 'Mumbai')">Mumbai</button>
                                            <button class="btn btn-outline-secondary btn-sm ms-1" onclick="setQuickLocation(12.9716, 77.5946, 'Bangalore')">Bangalore</button>
                                            <button class="btn btn-outline-secondary btn-sm ms-1" onclick="hideManualLocation()">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Suppliers Container -->
                        <div id="suppliersContainer" style="min-height: 400px; padding: 20px;">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-store fa-3x mb-3"></i>
                                <h5>Find Nearby Suppliers</h5>
                                <p>Click "Track My Location" and then "Find Suppliers" to discover suppliers near you</p>
                                <button class="btn btn-primary" onclick="useDefaultLocation()">
                                    🌍 Use Default Location (Delhi)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">⚡ Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="myorders.php" class="btn btn-outline-primary">
                                📋 View My Orders
                            </a>
                            <a href="review.php" class="btn btn-outline-success">
                                ⭐ Write Reviews
                            </a>
                            <button class="btn btn-outline-info" onclick="findSuppliersOnMap()">
                                🔄 Refresh Suppliers
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">📦 Recent Orders</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-muted small">No orders yet. Start ordering from nearby suppliers!</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($order['supplier_name']); ?></div>
                                        <div class="text-muted small"><?php echo $order['quantity'] . ' ' . $order['unit']; ?></div>
                                        <?php if (isset($order['payment_method'])): ?>
                                            <div class="text-muted small">
                                                <i class="fas fa-<?php echo $order['payment_method'] === 'upi' ? 'phone' : 'money-bill'; ?>"></i>
                                                <?php echo $order['payment_method'] === 'upi' ? 'UPI Payment' : 'Cash on Delivery'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="small"><?php echo formatCurrency($order['total_price']); ?></div>
                                        <span class="order-status status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="myorders.php" class="btn btn-sm btn-outline-primary">View All Orders</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Place Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="orderForm" action="../php/place_order.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="orderSupplierId" name="supplier_id">
                        <input type="hidden" id="orderProductId" name="product_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Product:</label>
                            <div id="modalProductName" class="form-control-plaintext"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Price:</label>
                            <div id="modalPrice" class="form-control-plaintext text-success"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Stock:</label>
                            <div id="modalStock" class="form-control-plaintext text-muted"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="orderQuantity" class="form-label fw-bold">Quantity:</label>
                            <input type="number" class="form-control" id="orderQuantity" name="quantity" 
                                   min="1" required onchange="updateOrderTotal()">
                        </div>
                        
                        <div class="mb-3">
                            <label for="deliveryAddress" class="form-label fw-bold">Delivery Address:</label>
                            <textarea class="form-control" id="deliveryAddress" name="delivery_address" 
                                      rows="3" placeholder="Enter your complete delivery address..."
                                      required></textarea>
                            <small class="text-muted">Please provide detailed address for accurate delivery</small>
                        </div>
                        
                        <input type="hidden" id="vendorLatitude" name="vendor_latitude">
                        <input type="hidden" id="vendorLongitude" name="vendor_longitude">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Total Amount:</label>
                            <div id="orderTotal" class="form-control-plaintext text-primary fs-5 fw-bold"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="orderSubmitBtn">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="profile-photo-container mb-3">
                                <?php 
                                $profile_photo = '';
                                if (!empty($user_profile['shop_logo'])) {
                                    if (file_exists("../uploads/profiles/" . $user_profile['shop_logo'])) {
                                        $profile_photo = "../uploads/profiles/" . $user_profile['shop_logo'];
                                    } elseif (file_exists("../uploads/shop_logos/" . $user_profile['shop_logo'])) {
                                        $profile_photo = "../uploads/shop_logos/" . $user_profile['shop_logo'];
                                    }
                                }
                                
                                if ($profile_photo): ?>
                                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" 
                                         alt="Profile Photo" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; font-size: 48px; color: #6c757d;">
                                        👤
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($user_profile['name'] ?? ''); ?></h5>
                            <p class="text-muted mb-0"><?php echo ucfirst($user_profile['role'] ?? 'vendor'); ?></p>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Global variables
        let userPosition = null;
        let currentSuppliersData = [];

        // Alert function for showing messages
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Track current location
        function trackCurrentLocation() {
            const btn = document.getElementById('locationBtn');
            const statusDiv = document.getElementById('locationStatus');
            const statusText = document.getElementById('locationText');
            
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Getting location...';
                btn.disabled = true;
            }
            
            statusDiv.style.display = 'block';
            statusText.textContent = '🔄 Getting your location...';
            
            if (!navigator.geolocation) {
                statusText.textContent = '❌ Geolocation not supported by this browser';
                showAlert('danger', '❌ Geolocation not supported by this browser');
                if (btn) {
                    btn.innerHTML = '📍 Update Location';
                    btn.disabled = false;
                }
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    
                    statusText.textContent = `📍 Location: ${userPosition.lat.toFixed(4)}, ${userPosition.lng.toFixed(4)}`;
                    
                    if (btn) {
                        btn.innerHTML = '✅ Location Tracked';
                        btn.className = 'btn btn-success';
                        btn.disabled = false;
                    }
                    
                    // Enable find suppliers button
                    const findBtn = document.getElementById('findSuppliersBtn');
                    if (findBtn) {
                        findBtn.disabled = false;
                        findBtn.innerHTML = '🔍 Find Suppliers';
                        findBtn.className = 'btn btn-success btn-sm w-100';
                    }
                    
                    showAlert('success', '✅ Location tracked successfully!');
                },
                (error) => {
                    let errorMsg = 'Unknown error';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location access denied by user.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location information unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out.';
                            break;
                    }
                    
                    statusText.textContent = `❌ Error: ${errorMsg}`;
                    showAlert('warning', `❌ Location Error: ${errorMsg}`);
                    
                    if (btn) {
                        btn.innerHTML = '📍 Update Location';
                        btn.disabled = false;
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        }

        // Show manual location input panel
        function showManualLocation() {
            const panel = document.getElementById('manualLocationPanel');
            const statusDiv = document.getElementById('locationStatus');
            
            panel.style.display = 'block';
            statusDiv.style.display = 'block';
            
            if (userPosition) {
                document.getElementById('manualLat').value = userPosition.lat.toFixed(6);
                document.getElementById('manualLng').value = userPosition.lng.toFixed(6);
            }
        }

        // Hide manual location input panel
        function hideManualLocation() {
            const panel = document.getElementById('manualLocationPanel');
            panel.style.display = 'none';
        }

        // Set manual location
        function setManualLocation() {
            const lat = parseFloat(document.getElementById('manualLat').value);
            const lng = parseFloat(document.getElementById('manualLng').value);
            
            if (isNaN(lat) || isNaN(lng)) {
                showAlert('danger', '❌ Please enter valid latitude and longitude values');
                return;
            }
            
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                showAlert('danger', '❌ Invalid coordinates. Latitude: -90 to 90, Longitude: -180 to 180');
                return;
            }
            
            userPosition = { lat: lat, lng: lng };
            
            const statusText = document.getElementById('locationText');
            const statusDiv = document.getElementById('locationStatus');
            
            statusDiv.style.display = 'block';
            statusText.textContent = `📍 Manual Location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            
            const findBtn = document.getElementById('findSuppliersBtn');
            if (findBtn) {
                findBtn.disabled = false;
                findBtn.innerHTML = '🔍 Find Suppliers';
                findBtn.className = 'btn btn-success btn-sm w-100';
            }
            
            hideManualLocation();
            showAlert('success', '✅ Location set manually!');
        }

        // Set quick location
        function setQuickLocation(lat, lng, cityName) {
            document.getElementById('manualLat').value = lat;
            document.getElementById('manualLng').value = lng;
            showAlert('info', `📍 ${cityName} coordinates loaded. Click "Use This Location" to confirm.`);
        }

        // Use default location (Delhi)
        function useDefaultLocation() {
            userPosition = { lat: 28.6139, lng: 77.2090 };
            
            const statusText = document.getElementById('locationText');
            const statusDiv = document.getElementById('locationStatus');
            
            statusDiv.style.display = 'block';
            statusText.textContent = '📍 Using default location: Delhi (28.6139, 77.2090)';
            
            const findBtn = document.getElementById('findSuppliersBtn');
            if (findBtn) {
                findBtn.disabled = false;
                findBtn.innerHTML = '🔍 Find Suppliers';
                findBtn.className = 'btn btn-success btn-sm w-100';
            }
            
            showAlert('info', '📍 Using default location (Delhi). You can set your actual location for better results.');
            
            // Auto-find suppliers
            setTimeout(() => {
                findSuppliersOnMap();
            }, 1000);
        }

        // Find suppliers on map
        async function findSuppliersOnMap() {
            let searchLat, searchLng;
            
            if (!userPosition) {
                searchLat = 28.6139; // Default to Delhi
                searchLng = 77.2090;
                showAlert('info', '🌍 Using default location (Delhi). Click "Track My Location" for personalized results.');
            } else {
                searchLat = userPosition.lat;
                searchLng = userPosition.lng;
            }
            
            const radius = document.getElementById('radiusSelect').value;
            const btn = document.getElementById('findSuppliersBtn');
            const container = document.getElementById('suppliersContainer');
            
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Searching...';
                btn.disabled = true;
            }
            
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">🔍 Searching for suppliers...</p>
                </div>
            `;
            
            try {
                const url = `../php/get_suppliers_with_products.php?lat=${searchLat}&lng=${searchLng}&radius=${radius}`;
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    const suppliers = data.suppliers || [];
                    currentSuppliersData = suppliers;
                    
                    displaySuppliers(suppliers);
                    
                    if (btn) {
                        btn.innerHTML = `✅ Found ${suppliers.length} Suppliers`;
                        btn.className = 'btn btn-success btn-sm w-100';
                        btn.disabled = false;
                    }
                    
                    showAlert('success', `✅ Found ${suppliers.length} suppliers in ${radius}km radius!`);
                } else {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ No suppliers found</h6>
                            <p>${data.message || 'No suppliers available in your area.'}</p>
                            <button class="btn btn-primary btn-sm" onclick="findSuppliersOnMap()">
                                🔄 Try Again
                            </button>
                        </div>
                    `;
                    
                    if (btn) {
                        btn.innerHTML = '🔄 Try Again';
                        btn.className = 'btn btn-warning btn-sm w-100';
                        btn.disabled = false;
                    }
                }
            } catch (error) {
                console.error('Error finding suppliers:', error);
                
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ Error Loading Suppliers</h6>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <div class="mt-2">
                            <button class="btn btn-primary btn-sm" onclick="findSuppliersOnMap()">
                                🔄 Try Again
                            </button>
                        </div>
                    </div>
                `;
                
                if (btn) {
                    btn.innerHTML = '❌ Error - Retry';
                    btn.className = 'btn btn-danger btn-sm w-100';
                    btn.disabled = false;
                }
                
                showAlert('danger', `❌ Error: ${error.message}`);
            }
        }

        // Display suppliers
        function displaySuppliers(suppliers) {
            const container = document.getElementById('suppliersContainer');
            
            if (!suppliers || suppliers.length === 0) {
                container.innerHTML = '<div class="alert alert-warning">No suppliers found</div>';
                return;
            }
            
            // Separate nearby suppliers (within 10km) and others with products
            const nearbySuppliers = suppliers.filter(s => s.distance <= 10);
            const suppliersWithProducts = suppliers.filter(s => s.products && s.products.length > 0);
            const otherSuppliers = suppliers.filter(s => s.distance > 10 || !s.products || s.products.length === 0);
            
            let html = `<div class="alert alert-success">✅ Found ${suppliers.length} suppliers!</div>`;
            
            // Show nearby suppliers first
            if (nearbySuppliers.length > 0) {
                html += `
                    <div class="mb-4">
                        <h6 class="text-success mb-3">📍 Nearby Suppliers (${nearbySuppliers.length})</h6>
                        ${renderSupplierCards(nearbySuppliers, true)}
                    </div>
                `;
            }
            
            // Show suppliers with products
            if (suppliersWithProducts.length > 0) {
                html += `
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">🏪 Suppliers with Products (${suppliersWithProducts.length})</h6>
                        ${renderSupplierCards(suppliersWithProducts, false)}
                    </div>
                `;
            }
            
            // Show other suppliers
            if (otherSuppliers.length > 0) {
                html += `
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">📋 Other Suppliers (${otherSuppliers.length})</h6>
                        ${renderSupplierCards(otherSuppliers, false)}
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }

        // Render supplier cards
        function renderSupplierCards(suppliers, isNearby = false) {
            return suppliers.map(supplier => {
                const productCount = supplier.products ? supplier.products.length : 0;
                const rating = supplier.avg_rating || 0;
                const stars = '⭐'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating));
                const nearbyBadge = isNearby ? '<span class="badge bg-success me-2">📍 Nearby</span>' : '';
                const cardClass = isNearby ? 'border-success' : '';
                
                return `
                    <div class="card mb-3 ${cardClass}" data-supplier-id="${supplier.id}">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1">${nearbyBadge}🏪 ${supplier.business_name || supplier.name}</h6>
                                    <div class="mb-2">${stars} (${rating}/5) - ${supplier.review_count || 0} reviews</div>
                                    <p class="text-muted mb-1">📦 ${productCount} products available</p>
                                    <p class="text-muted mb-1">📍 ${supplier.address || 'Address not provided'}</p>
                                    <p class="text-muted mb-1">📞 ${supplier.phone || 'Phone not provided'}</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-success btn-sm mb-2 w-100" onclick="toggleProducts(${supplier.id})">
                                        📦 View Products (${productCount})
                                    </button>
                                    <button class="btn btn-info btn-sm w-100" onclick="openProfileModal(${supplier.id})">
                                        👤 View Profile
                                    </button>
                                </div>
                            </div>
                            <!-- Products Section -->
                            <div id="products-${supplier.id}" class="products-section mt-3" style="display: none;">
                                <hr>
                                <h6>📦 Available Products:</h6>
                                <div class="row" id="products-list-${supplier.id}">
                                    <!-- Products will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Toggle products display
        function toggleProducts(supplierId) {
            const productsSection = document.getElementById(`products-${supplierId}`);
            const productsList = document.getElementById(`products-list-${supplierId}`);
            
            if (productsSection.style.display === 'none') {
                const supplier = currentSuppliersData.find(s => s.id == supplierId);
                if (supplier && supplier.products && supplier.products.length > 0) {
                    let productsHtml = '';
                    supplier.products.forEach(product => {
                        productsHtml += `
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2">${product.name}</h6>
                                        <p class="text-success fw-bold mb-2">₹${product.price}</p>
                                        <p class="text-muted small mb-3">${product.description || 'Fresh and quality product'}</p>
                                        <button class="btn btn-primary btn-sm w-100" onclick="orderProduct(${product.id}, ${supplier.id}, '${product.name}', ${product.price})">
                                            🛒 Order Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    productsList.innerHTML = productsHtml;
                } else {
                    productsList.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-box-open fa-2x mb-2"></i>
                                <h6>No products available</h6>
                                <p class="mb-0">This supplier hasn't added any products yet.</p>
                            </div>
                        </div>
                    `;
                }
                productsSection.style.display = 'block';
            } else {
                productsSection.style.display = 'none';
            }
        }

        // View supplier profile
        // Open supplier profile modal (reuses the main profile modal, fills with supplier data)
        function openProfileModal(supplierId) {
            const supplier = currentSuppliersData.find(s => s.id == supplierId);
            if (!supplier) return;
            // Fill modal fields
            document.querySelector('#profileModal .modal-title').textContent = 'Supplier Profile';
            const photoContainer = document.querySelector('#profileModal .profile-photo-container');
            let photoHtml = '';
            // Always load shop_logo from users table (supplier.shop_logo)
            if (supplier.shop_logo) {
                if (supplier.shop_logo.startsWith('shop_')) {
                    photoHtml = `<img src="../uploads/shop_logos/${supplier.shop_logo}" alt="Shop Logo" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">`;
                } else {
                    photoHtml = `<img src="../uploads/profiles/${supplier.shop_logo}" alt="Profile Photo" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">`;
                }
            } else {
                photoHtml = `<div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; font-size: 48px; color: #6c757d;">👤</div>`;
            }
            photoContainer.innerHTML = photoHtml;
            document.querySelector('#profileModal h5.mb-1').textContent = supplier.name || '';
            document.querySelector('#profileModal p.text-muted.mb-0').textContent = 'Supplier';
            // Email
            document.querySelector('#profileModal .row .col-md-6:nth-child(1) span').textContent = supplier.email || 'Not provided';
            // Mobile
            document.querySelector('#profileModal .row .col-md-6:nth-child(2) span').textContent = supplier.phone || 'Not provided';
            // Business Name
            document.querySelector('#profileModal .row.mt-3 .col-md-6:nth-child(1) span').textContent = supplier.business_name || 'Not provided';
            // City (from users table)
            document.querySelector('#profileModal .row.mt-3 .col-md-6:nth-child(2) span').textContent = supplier.city || 'Not provided';
            // Address
            document.querySelector('#profileModal .row.mt-3+.row .col-12 span').textContent = supplier.address || 'Not provided';

            // Add rating and reviews below the name (in the left column)
            let ratingHtml = '';
            const rating = supplier.avg_rating || 0;
            const reviewCount = supplier.review_count || 0;
            if (document.querySelector('#profileModal .supplier-rating')) {
                document.querySelector('#profileModal .supplier-rating').remove();
            }
            ratingHtml = `<div class="supplier-rating mt-2 mb-2">
                <span class="badge bg-warning text-dark">${'⭐'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating))} (${rating}/5)</span>
                <span class="ms-2 text-muted">${reviewCount} review${reviewCount === 1 ? '' : 's'}</span>
            </div>`;
            document.querySelector('#profileModal .profile-photo-container').insertAdjacentHTML('afterend', ratingHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('profileModal'));
            modal.show();
        }

        // Order product
        function orderProduct(productId, supplierId, productName, price) {
            const orderUrl = `../modern_order.php?product_id=${productId}&supplier_id=${supplierId}&product_name=${encodeURIComponent(productName)}&price=${price}`;
            window.location.href = orderUrl;
        }

        // Update vendor location (wrapper)
        function updateVendorLocation() {
            trackCurrentLocation();
        }

        // Order Modal Functions
        function openOrderModal(supplier, product) {
            document.getElementById('orderSupplierId').value = supplier.id;
            document.getElementById('orderProductId').value = product.id;
            document.getElementById('modalProductName').textContent = product.name;
            document.getElementById('modalPrice').textContent = `₹${product.price}/${product.unit}`;
            document.getElementById('modalStock').textContent = `${product.stock} ${product.unit} available`;
            document.getElementById('orderQuantity').value = 1;
            document.getElementById('orderQuantity').max = product.stock;
            
            if (userPosition) {
                document.getElementById('vendorLatitude').value = userPosition.lat;
                document.getElementById('vendorLongitude').value = userPosition.lng;
            }
            
            updateOrderTotal();
            
            const orderModal = new bootstrap.Modal(document.getElementById('orderModal'));
            orderModal.show();
        }
        
        function updateOrderTotal() {
            const priceText = document.getElementById('modalPrice').textContent;
            const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
            const quantity = parseInt(document.getElementById('orderQuantity').value) || 0;
            const total = price * quantity;
            
            document.getElementById('orderTotal').textContent = `₹${total.toFixed(2)}`;
            
            const stock = parseInt(document.getElementById('modalStock').textContent);
            const submitBtn = document.getElementById('orderSubmitBtn');
            if (quantity > stock) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Insufficient Stock';
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Place Order';
            }
        }

        // Auto-load suppliers on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-use default location and find suppliers
            setTimeout(() => {
                useDefaultLocation();
            }, 1000);
        });
    </script>
</body>
</html>