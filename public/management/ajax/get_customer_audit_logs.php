<?php
// Include database configuration (session is already started in config.php)
require_once '../../config.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Get customer ID from request
$customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);

if (!$customerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer ID']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Get request parameters for DataTables
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = $_GET['search']['value'] ?? '';
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
    
    // Define column mapping for ordering
    $columns = [
        'ca.changed_at',
        'u.name',
        'ca.action',
        'ca.old_data',
        'ca.new_data',
        'ca.ip_address'
    ];
    
    // Base query
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                ca.id,
                ca.changed_at,
                u.name as changed_by,
                ca.action,
                ca.old_data,
                ca.new_data,
                ca.ip_address,
                ca.user_agent
              FROM customer_audit ca
              LEFT JOIN users u ON ca.changed_by = u.id
              WHERE ca.customer_id = :customer_id";
    
    $params = ['customer_id' => $customerId];
    $types = ['customer_id' => PDO::PARAM_INT];
    
    // Add search condition
    if (!empty($search)) {
        $query .= " AND (
            ca.action LIKE :search OR 
            u.name LIKE :search OR 
            ca.ip_address LIKE :search OR
            ca.old_data LIKE :search OR
            ca.new_data LIKE :search
        )";
        $params['search'] = "%$search%";
    }
    
    // Add ordering
    $orderColumn = $columns[$orderColumn] ?? $columns[0];
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
    $query .= " ORDER BY $orderColumn $orderDir";
    
    // Add limit/offset
    $query .= " LIMIT :start, :length";
    $params['start'] = $start;
    $params['length'] = $length;
    $types['start'] = PDO::PARAM_INT;
    $types['length'] = PDO::PARAM_INT;
    
    // Prepare and execute query
    $stmt = $db->prepare($query);
    
    // Bind parameters with their types
    foreach ($params as $key => $value) {
        $paramType = $types[$key] ?? PDO::PARAM_STR;
        $stmt->bindValue(":$key", $value, $paramType);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total records count
    $totalRecords = $db->query('SELECT FOUND_ROWS()')->fetchColumn();
    
    // Format the data for DataTables
    $formattedData = array_map(function($row) {
        return [
            $row['changed_at'],
            $row['changed_by'] ?? 'System',
            strtoupper($row['action']),
            $this->formatAuditData($row['action'], $row['old_data'] ?? null, $row['new_data'] ?? null),
            $row['ip_address'],
            $row['user_agent']
        ];
    }, $results);
    
    // Return JSON response
    echo json_encode([
        'draw' => (int)($_GET['draw'] ?? 1),
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalRecords,
        'data' => $formattedData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while fetching audit logs']);
    error_log('Failed to fetch customer audit logs: ' . $e->getMessage());
}

/**
 * Format audit data for display
 */
function formatAuditData($action, $oldData, $newData) {
    $output = '';
    
    try {
        $oldData = $oldData ? json_decode($oldData, true) : [];
        $newData = $newData ? json_decode($newData, true) : [];
        
        if ($action === 'INSERT') {
            $output = '<div class="audit-diff">';
            foreach ($newData as $key => $value) {
                if ($value !== null) {
                    $output .= sprintf(
                        '<div><strong>%s:</strong> %s</div>',
                        htmlspecialchars($key),
                        htmlspecialchars($value)
                    );
                }
            }
            $output .= '</div>';
        } elseif ($action === 'UPDATE') {
            $output = '<div class="audit-diff">';
            foreach ($newData as $key => $value) {
                $oldValue = $oldData[$key] ?? null;
                if ($oldValue !== $value) {
                    $output .= sprintf(
                        '<div><strong>%s:</strong> <span class="text-danger">%s</span> → <span class="text-success">%s</span></div>',
                        htmlspecialchars($key),
                        htmlspecialchars($oldValue),
                        htmlspecialchars($value)
                    );
                }
            }
            $output .= '</div>';
        } elseif ($action === 'DELETE') {
            $output = '<div class="audit-diff">';
            foreach ($oldData as $key => $value) {
                $output .= sprintf(
                    '<div><strong>%s:</strong> %s</div>',
                    htmlspecialchars($key),
                    htmlspecialchars($value)
                );
            }
            $output .= '</div>';
        }
    } catch (Exception $e) {
        error_log('Error formatting audit data: ' . $e->getMessage());
        return 'Error formatting audit data';
    }
    
    return $output;
}
