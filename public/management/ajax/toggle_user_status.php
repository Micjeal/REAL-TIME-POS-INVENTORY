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

// Get user ID and status from request
$userId = $_POST['id'] ?? null;
$status = isset($_POST['status']) ? (int)$_POST['status'] : null;

// Validate input
if (!$userId || $status === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'User ID and status are required'
    ]);
    exit();
}

// Prevent deactivating own account
if ($userId == $_SESSION['user_id'] && $status == 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot deactivate your own account'
    ]);
    exit();
}

try {
    // Check if user exists and get current status
    $stmt = $pdo->prepare("SELECT id, active FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // If status is the same, no need to update
    if ($user['active'] == $status) {
        echo json_encode([
            'success' => true,
            'message' => 'No change needed',
            'status' => (int)$status
        ]);
        exit();
    }
    
    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET active = :status, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Log this action
        $action = $status ? 'activated' : 'deactivated';
        $logStmt = $pdo->prepare("
            INSERT INTO user_activity_log 
            (user_id, action, action_details, ip_address, user_agent, created_at)
            VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())
        ");
        
        $actionDetails = json_encode([
            'target_user_id' => $userId,
            'previous_status' => $user['active'],
            'new_status' => $status,
            'changed_by' => $_SESSION['user_id']
        ]);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt->bindParam(':user_id', $_SESSION['user_id']);
        $logStmt->bindValue(':action', 'user_' . $action);
        $logStmt->bindParam(':details', $actionDetails);
        $logStmt->bindParam(':ip_address', $ipAddress);
        $logStmt->bindParam(':user_agent', $userAgent);
        $logStmt->execute();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'User ' . ($status ? 'activated' : 'deactivated') . ' successfully',
            'status' => (int)$status
        ]);
    } else {
        throw new Exception('Failed to update user status');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
