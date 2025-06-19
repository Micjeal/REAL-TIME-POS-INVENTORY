<?php
require_once '../../config.php';

try {
    $db = get_db_connection();

    // Add missing columns to users table
    $db->exec("ALTER TABLE users 
               ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) AFTER username,
               ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER full_name,
               ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'cashier' AFTER email,
               ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER role,
               ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

    // Update existing admin user with full name if it exists
    $db->exec("UPDATE users SET full_name = 'Administrator' WHERE username = 'admin'");
    
    echo json_encode(['success' => true, 'message' => 'User table updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
