<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['available' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get username from request
$username = trim($_GET['username'] ?? '');
$currentUserId = $_GET['current_id'] ?? null;

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['available' => false, 'message' => 'Username is required']);
    exit();
}

try {
    // Check if username exists
    $query = "SELECT id, username FROM users WHERE username = :username";
    $params = [':username' => $username];
    
    // If checking for an existing user (update case), exclude the current user
    if ($currentUserId) {
        $query .= " AND id != :user_id";
        $params[':user_id'] = $currentUserId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        echo json_encode([
            'available' => false,
            'message' => 'This username is already taken'
        ]);
    } else {
        // Check username requirements
        if (strlen($username) < 3) {
            echo json_encode([
                'available' => false,
                'message' => 'Username must be at least 3 characters long'
            ]);
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            echo json_encode([
                'available' => false,
                'message' => 'Username can only contain letters, numbers, and underscores'
            ]);
        } else {
            echo json_encode([
                'available' => true,
                'message' => 'Username is available'
            ]);
        }
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'available' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
