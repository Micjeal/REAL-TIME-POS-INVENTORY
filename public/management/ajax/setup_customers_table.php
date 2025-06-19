<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Only admin and manager can access this endpoint
if (!in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Check if customers table exists, create if not
    $tableCheck = $db->query("SHOW TABLES LIKE 'customers'");
    $tableExists = $tableCheck->rowCount() > 0;
    
    if (!$tableExists) {
        // Create customers table if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type ENUM('customer', 'supplier', 'both') DEFAULT 'customer',
            tax_number VARCHAR(100),
            address VARCHAR(255),
            city VARCHAR(100),
            postal_code VARCHAR(20),
            country VARCHAR(100),
            phone VARCHAR(50),
            email VARCHAR(100),
            contact_person VARCHAR(100),
            notes TEXT,
            discount_percent DECIMAL(5,2) DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_type (type),
            INDEX idx_phone (phone),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // If customers_suppliers table exists, migrate data
        $legacyTableCheck = $db->query("SHOW TABLES LIKE 'customers_suppliers'");
        if ($legacyTableCheck->rowCount() > 0) {
            $db->exec("INSERT INTO customers (name, type, tax_number, address, city, postal_code, country, phone, email, contact_person, notes, discount_percent, active, created_at, updated_at)
                SELECT 
                    name, 
                    CASE 
                        WHEN is_customer = 1 AND is_supplier = 1 THEN 'both'
                        WHEN is_supplier = 1 THEN 'supplier'
                        ELSE 'customer'
                    END as type,
                    tax_number,
                    address,
                    city,
                    postal_code,
                    country,
                    phone,
                    email,
                    contact_person,
                    notes,
                    discount_percent,
                    is_active,
                    created_at,
                    updated_at
                FROM customers_suppliers");
        }
    }
    
    // Handle DataTables server-side processing
    $columns = [
        0 => 'id',
        1 => 'name',
        2 => 'type',
        3 => 'phone',
        4 => 'email',
        5 => 'tax_number',
        6 => 'discount_percent',
        7 => 'active'
    ];
    
    // Get request parameters
    $draw = $_GET['draw'] ?? 1;
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = $_GET['search']['value'] ?? '';
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtoupper($_GET['order'][0]['dir'] ?? 'asc');
    $orderDir = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'ASC';
    
    // Build query
    $query = "SELECT SQL_CALC_FOUND_ROWS * FROM customers";
    $countQuery = "SELECT COUNT(*) as total FROM customers";
    $where = [];
    $params = [];
    
    // Apply search filter
    if (!empty($search)) {
        $searchTerms = [];
        $searchTerms[] = "name LIKE :search";
        $searchTerms[] = "phone LIKE :search";
        $searchTerms[] = "email LIKE :search";
        $searchTerms[] = "tax_number LIKE :search";
        $searchTerms[] = "contact_person LIKE :search";
        $params['search'] = "%$search%";
        
        $where[] = "(" . implode(" OR ", $searchTerms) . ")";
    }
    
    // Add where conditions
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
        $countQuery .= " WHERE " . implode(" AND ", $where);
    }
    
    // Add ordering
    $orderColumnName = $columns[$orderColumn] ?? 'name';
    $query .= " ORDER BY $orderColumnName $orderDir";
    
    // Add limit/offset
    $query .= " LIMIT :start, :length";
    
    // Prepare and execute the query
    $stmt = $db->prepare($query);
    
    // Bind parameters
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total records count
    $totalRecords = $db->query('SELECT COUNT(*) as total FROM customers')->fetchColumn();
    $filteredRecords = $totalRecords;
    
    // If we have a search, get the filtered count
    if (!empty($search)) {
        $filteredStmt = $db->prepare($countQuery);
        foreach ($params as $key => $value) {
            $filteredStmt->bindValue(":$key", $value);
        }
        $filteredStmt->execute();
        $filteredRecords = $filteredStmt->fetchColumn();
    }
    
    // Prepare response
    $response = [
        'draw' => (int)$draw,
        'recordsTotal' => (int)$totalRecords,
        'recordsFiltered' => (int)$filteredRecords,
        'data' => $results
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
