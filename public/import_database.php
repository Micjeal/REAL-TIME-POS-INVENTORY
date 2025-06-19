<?php
// Database configuration
$host = 'localhost';
$dbname = 'mtech_uganda';
$username = 'root';
$password = '';

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/../database_setup.sql');
    
    if ($sql === false) {
        throw new Exception('Could not read SQL file');
    }
    
    // Split the SQL into individual statements
    $queries = explode(';', $sql);
    
    // Execute each query
    $pdo->exec("DROP DATABASE IF EXISTS $dbname");
    $pdo->exec("CREATE DATABASE $dbname");
    $pdo->exec("USE $dbname");
    
    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    // Now add the credit payments table
    $creditPaymentsSql = file_get_contents(__DIR__ . '/../database/create_credit_payments_table.sql');
    if ($creditPaymentsSql !== false) {
        $creditQueries = explode(';', $creditPaymentsSql);
        foreach ($creditQueries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }
    }
    
    echo "Database imported successfully!<br>";
    echo "<a href='welcome.php'>Go to Application</a>";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
