<?php
// Clear any previous output and set JSON header
if (ob_get_level()) {
    ob_end_clean();
}

include 'db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Calculate distance between two coordinates using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
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
        
        // Database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Get all suppliers (handle missing columns gracefully)
        $query = "SELECT u.id, u.name, u.email, u.role,
                  COALESCE(u.business_name, u.name) as display_name,
                  COALESCE(u.latitude, 0) as latitude, 
                  COALESCE(u.longitude, 0) as longitude,
                  COALESCE(u.address, '') as address, 
                  COALESCE(u.phone, '') as phone
                  FROM users u 
                  WHERE u.role = 'supplier'
                  ORDER BY u.name";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $all_suppliers = [];
        
        foreach ($suppliers as $supplier) {
            // Safely get coordinates with defaults
            $supplier_lat = floatval($supplier['latitude'] ?? 0);
            $supplier_lng = floatval($supplier['longitude'] ?? 0);
            
            // Calculate distance and nearby status
            if ($supplier_lat != 0 && $supplier_lng != 0) {
                $distance = calculateDistance(
                    $vendor_latitude, 
                    $vendor_longitude, 
                    $supplier_lat, 
                    $supplier_lng
                );
                $supplier['distance'] = round($distance, 2);
                $supplier['is_nearby'] = ($distance <= $max_distance);
            } else {
                $supplier['distance'] = 9999;
                $supplier['is_nearby'] = false;
            }
            
            // Safely handle rating data
            try {
                $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                               FROM reviews WHERE supplier_id = ?";
                $rating_stmt = $db->prepare($rating_query);
                $rating_stmt->execute([$supplier['id']]);
                $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
                
                $supplier['avg_rating'] = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 4.0;
                $supplier['review_count'] = $rating_data['review_count'] ?? 0;
            } catch (PDOException $e) {
                // If reviews table doesn't exist, use defaults
                $supplier['avg_rating'] = 4.0;
                $supplier['review_count'] = 0;
            }
            
            // Safely handle products data
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
            
            // Ensure required fields exist
            $supplier['name'] = $supplier['display_name'] ?? $supplier['name'] ?? 'Unknown Supplier';
            $supplier['email'] = $supplier['email'] ?? '';
            $supplier['phone'] = $supplier['phone'] ?? '';
            $supplier['address'] = $supplier['address'] ?? '';
            
            $all_suppliers[] = $supplier;
        }
        
        // Sort by distance (nearby first)
        usort($all_suppliers, function($a, $b) {
            if ($a['is_nearby'] && !$b['is_nearby']) {
                return -1;
            }
            if (!$a['is_nearby'] && $b['is_nearby']) {
                return 1;
            }
            return $a['distance'] <=> $b['distance'];
        });
        
        // Count nearby suppliers
        $nearby_count = count(array_filter($all_suppliers, function($supplier) {
            return $supplier['is_nearby'];
        }));
        
        echo json_encode([
            'success' => true,
            'suppliers' => $all_suppliers,
            'total_found' => count($all_suppliers),
            'nearby_count' => $nearby_count,
            'search_radius' => $max_distance,
            'vendor_location' => [
                'latitude' => $vendor_latitude,
                'longitude' => $vendor_longitude
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Supplier fetch error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'System error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if user is logged in - if not, provide a more helpful error
        if (!isLoggedIn()) {
            echo json_encode([
                'success' => false,
                'error' => 'Please log in to search for suppliers',
                'code' => 'NOT_LOGGED_IN',
                'redirect' => '../index.php'
            ]);
            exit;
        }
        
        // Get user role, try both session keys for compatibility
        $user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
        
        // Check if user is a vendor
        if ($user_role !== 'vendor') {
            echo json_encode([
                'success' => false,
                'error' => 'Only vendors can search for suppliers',
                'code' => 'INVALID_ROLE',
                'current_role' => $user_role
            ]);
            exit;
        }
        
        // Get coordinates from GET parameters
        $vendor_latitude = floatval($_GET['lat'] ?? 0);
        $vendor_longitude = floatval($_GET['lng'] ?? 0);
        $max_distance = floatval($_GET['radius'] ?? 10); // Default 10 km
        
        // Check if we have valid coordinates
        if ($vendor_latitude == 0 || $vendor_longitude == 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Location coordinates are required. Please enable location tracking.',
                'code' => 'NO_LOCATION',
                'received_lat' => $_GET['lat'] ?? 'not provided',
                'received_lng' => $_GET['lng'] ?? 'not provided'
            ]);
            exit;
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Simplified query to get suppliers with their location
        $query = "SELECT u.id, u.name, u.email, u.phone, u.latitude, u.longitude
                  FROM users u
                  WHERE u.role = 'supplier' 
                    AND u.is_verified = 1
                    AND u.latitude IS NOT NULL 
                    AND u.longitude IS NOT NULL";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $all_suppliers = [];
        
        foreach ($suppliers as $supplier) {
            // Ensure coordinates are properly converted to float
            $supplier_lat = floatval($supplier['latitude']);
            $supplier_lng = floatval($supplier['longitude']);
            
            // Calculate distance for all suppliers (even those without coordinates)
            if ($supplier_lat != 0 && $supplier_lng != 0) {
                $distance = calculateDistance(
                    $vendor_latitude, 
                    $vendor_longitude, 
                    $supplier_lat, 
                    $supplier_lng
                );
                $supplier['distance'] = round($distance, 2);
                $supplier['is_nearby'] = ($distance <= $max_distance);
            } else {
                // For suppliers without coordinates, set a high distance
                $supplier['distance'] = 9999;
                $supplier['is_nearby'] = false;
            }
            
            // Calculate real average rating from reviews table
            $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                           FROM reviews WHERE supplier_id = ?";
            $rating_stmt = $db->prepare($rating_query);
            $rating_stmt->execute([$supplier['id']]);
            $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
            
            $supplier['avg_rating'] = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 4.0;
            $supplier['review_count'] = $rating_data['review_count'];
            
            // Get products for this supplier
            $product_query = "SELECT id, name, price, stock, unit, description, image_url
                             FROM products 
                             WHERE supplier_id = :supplier_id AND is_active = 1
                             ORDER BY created_at DESC LIMIT 10";
            $product_stmt = $db->prepare($product_query);
            $product_stmt->bindParam(':supplier_id', $supplier['id']);
            $product_stmt->execute();
            $supplier['products'] = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $all_suppliers[] = $supplier;
        }
        
        // Sort by distance (nearest first, then all others)
        usort($all_suppliers, function($a, $b) {
            // Nearby suppliers first, then by distance
            if ($a['is_nearby'] && !$b['is_nearby']) {
                return -1; // a comes before b
            }
            if (!$a['is_nearby'] && $b['is_nearby']) {
                return 1; // b comes before a
            }
            // Both nearby or both far - sort by distance
            return $a['distance'] <=> $b['distance'];
        });
        
        // Count nearby suppliers
        $nearby_count = array_filter($all_suppliers, function($supplier) {
            return $supplier['is_nearby'];
        });
        
        echo json_encode([
            'success' => true,
            'suppliers' => $all_suppliers,
            'vendor_location' => [
                'latitude' => $vendor_latitude,
                'longitude' => $vendor_longitude
            ],
            'total_found' => count($all_suppliers),
            'nearby_count' => count($nearby_count),
            'search_radius' => $max_distance
        ]);
        
    } catch (PDOException $e) {
        // Log the actual error for debugging
        error_log("Supplier fetch error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred'
        ]);
    } catch (Exception $e) {
        error_log("General error in get_nearby_suppliers: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => 'System error occurred'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
