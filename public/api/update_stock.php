<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set error log path
$logFile = __DIR__ . '/../../logs/pos_errors.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}
ini_set('error_log', $logFile);

// Set headers for JSON response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    // Log the response for debugging
    error_log("Sending JSON response: " . json_encode($response));
    
    // Ensure no output before this
    if (headers_sent()) {
        die(json_encode($response));
    }
    
    echo json_encode($response);
    exit;
}

// Log the request for debugging
function logRequest($data) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'data' => $data
    ];
    error_log("API Request: " . json_encode($logData));
}

try {
    // Log the request
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?: $_POST;
    logRequest($inputData);
    
    // Include database configuration
    require_once __DIR__ . '/../../config.php';
    
    // Use the already parsed input data
    $data = $inputData;
    
    // Get and validate input
    $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
    $stock = isset($data['stock']) ? intval($data['stock']) : 0;
    
    if ($product_id <= 0) {
        sendJsonResponse(false, 'Invalid product ID');
    }

    // Update the product stock in the database
    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
    $result = $stmt->execute([$stock, $product_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        sendJsonResponse(true, 'Stock updated successfully', ['product_id' => $product_id, 'new_stock' => $stock]);
    } else {
        // Check if the product exists
        $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $checkStmt->execute([$product_id]);
        
        if ($checkStmt->rowCount() === 0) {
            sendJsonResponse(false, 'Product not found');
        } else {
            // Product exists but no rows were updated (same stock value)
            sendJsonResponse(true, 'Stock already up to date', ['product_id' => $product_id, 'new_stock' => $stock]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error in update_stock.php: " . $e->getMessage());
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Error in update_stock.php: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage());
}
