<?php
/**
 * Email Configuration for StreetSource
 * 
 * IMPORTANT: Update these settings with your actual email credentials
 * 
 * For Gmail:
 * 1. Enable 2-Factor Authentication
 * 2. Generate an App Password: https://support.google.com/mail/answer/185833
 * 3. Use the App Password (not your regular Gmail password)
 * 
 * For other email providers:
 * Update the SMTP settings according to your provider's documentation
 */

return [
    // SMTP Server Settings
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_auth' => true,
    'smtp_username' => 'pavanmalith3@gmail.com',
    'smtp_password' => 'qsqa drxj xflr ergx',
    'smtp_security' => 'tls', // 'tls' or 'ssl'
    
    // Sender Information
    'from_email' => 'pavanmalith3@gmail.com',    // Sender email (same as username)
    'from_name' => 'StreetSource Platform',    // Sender name
    
    // Email Settings
    'reply_to' => 'pavanmalith3@gmail.com',  // Reply-to address
    'charset' => 'UTF-8',                      // Email charset
    
    // For other email providers, use these common settings:
    
    // Outlook/Hotmail:
    // 'smtp_host' => 'smtp-mail.outlook.com',
    // 'smtp_port' => 587,
    
    // Yahoo:
    // 'smtp_host' => 'smtp.mail.yahoo.com',
    // 'smtp_port' => 587,
    
    // Custom SMTP:
    // 'smtp_host' => 'mail.yourdomain.com',
    // 'smtp_port' => 587,
];

/**
 * SETUP INSTRUCTIONS:
 * 
 * 1. GMAIL SETUP (Recommended for testing):
 *    - Go to Google Account settings
 *    - Enable 2-Factor Authentication
 *    - Generate App Password for "Mail"
 *    - Replace 'your_email@gmail.com' with your Gmail
 *    - Replace 'your_app_password' with the 16-character app password
 * 
 * 2. TEST EMAIL FUNCTIONALITY:
 *    - Run setup.php to create database
 *    - Register a new account with a real email
 *    - Check if you receive the OTP email
 * 
 * 3. PRODUCTION DEPLOYMENT:
 *    - Use a dedicated email service (SendGrid, Mailgun, etc.)
 *    - Set up proper SPF, DKIM, and DMARC records
 *    - Use environment variables for credentials
 * 
 * 4. TROUBLESHOOTING:
 *    - Check spam/junk folder
 *    - Verify SMTP credentials
 *    - Enable "Less secure app access" (not recommended for production)
 *    - Check firewall/network settings
 */
?>
