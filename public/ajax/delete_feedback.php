<?php
// Start the session
session_start();

// Include database configuration
require_once '../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required data is provided
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$feedback_id = (int)$_POST['id'];

try {
    // Delete the feedback
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
    $result = $stmt->execute([$feedback_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Feedback not found or already deleted']);
    }
} catch (PDOException $e) {
    error_log('Error deleting feedback: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
