<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Unauthorized access. Please log in as an administrator.');
}

// Include database configuration
require_once __DIR__ . '/config.php';

// Check if migration has already been run
$migrationFile = __DIR__ . '/database/migrations/20240617_add_customer_audit_tables.sql';
$migrationLog = __DIR__ . '/database/migrations/migration_20240617.log';

if (file_exists($migrationLog)) {
    $migrationStatus = file_get_contents($migrationLog);
    echo "<h2>Migration Status</h2>";
    echo "<pre>$migrationStatus</pre>";
    echo "<p>Migration has already been run. <a href='/management/customers.php'>Go to Customers</a></p>";
    exit;
}

// Run the migration
ob_start();

try {
    $db = get_db_connection();
    
    // Read the SQL file
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception('Failed to read migration file');
    }
    
    // Split the SQL into individual queries
    $queries = explode(';', $sql);
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) {
            continue;
        }
        
        try {
            $db->exec($query);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = [
                'query' => $query,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Prepare migration result
    $result = "Migration Results\n";
    $result .= "================\n";
    $result .= "Successfully executed: $successCount queries\n";
    $result .= "Failed: $errorCount queries\n\n";
    
    if (!empty($errors)) {
        $result .= "Errors:\n";
        $result .= "-------\n";
        foreach ($errors as $error) {
            $result .= "Query: " . substr($error['query'], 0, 200) . "...\n";
            $result .= "Error: " . $error['error'] . "\n\n";
        }
    }
    
    // Save migration result to log file
    file_put_contents($migrationLog, $result);
    
    // Output the result
    echo "<h2>Migration Results</h2>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
    
    if ($errorCount === 0) {
        echo "<div class='alert alert-success'>Migration completed successfully! <a href='/management/customers.php'>Go to Customers</a></div>";
    } else {
        echo "<div class='alert alert-warning'>Migration completed with $errorCount error(s). <a href='/management/customers.php'>Go to Customers</a></div>";
    }
    
} catch (Exception $e) {
    $error = "Migration failed: " . $e->getMessage();
    file_put_contents($migrationLog, $error);
    echo "<div class='alert alert-danger'>$error</div>";
}

// Add some basic styling
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>";
?>
