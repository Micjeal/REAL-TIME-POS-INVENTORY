<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$page_title = 'My Profile';

// Include database connection
require_once '../../config.php';

// Initialize database connection
try {
    $pdo = get_db_connection();
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
    
    // Get current user's data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, username, name, email, role, active, avatar_path, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User not found, redirect to login
        header('Location: /login.php');
        exit();
    }
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log('Database error: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}

// Fetch available roles (for admin users, if applicable)
$roles = [];
if ($user['role'] === 'admin') {
    $stmt = $pdo->query("SELECT DISTINCT role FROM users WHERE active = 1");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle AJAX form submissions (profile update and password change)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = ($user['role'] === 'admin' && isset($_POST['role'])) ? trim($_POST['role']) : $user['role'];
        $avatar_path = $user['avatar_path'];

        // Validate input
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
            exit;
        }

        // Handle avatar upload
        if (isset($_FILES['avatar_path']) && $_FILES['avatar_path']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $upload_dir = 'uploads/avatars/';
            $file = $_FILES['avatar_path'];

            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP']);
                exit;
            }
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'Image size exceeds 5MB limit']);
                exit;
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $filename;

            // Create upload directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Delete old avatar if exists
                if ($avatar_path && file_exists($avatar_path)) {
                    unlink($avatar_path);
                }
                $avatar_path = $destination;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error uploading image']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, avatar_path = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $avatar_path, $user_id]);

            // Update session variables
            $_SESSION['full_name'] = $name;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
            exit;
        }

        // Fetch user to verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $current_user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password in database
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            // Add to password history
            $stmt = $pdo->prepare("INSERT INTO user_password_history (user_id, username, password_hash, changed_at, changed_by, ip_address, user_agent) 
                                 VALUES (?, ?, ?, NOW(), ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $user['username'],
                $hashed_password,
                $user_id,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }

        /* App Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Menu Toggle Button */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Sidebar Styles - Modern Glass Morphism */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: rgba(26, 35, 126, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.2);
            overflow-y: auto;
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1.5rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-align: center;
        }

        /* Menu Sections */
        .menu-section {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .menu-section:last-child {
            border-bottom: none;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            position: relative;
        }
        
        .menu-section-title:after {
            content: '';
            position: absolute;
            left: 1.5rem;
            right: 1.5rem;
            bottom: -0.25rem;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            border-left: 4px solid transparent;
            margin: 0.15rem 1rem;
            border-radius: 8px;
        }

        .menu-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(4px);
        }

        .menu-item.active {
            background: linear-gradient(90deg, rgba(67, 97, 238, 0.2), transparent);
            color: #fff;
            border-left-color: var(--primary);
            font-weight: 500;
        }

        .menu-item:hover i {
            color: #fff;
        }

        .menu-item.active i {
            color: var(--primary-light);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
            transition: all 0.3s ease;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 5rem 1.5rem 2rem;
            }

            .sidebar.active + .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }

            .top-nav {
                left: 0;
                width: 100%;
                padding-left: 1.5rem;
            }
        }

        /* Nav Link Styles */
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.15rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-transform: uppercase;
            object-fit: cover;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        /* Card Styles */
        .card {
            background: #fff;
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .card-btn {
            background: none;
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .card-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--gray-100);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            border-bottom: none;
            white-space: nowrap;
        }

        .table td {
            padding: 14px 16px;
            vertical-align: middle;
            border-color: var(--gray-200);
            color: var(--gray-800);
        }

        .table tr:last-child td {
            border-bottom: 1px solid var(--gray-200);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }

        /* Badge Styles */
        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
            font-size: 0.75rem;
            border-radius: 50px;
            letter-spacing: 0.3px;
        }

        .badge.bg-success {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
        }

        .badge.bg-danger {
            background-color: #ffebee !important;
            color: #c62828 !important;
        }

        .badge.bg-warning {
            background-color: #fff8e1 !important;
            color: #f57f17 !important;
        }

        /* Button Styles */
        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn i {
            font-size: 0.9em;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #3a56d4;
            border-color: #3a56d4;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            color: var(--gray-600);
            border-color: var(--gray-300);
        }

        .btn-outline-secondary:hover {
            background-color: var(--gray-100);
            color: var(--dark);
            border-color: var(--gray-400);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Form Styles */
        .form-control, .form-select {
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Tab Styles */
        .nav-tabs {
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            color: var(--gray-600);
            font-weight: 500;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px 8px 0 0;
            margin-right: 0.5rem;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary);
            background-color: rgba(67, 97, 238, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom: 3px solid var(--primary);
            font-weight: 600;
        }

        .tab-content {
            padding: 1.5rem;
            background: #fff;
            border-radius: 0 0 12px 12px;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: var(--primary);
            color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1rem 1.5rem;
        }

        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Product Image */
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .action-btns .btn {
            padding: 0.35rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* Category Badge */
        .category-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 0.4em 0.8em;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.4em 0.8em;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge i {
            font-size: 0.7em;
        }

        .status-active {
            background-color: rgba(67, 160, 71, 0.1);
            color: #43a047;
            border: 1px solid rgba(67, 160, 71, 0.2);
        }

        .status-inactive {
            background-color: rgba(239, 83, 80, 0.1);
            color: #ef5350;
            border: 1px solid rgba(239, 83, 80, 0.2);
        }

        /* Search Box */
        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding-left: 2.5rem;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            z-index: 5;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }

        /* Menu Toggle Button */
        .menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1001;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .category-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            display: inline-block;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .data-table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            background-color: var(--gray-50);
            padding: 0.75rem 1rem;
        }

        .data-table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid var(--gray-100);
        }

        .data-table tr:last-child td {
            border-bottom: 1px solid var(--gray-100);
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <button class="menu-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>POS System</h2>
            </div>
            <nav>
                <div class="menu-section">
                    <div class="menu-section-title">Main</div>
                    <a href="dashboard.php" class="menu-item">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="products.php" class="menu-item">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="categories.php" class="menu-item">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                    <a href="sales.php" class="menu-item">
                        <i class="fas fa-shopping-cart"></i> Sales
                    </a>
                </div>
                <div class="menu-section">
                    <div class="menu-section-title">Settings</div>
                    <a href="profile.php" class="menu-item active">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="users.php" class="menu-item">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <h1 class="page-title">
                    <i class="fas fa-user-circle me-2"></i>My Profile
                </h1>
                <div class="user-info">
                    <div class="user-details">
                        <?php if ($user['avatar_path'] && file_exists($user['avatar_path'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="User Avatar" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="user-text ms-2">
                            <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
                            <div class="user-role"><?php echo ucfirst($user['role'] ?? 'user'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user me-2"></i>Profile Information
                    </h5>
                    <div class="card-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Avatar</label>
                            <p class="form-control-static">
                                <?php if ($user['avatar_path'] && file_exists($user['avatar_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="User Avatar" class="product-image">
                                <?php else: ?>
                                    No avatar uploaded
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Username</label>
                            <p class="form-control-static"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Full Name</label>
                            <p class="form-control-static"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email Address</label>
                            <p class="form-control-static"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Role</label>
                            <p class="form-control-static"><?php echo ucfirst($user['role'] ?? 'user'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Account Created</label>
                            <p class="form-control-static"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Account Status</label>
                            <p class="form-control-static">
                                <span class="status-badge <?php echo $user['active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas fa-circle me-1"></i>
                                    <?php echo $user['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Modal -->
            <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editProfileModalLabel">
                                <i class="fas fa-user-edit me-2"></i>Edit Profile
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="profileForm" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="update_profile">

                                <!-- Profile Information -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">
                                            Username
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">
                                            Full Name <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" maxlength="255" required>
                                            <div class="invalid-feedback">Please enter your full name.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">
                                            Email Address <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                            <div class="invalid-feedback">Please enter a valid email address.</div>
                                        </div>
                                    </div>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <div class="col-md-6">
                                        <label for="role" class="form-label">
                                            Role
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                            <select class="form-select" id="role" name="role">
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role === $user['role'] ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst(htmlspecialchars($role)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-md-6">
                                        <label for="role_display" class="form-label">
                                            Role
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                            <input type="text" class="form-control" id="role_display" value="<?php echo ucfirst(htmlspecialchars($user['role'] ?? 'user')); ?>" readonly>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Avatar Upload -->
                                <div class="mb-4">
                                    <label for="avatar_path" class="form-label">
                                        Profile Avatar
                                    </label>
                                    <div class="image-upload-container border rounded p-3 text-center">
                                        <div class="mb-3">
                                            <img id="avatar_preview" class="img-thumbnail" 
                                                 src="<?php echo $user['avatar_path'] && file_exists($user['avatar_path']) ? htmlspecialchars($user['avatar_path']) : 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22 width=%22200%22 height=%22150%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23f8f9fa%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2214%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 fill=%22%236c757d%22%3ENo image%3C/text%3E%3C/svg%3E'; ?>" 
                                                 alt="Avatar Preview" style="max-height: 150px;">
                                        </div>
                                        <div class="d-flex flex-column align-items-center">
                                            <label class="btn btn-outline-primary btn-sm mb-2" for="avatar_path">
                                                <i class="fas fa-upload me-1"></i> Choose Image
                                            </label>
                                            <input type="file" class="d-none" id="avatar_path" name="avatar_path" 
                                                   accept="image/png,image/jpeg,image/jpg,image/gif,image/webp">
                                            <small class="form-text text-muted">
                                                Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-end align-items-center p-3 bg-light rounded">
                                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Modal -->
            <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="changePasswordModalLabel">
                                <i class="fas fa-key me-2"></i>Change Password
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="changePasswordForm" method="post" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="change_password">

                                <!-- Password Fields -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-12">
                                        <label for="current_password" class="form-label">
                                            Current Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="invalid-feedback">Please enter your current password.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">
                                            New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="invalid-feedback">Please enter a new password (min 8 characters).</div>
                                        </div>
                                        <small class="form-text text-muted">Must be at least 8 characters long</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">
                                            Confirm New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="invalid-feedback">Please confirm your new password.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-end align-items-center p-3 bg-light rounded">
                                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key me-1"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Container -->
            <div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
        </main>
    </div>

    <!-- Bootstrap JS and Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            $('.sidebar').toggleClass('active');
            if ($('.sidebar').hasClass('active')) {
                $('.main-content').css('margin-left', '280px').css('width', 'calc(100% - 280px)');
            } else {
                $('.main-content').css('margin-left', '0').css('width', '100%');
            }
        }

        $(document).ready(function() {
            // Responsive Menu Toggle
            $('.menu-toggle').click(function() {
                toggleSidebar();
            });

            // Toggle Password Visibility
            $('.toggle-password').click(function() {
                const target = $(this).data('target');
                const input = $('#' + target);
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Avatar Preview
            $('#avatar_path').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#avatar_preview').attr('src', e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Profile Form Submission
            $('#profileForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const formData = new FormData(form[0]);

                if (form[0].checkValidity() === false) {
                    e.stopPropagation();
                    form.addClass('was-validated');
                    return;
                }

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            $('#editProfileModal').modal('hide');
                            // Update displayed profile info
                            $('.user-name').text($('#name').val());
                            $('.card-body .form-control-static').eq(1).text($('#username').val());
                            $('.card-body .form-control-static').eq(2).text($('#name').val());
                            $('.card-body .form-control-static').eq(3).text($('#email').val());
                            $('.card-body .form-control-static').eq(4).text($('#role').val() ? $('#role').val().charAt(0).toUpperCase() + $('#role').val().slice(1) : '<?php echo ucfirst($user['role']); ?>');
                            // Update avatar
                            const avatarSrc = $('#avatar_preview').attr('src');
                            if (avatarSrc && !avatarSrc.includes('data:image/svg+xml')) {
                                $('.user-info .user-avatar').replaceWith(`<img src="${avatarSrc}" alt="User Avatar" class="user-avatar">`);
                                $('.card-body .form-control-static').eq(0).html(`<img src="${avatarSrc}" alt="User Avatar" class="product-image">`);
                            }
                        } else {
                            showToast('danger', response.message);
                        }
                    },
                    error: function() {
                        showToast('danger', 'Error updating profile');
                    },
                    complete: function() {
                        form.find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Profile');
                    }
                });
            });

            // Password Form Submission
            $('#changePasswordForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const newPassword = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();

                if (newPassword !== confirmPassword) {
                    showToast('danger', 'New passwords do not match');
                    return;
                }
                if (newPassword.length < 8) {
                    showToast('danger', 'Password must be at least 8 characters long');
                    return;
                }

                if (form[0].checkValidity() === false) {
                    e.stopPropagation();
                    form.addClass('was-validated');
                    return;
                }

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Changing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            $('#changePasswordModal').modal('hide');
                            form[0].reset();
                            form.removeClass('was-validated');
                        } else {
                            showToast('danger', response.message);
                        }
                    },
                    error: function() {
                        showToast('danger', 'Error changing password');
                    },
                    complete: function() {
                        form.find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-key me-1"></i> Change Password');
                    }
                });
            });

            // Show Toast Notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `);
                $('#toastContainer').append(toast);
                toast.toast({ delay: 3000 }).toast('show');
                setTimeout(() => toast.remove(), 3500);
            }
        });
    </script>
</body>
</html>