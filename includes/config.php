<?php
/**
 * MTECH UGANDA - Configuration File
 * Database connection settings and application configuration
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - Must be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters before starting the session
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    
    // Start the session
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Default XAMPP username
define('DB_PASS', '');         // Default XAMPP password is empty
define('DB_NAME', 'mtech_uganda');

// Application configuration
define('SITE_NAME', 'MTECH UGANDA');
define('SITE_URL', 'http://localhost/MTECH%20UGANDA');

// Timezone
date_default_timezone_set('Africa/Kampala');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
