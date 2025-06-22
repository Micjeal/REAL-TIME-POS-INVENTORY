<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get user ID from request
$userId = $_POST['id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Prevent deleting own account
if ($userId == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot delete your own account'
    ]);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, archive the user data (optional but recommended)
    $stmt = $pdo->prepare("
        INSERT INTO deleted_users 
        (user_id, username, name, email, role, deleted_at, deleted_by)
        SELECT id, username, name, email, role, NOW(), :deleted_by 
        FROM users 
        WHERE id = :id
    ");
    
    $deletedBy = $_SESSION['user_id'];
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':deleted_by', $deletedBy, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to archive user data');
    }
    
    // Delete related records from user_password_history
    $stmt = $pdo->prepare("DELETE FROM user_password_history WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete user password history');
    }
    
    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete user');
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
