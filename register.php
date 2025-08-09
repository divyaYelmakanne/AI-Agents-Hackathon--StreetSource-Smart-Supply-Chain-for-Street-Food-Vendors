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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $business_name = isset($_POST['business_name']) ? trim($_POST['business_name']) : null;
    $shop_logo = null;
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $_SESSION['error'] = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long';
    } elseif (!in_array($role, ['vendor', 'supplier'])) {
        $_SESSION['error'] = 'Invalid role selected';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address';
    } else {
        // Handle shop logo upload if provided
        if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/shop_logos/';
            if (!is_dir($upload_dir)) { 
                mkdir($upload_dir, 0777, true); 
            }
            $file_extension = strtolower(pathinfo($_FILES['shop_logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_extension, $allowed_extensions)) {
                $shop_logo = uniqid('shop_', true) . '.' . $file_extension;
                $upload_path = $upload_dir . $shop_logo;
                if (!move_uploaded_file($_FILES['shop_logo']['tmp_name'], $upload_path)) {
                    $_SESSION['error'] = 'Failed to upload shop logo';
                    $shop_logo = null;
                }
            } else {
                $_SESSION['error'] = 'Invalid image format. Please use JPG, PNG, or GIF';
            }
        }
        
        // Only proceed if no file upload errors
        if (!isset($_SESSION['error'])) {
            $database = new Database();
            $db = $database->getConnection();
            
            try {
                // Check if email already exists
                $query = "SELECT id FROM users WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = 'Email already registered';
            } else {
                // Hash password and insert user (unverified)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (name, business_name, shop_logo, email, password, role, phone, address, city, is_verified) 
                         VALUES (:name, :business_name, :shop_logo, :email, :password, :role, :phone, :address, :city, FALSE)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':business_name', $business_name);
                $stmt->bindParam(':shop_logo', $shop_logo);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':city', $city);
                
                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();
                    
                    // Generate OTP - ensure it's always a 6-digit string
                    $otp_code = str_pad(strval(rand(0, 999999)), 6, '0', STR_PAD_LEFT);
                    
                    // Log OTP generation for debugging
                    error_log("Registration OTP generated for user $user_id: '$otp_code'");
                    
                    // Save OTP using MySQL NOW() to avoid timezone issues
                    $query = "INSERT INTO email_verifications (user_id, otp_code, expires_at, created_at) 
                             VALUES (:user_id, :otp_code, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->bindParam(':otp_code', $otp_code, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    // Send OTP email
                    require_once 'php/email_service.php';
                    $emailService = new EmailService();
                    
                    if ($emailService->sendOTPEmail($email, $name, $otp_code)) {
                        $_SESSION['temp_user_id'] = $user_id;
                        $_SESSION['temp_user_email'] = $email;
                        $_SESSION['temp_user_name'] = $name;
                        $_SESSION['success'] = 'Registration successful! Please check your email for the OTP verification code.';
                        header('Location: verify_email.php');
                        exit();
                    } else {
                        // If email fails, delete the user and show error
                        $query = "DELETE FROM users WHERE id = :user_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        
                        $_SESSION['error'] = 'Failed to send verification email. Please try again or contact support.';
                    }
                } else {
                    $_SESSION['error'] = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Registration failed. Please try again.';
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - StreetSource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">🍲 StreetSource</a>
            <a class="nav-link" href="index.php">← Back to Home</a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="register-container">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0">Create Your Account</h3>
                    <p class="text-muted small">Join the StreetSource community</p>
                </div>
                <div class="card-body">
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h5 class="border-bottom pb-2">Personal Information</h5>
                            </div>
                            

                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="business_name" class="form-label">Business Name</label>
                                <input type="text" class="form-control" id="business_name" name="business_name" 
                                       value="<?php echo isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="shop_logo" class="form-label">Shop Logo/Photo (optional)</label>
                                <input type="file" class="form-control" id="shop_logo" name="shop_logo" accept="image/*">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Account Type -->
                            <div class="col-12 mt-4">
                                <h5 class="border-bottom pb-2">Account Type</h5>
                            </div>
                            
                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card h-100 <?php echo (isset($_POST['role']) && $_POST['role'] === 'vendor') ? 'border-primary' : ''; ?>">
                                            <div class="card-body text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="role" id="vendor" value="vendor" required
                                                           <?php echo (isset($_POST['role']) && $_POST['role'] === 'vendor') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label w-100" for="vendor">
                                                        <div class="feature-icon">🧑‍🍳</div>
                                                        <h5>I'm a Vendor</h5>
                                                        <p class="text-muted small">I run a street food stall and want to source raw materials from suppliers</p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card h-100 <?php echo (isset($_POST['role']) && $_POST['role'] === 'supplier') ? 'border-primary' : ''; ?>">
                                            <div class="card-body text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="role" id="supplier" value="supplier" required
                                                           <?php echo (isset($_POST['role']) && $_POST['role'] === 'supplier') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label w-100" for="supplier">
                                                        <div class="feature-icon">🚚</div>
                                                        <h5>I'm a Supplier</h5>
                                                        <p class="text-muted small">I supply raw materials and want to connect with street food vendors</p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password -->
                            <div class="col-12 mt-4">
                                <h5 class="border-bottom pb-2">Security</h5>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <!-- Submit -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
                            </div>
                            
                            <div class="col-12 text-center">
                                <p class="text-muted small">Already have an account? <a href="index.php">Login here</a></p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add interactivity to role selection
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.card').forEach(card => {
                    card.classList.remove('border-primary');
                });
                this.closest('.card').classList.add('border-primary');
            });
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
