<?php
require_once '../../config.php';

try {
    $db = get_db_connection();
    
    // Add full_name column to users table if it doesn't exist
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) AFTER username");
    
    // Update existing admin user with full name if it exists
    $db->exec("UPDATE users SET full_name = 'Administrator' WHERE username = 'admin' AND (full_name IS NULL OR full_name = '')");
    
    echo json_encode(['success' => true, 'message' => 'Users table updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
