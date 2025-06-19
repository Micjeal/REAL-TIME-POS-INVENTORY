<?php
// Process a credit payment for a customer
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}
$data = json_decode(file_get_contents('php://input'), true);
$customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
$amount = isset($data['amount']) ? (float)$data['amount'] : 0;
$payment_type = isset($data['payment_type']) ? $data['payment_type'] : '';
$auto_dist = isset($data['auto_dist']) ? (bool)$data['auto_dist'] : true;
if (!$customer_id || $amount <= 0 || !$payment_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}
try {
    $db = get_db_connection();
    $db->beginTransaction();
    // Insert payment record
    $stmt = $db->prepare("INSERT INTO payments (customer_id, amount, payment_type, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$customer_id, $amount, $payment_type, $_SESSION['user_id']]);
    $payment_id = $db->lastInsertId();
    if ($auto_dist) {
        // Distribute payment across unpaid docs
        $stmt = $db->prepare("SELECT id, (total_amount - paid_amount) AS balance FROM sales WHERE customer_id = ? AND (total_amount - paid_amount) > 0 ORDER BY id");
        $stmt->execute([$customer_id]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $remaining = $amount;
        foreach ($docs as $doc) {
            if ($remaining <= 0) break;
            $apply = min($doc['balance'], $remaining);
            $db->prepare("UPDATE sales SET paid_amount = paid_amount + ? WHERE id = ?")->execute([$apply, $doc['id']]);
            $db->prepare("INSERT INTO payment_allocations (payment_id, sale_id, amount) VALUES (?, ?, ?)")->execute([$payment_id, $doc['id'], $apply]);
            $remaining -= $apply;
        }
    }
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
