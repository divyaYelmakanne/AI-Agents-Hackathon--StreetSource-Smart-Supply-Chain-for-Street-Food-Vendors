<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all fields';
        header('Location: ../index.php');
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT id, name, email, password, role, latitude, longitude, is_verified FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Check if email is verified
                if (!$user['is_verified']) {
                    // Check if user has pending verification
                    $query = "SELECT COUNT(*) as pending_count FROM email_verifications 
                             WHERE user_id = :user_id AND is_used = FALSE AND expires_at > NOW()";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user['id']);
                    $stmt->execute();
                    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pending['pending_count'] > 0) {
                        // User has pending OTP
                        $_SESSION['temp_user_id'] = $user['id'];
                        $_SESSION['temp_user_email'] = $user['email'];
                        $_SESSION['temp_user_name'] = $user['name'];
                        $_SESSION['error'] = 'Please verify your email address first. Check your inbox for the OTP code.';
                        header('Location: ../verify_email.php');
                        exit();
                    } else {
                        // Generate new OTP
                        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        
                        // Save OTP using MySQL NOW() to avoid timezone issues
                        $query = "INSERT INTO email_verifications (user_id, otp_code, expires_at, created_at) 
                                 VALUES (:user_id, :otp_code, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':user_id', $user['id']);
                        $stmt->bindParam(':otp_code', $otp_code);
                        $stmt->execute();
                        
                        // Send OTP email
                        require_once 'email_service.php';
                        $emailService = new EmailService();
                        
                        if ($emailService->sendOTPEmail($user['email'], $user['name'], $otp_code)) {
                            $_SESSION['temp_user_id'] = $user['id'];
                            $_SESSION['temp_user_email'] = $user['email'];
                            $_SESSION['temp_user_name'] = $user['name'];
                            $_SESSION['error'] = 'Your email is not verified. We\'ve sent a new verification code to your email.';
                            header('Location: ../verify_email.php');
                            exit();
                        } else {
                            $_SESSION['error'] = 'Email verification required but failed to send OTP. Please contact support.';
                        }
                    }
                } else {
                    // Email is verified, proceed with login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_latitude'] = $user['latitude'];
                    $_SESSION['user_longitude'] = $user['longitude'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'vendor') {
                        header('Location: ../vendor/dashboard.php');
                    } else {
                        header('Location: ../supplier/dashboard.php');
                    }
                    exit();
                }
            } else {
                $_SESSION['error'] = 'Invalid password';
            }
        } else {
            $_SESSION['error'] = 'Email not found';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Login failed. Please try again.';
    }
    
    header('Location: ../index.php');
    exit();
}
?>
