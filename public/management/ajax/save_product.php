<?php
require_once('../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = $_POST['name'];
        $code = $_POST['code'];
        $group = $_POST['group'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];

        // If an ID is provided, update existing product
        if (isset($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE products SET 
                name = ?, 
                code = ?, 
                group_id = ?, 
                price = ?, 
                stock = ? 
                WHERE id = ?");
            $stmt->execute([$name, $code, $group, $price, $stock, $_POST['id']]);
        } else {
            // Insert new product
            $stmt = $pdo->prepare("INSERT INTO products (name, code, group_id, price, stock, active) 
                VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$name, $code, $group, $price, $stock]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
