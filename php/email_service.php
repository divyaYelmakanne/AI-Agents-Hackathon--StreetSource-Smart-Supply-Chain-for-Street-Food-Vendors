<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        // Load email configuration
        $this->config = include 'email_config.php';
        
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->config['smtp_host'];
            $this->mailer->SMTPAuth   = $this->config['smtp_auth'];
            $this->mailer->Username   = 'pavanmalith3@gmail.com';
            $this->mailer->Password   = 'qsqa drxj xflr ergx';
            
            if ($this->config['smtp_security'] === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->config['smtp_security'] === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            $this->mailer->Port       = $this->config['smtp_port'];
            $this->mailer->CharSet    = $this->config['charset'];
            
            // Sender info
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addReplyTo($this->config['reply_to'], $this->config['from_name']);
            
        } catch (Exception $e) {
            error_log("Email service initialization failed: " . $e->getMessage());
        }
    }
    
    public function sendOTPEmail($to_email, $to_name, $otp_code) {
        try {
            // Recipients
            $this->mailer->addAddress($to_email, $to_name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Verify Your StreetSource Account - OTP Code';
            
            $html_body = $this->getOTPEmailTemplate($to_name, $otp_code);
            $this->mailer->Body = $html_body;
            
            // Plain text version
            $this->mailer->AltBody = "Dear $to_name,\n\nYour OTP for StreetSource account verification is: $otp_code\n\nThis code will expire in 10 minutes.\n\nBest regards,\nStreetSource Team";
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $e->getMessage());
            return false;
        } finally {
            $this->mailer->clearAddresses();
        }
    }
    
    public function sendWelcomeEmail($to_email, $to_name, $role) {
        try {
            // Recipients
            $this->mailer->addAddress($to_email, $to_name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Welcome to StreetSource - Account Verified!';
            
            $html_body = $this->getWelcomeEmailTemplate($to_name, $role);
            $this->mailer->Body = $html_body;
            
            // Plain text version
            $role_text = $role === 'vendor' ? 'vendor' : 'supplier';
            $this->mailer->AltBody = "Dear $to_name,\n\nWelcome to StreetSource! Your $role_text account has been successfully verified.\n\nYou can now start using our platform to connect with " . ($role === 'vendor' ? 'suppliers' : 'vendors') . " in your area.\n\nBest regards,\nStreetSource Team";
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
            return false;
        } finally {
            $this->mailer->clearAddresses();
        }
    }
    
    private function getOTPEmailTemplate($name, $otp_code) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>StreetSource OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; }
                .header { background: linear-gradient(135deg, #ff6b35, #004e89); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .otp-box { background-color: #f8f9fa; border: 2px dashed #ff6b35; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .otp-code { font-size: 32px; font-weight: bold; color: #ff6b35; letter-spacing: 8px; margin: 10px 0; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; }
                .btn { background-color: #ff6b35; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍲 StreetSource</h1>
                    <p>Email Verification Required</p>
                </div>
                <div class='content'>
                    <h2>Hello $name!</h2>
                    <p>Thank you for registering with StreetSource. To complete your account setup, please verify your email address using the OTP code below:</p>
                    
                    <div class='otp-box'>
                        <p><strong>Your OTP Code:</strong></p>
                        <div class='otp-code'>$otp_code</div>
                        <p><small>This code will expire in 10 minutes</small></p>
                    </div>
                    
                    <p>Enter this code on the verification page to activate your account and start connecting with the StreetSource community.</p>
                    
                    <p><strong>Why verify your email?</strong></p>
                    <ul>
                        <li>Secure your account</li>
                        <li>Receive important order notifications</li>
                        <li>Get platform updates and features</li>
                        <li>Build trust in the community</li>
                    </ul>
                    
                    <p>If you didn't create an account with StreetSource, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 StreetSource - Connecting Street Food Vendors with Trusted Suppliers</p>
                    <p><small>This is an automated email. Please do not reply to this message.</small></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getWelcomeEmailTemplate($name, $role) {
        $role_emoji = $role === 'vendor' ? '🧑‍🍳' : '🚚';
        $role_text = $role === 'vendor' ? 'Vendor' : 'Supplier';
        $features = $role === 'vendor' 
            ? "
                <li>🔍 Find nearby suppliers using GPS location</li>
                <li>🛒 Browse and order fresh ingredients</li>
                <li>📱 Track your orders in real-time</li>
                <li>⭐ Rate and review suppliers</li>
                <li>💰 Compare prices from multiple suppliers</li>
            "
            : "
                <li>📦 List your products and manage inventory</li>
                <li>📋 Receive and process vendor orders</li>
                <li>🌟 Build reputation through customer reviews</li>
                <li>📊 Track sales and performance metrics</li>
                <li>🤝 Connect with local street food vendors</li>
            ";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome to StreetSource</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; }
                .header { background: linear-gradient(135deg, #ff6b35, #004e89); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .welcome-box { background-color: #e8f5e8; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; }
                .btn { background-color: #ff6b35; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
                ul { text-align: left; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍲 StreetSource</h1>
                    <p>Welcome to the Community!</p>
                </div>
                <div class='content'>
                    <div class='welcome-box'>
                        <h2>$role_emoji Welcome, $name!</h2>
                        <p><strong>Your $role_text account is now verified and ready to use!</strong></p>
                    </div>
                    
                    <p>Congratulations! You've successfully joined StreetSource, India's leading platform connecting street food vendors with trusted suppliers.</p>
                    
                    <h3>🚀 What you can do now:</h3>
                    <ul>
                        $features
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/StreetSource/' class='btn'>Start Using StreetSource</a>
                    </div>
                    
                    <h3>📞 Need Help?</h3>
                    <p>Our support team is here to help you get started:</p>
                    <ul>
                        <li>📧 Email: support@streetsource.com</li>
                        <li>📱 WhatsApp: +91-9999-999-999</li>
                        <li>🕒 Support Hours: 9 AM - 6 PM (Mon-Sat)</li>
                    </ul>
                    
                    <p>Thank you for choosing StreetSource. Together, we're building a stronger street food ecosystem!</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 StreetSource - Empowering Street Food Vendors</p>
                    <p><small>Follow us on social media for updates and tips!</small></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public function sendEmail($to_email, $subject, $message, $to_name = '') {
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Recipients
            $this->mailer->addAddress($to_email, $to_name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            
            // Send email
            $result = $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
