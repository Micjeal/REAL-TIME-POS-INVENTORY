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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit();
}

$id = (int)$_GET['id'];

try {
    $db = get_db_connection();
    
    // Get customer/supplier data
    $query = "SELECT * FROM customers_suppliers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
