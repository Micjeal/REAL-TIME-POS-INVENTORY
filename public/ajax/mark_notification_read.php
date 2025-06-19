<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if notification ID is provided
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
    exit();
}

$notificationId = (int)$_POST['id'];
$userId = $_SESSION['user_id'];

try {
    require_once '../config.php';
    require_once '../includes/NotificationHelper.php';
    
    $db = get_db_connection();
    $notificationHelper = new NotificationHelper($db);
    
    // Mark notification as read
    $success = $notificationHelper->markAsRead($notificationId, $userId);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log('Error marking notification as read: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
