<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Include database configuration
require_once('../../config.php');

try {
    // Get database connection
    $pdo = get_db_connection();
    
    // Check if documents table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'documents'");
    $documentsTableExists = $stmt->rowCount() > 0;
    
    // Check if document_items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'document_items'");
    $documentItemsTableExists = $stmt->rowCount() > 0;
    
    // Create tables if they don't exist
    if (!$documentsTableExists || !$documentItemsTableExists) {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            if (!$documentsTableExists) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    document_number VARCHAR(50) NOT NULL,
                    document_type ENUM('invoice', 'receipt', 'order', 'quote', 'credit_note', 'delivery_note') NOT NULL DEFAULT 'receipt',
                    document_date DATETIME NOT NULL,
                    customer_id INT DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    payment_method VARCHAR(50) DEFAULT NULL,
                    paid_status ENUM('paid', 'unpaid', 'partial', 'cancelled') DEFAULT 'unpaid',
                    total DECIMAL(10,2) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )");
            } else {
                // Check if payment_method column exists, if not add it
                $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'payment_method'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE documents ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER user_id");
                    error_log("Added payment_method column to documents table");
                }
            }
            
            if (!$documentItemsTableExists) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS document_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    document_id INT NOT NULL,
                    product_id INT DEFAULT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                    price_before_tax DECIMAL(10,2) DEFAULT 0,
                    tax DECIMAL(10,2) DEFAULT 0,
                    price DECIMAL(10,2) DEFAULT 0,
                    total DECIMAL(10,2) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
                )");
            }
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Tables created successfully',
                'tables_created' => [
                    'documents' => !$documentsTableExists,
                    'document_items' => !$documentItemsTableExists
                ]
            ]);
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'All required tables exist',
            'tables_exist' => true
        ]);
    }
} catch (Exception $e) {
    error_log("Error checking/creating tables: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error checking/creating tables',
        'error' => $e->getMessage()
    ]);
}
