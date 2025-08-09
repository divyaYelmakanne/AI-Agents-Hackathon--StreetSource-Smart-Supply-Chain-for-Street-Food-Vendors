<?php
// Minimal working API for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clear any output
if (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session
session_start();

try {
    // Simple database connection test
    $host = 'localhost';
    $dbname = 'streetsource';
    $username = 'root';
    $password = '';
    
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test if we can query users table
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'supplier'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return simple success response
    echo json_encode([
        'success' => true,
        'message' => 'API is working!',
        'supplier_count' => $result['count'],
        'session_user_id' => $_SESSION['user_id'] ?? 'not set',
        'session_role' => $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'not set',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
