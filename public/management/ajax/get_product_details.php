<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Start the session
session_start();

// Include database configuration
require_once('../../config.php');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    // Ensure no output before this
    if (headers_sent()) {
        die(json_encode($response));
    }
    
    echo json_encode($response);
    exit;
}

try {
    // Get database connection
    $pdo = get_db_connection();
    
    // Get product ID or barcode from request
    $id = $_GET['id'] ?? null;
    $barcode = $_GET['barcode'] ?? null;
    
    if (!$id && !$barcode) {
        sendJsonResponse(false, 'Product ID or barcode is required');
    }
    
    // Prepare query
    $query = "SELECT p.*, c.name as category_name, t.rate as tax_rate 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN tax_rates t ON p.tax_rate_id = t.id 
              WHERE p.active = 1 ";
    
    $params = [];
    
    if ($id) {
        $query .= " AND p.id = ?";
        $params[] = $id;
    } else {
        $query .= " AND p.barcode = ?";
        $params[] = $barcode;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendJsonResponse(false, 'Product not found or inactive');
    }
    
    // Format response
    $response = [
        'id' => (int)$product['id'],
        'code' => $product['code'],
        'name' => $product['name'],
        'description' => $product['description'] ?? '',
        'category_id' => $product['category_id'] ? (int)$product['category_id'] : null,
        'category_name' => $product['category_name'] ?? null,
        'unit_of_measure' => $product['unit_of_measure'] ?? 'pcs',
        'price' => (float)$product['price'],
        'cost' => (float)$product['cost'],
        'tax_rate_id' => $product['tax_rate_id'] ? (int)$product['tax_rate_id'] : null,
        'tax_rate' => $product['tax_rate'] ? (float)$product['tax_rate'] : 0,
        'tax_included' => (bool)$product['tax_included'],
        'stock_quantity' => (float)$product['stock_quantity'],
        'barcode' => $product['barcode'] ?? '',
        'image_path' => $product['image_path'] ?? null
    ];
    
    sendJsonResponse(true, 'Product details retrieved', $response);
    
} catch (Exception $e) {
    error_log('Error getting product details: ' . $e->getMessage());
    sendJsonResponse(false, 'Error retrieving product details: ' . $e->getMessage());
}