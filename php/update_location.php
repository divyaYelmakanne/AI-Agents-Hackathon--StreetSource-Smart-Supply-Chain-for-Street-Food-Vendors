<?php
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    
    // Allow both vendors and suppliers to update location
    if (!in_array($_SESSION['user_role'], ['vendor', 'supplier'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Access denied'
        ]);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    $user_id = $_SESSION['user_id'];
    
    if ($latitude && $longitude) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $query = "UPDATE users SET latitude = :latitude, longitude = :longitude WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_latitude'] = $latitude;
                $_SESSION['user_longitude'] = $longitude;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Location updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update location'
                ]);
            }
        } catch (PDOException $e) {
            error_log("Location update error: " . $e->getMessage());
            error_log("User ID: $user_id, Lat: $latitude, Lng: $longitude");
            
            echo json_encode([
                'success' => false,
                'error' => 'Database error',
                'debug_info' => ini_get('display_errors') ? $e->getMessage() : 'Check server logs'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid coordinates'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
