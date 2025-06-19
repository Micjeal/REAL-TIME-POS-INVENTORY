<?php
// Start the session
session_start();

// Include database configuration
require_once '../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin/manager
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required data is provided
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$feedback_id = (int)$_POST['id'];
$status = in_array($_POST['status'], ['new', 'in_progress', 'resolved']) ? $_POST['status'] : 'new';

try {
    $db = get_db_connection();
    
    // Update the feedback status
    $stmt = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $feedback_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (PDOException $e) {
    error_log('Error updating feedback status: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
