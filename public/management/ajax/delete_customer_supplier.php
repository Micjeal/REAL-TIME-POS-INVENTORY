<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit();
}

$id = (int)$_POST['id'];

try {
    $db = get_db_connection();
    
    // Check if the customer/supplier exists
    $query = "SELECT id FROM customers_suppliers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }
    
    // TODO: Check if customer/supplier is used in any transactions before deleting
    // This would depend on your database structure and business logic
    
    // Delete the customer/supplier
    $query = "DELETE FROM customers_suppliers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Customer/supplier deleted successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
