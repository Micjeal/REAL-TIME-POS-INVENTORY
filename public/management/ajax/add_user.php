<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get and validate input data
$username = trim($_POST['username'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';
$active = isset($_POST['active']) ? 1 : 0;

// Validate required fields
$errors = [];

if (empty($username)) {
    $errors['username'] = 'Username is required';
} elseif (strlen($username) < 3) {
    $errors['username'] = 'Username must be at least 3 characters long';
}

if (empty($name)) {
    $errors['name'] = 'Full name is required';
}

if (empty($role) || !in_array($role, ['admin', 'manager', 'cashier'])) {
    $errors['role'] = 'Please select a valid role';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long';
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

// Return validation errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $errors
    ]);
    exit();
}

// Check if username already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
$stmt->bindParam(':username', $username);
$stmt->execute();

if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username already exists',
        'errors' => ['username' => 'This username is already taken']
    ]);
    exit();
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Start transaction
try {
    $pdo->beginTransaction();
    
    // Insert the new user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, name, email, role, password, active, created_at) 
        VALUES (:username, :name, :email, :role, :password, :active, NOW())
    ");
    
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email ?: null);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':active', $active, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create user');
    }
    
    $userId = $pdo->lastInsertId();
    
    // Log the password change in history
    $stmt = $pdo->prepare("
        INSERT INTO user_password_history (
            user_id, username, password_hash, changed_at, changed_by, 
            ip_address, user_agent
        ) VALUES (
            :user_id, :username, :password_hash, NOW(), :changed_by,
            :ip_address, :user_agent
        )
    ");
    
    $changedBy = $_SESSION['user_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password_hash', $hashedPassword);
    $stmt->bindParam(':changed_by', $changedBy);
    $stmt->bindParam(':ip_address', $ipAddress);
    $stmt->bindParam(':user_agent', $userAgent);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to log password history');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'userId' => $userId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create user: ' . $e->getMessage()
    ]);
}
?>
