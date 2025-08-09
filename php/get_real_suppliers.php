<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

include 'db.php';

try {
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get parameters
    $vendor_lat = floatval($_GET['lat'] ?? 28.6139); // Default to Delhi
    $vendor_lng = floatval($_GET['lng'] ?? 77.2090);
    $radius = intval($_GET['radius'] ?? 50); // Default 50km
    
    // First, try to get nearby suppliers within the specified radius
    $query = "
        SELECT 
            id,
            name,
            business_name,
            email,
            phone,
            address,
            latitude,
            longitude,
            created_at,
            (
                6371 * acos(
                    cos(radians(:vendor_lat)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(:vendor_lng)) + 
                    sin(radians(:vendor_lat)) * 
                    sin(radians(latitude))
                )
            ) AS distance
        FROM users 
        WHERE role = 'supplier' 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
        HAVING distance <= :radius
        ORDER BY distance ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendor_lat', $vendor_lat, PDO::PARAM_STR);
    $stmt->bindParam(':vendor_lng', $vendor_lng, PDO::PARAM_STR);
    $stmt->bindParam(':radius', $radius, PDO::PARAM_INT);
    $stmt->execute();
    
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no suppliers found within radius, get ALL suppliers and show them
    if (empty($suppliers)) {
        $query_all = "
            SELECT 
                id,
                name,
                business_name,
                email,
                phone,
                address,
                latitude,
                longitude,
                created_at,
                (
                    6371 * acos(
                        cos(radians(:vendor_lat)) * 
                        cos(radians(latitude)) * 
                        cos(radians(longitude) - radians(:vendor_lng)) + 
                        sin(radians(:vendor_lat)) * 
                        sin(radians(latitude))
                    )
                ) AS distance
            FROM users 
            WHERE role = 'supplier' 
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
            ORDER BY distance ASC
            LIMIT 20
        ";
        
        $stmt_all = $db->prepare($query_all);
        $stmt_all->bindParam(':vendor_lat', $vendor_lat, PDO::PARAM_STR);
        $stmt_all->bindParam(':vendor_lng', $vendor_lng, PDO::PARAM_STR);
        $stmt_all->execute();
        
        $suppliers = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Process suppliers data
    $processed_suppliers = [];
    $nearby_count = 0;
    
    foreach ($suppliers as $supplier) {
        $distance = round($supplier['distance'], 1);
        $is_nearby = $distance <= 10; // Consider within 10km as nearby
        
        if ($is_nearby) {
            $nearby_count++;
        }
        
        // Get products details for this supplier
        $products = [];
        $product_count = 0;
        try {
            $products_query = "SELECT id, name, price, description, image FROM products WHERE supplier_id = :supplier_id ORDER BY name";
            $products_stmt = $db->prepare($products_query);
            $products_stmt->bindParam(':supplier_id', $supplier['id']);
            $products_stmt->execute();
            $products_data = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $product_count = count($products_data);
            
            // Process products
            foreach ($products_data as $product) {
                $products[] = [
                    'id' => intval($product['id']),
                    'name' => $product['name'],
                    'price' => floatval($product['price']),
                    'description' => $product['description'] ?: 'No description available',
                    'image' => $product['image'] ?: null,
                    'image_url' => $product['image'] ? '../uploads/products/' . $product['image'] : null
                ];
            }
        } catch (Exception $e) {
            $product_count = 0;
            $products = [];
        }
        
        // Get average rating (if reviews table exists)
        $avg_rating = 4.0; // Default rating
        try {
            $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE supplier_id = :supplier_id";
            $rating_stmt = $db->prepare($rating_query);
            $rating_stmt->bindParam(':supplier_id', $supplier['id']);
            $rating_stmt->execute();
            $rating_result = $rating_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rating_result && $rating_result['avg_rating']) {
                $avg_rating = round($rating_result['avg_rating'], 1);
                $review_count = $rating_result['review_count'];
            } else {
                $review_count = 0;
            }
        } catch (Exception $e) {
            $review_count = 0;
        }
        
        $processed_suppliers[] = [
            'id' => intval($supplier['id']),
            'name' => $supplier['business_name'] ?: $supplier['name'],
            'business_name' => $supplier['business_name'],
            'contact_name' => $supplier['name'],
            'email' => $supplier['email'],
            'phone' => $supplier['phone'],
            'address' => $supplier['address'],
            'latitude' => floatval($supplier['latitude']),
            'longitude' => floatval($supplier['longitude']),
            'distance' => $distance,
            'is_nearby' => $is_nearby,
            'avg_rating' => $avg_rating,
            'review_count' => $review_count,
            'product_count' => $product_count,
            'products' => $products, // Full product details included
            'created_at' => $supplier['created_at'],
            'profile' => [
                'business_name' => $supplier['business_name'],
                'contact_person' => $supplier['name'],
                'email' => $supplier['email'],
                'phone' => $supplier['phone'],
                'address' => $supplier['address'],
                'member_since' => date('F Y', strtotime($supplier['created_at'])),
                'total_products' => $product_count,
                'average_rating' => $avg_rating,
                'total_reviews' => $review_count
            ]
        ];
    }
    
    // Determine search strategy message
    $search_message = "";
    if (count($processed_suppliers) > 0) {
        $nearby_suppliers = array_filter($processed_suppliers, function($s) { return $s['is_nearby']; });
        if (count($nearby_suppliers) > 0) {
            $search_message = "Found " . count($nearby_suppliers) . " nearby suppliers";
            if (count($processed_suppliers) > count($nearby_suppliers)) {
                $search_message .= " and " . (count($processed_suppliers) - count($nearby_suppliers)) . " more within {$radius}km";
            }
        } else {
            $search_message = "No nearby suppliers found. Showing " . count($processed_suppliers) . " suppliers from entire database";
        }
    } else {
        $search_message = "No suppliers found in database";
    }

    // Prepare response
    $response = [
        'success' => true,
        'suppliers' => $processed_suppliers,
        'data' => $processed_suppliers, // For backward compatibility
        'total_found' => count($processed_suppliers),
        'nearby_count' => $nearby_count,
        'search_radius' => $radius,
        'vendor_location' => [
            'latitude' => $vendor_lat,
            'longitude' => $vendor_lng
        ],
        'message' => $search_message,
        'version' => 'real_database_enhanced'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Error response
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'suppliers' => [],
        'data' => [],
        'total_found' => 0,
        'nearby_count' => 0,
        'message' => 'Error fetching suppliers: ' . $e->getMessage(),
        'version' => 'real_database'
    ];
    
    echo json_encode($error_response);
}
?>
