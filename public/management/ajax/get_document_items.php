<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix potential path issues
if (file_exists('../../../config.php')) {
    require_once '../../../config.php';
} else {
    require_once '../../config.php';
}

// Check if document ID is provided
if (!isset($_GET['document_id']) || empty($_GET['document_id'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit();
}

$document_id = intval($_GET['document_id']);

try {
    $db = get_db_connection();
    
    // Get document items
    $query = "SELECT di.id, p.code, p.name, di.unit_of_measure, di.quantity, 
              di.price_before_tax, di.tax_amount as tax, di.price, 
              (di.quantity * di.price) as total_before_discount, 
              di.discount, di.total
              FROM document_items di
              LEFT JOIN products p ON di.product_id = p.id
              WHERE di.document_id = :document_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
