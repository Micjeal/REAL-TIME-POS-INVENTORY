<?php
// Include database configuration (session is already started in config.php)
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit();
}

$id = (int)$_GET['id'];

try {
    $db = get_db_connection();
    
    // Get customer data from the new customers table
    $query = "SELECT * FROM customers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'type' => $customer['type'],
            'tax_number' => $customer['tax_number'],
            'address' => $customer['address'],
            'city' => $customer['city'],
            'postal_code' => $customer['postal_code'],
            'country' => $customer['country'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'contact_person' => $customer['contact_person'],
            'notes' => $customer['notes'],
            'discount_percent' => (float)$customer['discount_percent'],
            'active' => (bool)$customer['active']
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Database error in get_customer.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
