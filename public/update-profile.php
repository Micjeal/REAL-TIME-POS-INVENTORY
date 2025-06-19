<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['phone'] ?? ''));
    
    // Basic validation
    if (empty($full_name)) {
        $error = 'Full name is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = get_db_connection();
            
            // Check if email is already taken by another user
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $stmt->execute([
                    'email' => $email,
                    'id' => $user_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'This email is already registered to another account.';
                    $_SESSION['profile_update_error'] = $error;
                    header('Location: user-info.php');
                    exit();
                }
            }
            
            // Update user profile
            $sql = "UPDATE users SET full_name = :full_name, 
                    email = :email, 
                    phone = :phone 
                    WHERE id = :id";
                    
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                'full_name' => $full_name,
                'email' => !empty($email) ? $email : null,
                'phone' => !empty($phone) ? $phone : null,
                'id' => $user_id
            ]);
            
            if ($result) {
                // Update session variables
                $_SESSION['full_name'] = $full_name;
                if (!empty($email)) $_SESSION['email'] = $email;
                if (!empty($phone)) $_SESSION['phone'] = $phone;
                
                $success = 'Profile updated successfully!';
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Store status in session
if (!empty($error)) {
    $_SESSION['profile_update_error'] = $error;
} elseif (!empty($success)) {
    $_SESSION['profile_update_success'] = $success;
}

header('Location: user-info.php');
exit();
