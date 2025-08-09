<?php
/**
 * Real-time Location Update Service
 * Tracks and updates user location in real-time
 */

include 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure user is logged in
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['latitude']) || !isset($input['longitude'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid location data']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    $accuracy = floatval($input['accuracy'] ?? 0);
    
    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Update user location
        $query = "UPDATE users SET 
                    latitude = :latitude, 
                    longitude = :longitude, 
                    location_accuracy = :accuracy,
                    location_updated_at = NOW() 
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':accuracy', $accuracy);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            // Store in session for quick access
            $_SESSION['user_latitude'] = $latitude;
            $_SESSION['user_longitude'] = $longitude;
            
            // Log location update (optional - for tracking history)
            $log_query = "INSERT INTO location_history (user_id, latitude, longitude, accuracy, created_at) 
                         VALUES (:user_id, :latitude, :longitude, :accuracy, NOW())";
            
            try {
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':latitude', $latitude);
                $log_stmt->bindParam(':longitude', $longitude);
                $log_stmt->bindParam(':accuracy', $accuracy);
                $log_stmt->execute();
            } catch (PDOException $e) {
                // Location history table might not exist, but that's okay
                // Main location update was successful
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully',
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy' => $accuracy,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update location']);
        }
        
    } catch (PDOException $e) {
        error_log("Location update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get current user location
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT latitude, longitude, location_accuracy, location_updated_at 
                  FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($location && $location['latitude'] && $location['longitude']) {
            echo json_encode([
                'success' => true,
                'location' => [
                    'latitude' => floatval($location['latitude']),
                    'longitude' => floatval($location['longitude']),
                    'accuracy' => floatval($location['location_accuracy']),
                    'updated_at' => $location['location_updated_at']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No location data available'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Location fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
