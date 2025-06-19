<?php
/**
 * MTECH UGANDA Database Connection Test
 * Created on: 2025-05-26
 * Description: Tests database connection and verifies required tables.
 */

// Include database configuration
require_once 'config.php';

// Header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .card { margin-bottom: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4">MTECH UGANDA Database Connection Test</h1>
        
        <div class="card">
            <div class="card-header">
                <h5>Database Configuration</h5>
            </div>
            <div class="card-body">
                <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
                <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                <p><strong>Username:</strong> <?php echo DB_USER; ?></p>
                <p><strong>Password:</strong> <?php echo DB_PASSWORD ? '****' : '<span class="text-muted">empty</span>'; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>MySQL Server Connection Test</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Try connecting to the server (without specifying database)
                    $serverDsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
                    $serverConn = new PDO($serverDsn, DB_USER, DB_PASSWORD);
                    echo "<p class='success'><strong>✓ Success:</strong> Connected to MySQL server successfully.</p>";
                    
                    // Check MySQL version
                    $version = $serverConn->query('SELECT VERSION() as version')->fetch();
                    echo "<p><strong>MySQL Version:</strong> {$version['version']}</p>";
                    
                    // Check if database exists
                    $stmt = $serverConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
                    $db_exists = $stmt->fetch();
                    
                    if ($db_exists) {
                        echo "<p class='success'><strong>✓ Success:</strong> Database '" . DB_NAME . "' exists.</p>";
                    } else {
                        echo "<p class='error'><strong>✗ Error:</strong> Database '" . DB_NAME . "' does not exist.</p>";
                        echo "<div class='alert alert-warning'>
                                <h5>Solution:</h5>
                                <p>Create the database by importing the SQL file:</p>
                                <ol>
                                    <li>Open phpMyAdmin (http://localhost/phpmyadmin/)</li>
                                    <li>Click 'New' in the left sidebar</li>
                                    <li>Enter '" . DB_NAME . "' as the database name and click 'Create'</li>
                                    <li>Select the newly created database</li>
                                    <li>Click the 'Import' tab</li>
                                    <li>Click 'Choose File' and select 'database_setup.sql' from your project folder</li>
                                    <li>Click 'Go' to import the database schema</li>
                                </ol>
                              </div>";
                    }
                } catch (PDOException $e) {
                    echo "<p class='error'><strong>✗ Error:</strong> Failed to connect to MySQL server: " . $e->getMessage() . "</p>";
                    
                    if ($e->getCode() == 2002) {
                        echo "<div class='alert alert-warning'>
                                <h5>Solution:</h5>
                                <p>MySQL server is not running. Start it using XAMPP Control Panel:</p>
                                <ol>
                                    <li>Open XAMPP Control Panel</li>
                                    <li>Click 'Start' next to MySQL</li>
                                    <li>Wait for the service to start (status should turn green)</li>
                                    <li>Refresh this page</li>
                                </ol>
                              </div>";
                    } else if ($e->getCode() == 1045) {
                        echo "<div class='alert alert-warning'>
                                <h5>Solution:</h5>
                                <p>Access denied. Check your database username and password in config.php.</p>
                              </div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <?php if (isset($db_exists) && $db_exists): ?>
        <div class="card">
            <div class="card-header">
                <h5>Database Tables Test</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Connect to the database
                    $dbDsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                    $dbConn = new PDO($dbDsn, DB_USER, DB_PASSWORD);
                    
                    // Required tables
                    $requiredTables = ['users', 'company', 'categories', 'tax_rates', 'products', 'customers', 
                                      'cash_registers', 'documents', 'document_items', 'price_lists', 
                                      'price_list_items', 'promotions', 'promotion_products', 'stock_movements'];
                    
                    // Check if all required tables exist
                    $missingTables = [];
                    foreach ($requiredTables as $table) {
                        $stmt = $dbConn->query("SHOW TABLES LIKE '{$table}'");
                        if (!$stmt->fetch()) {
                            $missingTables[] = $table;
                        }
                    }
                    
                    if (empty($missingTables)) {
                        echo "<p class='success'><strong>✓ Success:</strong> All required tables exist in the database.</p>";
                        
                        // Check if admin user exists
                        $stmt = $dbConn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
                        $adminExists = $stmt->fetch();
                        
                        if ($adminExists && $adminExists['count'] > 0) {
                            echo "<p class='success'><strong>✓ Success:</strong> Admin user exists. You can log in using:</p>";
                            echo "<ul>
                                    <li><strong>Username:</strong> admin</li>
                                    <li><strong>Password:</strong> password</li>
                                  </ul>";
                        } else {
                            echo "<p class='warning'><strong>⚠ Warning:</strong> Admin user does not exist. Database may be missing sample data.</p>";
                        }
                    } else {
                        echo "<p class='error'><strong>✗ Error:</strong> The following tables are missing: " . implode(', ', $missingTables) . "</p>";
                        echo "<div class='alert alert-warning'>
                                <h5>Solution:</h5>
                                <p>Import the complete database schema using phpMyAdmin or MySQL command line.</p>
                              </div>";
                    }
                } catch (PDOException $e) {
                    echo "<p class='error'><strong>✗ Error:</strong> Failed to check database tables: " . $e->getMessage() . "</p>";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="login.php" class="btn btn-primary">Go to Login Page</a>
            <a href="index.php" class="btn btn-secondary ml-2">Go to Home Page</a>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.9.12/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
