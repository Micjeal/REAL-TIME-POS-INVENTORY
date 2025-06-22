<?php
// Start the session
session_start();

// Include database configuration and activity logger
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Log failed access attempt
    log_activity('unauthorized_access', null, null, null, null, 'Attempted to access welcome.php without authentication');
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Log page access
log_activity('page_view', 'welcome', null, null, null, 'Accessed welcome page');

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Initialize variables
$categories = [];
$products = [];
$error = '';

// Get active categories and products from database
try {
    // Check if connection exists
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
    }
    
    // Get categories
    $result = $conn->query("SELECT id, name FROM categories WHERE active = 1 ORDER BY name");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get products with their categories
    $result = $conn->query("SELECT p.id, p.code, p.name, p.price, p.stock_quantity, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.active = 1
                         ORDER BY p.name");
    
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get tax rates
    $result = $conn->query("SELECT id, name, rate FROM tax_rates WHERE active = 1");
    if ($result) {
        $tax_rates = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $tax_rates = [];
    }
    
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
    error_log($error);
    $tax_rates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
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
            overflow: hidden;
            display: flex;
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
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-toggle {
            background: transparent;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 5px;
        }
        
        .sidebar-section {
            margin-bottom: 10px;
        }
        
        .sidebar-section-title {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-section-title i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu {
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-menu-item {
            padding: 10px 15px 10px 40px;
            display: flex;
            align-items: center;
            color: var(--text-light);
            text-decoration: none;
            position: relative;
        }
        
        .sidebar-menu-item:hover {
            background-color: var(--light-bg);
        }
        
        .sidebar-menu-item.active {
            background-color: var(--accent-blue);
            font-weight: 500;
        }
        
        .sidebar-menu-item i {
            position: absolute;
            left: 15px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            font-size: 12px;
            text-align: center;
        }
        
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background-color: var(--med-bg);
            padding: 0.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .search-container {
            position: relative;
            width: 50%;
            max-width: 500px;
        }
        
        .search-box {
            background-color: var(--light-bg);
            color: var(--text-light);
            border: none;
            border-radius: 4px;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            width: 100%;
        }
        
        .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .cart-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.1);
            min-width: 0; /* Prevent flex items from overflowing */
        }
        
        .cart-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr;
            cursor: pointer;
            transition: background-color 0.2s;
            
        .cart-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
            
        .cart-item.selected {
            background-color: rgba(40, 167, 69, 0.2);
            outline: 1px solid #28a745;
            border-radius: 4px;
        }
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--light-bg);
            gap: 10px;
        }

        .cart-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .cart-item-name {
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .product-code {
            font-size: 0.8em;
            color: #aaa;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .quantity-btn {
            padding: 2px 8px;
            min-width: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-display {
            min-width: 24px;
            text-align: center;
            font-weight: 500;
        }

        .cart-item-price,
        .cart-item-amount {
            text-align: right;
            padding: 0 10px;
            font-weight: 500;
        }

        .cart-item-amount {
            color: var(--accent-green);
        }

        /* Ensure cart header matches the grid layout */
        .cart-header {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr;
            padding: 10px;
            background-color: var(--med-bg);
            font-weight: bold;
            border-bottom: 2px solid var(--light-bg);
        }

        .cart-header-item {
            padding: 0 10px;
        }

        .cart-header-item:last-child {
            text-align: right;
        }
        
        .cart-item.selected {
            background-color: rgba(0,123,255,0.2);
            border-left: 3px solid #007bff;
        }
        
        .cart-header {
            background-color: var(--med-bg);
            padding: 0.5rem;
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .cart-header-item {
            padding: 0.5rem;
        }
        
        .cart-header-item:first-child {
            flex: 3;
        }
        
        .cart-header-item {
            flex: 1;
            text-align: center;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
            min-height: 0; /* Prevent flex items from overflowing */
        }
        
        .empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
        }
        
        .cart-totals {
            background-color: var(--med-bg);
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0; /* Prevent totals from shrinking */
        }
        
        .cart-total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
        }
        
        .cart-total-value {
            font-weight: bold;
        }
        
        .main-content {
            flex: 0 0 min(320px, 30%);
            background-color: var(--med-bg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-auto-rows: minmax(60px, 1fr);
            gap: 5px;
            padding: 5px;
            flex: 1;
            overflow-y: auto;
            min-height: 0; /* Prevent flex items from overflowing */
        }
        
        .action-button {
            background-color: var(--light-bg);
            border: none;
            color: var(--text-light);
            padding: 8px 5px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
            min-height: 70px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .action-button:hover {
            background-color: var(--accent-blue);
            cursor: pointer;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .action-button:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .action-button i {
            font-size: 1.6rem;
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        /* Style for selected payment method */
        .action-button.selected {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: 2px solid #45a049;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        
        .action-button.selected:hover {
            background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
        }
        
        /* Disabled state for payment button */
        #processPaymentBtn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .action-button span {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            padding: 0 5px;
            letter-spacing: 0.5px;
        }
        
        .blue-btn {
            background-color: var(--accent-blue);
        }
        
        .green-btn {
            background-color: var(--accent-green);
        }
        
        .red-btn {
            background-color: var(--accent-red);
        }
        
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .logo {
            max-width: 150px;
            opacity: 0.7;
        }
        
        /* Search Results Dropdown */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: var(--light-bg);
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        
        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
        }
        
        .search-result-item:hover {
            background-color: var(--med-bg);
        }
        
        .search-result-item .product-code {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .search-result-item .product-price {
            color: var(--accent-green);
            font-weight: bold;
        }
        
        /* Logout confirmation dialog */
        .logout-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        
        .logout-content {
            background-color: var(--med-bg);
            padding: 2rem;
            border-radius: 8px;
            width: 400px;
            text-align: center;
        }
        
        .logout-content h3 {
            margin-bottom: 1rem;
        }
        
        .logout-timer {
            font-size: 2rem;
            margin: 1rem 0;
            color: var(--accent-red);
        }
        
        .logout-buttons {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-control .btn {
            padding: 0.2rem 0.5rem;
            margin: 0 0.2rem;
        }

        .quantity-control .btn i {
            font-size: 0.8rem;
        }

        .spinner {
            margin-bottom: 1rem;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive design for different screen sizes */
        /* Fix logo container to ensure it doesn't take too much space */
        .logo-container {
            padding: 0.5rem;
            margin-top: auto;
            flex-shrink: 0;
        }
        
        /* Adjust grid to make buttons more consistent */
        .action-buttons {
            grid-template-rows: repeat(11, 1fr);
        }
        
        .modal-content {
            background-color: var(--dark-bg);
            color: #fff;
            border: 1px solid var(--light-bg);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--light-bg);
        }
        
        .modal-footer {
            border-top: 1px solid var(--light-bg);
        }
        
        .form-control {
            background-color: var(--med-bg);
            border: 1px solid var(--light-bg);
            color: #fff;
        }
        
        .form-control:focus {
            background-color: var(--med-bg);
            color: #fff;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Responsive design for different screen sizes */
        @media (max-width: 1366px) {
            .main-content {
                flex: 0 0 300px;
            }
            
            .action-button {
                min-height: 65px;
                padding: 6px 4px;
            }
            
            .action-button i {
                font-size: 1.4rem;
                margin-bottom: 6px;
            }
            
            .action-button span {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 1200px) {
            .main-content {
                flex: 0 0 280px;
            }
            
            .action-buttons {
                gap: 4px;
                padding: 4px;
            }
            
            .action-button {
                min-height: 60px;
            }
            
            .action-button i {
                font-size: 1.3rem;
                margin-bottom: 5px;
            }
            
            .action-button span {
                font-size: 0.8rem;
                letter-spacing: 0;
            }
        }
        
        @media (max-width: 992px) {
            .header {
                padding: 0.4rem 0.75rem;
            }
            
            .main-content {
                flex: 0 0 240px;
            }
            
            .action-buttons {
                gap: 3px;
                padding: 3px;
            }
            
            .action-button {
                min-height: 55px;
                padding: 5px 3px;
                border-radius: 4px;
            }
            
            .action-button i {
                font-size: 1.2rem;
                margin-bottom: 4px;
            }
            
            .cart-header-item {
                padding: 0.4rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .search-container {
                width: 40%;
            }
            
            .cart-header-item {
                font-size: 0.8rem;
                padding: 0.3rem;
            }
            
            .main-content {
                flex: 0 0 200px;
            }
            
            .action-button span {
                font-size: 0.75rem;
                font-weight: 600;
            }
            
            .logo {
                max-width: 100px;
            }
        }
        
        @media (max-height: 768px) {
            .action-button {
                min-height: 50px;
                padding: 4px 2px;
            }
            
            .action-button i {
                font-size: 1.1rem;
                margin-bottom: 3px;
            }
            
            .cart-totals {
                padding: 0.5rem;
            }
            
            .cart-total-row {
                padding: 0.1rem 0;
            }
            
            .logo-container {
                padding: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation - Only visible to Admin and Manager -->
    <?php if (in_array(strtolower($user_role), ['admin', 'manager'])): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">POS - <?php echo ucfirst($user_role); ?> <?php echo htmlspecialchars($user_fullname); ?></div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="sidebar-section">
            <a href="management/dashboard.php" class="sidebar-section-title" style="text-decoration: none; color: inherit; cursor: pointer;">
                <i class="fas fa-cog"></i>
                <span>Management</span>
            </a>
            <div class="sidebar-menu">
                <a href="sales.php" class="sidebar-menu-item">
                    <i class="fas fa-history"></i>
                    <span>View sales history</span>
                </a>
                <a href="open_sales.php" class="sidebar-menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>View open sales</span>
                </a>
                <a href="transfer.php" class="sidebar-menu-item">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transfer</span>
                </a>
                <a href="cash_operations.php" class="sidebar-menu-item">
                    <i class="fas fa-money-bill-alt"></i>
                    <span>Cash In / Out</span>
                </a>
                <a href="credit-payments.php" class="sidebar-menu-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit payments</span>
                </a>
                <a href="end-of-day.php" class="sidebar-menu-item">
                    <i class="fas fa-door-closed"></i>
                    <span>End of day</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fas fa-user"></i>
                <span>User</span>
            </div>
            <div class="sidebar-menu">
                <a href="user-info.php" class="sidebar-menu-item">
                    <i class="fas fa-id-card"></i>
                    <span>User info</span>
                </a>
                <a href="logout.php" class="sidebar-menu-item" id="signOutBtn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sign out</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-menu">
                <a href="feedback.php" class="sidebar-menu-item">
                    <i class="fas fa-comment"></i>
                    <span>Feedback</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="date"><?php echo date('Y-m-d'); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!in_array(strtolower($user_role), ['admin', 'manager'])): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">POS - <?php echo ucfirst($user_role); ?> <?php echo htmlspecialchars($user_fullname); ?></div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-menu">
                <a href="/MTECH%20UGANDA/public/feedback.php" class="sidebar-menu-item">
                    <i class="fas fa-comment"></i>
                    <span>Feedback</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            &copy; <?php echo date('Y'); ?> MTECH UGANDA
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="status-message" id="statusMessage">Processing...</div>
    </div>
    
    <!-- Logout Confirmation Dialog -->
    <div class="logout-dialog" id="logoutDialog">
        <div class="logout-content">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to log out?</p>
            <p>You will be automatically be logged out in:</p>
            <div class="logout-timer" id="logoutTimer">5</div>
            <div class="logout-buttons">
                <button class="btn btn-secondary" id="stayLoggedInBtn">Stay Logged In</button>
                <button class="btn btn-danger" id="confirmLogoutBtn">Confirm Logout</button>
            </div>
        </div>
    </div>

    <!-- Shutdown Confirmation Dialog -->
    <div class="logout-dialog" id="shutdownDialog">
        <div class="logout-content">
            <h3>Confirm Shutdown</h3>
            <p>Are you sure you want to shutdown the system?</p>
            <p>You will be redirected to the startup page in:</p>
            <div class="logout-timer" id="shutdownTimer">5</div>
            <div class="logout-buttons">
                <button class="btn btn-secondary" id="cancelShutdownBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmShutdownBtn">Confirm Shutdown</button>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="d-flex align-items-center">
            <span class="mr-3"><i class="fas fa-store"></i> <?php echo SITE_NAME; ?></span>
            <span class="badge badge-info">User: <?php echo htmlspecialchars($user_fullname); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
        </div>
        
        <div class="search-container" id="searchContainer">
            <input type="text" class="search-box" id="searchBox" placeholder="Search products by name, barcode or code...">
            <i class="fas fa-search search-icon"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
        
        <div>
            <span id="currentDateTime"></span>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Cart Container -->
        <div class="cart-container">
            <!-- Cart Header -->
            <div class="cart-header">
                <div class="cart-header-item">Product name</div>
                <div class="cart-header-item">Quantity</div>
                <div class="cart-header-item">Price</div>
                <div class="cart-header-item">Amount</div>
            </div>
            
            <!-- Cart Items -->
            <div class="cart-items" id="cartItems">
                <div class="empty-cart" id="emptyCart">
                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                    <h4>No items</h4>
                    <p>Add products to receipt using barcode, code or search by pressing F3 button</p>
                </div>
            </div>
            
            <!-- Cart Totals -->
            <div class="cart-totals">
                <div class="cart-total-row">
                    <div>Subtotal</div>
                    <div class="cart-total-value" id="subtotal">0.00</div>
                </div>
                <div class="cart-total-row">
                    <div>Tax</div>
                    <div class="cart-total-value" id="tax">0.00</div>
                </div>
                <div class="cart-total-row">
                    <div><strong>Total</strong></div>
                    <div class="cart-total-value" id="total">0.00</div>
                </div>
            </div>
        </div>
        
        <!-- Main Content / Function Buttons -->
        <div class="main-content">
            <div class="action-buttons">
                <a href="management/dashboard.php" class="action-button" title="Management">
                    <i class="fas fa-cog"></i>
                    <span>Management</span>
                </a>
                <button class="action-button" title="Delete">
                    <i class="fas fa-times"></i>
                    <span>Delete</span>
                </button>

                <button class="action-button" id="quantityBtn" title="Set Quantity">
                    <i class="fas fa-calculator"></i>
                    <span>Set Qty</span>
                </button>
                
                <!-- Quantity Input Modal -->
                <div class="modal fade" id="quantityModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Set Quantity</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="quantityInput" class="form-label">Enter Quantity:</label>
                                    <input type="number" class="form-control" id="quantityInput" min="1" value="1" autofocus>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmQuantity">Update</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="action-button" title="Cash" id="cashMethodBtn">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Cash</span>
                </button>
                <button class="action-button" title="Transfer">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transfer</span>
                </button>
                <button class="action-button" title="Credit Card">
                    <i class="far fa-credit-card"></i>
                    <span>Credit Card</span>
                </button>
                <button class="action-button" title="Debit Card">
                    <i class="fas fa-credit-card"></i>
                    <span>Debit Card</span>
                </button>
                <button class="action-button" title="Check">
                    <i class="fas fa-money-check"></i>
                    <span>Check</span>
                </button>
                <button class="action-button" title="Voucher">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Voucher</span>
                </button>
                <button class="action-button" title="Gift Card">
                    <i class="fas fa-gift"></i>
                    <span>Gift Card</span>
                </button>
                <button class="action-button" title="Cash drawer">
                    <i class="fas fa-cash-register"></i>
                    <span>Cash drawer</span>
                </button>
                <button class="action-button" title="Discount">
                    <i class="fas fa-percent"></i>
                    <span>Discount</span>
                </button>
                <button class="action-button" title="Comment">
                    <i class="far fa-comment-alt"></i>
                    <span>Comment</span>
                </button>
                <a href="management/customers.php" class="action-button" title="Customer">
                    <i class="fas fa-user"></i>
                    <span>Customer</span>
                </a>
                
                <button class="action-button blue-btn" title="Save sale" id="saveSaleBtn">
                    <i class="fas fa-save"></i>
                    <span>Save sale</span>
                </button>
                <button class="action-button blue-btn" title="Refund">
                    <i class="fas fa-undo"></i>
                    <span>Refund</span>
                </button>
                <button class="action-button green-btn" title="Payment" id="processPaymentBtn" disabled>
                    <i class="fas fa-dollar-sign"></i>
                    <span>Payment</span>
                </button>
                <button class="action-button" title="Lock">
                    <i class="fas fa-lock"></i>
                    <span>Lock</span>
                </button>
                <button class="action-button" title="Transfer">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transfer</span>
                </button>
                <button class="action-button red-btn" id="logoutBtn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Shutdown</span>
                </button>
            </div>
            
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="logo" onerror="this.style.display='none'">
            </div>
        </div>
    </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Email Notifications -->
    <script src="/MTECH%20UGANDA/public/js/email-notifications.js"></script>
    
    <script>
        // Global variables
        let cart = [];
        let sidebarCollapsed = false;
        let products = <?php echo json_encode($products ?? []); ?>;
        let selectedPaymentMethod = null; // Track selected payment method
        let tablesVerified = false; // Track if required tables have been verified
        
        // Show alert function
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.role = 'alert';
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(alertDiv)) {
                        document.body.removeChild(alertDiv);
                    }
                }, 300);
            }, 5000);
        }
        
        // Save sale function
        async function saveSale(paymentMethod = 'cash') {
            const saveBtn = document.getElementById('saveSaleBtn');
            const originalBtnText = saveBtn.innerHTML;
            
            // Log sale attempt
            try {
                const response = await fetch('ajax/log_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action_type=sale_attempt&details=${encodeURIComponent('Attempting to process a new sale')}`
                });
            } catch (e) {
                console.error('Error logging sale attempt:', e);
            }
            
            if (cart.length === 0) {
                showAlert('warning', 'Cannot save an empty cart');
                return;
            }
            
            if (!paymentMethod) {
                showAlert('warning', 'Please select a payment method first');
                return;
            }
            
            // Disable buttons and show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'savingOverlay';
            loadingOverlay.style.position = 'fixed';
            loadingOverlay.style.top = '0';
            loadingOverlay.style.left = '0';
            loadingOverlay.style.width = '100%';
            loadingOverlay.style.height = '100%';
            loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.flexDirection = 'column';
            loadingOverlay.style.justifyContent = 'center';
            loadingOverlay.style.alignItems = 'center';
            loadingOverlay.style.zIndex = '9999';
            loadingOverlay.style.color = 'white';
            loadingOverlay.style.fontSize = '1.5rem';
            loadingOverlay.innerHTML = `
                <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div>Processing your sale...</div>
                <div class="mt-2" style="font-size: 1rem; opacity: 0.8;">Please wait</div>
            `;
            document.body.appendChild(loadingOverlay);
            
            // Disable buttons during save
            const buttons = document.querySelectorAll('#saveSaleBtn, #processPaymentBtn');
            buttons.forEach(btn => {
                btn.disabled = true;
            });
            
            try {
                // Calculate totals
                const subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.price) * parseFloat(item.quantity)), 0);
                const taxRate = 0.18; // Default tax rate of 18%
                const taxAmount = subtotal * taxRate;
                const totalAmount = subtotal + taxAmount;
                
                // Get selected customer ID if any (you'll need to add this to your UI)
                const customerId = document.getElementById('customerSelect')?.value || null;
                
                // Prepare the sale data
                const saleData = {
                    customer_id: customerId,
                    user_id: <?php echo $_SESSION['user_id']; ?>,
                    items: cart.map(item => ({
                        product_id: parseInt(item.id),
                        quantity: parseFloat(item.quantity),
                        unit_price: parseFloat(item.price),
                        tax_rate_id: 1, // Default tax rate ID
                        tax_amount: parseFloat(item.price) * parseFloat(item.quantity) * taxRate,
                        discount_amount: 0, // Can be updated if you have discounts
                        subtotal: parseFloat(item.price) * parseFloat(item.quantity)
                    })),
                    payment_type: paymentMethod,
                    total_amount: totalAmount,
                    tax_amount: taxAmount,
                    discount_amount: 0, // Can be updated if you have discounts
                    notes: document.getElementById('saleNotes')?.value || ''
                };
                
                console.log('Sending sale data:', saleData);
                
                // Send the data to the server
                const response = await fetch('management/ajax/save_sale.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(saleData)
                });
                
                // Get the raw response text first for debugging
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', e);
                    throw new Error('Invalid response from server. Please check the console for details.');
                }
                
                if (!response.ok || !result.success) {
                    const errorMessage = result.message || 'Failed to save sale';
                    console.error('Server error:', result);
                    throw new Error(errorMessage);
                }
                
                // Clear the cart on success
                cart = [];
                updateCartDisplay();
                updateTotals(0, 0, 0);
                
                // Reset payment method selection
                selectedPaymentMethod = null;
                $('.action-button.selected').removeClass('selected');
                $('.payment-method').removeClass('selected');
                $('#processPaymentBtn').prop('disabled', true);
                
                // Get invoice number and show success message
                const invoiceNumber = result.data?.invoice_number || '';
                const saleId = result.data?.sale_id || '';
                const message = `Sale #${invoiceNumber} processed successfully!`;
                
                // Log successful sale
                try {
                    const response = await fetch('ajax/log_activity.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action_type=sale_completed&entity_type=sale&entity_id=${saleId}&details=${encodeURIComponent('Completed sale with ' + cart.length + ' items')}`
                    });
                } catch (e) {
                    console.error('Error logging sale completion:', e);
                }
                
                // Show success message
                showAlert('success', message);
                
                // Reset the form
                document.getElementById('saleForm')?.reset();
                
                // Close any open modal
                if ($('#paymentModal').length) {
                    $('#paymentModal').modal('hide');
                }
                
                // Print receipt if needed (cash payment)
                if (paymentMethod === 'cash' && invoiceNumber) {
                    console.log('Would print receipt for invoice:', invoiceNumber);
                    // Open receipt in new window
                    window.open(`print_receipt.php?invoice_number=${invoiceNumber}`, '_blank');
                }
                
                // Refresh the page to update stock levels
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            } catch (error) {
                console.error('Error saving sale:', error);
                let errorMessage = 'An error occurred while processing the sale';
                
                // Provide more specific error messages
                if (error.message.includes('payment_method')) {
                    errorMessage = 'Invalid payment method selected';
                } else if (error.message.includes('stock')) {
                    errorMessage = 'Insufficient stock for one or more items';
                } else if (error.message.includes('database')) {
                    errorMessage = 'Database error. Please try again or contact support';
                } else {
                    errorMessage = error.message || errorMessage;
                }
                
                showAlert('danger', errorMessage);
            } finally {
                // Hide loading overlay
                const overlay = document.getElementById('savingOverlay');
                if (overlay && overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                
                // Re-enable buttons and restore original state
                const buttons = document.querySelectorAll('#saveSaleBtn, #processPaymentBtn');
                buttons.forEach(btn => {
                    btn.disabled = false;
                });
                
                // Reset button state
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnText;
            }
        }
        
        // Initialize stock tracking
        function initializeStockTracking() {
            // Add stock information to search results
            $('.search-result-item').each(function() {
                const productId = $(this).data('product-id');
                const product = products.find(p => p.id == productId);
                if (product) {
                    const stockStatus = product.stock > 0 ? 
                        `<span class="text-success">In Stock (${product.stock})</span>` : 
                        `<span class="text-danger">Out of Stock</span>`;
                    $(this).append(`<div class="stock-status">${stockStatus}</div>`);
                }
            });
        }
        let taxRates = <?php echo json_encode($tax_rates ?? []); ?>;
        let selectedCartItemIndex = -1; // Track selected cart item
        
        // Verify required tables exist
        async function verifyTables() {
            if (tablesVerified) return true;
            
            try {
                const response = await fetch('management/ajax/check_tables.php');
                const result = await response.json();
                
                if (result.success) {
                    tablesVerified = true;
                    return true;
                } else {
                    console.error('Table verification failed:', result);
                    return false;
                }
            } catch (error) {
                console.error('Error verifying tables:', error);
                return false;
            }
        }
        
        // Handle payment method selection
        $(document).on('click', '#cashMethodBtn', async function(e) {
            e.preventDefault();
            
            // Verify tables before proceeding
            const tablesOk = await verifyTables();
            if (!tablesOk) {
                alert('Error: Unable to verify required database tables. Please try again or contact support.');
                return;
            }
            
            // Update UI to show selected payment method
            $('.payment-method').removeClass('selected');
            $(this).addClass('selected');
            selectedPaymentMethod = 'cash';
            $('#processPaymentBtn').prop('disabled', false);
        });
        
        // Handle process payment button click
        $(document).on('click', '#processPaymentBtn', async function(e) {
            e.preventDefault();
            
            // Verify tables before proceeding
            const tablesOk = await verifyTables();
            if (!tablesOk) {
                alert('Error: Unable to verify required database tables. Please try again or contact support.');
                return;
            }
            
            if (selectedPaymentMethod === 'cash') {
                // For cash payments, just mark as paid and save
                saveSale('cash');
            }
        });
        
        // Handle save sale button click
        $(document).on('click', '#saveSaleBtn', async function(e) {
            e.preventDefault();
            
            // Verify tables before proceeding
            const tablesOk = await verifyTables();
            if (!tablesOk) {
                alert('Error: Unable to verify required database tables. Please try again or contact support.');
                return;
            }
            
            saveSale(selectedPaymentMethod);
        });
        
        // Handle cart item selection
        function selectCartItem(itemElement) {
            // Remove selected class from all items
            $('.cart-item').removeClass('selected');
            // Add selected class to clicked item
            $(itemElement).addClass('selected');
        }

        $(document).ready(function() {
            // Update date and time
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Handle cart item click
            $(document).on('click', '.cart-item', function() {
                selectCartItem(this);
            });
            
            // Handle quantity button click
            $('#quantityBtn').on('click', function() {
                const selectedItem = $('.cart-item.selected');
                if (selectedItem.length) {
                    const currentQty = parseInt(selectedItem.find('.cart-item-quantity').text());
                    $('#quantityInput').val(currentQty).select();
                    const quantityModal = new bootstrap.Modal(document.getElementById('quantityModal'));
                    quantityModal.show();
                } else if ($('.cart-item').length > 0) {
                    // If no item is selected but cart is not empty, select the first one
                    const firstItem = $('.cart-item').first();
                    selectCartItem(firstItem);
                    const currentQty = parseInt(firstItem.find('.cart-item-quantity').text());
                    $('#quantityInput').val(currentQty).select();
                    const quantityModal = new bootstrap.Modal(document.getElementById('quantityModal'));
                    quantityModal.show();
                } else {
                    alert('Please add items to cart first');
                }
            });
            
            // Handle confirm quantity button
            $('#confirmQuantity').on('click', function() {
                const selectedItem = $('.cart-item.selected');
                if (!selectedItem.length) {
                    const modalElement = document.getElementById('quantityModal');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                        modal.hide();
                    }
                    return;
                }
                
                const productCode = selectedItem.data('code');
                const currentQty = parseInt(selectedItem.find('.cart-item-quantity').text());
                const newQty = parseInt($('#quantityInput').val());
                
                if (isNaN(newQty) || newQty < 1) {
                    alert('Please enter a valid quantity');
                    return;
                }
                
                // Calculate the difference to update the quantity correctly
                const quantityDifference = newQty - currentQty;
                
                if (quantityDifference !== 0) {
                    updateQuantity(productCode, quantityDifference, event);
                }
                
                bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide();
            });
            
            // Allow pressing Enter in the quantity input to confirm
            $('#quantityInput').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    $('#confirmQuantity').click();
                }
            });
            
            // Search functionality
            $('#searchBox').on('keyup', function() {
                const query = $(this).val().toLowerCase();
                if (query.length >= 2) {
                    searchProducts(query);
                } else {
                    $('#searchResults').hide();
                }
            });
            
            // Hide search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#searchContainer').length) {
                    $('#searchResults').hide();
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // F3 for search focus
                if (e.which === 114) { // F3 key
                    e.preventDefault();
                    $('#searchBox').focus();
                }
            });
            
            // Logout button functionality with countdown
            $('#logoutBtn').on('click', function() {
                showLogoutDialog();
            });
            
            $('#stayLoggedInBtn').on('click', function() {
                hideLogoutDialog();
            });
            
            $('#confirmLogoutBtn').on('click', function() {
                logout();
            });
            
            // Sidebar toggle functionality
            $('#sidebarToggle').click(function() {
                toggleSidebar();
            });
            
            // Add event handler to Transfer button in action buttons to open sidebar
            $('button.action-button[title="Transfer"]').click(function() {
                // Ensure sidebar is fully expanded when opened via Transfer button
                if (sidebarCollapsed || $('.sidebar').hasClass('collapsed')) {
                    sidebarCollapsed = false;
                    $('.sidebar').removeClass('collapsed');
                    $('#sidebarToggle').find('i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
                    $('.sidebar-title, .sidebar-section-title span, .sidebar-menu-item span, .sidebar-footer').css('display', '');
                }
                
                // Highlight the Transfer option in the sidebar to indicate it's selected
                $('.sidebar-menu-item').removeClass('active');
                $('.sidebar-menu-item:contains("Transfer")').addClass('active');
            });
            
            // Function to toggle sidebar collapsed state
            function toggleSidebar() {
                $('.sidebar').toggleClass('collapsed');
                sidebarCollapsed = !sidebarCollapsed;
                
                if (sidebarCollapsed) {
                    // Change icon to indicate sidebar can be expanded
                    $('#sidebarToggle').find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
                    // Hide text elements when sidebar is collapsed
                    $('.sidebar-title, .sidebar-section-title span, .sidebar-menu-item span, .sidebar-footer').css('display', 'none');
                } else {
                    // Change icon to indicate sidebar can be collapsed
                    $('#sidebarToggle').find('i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
                    // Show text elements when sidebar is expanded
                    $('.sidebar-title, .sidebar-section-title span, .sidebar-menu-item span, .sidebar-footer').css('display', '');
                }
            }
            
            // Initialize cart display
            updateCartDisplay();
            
            // Add click handler for cart items
            $(document).on('click', '.cart-item', function() {
                // Remove selection from other items
                $('.cart-item').removeClass('selected');
                
                // Add selection to this item
                $(this).addClass('selected');
                
                // Store the selected item index
                selectedCartItemIndex = $(this).data('index');
            });
            
            // Add click handler for Delete button
            $('button.action-button[title="Delete"]').click(function() {
                if (selectedCartItemIndex >= 0 && selectedCartItemIndex < cart.length) {
                    // Remove the item from the cart
                    removeFromCart(selectedCartItemIndex);
                    
                    // Reset selection
                    selectedCartItemIndex = -1;
                }
            });
        });
        
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const dateTimeStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
            $('#currentDateTime').text(dateTimeStr);
        }
        
        // Search products in database
        function searchProducts(query) {
            if (query.length < 2) {
                $('#searchResults').hide();
                return;
            }
            
            // Show loading state
            $('#searchResults').html('<div class="search-result-item">Searching...</div>').show();
            
            // Make AJAX request to search endpoint
            $.ajax({
                url: 'ajax/search_products.php',
                type: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(response) {
                    let resultsHtml = '';
                    
                    if (response.success && response.results && response.results.length > 0) {
                        response.results.forEach(product => {
                            resultsHtml += `
                                <div class="search-result-item" data-id="${product.id}">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div>${product.name}</div>
                                            <div class="product-code">${product.code} (${product.category_name || 'No Category'})</div>
                                        </div>
                                        <div class="product-price">${formatCurrency(product.price)}</div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        resultsHtml = '<div class="search-result-item">No products found</div>';
                    }
                    
                    $('#searchResults').html(resultsHtml);
                    
                    // Add product to cart when clicking on search result
                    $('.search-result-item').on('click', function() {
                        const productId = $(this).data('id');
                        const product = (response.results || []).find(p => p.id == productId);
                        if (product) {
                            addToCart(product);
                            $('#searchBox').val('');
                            $('#searchResults').hide();
                        }
                    });
                },
                error: function() {
                    $('#searchResults').html('<div class="search-result-item">Error searching products</div>');
                }
            });
        }
        
        // Add product to cart
        function addToCart(product) {
            // Check if product is already in cart
            const existingItemIndex = cart.findIndex(item => item.id == product.id);
            let newQuantity = 1;
            
            // Check if we have enough stock
            if (product.stock <= 0) {
                alert('This product is out of stock!');
                return;
            }
            
            if (existingItemIndex >= 0) {
                // Increase quantity if already in cart
                newQuantity = cart[existingItemIndex].quantity + 1;
                
                // Check if we have enough stock for the new quantity
                if (newQuantity > product.stock) {
                    alert('Cannot add more items. Only ' + product.stock + ' units available in stock.');
                    return;
                }
                
                cart[existingItemIndex].quantity = newQuantity;
            } else {
                // Check if we have enough stock for the first item
                if (product.stock < 1) {
                    alert('Cannot add item. Only ' + product.stock + ' units available in stock.');
                    return;
                }
                
                // Add new item to cart
                const cartItem = {
                    id: product.id,
                    code: product.code,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1
                };
                cart.push(cartItem);
            }
            
            // Update cart display
            updateCartDisplay();
            
            // Update stock in database
            updateStock(product.id, product.stock - newQuantity);
        }

        // Function to update stock in database
        async function updateStock(productId, newStock) {
            // Get the base URL
            const baseUrl = window.location.origin + window.location.pathname;
            const apiUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/')) + '/api/update_stock.php';
            
            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${encodeURIComponent(productId)}&stock=${encodeURIComponent(newStock)}`
                });

                // First check if response is valid JSON
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', responseText);
                    throw new Error('Invalid response from server. Please check server logs.');
                }
                
                if (!response.ok || !data.success) {
                    const errorMsg = data.message || `HTTP error! status: ${response.status}`;
                    console.error('Stock update failed:', errorMsg);
                    throw new Error(errorMsg);
                }
                
                // Update the product stock in our products array
                const productIndex = products.findIndex(p => p.id == productId);
                if (productIndex >= 0) {
                    products[productIndex].stock = newStock;
                }
                
                return data;
            } catch (error) {
                console.error('Error in updateStock:', error);
                throw error; // Re-throw to allow calling code to handle the error
            }
        }
        
        // Remove item from cart
        function removeFromCart(index) {
            // Remove the item at the specified index
            cart.splice(index, 1);
            
            // Update cart display
            updateCartDisplay();
        }
        
        // Update cart display
        function updateCartDisplay() {
            if (cart.length === 0) {
                $('#emptyCart').show();
                $('#cartItems').html('');
                updateTotals(0, 0, 0);
                return;
            }
            
            $('#emptyCart').hide();
            
            let cartHtml = '';
            let subtotal = 0;
            let taxTotal = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                const taxAmount = itemTotal * ((item.tax_rate || 0) / 100);
                
                subtotal += itemTotal;
                taxTotal += taxAmount;
                
                cartHtml += `
                    <div class="cart-item" data-index="${index}">
                        <div class="cart-item-name">
                            <div class="product-name">${item.name}</div>
                            <div class="product-code">${item.code}</div>
                        </div>
                        <div class="cart-item-quantity">
                            <button class="btn btn-sm btn-dark quantity-btn" onclick="updateQuantity('${item.code}', -1, event)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button class="btn btn-sm btn-dark quantity-btn" onclick="updateQuantity('${item.code}', 1, event)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="cart-item-price">${formatCurrency(item.price)}</div>
                        <div class="cart-item-amount">${formatCurrency(itemTotal)}</div>
                    </div>`;
            });
            
            $('#cartItems').html(cartHtml);
            
            const total = subtotal + taxTotal;
            updateTotals(subtotal, taxTotal, total);
        }
        
        // Update totals display
        function updateTotals(subtotal, tax, total) {
            $('#subtotal').text(formatCurrency(subtotal));
            $('#tax').text(formatCurrency(tax));
            $('#total').text(formatCurrency(total));
        }

        // Update item quantity
        function updateQuantity(productCode, change, event) {
            // Prevent event bubbling to avoid triggering cart item selection
            if (event) {
                event.stopPropagation();
            }
            
            const itemIndex = cart.findIndex(item => item.code == productCode);
            
            if (itemIndex !== -1) {
                const product = products.find(p => p.id == cart[itemIndex].id);
                if (!product) return;
                
                const newQuantity = cart[itemIndex].quantity + change;
                
                // Check if we have enough stock for the new quantity
                if (change > 0 && newQuantity > product.stock) {
                    alert('Cannot add more items. Only ' + (product.stock - cart[itemIndex].quantity + change) + ' units available in stock.');
                    return;
                }
                
                if (newQuantity > 0) {
                    cart[itemIndex].quantity = newQuantity;
                    // Update stock in database
                    updateStock(product.id, product.stock - change);
                } else if (newQuantity === 0) {
                    // If quantity becomes zero, remove from cart and return stock
                    updateStock(product.id, product.stock + cart[itemIndex].quantity);
                    cart.splice(itemIndex, 1);
                }
                
                // Update cart display and totals
                updateCartDisplay();
                
                // Update the selected item's quantity display immediately
                const selectedItem = $(`.cart-item[data-index="${itemIndex}"]`);
                if (selectedItem.length) {
                    selectedItem.find('.quantity-display').text(newQuantity);
                    
                    // Update the item total amount
                    const price = parseFloat(cart[itemIndex].price);
                    const total = price * newQuantity;
                    selectedItem.find('.cart-item-amount').text(formatCurrency(total));
                    
                    // Update the main totals
                    const subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.price) * parseFloat(item.quantity)), 0);
                    const taxRate = 0.18; // Assuming 18% tax rate
                    const taxTotal = subtotal * taxRate;
                    updateTotals(subtotal, taxTotal, subtotal + taxTotal);
                }
            }
        }
        
        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-UG', {
                style: 'currency',
                currency: 'UGX',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        // Show logout dialog
        function showLogoutDialog() {
            $('#logoutDialog').css('display', 'flex');
            let countdown = 5;
            $('#logoutTimer').text(countdown);
            
            const timer = setInterval(function() {
                countdown--;
                $('#logoutTimer').text(countdown);
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    logout();
                }
            }, 1000);
            
            // Store timer so we can clear it if user cancels
            $('#logoutDialog').data('timer', timer);
        }
        
        // Hide logout dialog
        function hideLogoutDialog() {
            // Clear the timer
            const timer = $('#logoutDialog').data('timer');
            if (timer) clearInterval(timer);
            
            // Hide the dialog
            $('#logoutDialog').hide();
        }
        
        // Logout function
        function logout() {
            // Show loading overlay
            $('#loadingOverlay').show();
            $('#statusMessage').text('Logging out...');
            
            // Get username from the page or session
            const username = $('.user-info .username').text() || 'User';
            
            // Send logout notification
            if (typeof EmailNotifications !== 'undefined') {
                EmailNotifications.trackLogout(username);
            }
            
            // Redirect to logout page after a short delay
            setTimeout(function() {
                window.location.href = 'logout.php?username=' + encodeURIComponent(username);
            }, 1000);
        }
        
        // Show shutdown dialog
        function showShutdownDialog() {
            $('#shutdownDialog').css('display', 'flex');
            let countdown = 5;
            $('#shutdownTimer').text(countdown);
            
            const timer = setInterval(function() {
                countdown--;
                $('#shutdownTimer').text(countdown);
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    shutdown();
                }
            }, 1000);
            
            // Store timer so we can clear it if user cancels
            $('#shutdownDialog').data('timer', timer);
        }
        
        // Hide shutdown dialog
        function hideShutdownDialog() {
            // Clear the timer
            const timer = $('#shutdownDialog').data('timer');
            if (timer) clearInterval(timer);
            
            // Hide the dialog
            $('#shutdownDialog').hide();
        }
        
        // Shutdown function - redirect to startup page
        function shutdown() {
            // Show loading overlay
            $('#loadingOverlay').show();
            $('#statusMessage').text('Shutting down system...');
            
            // Clear session and redirect to startup page
            setTimeout(function() {
                window.location.href = 'startup.php';
            }, 1500);
        }
    </script>
</body>
</html>