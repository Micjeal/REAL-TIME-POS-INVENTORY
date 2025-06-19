<?php
// Include the configuration file
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check for admin/manager role
$user_role = $_SESSION['user_role'] ?? 'user';
if (!in_array($user_role, ['admin', 'manager'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || !isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$action = $data['action'];
$ids = array_map('intval', $data['ids']);
$id_placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $db = get_db_connection();
    
    switch ($action) {
        case 'activate':
            $stmt = $db->prepare("UPDATE customers_suppliers SET is_active = 1 WHERE id IN ($id_placeholders)");
            foreach ($ids as $key => $id) {
                $stmt->bindValue($key + 1, $id);
            }
            $stmt->execute();
            $message = 'Selected contacts have been activated';
            break;
            
        case 'deactivate':
            $stmt = $db->prepare("UPDATE customers_suppliers SET is_active = 0 WHERE id IN ($id_placeholders)");
            foreach ($ids as $key => $id) {
                $stmt->bindValue($key + 1, $id);
            }
            $stmt->execute();
            $message = 'Selected contacts have been deactivated';
            break;
            
        case 'delete':
            $stmt = $db->prepare("DELETE FROM customers_suppliers WHERE id IN ($id_placeholders)");
            foreach ($ids as $key => $id) {
                $stmt->bindValue($key + 1, $id);
            }
            $stmt->execute();
            $message = 'Selected contacts have been deleted';
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'affected' => count($ids)
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
