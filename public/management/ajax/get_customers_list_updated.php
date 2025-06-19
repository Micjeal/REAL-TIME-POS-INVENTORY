<?php
// Include database configuration (session is already started in config.php)
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Get request parameters for DataTables
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = $_GET['search']['value'] ?? '';
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';
    
    // Define columns for ordering
    $columns = ['name', 'type', 'phone', 'email', 'tax_number'];
    $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'name';
    $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    
    // Base query
    $query = "FROM customers WHERE 1=1";
    $params = [];
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (name LIKE :search OR phone LIKE :search OR email LIKE :search OR tax_number LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Get total records count
    $countStmt = $db->prepare("SELECT COUNT(*) as total $query");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get filtered count (same as total for now, but could be different with filters)
    $filteredCount = $totalRecords;
    
    // Add sorting and pagination
    $query .= " ORDER BY $orderBy $orderDir LIMIT :start, :length";
    
    // Get the data
    $stmt = $db->prepare("SELECT * $query");
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for DataTables
    $formattedData = [];
    foreach ($customers as $customer) {
        $formattedData[] = [
            'id' => (int)$customer['id'],
            'name' => htmlspecialchars($customer['name']),
            'type' => ucfirst(htmlspecialchars($customer['type'])),
            'phone' => htmlspecialchars($customer['phone'] ?? ''),
            'email' => htmlspecialchars($customer['email'] ?? ''),
            'tax_number' => htmlspecialchars($customer['tax_number'] ?? ''),
            'active' => (bool)$customer['active']
        ];
    }
    
    // Prepare the response
    $response = [
        'draw' => $draw,
        'recordsTotal' => (int)$totalRecords,
        'recordsFiltered' => (int)$filteredCount,
        'data' => $formattedData
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Database error in get_customers_list_updated.php: ' . $e->getMessage());
    
    // Return empty result on error for DataTables
    echo json_encode([
        'draw' => (int)($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}
