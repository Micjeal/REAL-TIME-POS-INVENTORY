<?php
// Fetch unpaid sales/documents for a selected customer
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Customer ID required']);
    exit();
}
try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, document_no, total_amount, paid_amount, (total_amount - paid_amount) AS balance FROM sales WHERE customer_id = ? AND (total_amount - paid_amount) > 0");
    $stmt->execute([$customer_id]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'documents' => $docs]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
