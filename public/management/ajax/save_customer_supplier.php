<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$id = $_POST['id'] ?? '';
$code = $_POST['code'] ?? '';
$name = $_POST['name'] ?? '';
$tax_number = $_POST['tax_number'] ?? '';
$address = $_POST['address'] ?? '';
$country = $_POST['country'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$is_customer = isset($_POST['is_customer']) ? (int)$_POST['is_customer'] : 1;
$is_tax_exempt = isset($_POST['is_tax_exempt']) ? (int)$_POST['is_tax_exempt'] : 0;

// Validate required fields
if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Code and Name are required']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Check if customers_suppliers table exists
    $stmt = $db->query("SHOW TABLES LIKE 'customers_suppliers'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        // Create customers_suppliers table if it doesn't exist
        $db->exec("CREATE TABLE customers_suppliers (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            tax_number VARCHAR(50),
            address TEXT,
            country VARCHAR(100),
            phone VARCHAR(50),
            email VARCHAR(100),
            is_active TINYINT(1) DEFAULT 1,
            is_customer TINYINT(1) DEFAULT 1,
            is_tax_exempt TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    // Check if code already exists (except for the current record being edited)
    $check_query = "SELECT id FROM customers_suppliers WHERE code = ? AND id != ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$code, $id ?: 0]);
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Code already exists. Please use a different code.']);
        exit();
    }
    
    if (empty($id)) {
        // Insert new record
        $query = "INSERT INTO customers_suppliers (
                    code, name, tax_number, address, country, phone, email, 
                    is_active, is_customer, is_tax_exempt
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $code, $name, $tax_number, $address, $country, $phone, $email,
            $is_active, $is_customer, $is_tax_exempt
        ]);
        
        $id = $db->lastInsertId();
        $message = 'Customer/supplier created successfully';
    } else {
        // Update existing record
        $query = "UPDATE customers_suppliers SET 
                    code = ?, name = ?, tax_number = ?, address = ?, country = ?, 
                    phone = ?, email = ?, is_active = ?, is_customer = ?, is_tax_exempt = ? 
                WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $code, $name, $tax_number, $address, $country, $phone, $email,
            $is_active, $is_customer, $is_tax_exempt, $id
        ]);
        
        $message = 'Customer/supplier updated successfully';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'id' => $id
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
