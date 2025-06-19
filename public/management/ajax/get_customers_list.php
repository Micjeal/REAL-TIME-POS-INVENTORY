<?php
// Fetch all customers for dropdown
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}
try {
    $db = get_db_connection();
    $stmt = $db->query("SELECT id, name FROM customers_suppliers WHERE type = 'customer' ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'customers' => $customers]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
