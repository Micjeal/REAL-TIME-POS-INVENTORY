<?php
// Include database configuration (session is already started in config.php)
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit();
}

$id = (int)$_POST['id'];

try {
    $db = get_db_connection();
    
    // Set user tracking variables for triggers
    $db->exec("SET @current_user_id = " . (int)$_SESSION['user_id'] . ", 
              @current_ip_address = '" . addslashes($_SERVER['REMOTE_ADDR']) . "', 
              @current_user_agent = '" . addslashes($_SERVER['HTTP_USER_AGENT'] ?? '') . "'");
    
    // First check if the customer exists
    $checkStmt = $db->prepare("SELECT id FROM customers WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Check if customer has any related records (e.g., invoices, sales)
    // This is a safety check to prevent deleting customers with related records
    // You may need to adjust this based on your database schema
    $hasRelatedRecords = false;
    
    // Example check for related sales (adjust table/column names as needed)
    // $salesCheck = $db->prepare("SELECT id FROM sales WHERE customer_id = ? LIMIT 1");
    // $salesCheck->execute([$id]);
    // $hasRelatedRecords = $salesCheck->rowCount() > 0;
    
    if ($hasRelatedRecords) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete customer with related records. Please delete related records first.'
        ]);
        exit();
    }
    
    // Delete the customer
    $deleteStmt = $db->prepare("DELETE FROM customers WHERE id = ?");
    $deleteStmt->execute([$id]);
    
    if ($deleteStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Customer deleted successfully',
            'id' => $id
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete customer or customer already deleted'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Database error in delete_customer.php: ' . $e->getMessage());
    
    // Check for foreign key constraint violation
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete customer because it is referenced by other records.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
