<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database configuration
    require_once __DIR__ . '/../../config.php';

    // Initialize database connection
    $pdo = get_db_connection();

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../login.php');
        exit();
    }

    // Set page title
    $page_title = 'User Management';

    // Get user role for access control
    $user_role = $_SESSION['role'] ?? 'cashier';
    $user_id = $_SESSION['user_id'];

    // Fetch current user's data for sidebar
    $stmt = $pdo->prepare("SELECT name, role, avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user has permission to access this page (only admin can manage users)
    if (strtolower($user_role) !== 'admin') {
        header('Location: ../../unauthorized.php');
        exit();
    }
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log('Database error in users.php: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
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

        /* Product Image / Avatar */
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
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

        /* DataTable Styles */
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

        /* Action Buttons */
        .action-btns .btn {
            padding: 0.35rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
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
                    <a href="profile.php" class="menu-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <?php if (strtolower($current_user['role']) === 'admin'): ?>
                    <a href="users.php" class="menu-item active">
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
                    <i class="fas fa-users me-2"></i>User Management
                </h1>
                <div class="user-info">
                    <div class="user-details">
                        <?php if ($current_user['avatar_path'] && file_exists($current_user['avatar_path'])): ?>
                            <img src="<?php echo htmlspecialchars($current_user['avatar_path']); ?>" alt="User Avatar" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($current_user['name'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="user-text ms-2">
                            <div class="user-name"><?php echo htmlspecialchars($current_user['name'] ?? 'User'); ?></div>
                            <div class="user-role"><?php echo ucfirst($current_user['role'] ?? 'user'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Card -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users me-2"></i>Users
                    </h5>
                    <div class="card-actions">
                        <div class="search-box me-2">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover data-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Avatar</th>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="userForm" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" id="userId" name="id">
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="username" class="form-label">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <div class="invalid-feedback" id="usernameFeedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                    <div class="invalid-feedback">Please enter the full name.</div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="email" class="form-label">
                                    Email Address
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email">
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">
                                    Role <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin">Administrator</option>
                                        <option value="manager">Manager</option>
                                        <option value="cashier">Cashier</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a role.</div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">
                                    Password <span class="text-danger" id="passwordRequired">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Please enter a password.</div>
                                </div>
                                <small class="form-text text-muted">Leave blank to keep current password (edit mode).</small>
                            </div>
                            <div class="col-md-6">
                                <label for="active" class="form-label">
                                    Status
                                </label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                                    <label class="form-check-label" for="active">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="avatar_path" class="form-label">
                                Profile Avatar
                            </label>
                            <div class="image-upload-container border rounded p-3 text-center">
                                <div class="mb-3">
                                    <img id="avatar_preview" class="img-thumbnail" 
                                         src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22 width=%22200%22 height=%22150%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23f8f9fa%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2214%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 fill=%22%236c757d%22%3ENo image%3C/text%3E%3C/svg%3E" 
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
                    </div>
                    <div class="modal-footer d-flex justify-content-end align-items-center p-3 bg-light rounded">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveUserBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="fas fa-save me-1"></i> Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">
                        <i class="fas fa-trash-alt me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong id="deleteUserName"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="text/javascript" src="https://cdn.jsdelivr.com/npm/sweetalert2@11.6.8/dist/sweetalert2.all.min.js"></script>
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
            $('.menu-toggle').on('click', toggleSidebar);

            // Initialize Users DataTable
            const usersTable = $('#usersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'ajax/get_users.php',
                    type: 'POST'
                },
                columns: [
                    {
                        data: 'avatar_path',
                        render: function(data, type, row) {
                            if (data && data !== '') {
                                return `<img src="${data}" alt="User Avatar" class="user-avatar-sm">`;
                            } else {
                                return `<div class="user-avatar-sm">${row.name ? row.name.charAt(0).toUpperCase() : 'U'}</div>`;
                            }
                        },
                        orderable: false,
                        className: 'text-center'
                    },
                    { 
                        data: 'id',
                        className: 'text-center'
                    },
                    { 
                        data: 'username',
                        render: $.fn.dataTable.render.text()
                    },
                    { 
                        data: 'name',
                        render: $.fn.dataTable.render.text()
                    },
                    { 
                        data: 'email',
                        render: function(data) {
                            return data || '<span class="text-muted">No email</span>';
                        }
                    },
                    { 
                        data: 'role',
                        render: function(data) {
                            const roles = {
                                'admin': '<span class="badge bg-primary">Administrator</span>',
                                'manager': '<span class="badge bg-info">Manager</span>',
                                'cashier': '<span class="badge bg-secondary">Cashier</span>'
                            };
                            return roles[data] || data;
                        },
                        className: 'text-center'
                    },
                    { 
                        data: 'active',
                        render: function(data) {
                            return data == 1 
                                ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>'
                                : '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>';
                        },
                        className: 'text-center'
                    },
                    { 
                        data: 'created_at',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString() : '-';
                        },
                        className: 'text-center'
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            const isCurrentUser = row.id === '<?php echo $user_id; ?>';
                            const deleteBtn = !isCurrentUser ? `
                                <button class="btn btn-danger btn-sm delete-user" data-id="${row.id}" data-username="${row.username}" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>` : '';
                            
                            return `
                                <div class="action-btns d-flex justify-content-center">
                                    <button class="btn btn-primary btn-sm me-1 edit-user" data-id="${row.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    ${deleteBtn}
                                </div>`;
                        },
                        className: 'text-center'
                    }
                ],
                order: [[1, 'desc']],
                responsive: true,
                language: {
                    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                    emptyTable: 'No users found',
                    zeroRecords: 'No matching users found'
                },
                drawCallback: function() {
                    // Initialize tooltips
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }
            });

            // Toggle Password Visibility
            $('.toggle-password').on('click', function() {
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

            // DataTable initialization has been moved above to consolidate with other table configurations
                        },
                        orderable: false
                    },
                    { data: 'id' },
                    { data: 'username' },
                    { data: 'name' },
                    { data: 'email' },
                    { 
                        data: 'role',
                        render: function(data) {
                            const roles = {
                                'admin': 'Administrator',
                                'manager': 'Manager',
                                'cashier': 'Cashier'
                            };
                            return roles[data] || data;
                        }
                    },
                    { 
                        data: 'active',
                        render: function(data) {
                            return data == 1 
                                ? '<span class="status-badge status-active"><i class="fas fa-circle me-1"></i>Active</span>'
                                : '<span class="status-badge status-inactive"><i class="fas fa-circle me-1"></i>Inactive</span>';
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data) {
                            return new Date(data).toLocaleDateString();
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            return `
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm edit-user" data-id="${row.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-user" data-id="${row.id}" data-username="${row.username}" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                        }
                    }
                ],
                order: [[1, 'desc']],
                responsive: true
            });

            // Search functionality
            $('#searchInput').on('keyup', function() {
                usersTable.search(this.value).draw();
            });

            // Reset form when modal is hidden
            $('#userModal').on('hidden.bs.modal', function () {
                $('#userForm')[0].reset();
                $('#userId').val('');
                $('#passwordRequired').show();
                $('#avatar_preview').attr('src', 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22 width=%22200%22 height=%22150%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23f8f9fa%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2214%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 fill=%22%236c757d%22%3ENo image%3C/text%3E%3C/svg%3E');
                $('#userForm .is-invalid').removeClass('is-invalid');
                $('#userForm .invalid-feedback').text('');
                $('#userModalLabel').html('<i class="fas fa-user-plus me-2"></i>Add New User');
            });

            // Check username availability
            $('#username').on('blur', function() {
                const username = $(this).val();
                const userId = $('#userId').val();
                
                if (username.length < 3) {
                    $('#username').addClass('is-invalid');
                    $('#usernameFeedback').text('Username must be at least 3 characters long.');
                    return;
                }
                
                $.ajax({
                    url: 'check_username.php',
                    type: 'POST',
                    data: { 
                        username: username,
                        id: userId || ''
                    },
                    success: function(response) {
                        if (response.exists) {
                            $('#username').addClass('is-invalid');
                            $('#usernameFeedback').text('Username already exists');
                        } else {
                            $('#username').removeClass('is-invalid');
                            $('#usernameFeedback').text('');
                        }
                    },
                    error: function() {
                        showToast('danger', 'Error checking username availability');
                    }
                });
            });

            // Handle form submission
            $('#userForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const formData = new FormData(form[0]);
                const submitBtn = $('#saveUserBtn');
                const originalBtnText = submitBtn.html();
                
                if (form[0].checkValidity() === false) {
                    e.stopPropagation();
                    form.addClass('was-validated');
                    return;
                }

                const userId = $('#userId').val();
                const url = userId ? 'ajax/update_user.php' : 'ajax/add_user.php';
                
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        submitBtn.prop('disabled', true);
                        submitBtn.find('.spinner-border').removeClass('d-none');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#userModal').modal('hide');
                            usersTable.ajax.reload();
                            showToast('success', response.message || 'User saved successfully');
                        } else {
                            if (response.errors) {
                                Object.keys(response.errors).forEach(field => {
                                    const input = $(`#${field}`);
                                    input.addClass('is-invalid');
                                    $(`#${field}Feedback`).text(response.errors[field]);
                                });
                            } else {
                                showToast('danger', response.message || 'An error occurred while saving the user');
                            }
                        }
                    },
                    error: function() {
                        showToast('danger', 'An error occurred while processing your request. Please try again.');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalBtnText);
                    }
                });
            });

            // Edit user
            $(document).on('click', '.edit-user', function() {
                const userId = $(this).data('id');
                const editBtn = $(this);
                const originalBtnHtml = editBtn.html();
                
                editBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span>');
                
                $.ajax({
                    url: 'ajax/get_user.php',
                    type: 'POST',
                    data: { id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const user = response.data;
                            
                            // Populate form
                            $('#userId').val(user.id);
                            $('#username').val(user.username);
                            $('#name').val(user.name);
                            $('#email').val(user.email || '');
                            $('#role').val(user.role);
                            $('#active').prop('checked', user.active == 1);
                            $('#avatar_preview').attr('src', user.avatar_path || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22 width=%22200%22 height=%22150%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23f8f9fa%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2214%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 fill=%22%236c757d%22%3ENo image%3C/text%3E%3C/svg%3E');
                            
                            $('#userModalLabel').html('<i class="fas fa-user-edit me-2"></i>Edit User');
                            $('#passwordRequired').hide();
                            $('#userModal').modal('show');
                        } else {
                            showToast('danger', response.message || 'Failed to load user data');
                        }
                    },
                    error: function() {
                        showToast('danger', 'Failed to load user data. Please try again.');
                    },
                    complete: function() {
                        editBtn.html(originalBtnHtml);
                    }
                });
            });

            // Delete user
            let userIdToDelete = null;
            
            $(document).on('click', '.delete-user', function() {
                userIdToDelete = $(this).data('id');
                const username = $(this).data('username');
                
                $('#deleteUserName').text(username);
                $('#deleteConfirmModal').modal('show');
            });
            
            // Confirm delete
            $('#confirmDeleteBtn').on('click', function() {
                const deleteBtn = $(this);
                const originalBtnText = deleteBtn.html();
                
                deleteBtn.prop('disabled', true);
                deleteBtn.find('.spinner-border').removeClass('d-none');
                
                $.ajax({
                    url: 'ajax/delete_user.php',
                    type: 'POST',
                    data: { id: userIdToDelete },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#deleteConfirmModal').modal('hide');
                            usersTable.ajax.reload();
                            showToast('success', response.message || 'User deleted successfully');
                        } else {
                            showToast('danger', response.message || 'Failed to delete user');
                        }
                    },
                    error: function() {
                        showToast('danger', 'An error occurred while deleting the user. Please try again.');
                    },
                    complete: function() {
                        deleteBtn.prop('disabled', false);
                        deleteBtn.html(originalBtnText);
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