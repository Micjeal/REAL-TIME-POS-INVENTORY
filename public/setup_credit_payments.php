<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Unauthorized access');
}

try {
    $db = get_db_connection();
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/../database/create_credit_payments_table.sql');
    
    if ($sql === false) {
        throw new Exception('Could not read SQL file');
    }
    
    // Split the SQL into individual statements
    $queries = explode(';', $sql);
    
    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $db->exec($query);
        }
    }
    
    echo "Database tables created/updated successfully!";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
