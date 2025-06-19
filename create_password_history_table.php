<?php
/**
 * Script to create the user_password_history table
 */

// Include your database configuration
require_once __DIR__ . '/public/config.php';

// Database connection function
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

try {
    $db = getDbConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Create the table
    $sql = "
    CREATE TABLE IF NOT EXISTS `user_password_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `username` varchar(50) NOT NULL,
      `password_hash` varchar(255) NOT NULL,
      `changed_at` datetime NOT NULL,
      `changed_by` int(11) NOT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_changed_at` (`changed_at`),
      KEY `idx_user_changed_at` (`user_id`, `changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    // Remove existing foreign key if it exists
    $db->exec("ALTER TABLE `user_password_history` DROP FOREIGN KEY IF EXISTS `fk_user_password_history_user`");
    
    // Add foreign key constraint
    $db->exec("ALTER TABLE `user_password_history` 
              ADD CONSTRAINT `fk_user_password_history_user` 
              FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
    
    // Add index for better performance on lookups if it doesn't exist
    $indexExists = $db->query("SHOW INDEX FROM `user_password_history` WHERE Key_name = 'idx_user_changed_at'")->rowCount() > 0;
    if (!$indexExists) {
        $db->exec("CREATE INDEX `idx_user_changed_at` ON `user_password_history` (`user_id`, `changed_at`)");
    }
    
    // Commit transaction
    $db->commit();
    
    echo "✅ Successfully created/updated user_password_history table\n";
    echo "You can now safely delete this file: " . __FILE__ . "\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    
    // Show the SQL error info if available
    if (isset($db)) {
        echo "SQL Error Info: \n";
        print_r($db->errorInfo());
    }
}

echo "\nScript execution completed.\n";
