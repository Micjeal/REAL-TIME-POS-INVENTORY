<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /index.php?error=unauthorized');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = $_POST['user_id'];
    $defaultPassword = 'Welcome123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
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
