<?php
/**
 * MTECH UGANDA - Configuration File
 * Database connection settings and application configuration
 */

// Start output buffering to prevent headers already sent issues
if (!headers_sent() && !ob_get_level()) {
    ob_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - Must be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters before starting the session
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Set session name to avoid conflicts
    session_name('mtech_uganda_session');
    
    // Start the session
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Default XAMPP username
define('DB_PASS', '');         // Default XAMPP password is empty
define('DB_NAME', 'mtech-uganda');

// Application configuration
define('SITE_NAME', 'MTECH UGANDA');
define('SITE_URL', 'http://localhost/MTECH%20UGANDA');

// Email configuration
define('MAIL_HOST', 'smtp.example.com'); // Replace with your SMTP server
define('MAIL_PORT', 587); // Common ports: 587 (TLS) or 465 (SSL)
define('MAIL_USERNAME', 'noreply@mtechuganda.com'); // Your email address
define('MAIL_PASSWORD', 'your-email-password'); // Your email password
define('MAIL_FROM_EMAIL', 'noreply@mtechuganda.com'); // Sender email
// Email encryption - Options: 'tls', 'ssl' or '' (empty string for no encryption)
define('MAIL_ENCRYPTION', 'tls');
// Email debug mode (0 = off, 1 = client messages, 2 = client and server messages)
define('MAIL_DEBUG', 0);
// Admin email for notifications
define('ADMIN_EMAIL', 'admin@mtechuganda.com');

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
