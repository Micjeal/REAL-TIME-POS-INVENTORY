<?php
// Include the configuration file
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if contact ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Contact ID is required']);
    exit();
}

$contact_id = intval($_GET['id']);

try {
    $db = get_db_connection();
    
    // Get contact data
    $stmt = $db->prepare("SELECT * FROM customers_suppliers WHERE id = :id");
    $stmt->bindValue(':id', $contact_id);
    $stmt->execute();
    
    $contact = $stmt->fetch();
    
    if (!$contact) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Contact not found']);
        exit();
    }
    
    // Return contact data as JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $contact]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
