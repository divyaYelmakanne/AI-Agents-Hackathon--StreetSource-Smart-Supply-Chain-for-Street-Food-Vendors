<?php
include 'php/db.php';

// Redirect if not in verification process
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['temp_user_id'];
$email = $_SESSION['temp_user_email'];
$name = $_SESSION['temp_user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp_code']);
        
        if (empty($entered_otp)) {
            $_SESSION['error'] = 'Please enter the OTP code';
        } elseif (strlen($entered_otp) !== 6 || !ctype_digit($entered_otp)) {
            $_SESSION['error'] = 'Please enter a valid 6-digit OTP code';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            try {
                $transaction_started = false;
                
                // Debug: Log the OTP verification attempt
                error_log("OTP Verification Attempt - User ID: $user_id, Entered OTP: '$entered_otp'");
                
                // First, let's get the latest valid OTP for this user to see what we're working with
                $debug_query = "SELECT otp_code, expires_at, is_used, UNIX_TIMESTAMP(expires_at) as exp_timestamp, UNIX_TIMESTAMP() as current_ts 
                               FROM email_verifications 
                               WHERE user_id = :user_id 
                               ORDER BY created_at DESC LIMIT 1";
                $debug_stmt = $db->prepare($debug_query);
                $debug_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $debug_stmt->execute();
                
                if ($debug_stmt->rowCount() > 0) {
                    $debug_info = $debug_stmt->fetch(PDO::FETCH_ASSOC);
                    error_log("Latest OTP for user $user_id: Code='{$debug_info['otp_code']}', Used={$debug_info['is_used']}, Expires={$debug_info['expires_at']}, ExpTimestamp={$debug_info['exp_timestamp']}, CurrentTimestamp={$debug_info['current_ts']}");
                }
                
                // Check OTP - simplified query for better debugging
                $query = "SELECT id, otp_code, expires_at, is_used, created_at,
                                UNIX_TIMESTAMP(expires_at) as exp_timestamp,
                                UNIX_TIMESTAMP() as current_ts
                         FROM email_verifications 
                         WHERE user_id = :user_id 
                           AND otp_code = :otp_code
                           AND is_used = 0 
                           AND expires_at > NOW()
                         ORDER BY created_at DESC LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':otp_code', $entered_otp, PDO::PARAM_STR);
                $stmt->execute();
                
                // Debug: Log query result
                error_log("OTP Query Result - Rows found: " . $stmt->rowCount());
                
                if ($stmt->rowCount() > 0) {
                    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Start transaction
                    $db->beginTransaction();
                    $transaction_started = true;
                    
                    try {
                        // Mark user as verified
                        $query = "UPDATE users SET is_verified = TRUE, email_verified_at = NOW() WHERE id = :user_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        
                        // Mark OTP as used
                        $query = "UPDATE email_verifications SET is_used = TRUE WHERE id = :verification_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':verification_id', $verification['id']);
                        $stmt->execute();
                        
                        $db->commit();
                        $transaction_started = false;
                        
                        // Get user details
                        $query = "SELECT * FROM users WHERE id = :user_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Send welcome email (but don't let email errors stop verification)
                        try {
                            require_once 'php/email_service.php';
                            $emailService = new EmailService();
                            $emailService->sendWelcomeEmail($user['email'], $user['name'], $user['role']);
                        } catch (Exception $email_e) {
                            // Log email error but don't fail verification
                            error_log("Welcome email failed: " . $email_e->getMessage());
                        }
                        
                        // Clean up session
                        unset($_SESSION['temp_user_id']);
                        unset($_SESSION['temp_user_email']);
                        unset($_SESSION['temp_user_name']);
                        
                        $_SESSION['success'] = 'Email verified successfully! You can now login to your account.';
                        header('Location: index.php');
                        exit();
                    } catch (PDOException $inner_e) {
                        if ($transaction_started) {
                            $db->rollBack();
                        }
                        throw $inner_e;
                    }
                } else {
                    // OTP not found with current criteria - let's debug why
                    $debug_info = [];
                    $debug_info[] = "OTP NOT FOUND - Starting detailed debugging for user $user_id with OTP '$entered_otp'";
                    
                    // Check ALL OTPs for this user to see what we have
                    $debug_all_query = "SELECT id, otp_code, expires_at, is_used, created_at, 
                                              UNIX_TIMESTAMP(expires_at) as exp_ts, 
                                              UNIX_TIMESTAMP() as now_ts,
                                              (expires_at > NOW()) as not_expired,
                                              LENGTH(otp_code) as code_length,
                                              CHAR_LENGTH(otp_code) as char_length
                                       FROM email_verifications 
                                       WHERE user_id = :user_id 
                                       ORDER BY created_at DESC";
                    $debug_all_stmt = $db->prepare($debug_all_query);
                    $debug_all_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $debug_all_stmt->execute();
                    
                    $debug_info[] = "Found " . $debug_all_stmt->rowCount() . " total OTPs for user $user_id:";
                    
                    $found_exact_match = false;
                    while ($debug_row = $debug_all_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $debug_info[] = "OTP ID {$debug_row['id']}: Code='{$debug_row['otp_code']}' (len={$debug_row['code_length']}), Used={$debug_row['is_used']}, Expires={$debug_row['expires_at']}, NotExpired={$debug_row['not_expired']}";
                        
                        // Check exact match
                        if ($debug_row['otp_code'] === $entered_otp) {
                            $found_exact_match = true;
                            $debug_info[] = "EXACT MATCH FOUND! But failed main query - Used: {$debug_row['is_used']}, NotExpired: {$debug_row['not_expired']}";
                        }
                    }
                    
                    // If we're in development mode, show debug info
                    if (ini_get('display_errors') || isset($_GET['debug'])) {
                        $_SESSION['error'] = '🔍 DEBUG INFO:<br>' . implode('<br>', $debug_info) . '<br><br>❌ Invalid OTP code. <a href="otp_debug_live.php" target="_blank">Open Live Debugger</a>';
                    } else {
                        $_SESSION['error'] = '❌ Invalid OTP code. Please check and try again. <a href="?debug=1">Show Debug Info</a>';
                    }
                    
                    // Also log for server-side debugging
                    foreach ($debug_info as $info) {
                        error_log($info);
                    }
                    
                    // Still do the original check for proper error messages
                    $query = "SELECT expires_at, is_used, otp_code, created_at FROM email_verifications 
                             WHERE user_id = :user_id AND otp_code = :otp_code 
                             ORDER BY created_at DESC LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->bindParam(':otp_code', $entered_otp, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    // Debug: Log detailed check
                    error_log("OTP Detailed Check - User: $user_id, Entered: '$entered_otp', Found: " . $stmt->rowCount());
                    
                    if ($stmt->rowCount() > 0) {
                        $otp_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        error_log("OTP Info - Code in DB: '" . $otp_info['otp_code'] . "', Used: " . ($otp_info['is_used'] ? 'YES' : 'NO') . ", Expires: " . $otp_info['expires_at']);
                        
                        if ($otp_info['is_used']) {
                            $_SESSION['error'] = '❌ This OTP has already been used. Please request a new one.';
                        } elseif (strtotime($otp_info['expires_at']) < time()) {
                            $_SESSION['error'] = '⏰ This OTP has expired. Please request a new one.';
                        } else {
                            $_SESSION['error'] = '❌ Invalid OTP code. Please check and try again.';
                        }
                    } else {
                        // Check if any OTP exists for this user at all
                        $query = "SELECT COUNT(*) as count FROM email_verifications WHERE user_id = :user_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        error_log("Total OTPs for user $user_id: " . $count_result['count']);
                        
                        $_SESSION['error'] = '❌ Invalid OTP code. Please check the code and try again. If you need help, use the debug link below.';
                    }
                }
            } catch (PDOException $e) {
                // Only rollback if we started a transaction
                if (isset($transaction_started) && $transaction_started) {
                    try {
                        $db->rollBack();
                    } catch (PDOException $rollback_e) {
                        // Ignore rollback errors if transaction wasn't active
                        error_log("Rollback failed: " . $rollback_e->getMessage());
                    }
                }
                // Log detailed error information
                error_log("OTP verification PDO error: " . $e->getMessage());
                error_log("Error Code: " . $e->getCode());
                error_log("SQL State: " . $e->errorInfo[0] ?? 'N/A');
                error_log("User ID: $user_id, Entered OTP: '$entered_otp'");
                
                // Show more specific error message in development
                if (ini_get('display_errors')) {
                    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
                } else {
                    $_SESSION['error'] = 'Verification failed due to a system error. Please try again or contact support.';
                }
            }
        }
    } elseif (isset($_POST['resend_otp'])) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // Check if we can resend (limit to 1 request per minute)
            $query = "SELECT COUNT(*) as recent_count FROM email_verifications 
                     WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['recent_count'] > 0) {
                $_SESSION['error'] = 'Please wait at least 1 minute before requesting a new OTP.';
            } else {
                // Generate new OTP - ensure it's always a 6-digit string
                $otp_code = str_pad(strval(rand(0, 999999)), 6, '0', STR_PAD_LEFT);
                
                // Log OTP generation for debugging
                error_log("Generating new OTP for user $user_id: '$otp_code'");
                
                // Save new OTP using MySQL NOW() to avoid timezone issues
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
                    $_SESSION['success'] = 'New OTP sent to your email address.';
                } else {
                    $_SESSION['error'] = 'Failed to send OTP. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to resend OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - StreetSource</title>
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
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3 class="mb-0">📧 Verify Your Email</h3>
                        <p class="text-muted small mt-2">Check your inbox for the verification code</p>
                    </div>
                    <div class="card-body">
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
                        
                        <div class="text-center mb-4">
                            <div style="font-size: 4rem;">📨</div>
                            <h5>Email Sent!</h5>
                            <p class="text-muted">We've sent a 6-digit verification code to:</p>
                            <p class="fw-bold text-primary"><?php echo htmlspecialchars($email); ?></p>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="otp_code" class="form-label">Enter OTP Code *</label>
                                <input type="text" class="form-control form-control-lg text-center" 
                                       id="otp_code" name="otp_code" maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000" autocomplete="one-time-code" required
                                       value="<?php echo isset($_POST['otp_code']) ? htmlspecialchars($_POST['otp_code']) : ''; ?>"
                                       style="font-size: 24px; letter-spacing: 8px;">
                                <div id="otpHelper" class="form-text text-muted mt-2" style="display: none;">
                                    <small>Please enter the 6-digit code from your email</small>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="verify_otp" id="verifyBtn" class="btn btn-primary btn-lg">
                                    ✅ Verify Email
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted small">Didn't receive the code?</p>
                            <form method="POST" action="" class="d-inline">
                                <button type="submit" name="resend_otp" class="btn btn-outline-secondary btn-sm">
                                    🔄 Resend OTP
                                </button>
                            </form>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="text-muted">📋 Verification Tips:</h6>
                            <ul class="text-muted small mb-0">
                                <li>Check your spam/junk folder if you don't see the email</li>
                                <li>The OTP code expires in 10 minutes</li>
                                <li>You can request a new code if needed</li>
                                <li>Make sure your email address is correct</li>
                                <li><strong>Don't refresh the page</strong> - your entered code will be preserved</li>
                                <li>🔍 <a href="debug_otp.php" target="_blank" class="text-decoration-none">Debug OTP Issues</a> (for troubleshooting)</li>
                            </ul>
                        </div>
                        
                        <?php
                        // Get OTP expiry time for countdown
                        try {
                            $database = new Database();
                            $db = $database->getConnection();
                            $query = "SELECT expires_at FROM email_verifications 
                                     WHERE user_id = :user_id AND is_used = FALSE 
                                     ORDER BY created_at DESC LIMIT 1";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->execute();
                            
                            if ($stmt->rowCount() > 0) {
                                $verification = $stmt->fetch(PDO::FETCH_ASSOC);
                                $expires_at = strtotime($verification['expires_at']);
                                $remaining_seconds = $expires_at - time();
                                
                                if ($remaining_seconds > 0) {
                                    echo "<div class='alert alert-info mt-3' id='otpTimer'>";
                                    echo "<small>⏱️ OTP expires in: <span id='countdown' class='fw-bold'></span></small>";
                                    echo "</div>";
                                    echo "<script>var remainingTime = $remaining_seconds;</script>";
                                }
                            }
                        } catch (PDOException $e) {
                            // Ignore timer errors
                        }
                        ?>
                        
                        <div class="mt-4 text-center">
                            <h6 class="text-muted">Need Help?</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small>📧 Email: support@streetsource.com</small>
                                </div>
                                <div class="col-md-6">
                                    <small>📱 WhatsApp: +91-9999-999-999</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format OTP input and provide visual feedback
        document.getElementById('otp_code').addEventListener('input', function(e) {
            // Only allow numbers
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
            
            // Update UI based on input length
            const helper = document.getElementById('otpHelper');
            const button = document.getElementById('verifyBtn');
            
            if (value.length === 6) {
                helper.style.display = 'block';
                helper.className = 'form-text text-success mt-2';
                helper.innerHTML = '<small>✅ Ready to verify!</small>';
                button.className = 'btn btn-success btn-lg';
                button.innerHTML = '✅ Ready to Verify!';
                
                // Add subtle glow effect
                e.target.style.borderColor = '#28a745';
                e.target.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
            } else if (value.length > 0) {
                helper.style.display = 'block';
                helper.className = 'form-text text-info mt-2';
                helper.innerHTML = `<small>📝 ${value.length}/6 digits entered...</small>`;
                button.className = 'btn btn-primary btn-lg';
                button.innerHTML = '✅ Verify Email';
                
                // Reset styling
                e.target.style.borderColor = '';
                e.target.style.boxShadow = '';
            } else {
                helper.style.display = 'none';
                button.className = 'btn btn-primary btn-lg';
                button.innerHTML = '✅ Verify Email';
                
                // Reset styling
                e.target.style.borderColor = '';
                e.target.style.boxShadow = '';
            }
        });
        
        // Focus on OTP input
        document.getElementById('otp_code').focus();
        
        // Prevent form submission if OTP is not 6 digits
        document.querySelector('form').addEventListener('submit', function(e) {
            const otpValue = document.getElementById('otp_code').value;
            if (e.submitter && e.submitter.name === 'verify_otp' && otpValue.length !== 6) {
                e.preventDefault();
                document.getElementById('otp_code').classList.add('is-invalid');
                setTimeout(() => {
                    document.getElementById('otp_code').classList.remove('is-invalid');
                }, 2000);
            }
        });
        
        // Add shake animation on validation error
        document.addEventListener('DOMContentLoaded', function() {
            const errorAlert = document.querySelector('.alert-danger');
            if (errorAlert) {
                const otpInput = document.getElementById('otp_code');
                otpInput.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => {
                    otpInput.style.animation = '';
                }, 500);
            }
        });
        
        // Add CSS for shake animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
        
        // Auto-dismiss success alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    const alert = new bootstrap.Alert(successAlert);
                    alert.close();
                }, 5000);
            }
        });
        
        // OTP Countdown Timer
        if (typeof remainingTime !== 'undefined' && remainingTime > 0) {
            function updateCountdown() {
                const minutes = Math.floor(remainingTime / 60);
                const seconds = remainingTime % 60;
                const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                const countdownElement = document.getElementById('countdown');
                if (countdownElement) {
                    countdownElement.textContent = display;
                    
                    if (remainingTime <= 60) {
                        countdownElement.className = 'fw-bold text-danger';
                    } else if (remainingTime <= 180) {
                        countdownElement.className = 'fw-bold text-warning';
                    } else {
                        countdownElement.className = 'fw-bold text-success';
                    }
                }
                
                if (remainingTime <= 0) {
                    const timerDiv = document.getElementById('otpTimer');
                    if (timerDiv) {
                        timerDiv.innerHTML = '<small class="text-danger">⏰ OTP has expired. Please request a new one.</small>';
                    }
                    clearInterval(countdownInterval);
                    return;
                }
                
                remainingTime--;
            }
            
            updateCountdown(); // Initial call
            const countdownInterval = setInterval(updateCountdown, 1000);
        }
    </script>
</body>
</html>
