<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'cashier';
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (!in_array($role, ['admin', 'manager', 'cashier'])) {
        $errors[] = 'Invalid role selected';
    }
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Username already exists';
    }
    
    if (empty($errors)) {
        try {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user
            $stmt = $db->prepare("
                INSERT INTO users (username, name, email, role, password, active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $username,
                !empty($full_name) ? $full_name : null,
                !empty($email) ? $email : null,
                $role,
                $hashedPassword,
                $is_active
            ]);
            
            // Log this action
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("
                INSERT INTO user_password_history 
                (user_id, username, password_hash, changed_at, changed_by, ip_address, user_agent) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $username,
                $hashedPassword,
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $_SESSION['success'] = 'User added successfully';
            header('Location: security.php');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: security.php');
        exit();
    }
} else {
    header('Location: security.php');
    exit();
}
