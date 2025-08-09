<?php
include '../php/db.php';
requireRole('vendor');

$database = new Database();
$db = $database->getConnection();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $supplier_id = intval($_POST['supplier_id']);
    $rating = intval($_POST['rating']);
    $note = trim($_POST['note']);
    $vendor_id = $_SESSION['user_id'];
    
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Invalid rating. Please select 1-5 stars.';
    } else {
        try {
            // Check if order belongs to this vendor and is delivered
            $query = "SELECT id FROM orders WHERE id = :order_id AND vendor_id = :vendor_id AND supplier_id = :supplier_id AND status = 'delivered'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $_SESSION['error'] = 'Invalid order or order not yet delivered.';
            } else {
                // Check if review already exists
                $query = "SELECT id FROM reviews WHERE order_id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'You have already reviewed this order.';
                } else {
                    // Insert review
                    $query = "INSERT INTO reviews (order_id, vendor_id, supplier_id, rating, note) 
                              VALUES (:order_id, :vendor_id, :supplier_id, :rating, :note)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':vendor_id', $vendor_id);
                    $stmt->bindParam(':supplier_id', $supplier_id);
                    $stmt->bindParam(':rating', $rating);
                    $stmt->bindParam(':note', $note);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Review submitted successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to submit review. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to submit review. Please try again.';
        }
    }
}

try {
    $vendor_id = $_SESSION['user_id'];
    
    // Get delivered orders that can be reviewed
    $query = "SELECT o.*, p.name as product_name, u.name as supplier_name,
                     r.id as review_id, r.rating, r.note as review_note
              FROM orders o
              JOIN products p ON o.product_id = p.id
              JOIN users u ON o.supplier_id = u.id
              LEFT JOIN reviews r ON o.id = r.order_id
              WHERE o.vendor_id = :vendor_id AND o.status = 'delivered'
              ORDER BY o.delivered_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $reviewable_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all reviews submitted by this vendor
    $query = "SELECT r.*, o.id as order_id, p.name as product_name, u.name as supplier_name
              FROM reviews r
              JOIN orders o ON r.order_id = o.id
              JOIN products p ON o.product_id = p.id
              JOIN users u ON r.supplier_id = u.id
              WHERE r.vendor_id = :vendor_id
              ORDER BY r.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    $my_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $reviewable_orders = [];
    $my_reviews = [];
}

// Get specific order for review if order_id is provided
$review_order = null;
if (isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    foreach ($reviewable_orders as $order) {
        if ($order['id'] == $order_id && !$order['review_id']) {
            $review_order = $order;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - StreetSource</title>
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
                        <a class="nav-link" href="myorders.php">My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="review.php">Reviews</a>
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
            <!-- Write Review Section -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">⭐ Write a Review</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($review_order): ?>
                            <!-- Review Form for Specific Order -->
                            <div class="bg-light p-3 rounded mb-3">
                                <h6>Reviewing Order #<?php echo $review_order['id']; ?></h6>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($review_order['product_name']); ?></strong></p>
                                <p class="text-muted small mb-0">Supplier: <?php echo htmlspecialchars($review_order['supplier_name']); ?></p>
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="order_id" value="<?php echo $review_order['id']; ?>">
                                <input type="hidden" name="supplier_id" value="<?php echo $review_order['supplier_id']; ?>">
                                <input type="hidden" id="reviewRating" name="rating" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label">Rating *</label>
                                    <div class="star-rating">
                                        <span id="star1" class="star inactive" onclick="setRating(1)">☆</span>
                                        <span id="star2" class="star inactive" onclick="setRating(2)">☆</span>
                                        <span id="star3" class="star inactive" onclick="setRating(3)">☆</span>
                                        <span id="star4" class="star inactive" onclick="setRating(4)">☆</span>
                                        <span id="star5" class="star inactive" onclick="setRating(5)">☆</span>
                                    </div>
                                    <small class="text-muted">Click to rate from 1 to 5 stars</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="note" class="form-label">Review Note</label>
                                    <textarea class="form-control" id="note" name="note" rows="3" 
                                              placeholder="Share your experience with this supplier..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                                <a href="review.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        <?php else: ?>
                            <!-- Orders Available for Review -->
                            <?php
                            $pending_reviews = array_filter($reviewable_orders, function($order) {
                                return !$order['review_id'];
                            });
                            ?>
                            
                            <?php if (empty($pending_reviews)): ?>
                                <div class="text-center py-4">
                                    <div style="font-size: 3rem; opacity: 0.3;">⭐</div>
                                    <p class="text-muted">No orders available for review.</p>
                                    <p class="text-muted small">Orders become available for review after they are delivered.</p>
                                    <a href="dashboard.php" class="btn btn-primary">Place New Order</a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Select an order to review:</p>
                                <?php foreach ($pending_reviews as $order): ?>
                                    <div class="card mb-2">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Order #<?php echo $order['id']; ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($order['product_name']); ?> - 
                                                        <?php echo htmlspecialchars($order['supplier_name']); ?>
                                                    </small>
                                                </div>
                                                <a href="review.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Review
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- My Reviews Section -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📝 My Reviews</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_reviews)): ?>
                            <div class="text-center py-4">
                                <div style="font-size: 3rem; opacity: 0.3;">📝</div>
                                <p class="text-muted">You haven't written any reviews yet.</p>
                                <p class="text-muted small">Reviews help other vendors find trusted suppliers.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($my_reviews as $review): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($review['supplier_name']); ?></h6>
                                                <small class="text-muted">Order #<?php echo $review['order_id']; ?> - <?php echo htmlspecialchars($review['product_name']); ?></small>
                                            </div>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php echo ($i <= $review['rating']) ? '⭐' : '☆'; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($review['note']): ?>
                                            <p class="mb-2">"<?php echo htmlspecialchars($review['note']); ?>"</p>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted">
                                            Reviewed on <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
