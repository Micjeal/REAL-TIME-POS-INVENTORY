<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Check if product_groups table exists
    $stmt = $db->query("SHOW TABLES LIKE 'product_groups'");
    $groups_table_exists = $stmt->rowCount() > 0;
    
    if (!$groups_table_exists) {
        // Create product_groups table
        $db->exec("CREATE TABLE product_groups (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            parent_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (parent_id),
            FOREIGN KEY (parent_id) REFERENCES product_groups(id) ON DELETE SET NULL
        )");
        
        // Add default group
        $db->exec("INSERT INTO product_groups (name) VALUES ('Default')");
    }
    
    // Check if products table exists
    $stmt = $db->query("SHOW TABLES LIKE 'products'");
    $products_table_exists = $stmt->rowCount() > 0;
    
    if (!$products_table_exists) {
        // Create products table
        $db->exec("CREATE TABLE products (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL,
            barcode VARCHAR(50),
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            stock_quantity INT(11) NOT NULL DEFAULT 0,
            group_id INT(11),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (group_id),
            FOREIGN KEY (group_id) REFERENCES product_groups(id) ON DELETE SET NULL
        )");
    }
    
    echo json_encode(['success' => true, 'message' => 'Database structure created successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
