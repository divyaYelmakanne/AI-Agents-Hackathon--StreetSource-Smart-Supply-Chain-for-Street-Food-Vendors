<?php
include 'php/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'vendor') {
        header('Location: vendor/dashboard.php');
    } else {
        header('Location: supplier/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StreetSource - Raw Material Sourcing for Street Food Vendors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">🍲 StreetSource</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#features">Features</a>
                <a class="nav-link" href="#about">About</a>
                <a class="nav-link btn btn-outline-primary ms-2" href="#login">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="display-4 mb-4">Connect Street Food Vendors with Trusted Suppliers</h1>
                    <p class="lead mb-4">Find quality raw materials from verified suppliers near you. Build trust through reviews and streamline your sourcing process.</p>
                    <div class="hero-stats mb-4">
                        <div class="row">
                            <div class="col-4 text-center">
                                <div class="stat-number">1000+</div>
                                <div class="stat-label">Suppliers</div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="stat-number">30%</div>
                                <div class="stat-label">Cost Savings</div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="stat-number">95%</div>
                                <div class="stat-label">Success Rate</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#login" class="btn btn-light btn-lg">Get Started Today</a>
                        <a href="#features" class="btn btn-outline-light btn-lg">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-image text-center">
                        <div style="font-size: 12rem; opacity: 0.8; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);">🍲</div>
                        <div class="floating-elements">
                            <div class="floating-element" style="top: 10%; left: 10%;">🥕</div>
                            <div class="floating-element" style="top: 20%; right: 15%;">🧅</div>
                            <div class="floating-element" style="bottom: 30%; left: 20%;">🌶️</div>
                            <div class="floating-element" style="bottom: 15%; right: 10%;">🥬</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 mb-3">Why Choose StreetSource?</h2>
                    <p class="lead text-muted">Empowering street food vendors with technology</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">📍</div>
                        <h4>Location-Based Matching</h4>
                        <p>Find suppliers within 10km radius using GPS technology. Get the freshest ingredients with minimal travel time.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">⭐</div>
                        <h4>Trust Through Reviews</h4>
                        <p>Rate and review suppliers based on quality, delivery, and service. Build a network of trusted partners.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">📱</div>
                        <h4>Mobile-First Design</h4>
                        <p>Optimized for smartphones. Place orders, track deliveries, and manage your business on the go.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">🛒</div>
                        <h4>Easy Ordering</h4>
                        <p>Browse products, check stock levels, and place orders with just a few taps. No more phone calls or visits.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">💰</div>
                        <h4>Fair Pricing</h4>
                        <p>Compare prices from multiple suppliers. Transparent pricing with no hidden costs or middleman markups.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="feature-icon">📊</div>
                        <h4>Business Insights</h4>
                        <p>Track your orders, expenses, and supplier performance. Make data-driven decisions for your business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section id="login" class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-4">
                        <h2>Login to Your Account</h2>
                        <p class="text-muted">Access your vendor or supplier dashboard</p>
                    </div>
                    
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
                    
                    <div class="row g-4">
                        <!-- Login Form -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Existing User</h5>
                                </div>
                                <div class="card-body">
                                    <form action="php/login.php" method="POST">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Login</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Registration Link -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">New User</h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="mb-3">Don't have an account yet?</p>
                                    <p class="text-muted small mb-3">Join as a vendor to source materials or as a supplier to sell your products.</p>
                                    <a href="register.php" class="btn btn-outline-primary w-100">Create Account</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-6 mb-3">How StreetSource Works</h2>
                    <p class="lead text-muted">Simple steps to transform your sourcing experience</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h5>Register</h5>
                        <p class="text-muted">Sign up as a vendor or supplier with your business details</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h5>Connect</h5>
                        <p class="text-muted">Find verified suppliers or vendors in your area</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h5>Order</h5>
                        <p class="text-muted">Place orders directly through our platform</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h5>Grow</h5>
                        <p class="text-muted">Build relationships and grow your business</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6">
                    <h2 class="display-6 mb-4">Empowering India's Street Food Ecosystem</h2>
                    <p class="lead mb-4">Street food vendors are the backbone of India's food culture, but they often struggle with unreliable supply chains and unfair pricing.</p>
                    <p class="mb-4">StreetSource bridges this gap by connecting vendors directly with verified suppliers in their area, ensuring fresh ingredients, fair prices, and reliable service.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2">✅ Over 1000+ registered suppliers across major cities</li>
                        <li class="mb-2">✅ Average 30% cost savings for vendors</li>
                        <li class="mb-2">✅ 95% delivery success rate</li>
                        <li class="mb-2">✅ 24/7 customer support in local languages</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <div style="font-size: 15rem; opacity: 0.1;">🍲</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-6 mb-3">What Our Users Say</h2>
                    <p class="lead text-muted">Real stories from vendors and suppliers</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card testimonial-card">
                        <div class="card-body">
                            <div class="testimonial-rating mb-3">⭐⭐⭐⭐⭐</div>
                            <p class="card-text">"StreetSource has transformed my business. I now get fresh vegetables at 25% lower cost and my supplier is just 2km away!"</p>
                            <footer class="blockquote-footer">
                                <strong>Ravi Kumar</strong><br>
                                <small>Chaat Vendor, Delhi</small>
                            </footer>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card testimonial-card">
                        <div class="card-body">
                            <div class="testimonial-rating mb-3">⭐⭐⭐⭐⭐</div>
                            <p class="card-text">"As a supplier, I've connected with 50+ vendors through this platform. It's helped me grow my business significantly."</p>
                            <footer class="blockquote-footer">
                                <strong>Priya Sharma</strong><br>
                                <small>Vegetable Supplier, Mumbai</small>
                            </footer>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card testimonial-card">
                        <div class="card-body">
                            <div class="testimonial-rating mb-3">⭐⭐⭐⭐⭐</div>
                            <p class="card-text">"The quality tracking and review system gives me confidence. I know exactly what I'm getting before placing orders."</p>
                            <footer class="blockquote-footer">
                                <strong>Ahmed Ali</strong><br>
                                <small>Biryani Vendor, Hyderabad</small>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>🍲 StreetSource</h5>
                    <p class="mb-3">Connecting street food vendors with trusted suppliers across India.</p>
                    <div class="social-links">
                        <a href="#" class="text-white me-3">📧 Contact</a>
                        <a href="#" class="text-white me-3">📱 Support</a>
                        <a href="#" class="text-white">📍 Locations</a>
                    </div>
                </div>
                <div class="col-lg-2 mb-4">
                    <h6>For Vendors</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">Find Suppliers</a></li>
                        <li><a href="#" class="text-light">Place Orders</a></li>
                        <li><a href="#" class="text-light">Track Deliveries</a></li>
                        <li><a href="#" class="text-light">Rate & Review</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 mb-4">
                    <h6>For Suppliers</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">Join Platform</a></li>
                        <li><a href="#" class="text-light">Manage Products</a></li>
                        <li><a href="#" class="text-light">Handle Orders</a></li>
                        <li><a href="#" class="text-light">Grow Business</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 mb-4">
                    <h6>Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">Help Center</a></li>
                        <li><a href="#" class="text-light">Contact Us</a></li>
                        <li><a href="#" class="text-light">Report Issue</a></li>
                        <li><a href="#" class="text-light">Feedback</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 mb-4">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">About Us</a></li>
                        <li><a href="#" class="text-light">Privacy Policy</a></li>
                        <li><a href="#" class="text-light">Terms of Service</a></li>
                        <li><a href="#" class="text-light">Careers</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 StreetSource. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Made with ❤️ for India's street food vendors</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
