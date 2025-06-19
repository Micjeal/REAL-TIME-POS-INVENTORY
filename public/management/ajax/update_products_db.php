<?php
require_once('../../config.php');

try {
    $pdo = get_db_connection();
    
    // Check if products table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        // Create products table with all necessary fields
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE,
            barcode VARCHAR(50),
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INT,
            unit_of_measure VARCHAR(20) DEFAULT 'pcs',
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            tax_rate_id INT,
            tax_included TINYINT(1) DEFAULT 1,
            cost DECIMAL(10,2) DEFAULT 0,
            stock_quantity DECIMAL(10,2) DEFAULT 0,
            min_stock DECIMAL(10,2) DEFAULT 0,
            image_path VARCHAR(255),
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL
        )");

        // Create categories table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            parent_id INT DEFAULT NULL,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        )");

        // Create tax_rates table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS tax_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            rate DECIMAL(5,2) NOT NULL,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert default category
        $pdo->exec("INSERT INTO categories (name, description) VALUES 
            ('General', 'Default category for products')");

        // Insert default tax rates
        $pdo->exec("INSERT INTO tax_rates (name, rate) VALUES 
            ('No Tax', 0),
            ('Standard VAT', 18),
            ('Reduced VAT', 10)");

        echo json_encode(['success' => true, 'message' => 'Database tables created successfully']);
    } else {
        // Check and add any missing columns
        $required_columns = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'code' => 'VARCHAR(50) UNIQUE',
            'barcode' => 'VARCHAR(50)',
            'name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'category_id' => 'INT',
            'unit_of_measure' => 'VARCHAR(20)',
            'price' => 'DECIMAL(10,2)',
            'tax_rate_id' => 'INT',
            'tax_included' => 'TINYINT(1)',
            'cost' => 'DECIMAL(10,2)',
            'stock_quantity' => 'DECIMAL(10,2)',
            'min_stock' => 'DECIMAL(10,2)',
            'image_path' => 'VARCHAR(255)',
            'active' => 'TINYINT(1)',
            'created_at' => 'TIMESTAMP',
            'updated_at' => 'TIMESTAMP'
        ];

        // Get existing columns
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add missing columns
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                $pdo->exec("ALTER TABLE products ADD COLUMN {$column} {$definition}");
            }
        }

        echo json_encode(['success' => true, 'message' => 'Database structure verified and updated']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>
