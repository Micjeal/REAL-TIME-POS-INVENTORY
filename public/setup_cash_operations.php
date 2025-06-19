<?php
// Start the session
session_start();

// Include database configuration
require_once 'config.php';

// Create cash_operations table if it doesn't exist
try {
    $db = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS cash_operations (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        operation_type ENUM('in', 'out') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    
    echo "<div style='background-color: #2a2e43; color: #f0f0f0; font-family: Arial, sans-serif; padding: 20px; border-radius: 5px;'>";
    echo "<h3>Cash Operations Table Setup</h3>";
    echo "<p>The cash_operations table has been created successfully.</p>";
    echo "<p>You can now <a href='cash_operations.php' style='color: #3584e4;'>return to the Cash In/Out page</a>.</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background-color: #2a2e43; color: #f0f0f0; font-family: Arial, sans-serif; padding: 20px; border-radius: 5px;'>";
    echo "<h3>Error Setting Up Cash Operations Table</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
