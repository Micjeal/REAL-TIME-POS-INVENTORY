<?php
/**
 * Database Setup Script for MTECH UGANDA
 * 
 * This script creates the necessary database tables if they don't exist.
 * It should be run once during initial setup.
 */

// Load configuration
require_once __DIR__ . '/public/config.php';

// Database connection function
function getDbConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Function to execute SQL from file
function executeSqlFile($filePath) {
    $db = getDbConnection();
    
    // Read the SQL file
    $sql = file_get_contents($filePath);
    
    if ($sql === false) {
        die("Error reading SQL file: $filePath");
    }
    
    // Split the SQL into individual queries
    $queries = explode(';', $sql);
    
    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $db->exec($query);
                echo "Executed: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '') . "<br>\n";
            } catch (PDOException $e) {
                echo "Error executing query: " . $e->getMessage() . "<br>\n";
                echo "Query: " . substr($query, 0, 200) . (strlen($query) > 200 ? '...' : '') . "<br><br>\n";
            }
        }
    }
}

// Create the user_password_history table if it doesn't exist
function createPasswordHistoryTable() {
    $db = getDbConnection();
    
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
      KEY `idx_changed_at` (`changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Add foreign key constraint if the users table exists
    ALTER TABLE `user_password_history`
    ADD CONSTRAINT `fk_user_password_history_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
    
    -- Add index for better performance on lookups
    CREATE INDEX IF NOT EXISTS `idx_user_changed_at` ON `user_password_history` (`user_id`, `changed_at`);
    ";
    
    try {
        // Split the SQL into individual queries
        $queries = explode(';', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $db->exec($query);
            }
        }
        
        echo "✓ Created/Updated user_password_history table<br>\n";
    } catch (PDOException $e) {
        echo "Error creating user_password_history table: " . $e->getMessage() . "<br>\n";
    }
}

// Check if users table exists
function checkUsersTable() {
    $db = getDbConnection();
    
    try {
        $result = $db->query("SHOW TABLES LIKE 'users'");
        if ($result->rowCount() === 0) {
            die("Error: The 'users' table does not exist. Please create it first.");
        }
        return true;
    } catch (PDOException $e) {
        die("Error checking users table: " . $e->getMessage());
    }
}

// Main execution
header('Content-Type: text/html; charset=utf-8');
echo "<h2>MTECH UGANDA - Database Setup</h2>";
echo "<p>Setting up database tables...</p>";
echo "<pre>";

// Check if users table exists first
checkUsersTable();

// Create the password history table
createPasswordHistoryTable();

// Execute any SQL files in the database directory
$databaseDir = __DIR__ . '/database';
if (is_dir($databaseDir)) {
    $sqlFiles = glob($databaseDir . '/*.sql');
    
    foreach ($sqlFiles as $sqlFile) {
        echo "\nProcessing file: " . basename($sqlFile) . "\n";
        echo str_repeat("-", 80) . "\n";
        executeSqlFile($sqlFile);
        echo "\n" . str_repeat("-", 80) . "\n";
    }
}

echo "\nDatabase setup completed. <a href='public/'>Go to application</a>";
echo "</pre>";
