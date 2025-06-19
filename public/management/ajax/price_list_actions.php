<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$action = $_POST['action'] ?? '';
$response = ['success' => false];

switch ($action) {
    case 'get_price_lists':
        $stmt = $pdo->query("SELECT * FROM price_lists WHERE active = 1 ORDER BY name");
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['success'] = true;
        break;            case 'get_products':
        try {
            $price_list_id = $_POST['price_list_id'] ?? 0;
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT p.*, p.price as default_price, pl.price as list_price, t.rate as tax_rate,
                    COALESCE(pl.price, p.price) as effective_price,
                    CASE 
                        WHEN pl.price IS NOT NULL AND p.price > 0 THEN ROUND(((pl.price / p.price) - 1) * 100, 2)
                        ELSE 0 
                    END as markup
                    FROM products p 
                    LEFT JOIN price_list_items pl ON p.id = pl.product_id AND pl.price_list_id = ?
                    LEFT JOIN tax_rates t ON p.tax_rate_id = t.id 
                    WHERE p.active = 1 ";
        
        if ($search) {
            $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
        }
        
        $sql .= " ORDER BY p.code";
        
        $stmt = $pdo->prepare($sql);
        
        if ($search) {
            $search = "%$search%";
            $stmt->execute([$price_list_id, $search, $search]);
        } else {
            $stmt->execute([$price_list_id]);
        }
        
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['success'] = true;
        break;    case 'save_price_list':
        $name = $_POST['name'] ?? '';
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO price_lists (name, active) VALUES (?, 1)");
            $stmt->execute([$name]);
            $response['id'] = $pdo->lastInsertId();
            $response['success'] = true;
        }
        break;
        
    case 'delete_price_list':
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE price_lists SET active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
        }
        break;case 'update_prices':
        $price_list_id = $_POST['price_list_id'] ?? 0;
        $items = $_POST['items'] ?? [];
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO price_list_items (price_list_id, product_id, price) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE price = VALUES(price)");
            
            foreach ($items as $item) {
                if (isset($item['price']) && $item['price'] > 0) {
                    $stmt->execute([
                        $price_list_id,
                        $item['product_id'],
                        $item['price']
                    ]);
                }
            }
            
            $pdo->commit();
            $response['success'] = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['error'] = $e->getMessage();
        }
        break;
}

header('Content-Type: application/json');
echo json_encode($response);
