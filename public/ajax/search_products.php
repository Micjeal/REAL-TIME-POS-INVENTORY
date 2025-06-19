<?php
require_once '../config.php';
header('Content-Type: application/json');

// Check if search query is provided
if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit();
}

try {
    $searchTerm = '%' . trim($_GET['q']) . '%';
    $limit = 10; // Limit number of results
    
    $sql = "SELECT p.*, pc.name as category_name 
            FROM products p 
            LEFT JOIN product_categories pc ON p.category_id = pc.id 
            WHERE p.name LIKE :search 
               OR p.code LIKE :search 
               OR p.barcode LIKE :search 
            ORDER BY p.name 
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (PDOException $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching'
    ]);
}
?>
