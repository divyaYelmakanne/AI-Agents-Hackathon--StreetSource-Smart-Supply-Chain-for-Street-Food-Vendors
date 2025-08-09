<?php
include 'db.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        
        // Basic validation
        if (empty($name)) {
            $response['message'] = 'Name is required.';
        } else {
            try {
                // Handle profile photo upload
                $photo_path = null;
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $photo_path = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $photo_path;
                        
                        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                            $response['message'] = 'Failed to upload profile photo.';
                            $photo_path = null;
                        }
                    } else {
                        $response['message'] = 'Invalid image format. Please use JPG, PNG, or GIF.';
                    }
                }
                
                if (empty($response['message'])) {
                    // Update profile (only name and photo)
                    $query = "UPDATE users SET name = :name" . 
                            ($photo_path ? ", shop_logo = :photo_path" : "") . 
                            " WHERE id = :user_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($photo_path) {
                        $stmt->bindParam(':photo_path', $photo_path);
                    }
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $name; // Update session
                        $response['success'] = true;
                        $response['message'] = 'Profile updated successfully!';
                    } else {
                        $response['message'] = 'Failed to update profile.';
                    }
                }
            } catch (PDOException $e) {
                $response['message'] = 'Database error occurred.';
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $response['message'] = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $response['message'] = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $response['message'] = 'New password must be at least 6 characters long.';
        } else {
            try {
                // Get current password hash
                $query = "SELECT password FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = :password WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Password changed successfully!';
                    } else {
                        $response['message'] = 'Failed to change password.';
                    }
                } else {
                    $response['message'] = 'Current password is incorrect.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Database error occurred.';
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
