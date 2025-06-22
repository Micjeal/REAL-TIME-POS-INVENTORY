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

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare("SELECT id, username, name, email, role, active, created_at FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Format the response
        $response = [
            'success' => true,
            'data' => [
                'id' => (int)$user['id'],
                'username' => htmlspecialchars($user['username']),
                'name' => htmlspecialchars($user['name']),
                'email' => htmlspecialchars($user['email'] ?? ''),
                'role' => $user['role'],
                'active' => (int)$user['active'],
                'created_at' => $user['created_at']
            ]
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'User not found'
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
