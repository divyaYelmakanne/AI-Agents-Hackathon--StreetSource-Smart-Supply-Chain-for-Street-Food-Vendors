<?php
// Fixed version of get_nearby_suppliers.php with better error handling
if (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering to catch any stray output
ob_start();

try {
    // Start session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Clean any output and return error
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Please log in to search for suppliers',
            'code' => 'NOT_LOGGED_IN'
        ]);
        exit;
    }
    
    // Get parameters with defaults
    $vendor_latitude = floatval($_GET['lat'] ?? 28.6139);
    $vendor_longitude = floatval($_GET['lng'] ?? 77.2090);
    $max_distance = floatval($_GET['radius'] ?? 50);
    
    // Direct database connection
    $host = 'localhost';
    $dbname = 'streetsource';
    $username = 'root';
    $password = '';
    
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Calculate distance function
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371;
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
    
    // Get suppliers with safe field selection
    $query = "SELECT id, name, email, role,
              COALESCE(business_name, name) as display_name,
              COALESCE(latitude, 0) as latitude, 
              COALESCE(longitude, 0) as longitude,
              COALESCE(address, '') as address, 
              COALESCE(phone, '') as phone
              FROM users 
              WHERE role = 'supplier'
              ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        
        // Add default values and load products
        $supplier['avg_rating'] = 4.0;
        $supplier['review_count'] = 0;
        
        // Try to load products for this supplier
        try {
            $product_query = "SELECT id, name, price, stock, unit, description, image_url
                             FROM products 
                             WHERE supplier_id = ?
                             ORDER BY created_at DESC LIMIT 10";
            $product_stmt = $db->prepare($product_query);
            $product_stmt->execute([$supplier['id']]);
            $supplier['products'] = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If products table has issues, use empty array
            $supplier['products'] = [];
        }
        
        $supplier['name'] = $supplier['display_name'];
        
        $all_suppliers[] = $supplier;
    }
    
    // Sort by distance and nearby status (nearby first, then by distance)
    usort($all_suppliers, function($a, $b) {
        // Nearby suppliers first
        if ($a['is_nearby'] && !$b['is_nearby']) return -1;
        if (!$a['is_nearby'] && $b['is_nearby']) return 1;
        
        // Then sort by distance within each group
        return $a['distance'] <=> $b['distance'];
    });
    
    $nearby_count = count(array_filter($all_suppliers, function($s) { return $s['is_nearby']; }));
    
    // Clean any stray output before JSON
    ob_clean();
    
    // Return data in multiple formats for compatibility
    echo json_encode([
        'success' => true,
        'data' => $all_suppliers,        // For older frontend code
        'suppliers' => $all_suppliers,   // For newer frontend code
        'total_found' => count($all_suppliers),
        'nearby_count' => $nearby_count,
        'search_radius' => $max_distance,
        'vendor_location' => [
            'latitude' => $vendor_latitude,
            'longitude' => $vendor_longitude
        ],
        'message' => $nearby_count > 0 ? 
            "Found {$nearby_count} nearby suppliers within {$max_distance}km radius" :
            "No suppliers found within {$max_distance}km radius. Showing all {" . count($all_suppliers) . "} available suppliers."
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// Clean up
ob_end_flush();
?>
