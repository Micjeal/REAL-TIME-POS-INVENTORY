<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit();
}

$db = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = $_POST['user_id'];
    $defaultPassword = 'Welcome123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        // Log this action
        $stmt = $pdo->prepare("INSERT INTO user_password_history (user_id, username, password_hash, changed_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $_SESSION['username'],
            $hashedPassword,
            $_SESSION['user_id']
        ]);
        
        header('Location: security.php?success=password_reset');
        exit();
    } catch (PDOException $e) {
        header('Location: security.php?error=database_error');
        exit();
    }
} else {
    header('Location: security.php');
    exit();
}
