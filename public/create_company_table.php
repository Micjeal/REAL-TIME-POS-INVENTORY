<?php
// Create company table if it doesn't exist
require_once 'config.php';

try {
    $db = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS company (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        tax_number VARCHAR(100) DEFAULT NULL,
        street_name VARCHAR(255) DEFAULT NULL,
        building_number VARCHAR(50) DEFAULT NULL,
        additional_street_name VARCHAR(255) DEFAULT NULL,
        plot_identification VARCHAR(100) DEFAULT NULL,
        district VARCHAR(100) DEFAULT NULL,
        postal_code VARCHAR(20) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state_province VARCHAR(100) DEFAULT NULL,
        country VARCHAR(100) DEFAULT 'Uganda',
        phone_number VARCHAR(50) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        bank_account VARCHAR(255) DEFAULT NULL,
        bank_acc_number VARCHAR(100) DEFAULT NULL,
        bank_details TEXT DEFAULT NULL,
        logo_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "Company table created or already exists. <a href='management/company.php'>Go to Company Settings</a>";
    
} catch(PDOException $e) {
    die("Error creating company table: " . $e->getMessage());
}
?>
