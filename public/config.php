<?php
/**
 * MTECH UGANDA Configuration File
 * Created on: 2025-05-26
 * Description: Database and system configuration for MTECH UGANDA.
 * Important: Keep this file secure and DO NOT expose it publicly.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mtech-uganda');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Character Set and Collation
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// Optional: Define table prefix if needed
define('DB_PREFIX', '');



// Secure session settings
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    // Start the session
    session_start();
    
    // Regenerate session ID to prevent session fixation
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Regenerate session ID periodically (every 30 minutes)
    if (isset($_SESSION['last_regeneration']) && (time() - $_SESSION['last_regeneration'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}


// Database Connection Function with Improved Error Handling
function get_db_connection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Log the error
        error_log('Database connection error: ' . $e->getMessage());
        
        // Check specific error conditions
        if ($e->getCode() == 1049) {
            // Database doesn't exist
            die('Database does not exist. Please run the database setup script.');
        } else if ($e->getCode() == 2002) {
            // Server not running
            die('Cannot connect to MySQL server. Make sure XAMPP MySQL service is running.');
        } else if ($e->getCode() == 1045) {
            // Access denied (incorrect username/password)
            die('Access denied. Check your database username and password in config.php.');
        } else {
            // Other errors
            die('Database connection error: ' . $e->getMessage());
        }
    }
}

// Test Database Connection Function
function test_db_connection() {
    try {
        // First try connecting to just the server
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check if database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        $db_exists = $stmt->fetch();
        
        if (!$db_exists) {
            return ['status' => 'error', 'message' => 'Database "' . DB_NAME . '" does not exist. Please run the database setup script.'];
        }
        
        // Try connecting to the database
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $table_exists = $stmt->fetch();
        
        if (!$table_exists) {
            return ['status' => 'error', 'message' => 'Database tables not found. Please run the database setup script.'];
        }
        
        return ['status' => 'success', 'message' => 'Database connection successful'];
    } catch (PDOException $e) {
        if ($e->getCode() == 2002) {
            return ['status' => 'error', 'message' => 'Cannot connect to MySQL server. Make sure XAMPP MySQL service is running.'];
        } else if ($e->getCode() == 1045) {
            return ['status' => 'error', 'message' => 'Access denied. Check your database username and password in config.php.'];
        } else {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Additional Global Configurations
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'MTECH UGANDA');
}
define('SITE_URL', 'https://www.mtechuganda.com');
define('ADMIN_EMAIL', 'admin@mtechuganda.com');
define('VERSION', '1.0');

// Timezone
date_default_timezone_set('Africa/Kampala');

?>
