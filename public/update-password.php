<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['password_update_error'] = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['password_update_error'] = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $_SESSION['password_update_error'] = 'Password must be at least 8 characters long.';
    } else {
        try {
            $db = get_db_connection();
            
            // Start transaction
            $db->beginTransaction();
            
            // Get current user's password hash with FOR UPDATE to lock the row
            $stmt = $db->prepare("SELECT id, password, username FROM users WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found.');
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Check if new password is different from current
            if (password_verify($new_password, $user['password'])) {
                throw new Exception('New password must be different from current password.');
            }
            
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
            
            $result = $updateStmt->execute([
                'password' => $password_hash,
                'id' => $user_id
            ]);
            
            if (!$result) {
                throw new Exception('Failed to update password in database.');
            }
            
            // Get client IP and user agent
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Log the password change with additional security context
            $logStmt = $db->prepare("
                INSERT INTO user_password_history 
                (user_id, username, password_hash, changed_at, changed_by, ip_address, user_agent) 
                VALUES (:user_id, :username, :password_hash, NOW(), :changed_by, :ip_address, :user_agent)
            ");
            
            $logStmt->execute([
                'user_id' => $user_id,
                'username' => $user['username'],
                'password_hash' => $password_hash,
                'changed_by' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ]);
            
            // Update session timestamp
            $_SESSION['last_password_change'] = time();
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['password_update_success'] = 'Password updated successfully!';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            // Log the error
            error_log('Password update error for user ID ' . $user_id . ': ' . $e->getMessage());
            
            // Set user-friendly error message
            $_SESSION['password_update_error'] = $e->getMessage();
        }
    }
}

// Always redirect back to user-info.php
header('Location: user-info.php');
exit();
