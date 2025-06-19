<?php
// Include database configuration (session is already started in config.php)
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
$name = trim($_POST['name'] ?? '');
$type = $_POST['type'] ?? 'customer';
$tax_number = trim($_POST['tax_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$postal_code = trim($_POST['postal_code'] ?? '');
$country = trim($_POST['country'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$discount_percent = (float)($_POST['discount_percent'] ?? 0);
$active = isset($_POST['active']) ? 1 : 0;

// Validate required fields
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
    exit();
}

// Validate type
$valid_types = ['customer', 'supplier', 'both'];
if (!in_array($type, $valid_types)) {
    $type = 'customer';
}

// Validate discount percent
if ($discount_percent < 0) $discount_percent = 0;
if ($discount_percent > 100) $discount_percent = 100;

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Set user tracking variables for triggers
    $stmt = $db->prepare("SET @current_user_id = :user_id, 
                         @current_ip_address = :ip_address, 
                         @current_user_agent = :user_agent");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // Prepare data for insertion/update
    $data = [
        'name' => $name,
        'type' => $type,
        'tax_number' => $tax_number,
        'address' => $address,
        'city' => $city,
        'postal_code' => $postal_code,
        'country' => $country,
        'phone' => $phone,
        'email' => $email,
        'contact_person' => $contact_person,
        'notes' => $notes,
        'discount_percent' => $discount_percent,
        'active' => $active,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (empty($id)) {
        // Insert new customer
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO customers ($fields) VALUES ($placeholders)";
        $stmt = $db->prepare($query);
        $stmt->execute($data);
        
        $id = $db->lastInsertId();
        $message = 'Customer added successfully';
    } else {
        // Update existing customer
        $updates = [];
        foreach ($data as $key => $value) {
            $updates[] = "$key = :$key";
        }
        $updates = implode(', ', $updates);
        
        $data['id'] = $id;
        $query = "UPDATE customers SET $updates WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($data);
        
        $message = 'Customer updated successfully';
    }
    
    // Get the updated/inserted customer data
    $query = "SELECT * FROM customers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'customer' => $customer
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in save_customer.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save customer. Please try again.'
    ]);
}
