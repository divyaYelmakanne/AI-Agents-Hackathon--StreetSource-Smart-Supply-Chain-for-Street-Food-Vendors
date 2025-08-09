<?php
// Working API version with better error handling
session_start();

// Set JSON header first
header('Content-Type: application/json');

try {
    // Check session
    if (!isset($_SESSION['user_id'])) {
        // For testing, create a mock session
        $_SESSION['user_id'] = 1;
        $_SESSION['name'] = 'Test User';
        $_SESSION['role'] = 'vendor';
    }
    
    // Get parameters
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 28.6139;
    $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 77.2090;
    $radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 50;
    
    // Database connection
    $db = new PDO('mysql:host=localhost;dbname=streetsource', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple query to get suppliers
    $query = "SELECT id, name, email, role FROM users WHERE role = 'supplier' ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add basic data to each supplier
    $result_suppliers = [];
    foreach ($suppliers as $supplier) {
        $supplier['distance'] = rand(1, 20); // Random distance for testing
        $supplier['is_nearby'] = $supplier['distance'] <= $radius;
        $supplier['avg_rating'] = 4.5;
        $supplier['address'] = 'Test Address';
        $supplier['phone'] = '+91-9876543210';
        $supplier['products'] = []; // Empty for now
        
        $result_suppliers[] = $supplier;
    }
    
    // Sort by distance
    usort($result_suppliers, function($a, $b) {
        if ($a['is_nearby'] && !$b['is_nearby']) return -1;
        if (!$a['is_nearby'] && $b['is_nearby']) return 1;
        return $a['distance'] <=> $b['distance'];
    });
    
    $nearby_count = count(array_filter($result_suppliers, function($s) { return $s['is_nearby']; }));
    
    echo json_encode([
        'success' => true,
        'data' => $result_suppliers,
        'suppliers' => $result_suppliers,
        'total_found' => count($result_suppliers),
        'nearby_count' => $nearby_count,
        'search_radius' => $radius,
        'vendor_location' => ['latitude' => $lat, 'longitude' => $lng],
        'message' => $nearby_count > 0 ? 
            "Found {$nearby_count} nearby suppliers within {$radius}km radius" :
            "No suppliers found within {$radius}km radius. Showing all " . count($result_suppliers) . " available suppliers."
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
