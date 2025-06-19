<?php
// Start the session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Get customer ID from request
$customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);

if (!$customer_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid customer ID']));
}

try {
    $db = get_db_connection();
    
    // Get open invoices for the customer
    $stmt = $db->prepare("SELECT id, invoice_number, total, balance 
                         FROM sales 
                         WHERE customer_id = ? AND balance > 0 
                         ORDER BY date DESC");
    $stmt->execute([$customer_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return as JSON
    header('Content-Type: application/json');
    echo json_encode($invoices);
    
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>
