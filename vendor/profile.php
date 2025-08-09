<?php
include '../php/db.php';
requireRole('vendor');

// Get vendor details
$database = new Database();
$db = $database->getConnection();

try {
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor information
    $query = "SELECT * FROM users WHERE id = :vendor_id AND role = 'vendor'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../index.php');
        exit;
    }
    
    // Get vendor statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                        AVG(CASE WHEN status = 'delivered' THEN total_price ELSE NULL END) as avg_order_value
                    FROM orders WHERE vendor_id = :vendor_id";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':vendor_id', $vendor_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent reviews (placeholder - you can add reviews table later)
    $reviews = [
        ['rating' => 5, 'comment' => 'Great vendor, always orders quality products!', 'supplier' => 'Fresh Foods Co.', 'date' => '2025-01-20'],
        ['rating' => 4, 'comment' => 'Reliable and prompt payments', 'supplier' => 'Organic Supplies', 'date' => '2025-01-15'],
        ['rating' => 5, 'comment' => 'Easy to work with, clear communication', 'supplier' => 'Local Farmers', 'date' => '2025-01-10']
    ];
    
} catch (PDOException $e) {
    $vendor = null;
    $stats = ['total_orders' => 0, 'completed_orders' => 0, 'avg_order_value' => 0];
    $reviews = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        $update_query = "UPDATE users SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :vendor_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'vendor_id' => $vendor_id
        ]);
        
        $_SESSION['name'] = $name;
        $_SESSION['user_name'] = $name;
        
        $success_message = "Profile updated successfully!";
        
        // Refresh vendor data
        $stmt->execute();
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border: 4px solid white;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
            margin: 0 auto 1rem;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .review-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .rating-stars {
            color: #ffc107;
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="myorders.php">My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            👤 <?php echo htmlspecialchars($vendor['name'] ?? 'Vendor'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../php/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <section class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <div class="profile-avatar">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo htmlspecialchars($vendor['name'] ?? 'Vendor Name'); ?></h1>
                    <p class="mb-1"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($vendor['email'] ?? 'No email'); ?></p>
                    <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($vendor['phone'] ?? 'No phone'); ?></p>
                    <p class="mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($vendor['address'] ?? 'No address provided'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Profile Content -->
    <div class="container mt-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="text-muted">Total Orders</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
                    <div class="text-muted">Completed Orders</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">₹<?php echo number_format($stats['avg_order_value'] ?? 0, 0); ?></div>
                    <div class="text-muted">Avg Order Value</div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Profile Edit Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-gear"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($vendor['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Type</label>
                                    <input type="text" class="form-control" value="Vendor" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3" placeholder="Enter your complete address"><?php echo htmlspecialchars($vendor['address'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reviews & Ratings -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-star-fill"></i> Reviews & Ratings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted text-center">No reviews yet</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?php echo $review['date']; ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($review['comment']); ?></p>
                                    <small class="text-muted">- <?php echo htmlspecialchars($review['supplier']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contact Info Card -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle"></i> Quick Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-calendar-plus text-primary me-2"></i>
                            <small>Member since: <?php echo date('M Y', strtotime($vendor['created_at'] ?? '2025-01-01')); ?></small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-shield-check text-success me-2"></i>
                            <small>Verified Vendor</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-award text-warning me-2"></i>
                            <small>Trusted Partner</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
