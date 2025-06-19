<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config.php';
require_once '../includes/activity_logger.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get POST data
$action_type = $_POST['action_type'] ?? null;
$entity_type = $_POST['entity_type'] ?? null;
$entity_id = isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
$details = $_POST['details'] ?? null;
$old_values = isset($_POST['old_values']) ? json_decode($_POST['old_values'], true) : null;
$new_values = isset($_POST['new_values']) ? json_decode($_POST['new_values'], true) : null;

// Validate required fields
if (empty($action_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action type is required']);
    exit();
}

try {
    // Log the activity
    $result = log_activity(
        $action_type,
        $entity_type,
        $entity_id,
        $old_values,
        $new_values,
        $details
    );
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged successfully'
        ]);
    } else {
        throw new Exception('Failed to log activity');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in log_activity.php: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while logging the activity',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}
