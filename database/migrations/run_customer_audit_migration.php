<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../../config.php';

// Only allow admins to run migrations
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin'])) {
    die('Unauthorized access');
}

try {
    // Read the SQL file
    $migrationFile = __DIR__ . '/20240617_add_customer_audit_tables.sql';
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception('Failed to read migration file');
    }
    
    // Split the SQL into individual queries
    $queries = explode(';', $sql);
    
    // Execute each query
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) {
            continue;
        }
        
        try {
            $pdo->exec($query);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = [
                'query' => $query,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Output results
    echo "<h2>Migration Results</h2>";
    echo "<p>Successfully executed: $successCount queries</p>";
    echo "<p>Failed: $errorCount queries</p>";
    
    if (!empty($errors)) {
        echo "<h3>Errors:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li><strong>Query:</strong> " . htmlspecialchars(substr($error['query'], 0, 200)) . "...<br>";
            echo "<strong>Error:</strong> " . htmlspecialchars($error['error']) . "</li><br>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}

echo "<p>Migration completed. <a href='/management/customers.php'>Return to Customers</a></p>";
