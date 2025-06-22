<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set JSON content type
header('Content-Type: application/json');

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Check if search query is provided
if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit();
}

try {
    // Get database connection
    $pdo = get_db_connection();
    
    if (!$pdo) {
        throw new Exception('Failed to connect to database');
    }
    
    $searchTerm = '%' . trim($_GET['q']) . '%';
    $limit = 10; // Limit number of results
    
    $sql = "SELECT p.*, '' as category_name 
            FROM products p 
            WHERE p.name LIKE ? 
               OR p.code LIKE ? 
               OR p.barcode LIKE ? 
            ORDER BY p.name 
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log successful search (for debugging)
    error_log('Search successful for: ' . $_GET['q'] . ' - Found ' . count($results) . ' results');
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (PDOException $e) {
    // Log the full error for debugging
    error_log('Database error in search_products.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log the full error for debugging
    error_log('Error in search_products.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching',
        'error' => $e->getMessage()
    ]);
}
?>
