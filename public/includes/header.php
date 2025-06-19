<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user data
$user_fullname = $_SESSION['full_name'] ?? $_SESSION['username'];
$user_role = $_SESSION['role'] ?? 'cashier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Bootstrap 4.5.2 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <!-- Font Awesome 5.15.3 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" />
    <!-- Custom Profile Styles -->
    <link rel="stylesheet" href="/MTECH UGANDA/public/css/profile.css">
    <style>
        /* Custom dropdown styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 1000;
            min-width: 12rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            font-size: 0.875rem;
            color: #212529;
            text-align: left;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu-right {
            right: 0;
            left: auto;
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }
        
        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }
        
        .dropdown-header {
            display: block;
            padding: 0.5rem 1.5rem;
            margin-bottom: 0;
            font-size: 0.875rem;
            color: #6c757d;
            white-space: nowrap;
        }
        
        :root {
            --dark-bg: #1e2130;
            --med-bg: #2a2e43;
            --light-bg: #3a3f55;
            --text-light: #f0f0f0;
            --text-muted: #a0a0a0;
            --accent-blue: #3584e4;
            --accent-green: #2fac66;
            --accent-red: #e35d6a;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        
        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: var(--dark-bg);
            border-right: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: width 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
            position: fixed;
            z-index: 1000;
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar-header {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-title {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }
        
        .nav-link {
            color: var(--text-muted);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--text-light);
            background-color: var(--light-bg);
            text-decoration: none;
        }
        
        .nav-link.active {
            border-left: 3px solid var(--accent-blue);
            padding-left: 17px;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.2rem;
        }
        
        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .toggle-sidebar:hover {
            color: var(--text-light);
            background-color: var(--light-bg);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            transition: margin-left 0.3s;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 60px;
        }
        
        /* Header */
        .header {
            background-color: var(--med-bg);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--light-bg);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-menu .dropdown-toggle {
            display: flex;
            align-items: center;
            color: var(--text-light);
            text-decoration: none;
        }
        
        .user-menu .dropdown-toggle::after {
            margin-left: 5px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Cards */
        .card {
            background-color: var(--med-bg);
            border: 1px solid var(--light-bg);
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--light-bg);
            border-bottom: 1px solid var(--light-bg);
            padding: 12px 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Forms */
        .form-control, .form-control:focus {
            background-color: var(--dark-bg);
            border: 1px solid var(--light-bg);
            color: var(--text-light);
        }
        
        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(53, 132, 228, 0.25);
        }
        
        .form-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        /* Buttons */
        .btn {
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }
        
        .btn-primary:hover {
            background-color: #2a6fc9;
            border-color: #2a6fc9;
        }
        
        .btn-success {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
        }
        
        .btn-danger {
            background-color: var(--accent-red);
            border-color: var(--accent-red);
        }
        
        /* Tables */
        .table {
            color: var(--text-light);
            margin-bottom: 0;
        }
        
        .table thead th {
            border-bottom: 1px solid var(--light-bg);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .table td, .table th {
            border-top: 1px solid var(--light-bg);
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        /* Alerts */
        .alert {
            border: none;
            color: white;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: var(--accent-green);
        }
        
        .alert-danger {
            background-color: var(--accent-red);
        }
        
        .alert-warning {
            background-color: #e6a23c;
        }
        
        .alert-info {
            background-color: #17a2b8;
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 5px 8px;
            border-radius: 3px;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1050;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title"><?php echo SITE_NAME; ?></div>
            <button class="toggle-sidebar" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <a href="welcome.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'welcome.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="sales.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Sales</span>
            </a>
            
            <a href="invoices.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'invoices.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i>
                <span>Invoices</span>
            </a>
            
            <a href="customers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
            
            <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Products</span>
            </a>
            
            <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i>
                <span>Inventory</span>
            </a>
            
            <a href="credit-payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'credit-payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Credit Payments</span>
            </a>
            
            <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <div class="dropdown-divider"></div>
            
            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>User Management</span>
            </a>
            
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-footer">
            <div class="dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-user-circle"></i>
                    <span class="ml-2"><?php echo htmlspecialchars($user_fullname); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit mr-2"></i>My Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#" id="logoutBtn">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <button class="btn btn-link text-white d-md-none mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <h1 class="header-title d-none d-md-block">
                <?php echo $page_title ?? 'Dashboard'; ?>
            </h1>
            
            <div class="d-flex align-items-center">
                <div class="mr-3 d-none d-md-block">
                    <div class="text-muted small">
                        <i class="far fa-clock mr-1"></i>
                        <span id="currentDateTime"><?php echo date('M j, Y h:i A'); ?></span>
                    </div>
                </div>
                
                <!-- Notifications Dropdown -->
                <?php if (in_array($user_role, ['admin', 'manager'])): ?>
                <?php include 'notifications_dropdown.php'; ?>
                <?php endif; ?>
                
                <div class="d-flex align-items-center">
                    <span class="user-avatar mr-2"><?php echo strtoupper(substr($user_fullname, 0, 1)); ?></span>
                    <span class="text-white"><?php echo htmlspecialchars($user_fullname); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="container-fluid py-4">
            <!-- Content will be inserted here -->
