<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../../config.php';
require_once '../../includes/activity_logger.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if user has management access
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

try {
    // Get limit from query parameter or use default
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Get filter parameters
    $user_id = $_GET['user_id'] ?? null;
    $action_type = $_GET['action_type'] ?? null;
    $entity_type = $_GET['entity_type'] ?? null;
    $entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
    
    // Get recent activities
    $activities = get_recent_activities($limit, $offset, $user_id, $action_type, $entity_type, $entity_id);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => $activities,
        'total' => count($activities)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_recent_activities.php: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching activities',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}
