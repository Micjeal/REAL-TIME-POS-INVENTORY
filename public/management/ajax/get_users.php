<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config.php';

// Database connection
header('Content-Type: application/json');

// Get request parameters from DataTables
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';
$orderColumn = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

// Define column names
$columns = [
    0 => 'id',
    1 => 'username',
    2 => 'name',
    3 => 'email',
    4 => 'role',
    5 => 'active',
    6 => 'created_at'
];

// Build the query
$query = "SELECT SQL_CALC_FOUND_ROWS id, username, name, email, role, active, created_at 
          FROM users 
          WHERE 1=1";

// Add search condition
if (!empty($search)) {
    $query .= " AND (username LIKE :search OR name LIKE :search OR email LIKE :search)";
    $searchParam = "%$search%";
}

// Add order by
$orderBy = $columns[$orderColumn] ?? 'id';
$query .= " ORDER BY $orderBy " . ($orderDir === 'asc' ? 'ASC' : 'DESC');

// Add limit and offset
$query .= " LIMIT :start, :length";

try {
    // Get total records count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    
    // Bind search parameter if exists
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    // Bind limit and offset
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':length', $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get filtered count
    $filteredCount = count($users);
    
    // Format the data for DataTables
    $data = [];
    foreach ($users as $user) {
        $data[] = [
            'id' => (int)$user['id'],
            'username' => htmlspecialchars($user['username']),
            'name' => htmlspecialchars($user['name']),
            'email' => htmlspecialchars($user['email'] ?? ''),
            'role' => $user['role'],
            'active' => (int)$user['active'],
            'created_at' => $user['created_at']
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'draw' => (int)$draw,
        'recordsTotal' => (int)$totalRecords,
        'recordsFiltered' => (int)$filteredCount,
        'data' => $data
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
