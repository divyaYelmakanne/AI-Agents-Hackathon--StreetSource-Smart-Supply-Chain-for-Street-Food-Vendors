<?php
// Ultra-clean API version to fix the "Unexpected token '<'" error
error_reporting(0);
ini_set('display_errors', 0);

// Clean any previous output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

try {
    // Start session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set headers FIRST
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Please log in to search for suppliers',
            'code' => 'NOT_LOGGED_IN'
        ]);
        exit;
    }
    
    // Get parameters with defaults
    $vendor_latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : 28.6139;
    $vendor_longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : 77.2090;
    $max_distance = isset($_GET['radius']) ? floatval($_GET['radius']) : 50;
    
    // Direct database connection (no includes)
    $host = 'localhost';
    $dbname = 'streetsource';
    $username = 'root';
    $password = '';
    
    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'debug' => $e->getMessage()
        ]);
        exit;
    }
    
    // Calculate distance function
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // km
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
    
    // Get suppliers
    $query = "SELECT id, name, email, role,
              COALESCE(business_name, name) as display_name,
              COALESCE(latitude, 0) as latitude, 
              COALESCE(longitude, 0) as longitude,
              COALESCE(address, '') as address, 
              COALESCE(phone, '') as phone
              FROM users 
              WHERE role = 'supplier' AND status != 'inactive'
              ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll();
    
    $all_suppliers = [];
    
    foreach ($suppliers as $supplier) {
        // Safely get coordinates
        $supplier_lat = floatval($supplier['latitude']);
        $supplier_lng = floatval($supplier['longitude']);
        
        // Calculate distance
        if ($supplier_lat != 0 && $supplier_lng != 0) {
            $distance = calculateDistance($vendor_latitude, $vendor_longitude, $supplier_lat, $supplier_lng);
            $supplier['distance'] = round($distance, 2);
            $supplier['is_nearby'] = ($distance <= $max_distance);
        } else {
            $supplier['distance'] = 9999;
            $supplier['is_nearby'] = false;
        }
        
        // Add default values
        $supplier['avg_rating'] = 4.5;
        $supplier['review_count'] = 0;
        
        // Load products
        try {
            $product_query = "SELECT id, name, price, stock, unit, description, image_url
                             FROM products 
                             WHERE supplier_id = ?
                             ORDER BY created_at DESC LIMIT 10";
            $product_stmt = $db->prepare($product_query);
            $product_stmt->execute([$supplier['id']]);
            $supplier['products'] = $product_stmt->fetchAll();
        } catch (Exception $e) {
            $supplier['products'] = [];
        }
        
        // Clean up name field
        $supplier['name'] = $supplier['display_name'];
        unset($supplier['display_name']);
        
        $all_suppliers[] = $supplier;
    }
    
    // Sort by distance and nearby status
    usort($all_suppliers, function($a, $b) {
        if ($a['is_nearby'] && !$b['is_nearby']) return -1;
        if (!$a['is_nearby'] && $b['is_nearby']) return 1;
        return $a['distance'] <=> $b['distance'];
    });
    
    $nearby_count = count(array_filter($all_suppliers, function($s) { 
        return $s['is_nearby']; 
    }));
    
    // Clean output and return JSON
    ob_clean();
    
    $response = [
        'success' => true,
        'data' => $all_suppliers,
        'suppliers' => $all_suppliers,
        'total_found' => count($all_suppliers),
        'nearby_count' => $nearby_count,
        'search_radius' => $max_distance,
        'vendor_location' => [
            'latitude' => $vendor_latitude,
            'longitude' => $vendor_longitude
        ],
        'message' => $nearby_count > 0 ? 
            "Found {$nearby_count} nearby suppliers within {$max_distance}km radius" :
            "No suppliers found within {$max_distance}km radius. Showing all " . count($all_suppliers) . " available suppliers."
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

ob_end_flush();
?>
