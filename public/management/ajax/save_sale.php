<?php
// Enable output buffering to prevent unwanted output
ob_start();

// Enable error reporting for debugging (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

// Set a valid error log path
$logFile = __DIR__ . '/../../../logs/pos_errors.log';
// Create logs directory if it doesn't exist
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}
ini_set('error_log', $logFile);

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
    
    // Clear output buffer
    ob_end_clean();
    
    // Ensure no output before this
    if (headers_sent()) {
        die(json_encode($response));
    }
    
    echo json_encode($response);
    exit;
}

// Get database connection
try {
    $pdo = get_db_connection();
    $pdo->query('SELECT 1');
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    $errorMessage = 'Database connection failed';
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        $errorMessage = 'Database access denied. Please check your credentials.';
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        $errorMessage = 'Database not found. Please check your database configuration.';
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        $errorMessage = 'Unable to connect to the database server. Please check if the database server is running.';
    }
    
    http_response_code(500);
    sendJsonResponse(false, $errorMessage, ['error' => $e->getMessage()]);
}

// Check if the request is POST and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(405);
    sendJsonResponse(false, 'Invalid request method or not an AJAX request');
}

// Get the raw POST data
$json = file_get_contents('php://input');
$input = json_decode($json, true);

// Log received data for debugging
error_log("Received data: " . print_r($input, true));

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    sendJsonResponse(false, 'Invalid JSON data: ' . json_last_error_msg());
}

// Validate required fields
$requiredFields = ['user_id', 'items', 'payment_type', 'total_amount'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        sendJsonResponse(false, "Missing required field: $field");
    }
}

// Set default customer_id if not provided
if (!isset($input['customer_id'])) {
    $input['customer_id'] = null; // or set to a default customer ID if you have one
}

// Validate items
if (!is_array($input['items']) || empty($input['items'])) {
    sendJsonResponse(false, 'No items in the sale');
}

// Validate payment method
$valid_payment_methods = ['cash', 'card', 'mobile_money', 'bank_transfer'];
if (!in_array($input['payment_type'], $valid_payment_methods)) {
    sendJsonResponse(false, 'Invalid payment method');
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate invoice number (format: INV-YYYYMMDD-XXXXXX)
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Insert into sales table
    $saleData = [
        'customer_id' => !empty($input['customer_id']) ? $input['customer_id'] : null,
        'user_id' => $input['user_id'],
        'invoice_number' => $invoiceNumber,
        'date' => date('Y-m-d H:i:s'),
        'total_amount' => $input['total_amount'],
        'payment_type' => $input['payment_type'],
        'payment_status' => $input['payment_type'] === 'cash' ? 'paid' : 'pending',
        'notes' => $input['notes'] ?? null
    ];
    
    // Insert sale record
    $saleColumns = implode(', ', array_keys($saleData));
    $salePlaceholders = ':' . implode(', :', array_keys($saleData));
    
    $stmt = $pdo->prepare("INSERT INTO sales ($saleColumns) VALUES ($salePlaceholders)");
    $stmt->execute($saleData);
    
    $saleId = $pdo->lastInsertId();
    
    // Process items
    foreach ($input['items'] as $item) {
        // Validate required item data
        if (empty($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
            throw new Exception('Invalid item data: missing required fields');
        }
        
        // Get product details including name
        $productStmt = $pdo->prepare('SELECT id, stock_quantity, name FROM products WHERE id = ?');
        $productStmt->execute([$item['product_id']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception("Product not found: " . $item['product_id']);
        }
        
        // Use provided name or fetch from database if not provided
        $productName = $item['name'] ?? $product['name'];
        
        // Check stock
        if (($product['stock_quantity'] - $item['quantity']) < 0) {
            throw new Exception("Insufficient stock for product: " . $product['name']);
        }
        
        // Insert sales item
        $itemData = [
            'sale_id' => $saleId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax_amount' => $item['tax_amount'] ?? 0,
            'discount_amount' => $item['discount_amount'] ?? 0,
            'subtotal' => $item['quantity'] * $item['unit_price']
        ];
        
        $itemColumns = implode(', ', array_keys($itemData));
        $itemPlaceholders = ':' . implode(', :', array_keys($itemData));
        
        $itemStmt = $pdo->prepare("INSERT INTO sale_items ($itemColumns) VALUES ($itemPlaceholders)");
        $itemStmt->execute($itemData);
        
        // Update product stock
        $updateStmt = $pdo->prepare('UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?');
        $updateStmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success with sale ID and invoice number
    sendJsonResponse(true, 'Sale processed successfully', [
        'sale_id' => $saleId,
        'invoice_number' => $invoiceNumber
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error processing sale: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Provide specific error messages
    $errorMessage = 'Error processing sale';
    if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            $errorMessage = 'Database error: Referential integrity constraint failed. A related record is missing.';
        } elseif (strpos($e->getMessage(), 'duplicate entry') !== false) {
            $errorMessage = 'Database error: Duplicate entry detected. This record already exists.';
        } else {
            $errorMessage = 'Database error: ' . $e->getMessage();
        }
    } else {
        $errorMessage = $e->getMessage();
    }
    
    http_response_code(500);
    sendJsonResponse(false, $errorMessage, ['error' => $e->getMessage()]);
}