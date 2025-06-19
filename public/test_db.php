<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mtech-uganda');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf8mb4');

// Test database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    echo "<h2>Testing Database Connection</h2>";
    echo "Connecting to database...<br>";
    
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    echo "<span style='color: green;'>✓ Successfully connected to the database.</span><br>";
    
    // Test query
    $stmt = $pdo->query('SELECT 1');
    if ($stmt->fetchColumn()) {
        echo "<span style='color: green;'>✓ Database query executed successfully.</span><br>";
    }
    
    // List tables
    echo "<h3>Database Tables:</h3>";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<span style='color: red;'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    
    // Additional error information
    echo "<h3>Error Details:</h3>";
    echo "<pre>Error Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "</pre>";
    
    // Check MySQL service status
    echo "<h3>MySQL Service Status:</h3>";
    echo "<pre>";
    system('sc query mysql 2>&1');
    system('net start | findstr /i mysql 2>&1');
    echo "</pre>";
}

// Check PHP configuration
echo "<h3>PHP Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Drivers: " . implode(", ", PDO::getAvailableDrivers()) . "<br>";

// Check if PDO MySQL is enabled
if (!in_array('mysql', PDO::getAvailableDrivers())) {
    echo "<span style='color: red;'>✗ PDO MySQL driver is not enabled. Please enable it in your php.ini file.</span><br>";
}

// Check file permissions
echo "<h3>File Permissions:</h3>";
$filesToCheck = [
    __FILE__ => 'Test Script',
    dirname(__DIR__) . '/config.php' => 'Config File',
    __DIR__ . '/management/company.php' => 'Company Page (Relative to test script)',
    'C:\\xampp\\htdocs\\MTECH UGANDA\\public\\management\\company.php' => 'Company Page (Full Path)'
];

echo "<ul>";
foreach ($filesToCheck as $file => $desc) {
    $exists = file_exists($file);
    $readable = is_readable($file);
    $writable = is_writable($file);
    
    echo "<li><strong>$desc</strong> ($file):";
    echo "<ul>";
    echo "<li>Exists: " . ($exists ? '✅' : '❌') . "</li>";
    if ($exists) {
        echo "<li>Readable: " . ($readable ? '✅' : '❌') . "</li>";
        echo "<li>Writable: " . ($writable ? '✅' : '❌') . "</li>";
        echo "<li>Last Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "</li>";
    }
    echo "</ul></li>";
}
echo "</ul>";
?>
