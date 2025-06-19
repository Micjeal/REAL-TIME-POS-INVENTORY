<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['username'])) {
    echo json_encode(['exists' => false]);
    exit();
}

$username = trim($_GET['username']);
$userId = $_GET['user_id'] ?? 0;

try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    $params = [$username];
    
    if ($userId) {
        $sql .= " AND id != ?";
        $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['exists' => $result['count'] > 0]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
