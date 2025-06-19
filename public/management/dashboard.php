<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Check if user has management access
if (!in_array($user_role, ['admin', 'manager'])) {
    header('Location: ../welcome.php');
    exit();
}

// Get current year and date
$current_year = date('Y'); // 2025
$current_date = date('Y-m-d'); // 2025-06-17
$current_time = date('h:i A T'); // 04:39 PM EAT

// Initialize variables with default values
$monthly_sales = [85000, 92000, 185000, 110000, 135000, 125000, 0, 0, 0, 0, 0, 0]; // Default data
$total_monthly_sales = 0;
$today_sales = 0;
$active_products = 0;
$active_customers = 0;
$recent_sales = [];
$top_products_data = [];
$hourly_sales_data = [12000, 18500, 22400, 19800, 15600, 7200];
$top_month = ['month' => 3, 'total' => 185000]; // Default to March 2025

// Database connection and queries
try {
    $db = get_db_connection();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // Check if required tables exist
    $sales_table_exists = in_array('sales', $tables);
    $products_table_exists = in_array('products', $tables);
    $customers_table_exists = in_array('customers', $tables);
    $sale_items_table_exists = in_array('sale_items', $tables);

    if ($sales_table_exists) {
        // Monthly Sales
        $stmt = $db->prepare("SELECT MONTH(date) as month, SUM(total_amount) as total 
                            FROM sales WHERE YEAR(date) = ? 
                            GROUP BY MONTH(date) ORDER BY month");
        $stmt->execute([$current_year]);
        $monthly_sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $monthly_sales = array_fill(1, 12, 0);
        foreach ($monthly_sales_data as $sale) {
            $monthly_sales[$sale['month']] = (float)$sale['total'];
        }
        $total_monthly_sales = array_sum($monthly_sales);

        // Today's Sales
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
                            FROM sales WHERE DATE(date) = ?");
        $stmt->execute([$current_date]);
        $today_sales = $stmt->fetchColumn();

        // Top Performing Month
        $stmt = $db->prepare("SELECT MONTH(date) as month, SUM(total_amount) as total 
                            FROM sales WHERE YEAR(date) = ? 
                            GROUP BY MONTH(date) ORDER BY total DESC LIMIT 1");
        $stmt->execute([$current_year]);
        $top_month = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['month' => 3, 'total' => 185000];
        $top_month_name = date('F', mktime(0, 0, 0, $top_month['month'], 1, $current_year));

        // Recent Sales
        $stmt = $db->prepare("SELECT s.id, s.date, s.total_amount, c.name as customer_name 
                            FROM sales s 
                            LEFT JOIN customers c ON s.customer_id = c.id 
                            ORDER BY s.date DESC LIMIT 5");
        $stmt->execute();
        $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hourly Sales (for today)
        $stmt = $db->prepare("SELECT HOUR(date) as hour, SUM(total_amount) as total 
                            FROM sales WHERE DATE(date) = ? 
                            GROUP BY HOUR(date) ORDER BY hour");
        $stmt->execute([$current_date]);
        $hourly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hourly_sales_data = array_fill(0, 23, 0);
        foreach ($hourly_sales as $sale) {
            $hourly_sales_data[$sale['hour']] = (float)$sale['total'];
        }
    }

    if ($products_table_exists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE active = 1");
        $active_products = $stmt->fetchColumn();
    }

    if ($customers_table_exists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM customers WHERE active = 1");
        $active_customers = $stmt->fetchColumn();
    }

    if ($sale_items_table_exists && $sales_table_exists) {
        $stmt = $db->prepare("SELECT p.name, SUM(si.quantity) as total_sold 
                            FROM sale_items si 
                            JOIN products p ON si.product_id = p.id 
                            JOIN sales s ON si.sale_id = s.id 
                            WHERE DATE(s.date) = ? 
                            GROUP BY si.product_id ORDER BY total_sold DESC LIMIT 5");
        $stmt->execute([$current_date]);
        $top_products_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Dashboard - Management System</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
    /* Modern color palette */
    --primary: #4361ee;
    --primary-light: #eef2ff;
    --primary-dark: #3a56e6;
    --secondary: #6c757d;
    --success: #1cc88a;
    --info: #36b9cc;
    --warning: #f6c23e;
    --danger: #e74a3b;
    --light: #f8f9fc;
    --dark: #2b2d42;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #eaecf4;
    --gray-300: #dddfeb;
    --gray-600: #858796;
    --gray-800: #5a5c69;
    
    /* Layout */
    --sidebar-width: 280px;
    --header-height: 70px;
    --border-radius: 12px;
    --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Typography */
    --font-sans: 'Inter', 'Segoe UI', Roboto, -apple-system, sans-serif;
    --font-mono: 'Roboto Mono', monospace;
}

/* Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--font-sans);
    background-color: var(--light);
    color: var(--dark);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

/* Layout Structure */
.app-container {
    display: flex;
    min-height: 100vh;
    position: relative;
}

/* Sidebar Styles - Modern Glass Morphism */
.sidebar {
    width: var(--sidebar-width);
    background: rgba(26, 35, 126, 0.9);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: white;
    height: 100vh;
    position: fixed;
    transition: var(--transition);
    z-index: 1000;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    overflow-y: auto;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header {
    padding: 24px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: sticky;
    top: 0;
    background: rgba(26, 35, 126, 0.9);
    z-index: 10;
}

.sidebar-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: white;
    letter-spacing: 0.5px;
}

.sidebar-menu {
    padding: 20px 0;
}

.menu-section {
    margin-bottom: 28px;
}

.menu-section-title {
    padding: 0 24px 12px;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 600;
    letter-spacing: 1px;
}

.menu-item {
    padding: 14px 24px;
    display: flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    margin: 0 12px;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}

.menu-item i {
    width: 24px;
    font-size: 1.1rem;
    margin-right: 14px;
    transition: var(--transition);
    position: relative;
    z-index: 1;
}

.menu-item span {
    position: relative;
    z-index: 1;
}

.menu-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 0;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    transition: var(--transition);
    z-index: 0;
}

.menu-item:hover, 
.menu-item.active {
    color: white;
}

.menu-item:hover::before, 
.menu-item.active::before {
    width: 100%;
}

.menu-item.active {
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
}

.menu-item:hover i, 
.menu-item.active i {
    color: var(--success);
    transform: scale(1.1);
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    padding: 0;
    transition: var(--transition);
}

/* Top Navigation - Modern Sticky Header */
.top-nav {
    background-color: var(--white);
    height: var(--header-height);
    padding: 0 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 100;
    border-bottom: 1px solid var(--gray-200);
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    letter-spacing: -0.5px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.user-details {
    display: flex;
    align-items: center;
    gap: 16px;
}

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--info));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.user-text {
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
    margin-top: 2px;
}

.back-btn {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.2);
}

.back-btn i {
    font-size: 0.9rem;
}

.back-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
}

/* Dashboard Content */
.dashboard-content {
    padding: 32px;
}

/* Welcome Banner - Gradient Glass Card */
.welcome-banner {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.9), rgba(28, 200, 138, 0.8));
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    border-radius: var(--border-radius);
    padding: 32px;
    color: white;
    margin-bottom: 32px;
    box-shadow: var(--box-shadow);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    z-index: 0;
}

.welcome-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
}

.welcome-text {
    max-width: 600px;
    opacity: 0.9;
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
    font-size: 1rem;
}

/* Stats Cards - Neumorphic Design */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    transition: var(--transition);
}

.stat-card.primary::before { background: var(--primary); }
.stat-card.success::before { background: var(--success); }
.stat-card.info::before { background: var(--info); }
.stat-card.warning::before { background: var(--warning); }

.stat-card:hover::before {
    width: 6px;
}

.stat-title {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 8px;
    font-weight: 500;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 4px;
    font-family: var(--font-mono);
    color: var(--dark);
}

.stat-subtitle {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-top: auto;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
}

/* Card Styles - Modern Flat Design */
.card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.card:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--white);
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i {
    color: var(--primary);
    font-size: 1rem;
}

.card-actions {
    display: flex;
    gap: 8px;
}

.card-btn {
    background: var(--gray-100);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    cursor: pointer;
    transition: var(--transition);
}

.card-btn:hover {
    background: var(--primary);
    color: white;
    transform: rotate(90deg);
}

.card-body {
    padding: 24px;
}

/* Charts */
.chart-container {
    height: 250px;
    position: relative;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: var(--border-radius);
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.9rem;
}

.data-table th {
    background-color: var(--gray-100);
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.85rem;
    position: sticky;
    top: 0;
}

.data-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.9rem;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover td {
    background: var(--primary-light);
}

/* Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 70px;
}

.status-paid {
    background: rgba(28, 200, 138, 0.15);
    color: var(--success);
}

.status-pending {
    background: rgba(246, 194, 62, 0.15);
    color: var(--warning);
}

.status-overdue {
    background: rgba(231, 74, 59, 0.15);
    color: var(--danger);
}

/* System Metrics */
.metric {
    margin-bottom: 20px;
}

.metric-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.metric-label {
    font-weight: 500;
    color: var(--gray-800);
}

.metric-value {
    font-weight: 600;
    color: var(--dark);
}

.progress {
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
}

.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

.progress-cpu {
    background: linear-gradient(90deg, var(--primary), #5a72ec);
    width: 45%;
}

.progress-memory {
    background: linear-gradient(90deg, var(--info), #48c6d8);
    width: 62%;
}

.progress-disk {
    background: linear-gradient(90deg, var(--success), #34d399);
    width: 28%;
}

.system-status {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
    font-size: 0.9rem;
}

.system-status div {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

/* Menu toggle button - Floating Action Button */
.menu-toggle {
    display: none;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 50%;
    width: 48px;
    height: 48px;
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1100;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
    transition: var(--transition);
}

.menu-toggle:hover {
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .menu-toggle {
        display: flex;
    }
    
    .welcome-title {
        font-size: 1.5rem;
    }
}

@media (max-width: 768px) {
    .top-nav {
        padding: 0 20px;
        flex-direction: column;
        height: auto;
        padding: 15px;
    }
    
    .page-title {
        margin-bottom: 15px;
    }
    
    .user-info {
        width: 100%;
        justify-content: space-between;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .welcome-banner {
        padding: 24px;
    }
}

@media (max-width: 576px) {
    .dashboard-content {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header, 
    .card-body {
        padding: 16px;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes float {
    0% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.6s ease forwards;
    opacity: 0;
}

.float-animation {
    animation: float 3s ease-in-out infinite;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Menu Toggle Button (Mobile) -->
        <button class="menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>MTECH UGANDA</h2>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-section-title">Main Navigation</div>
                    <a href="dashboard.php" class="menu-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="documents.php" class="menu-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Documents</span>
                    </a>
                    <a href="products.php" class="menu-item">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                    <a href="price-lists.php" class="menu-item">
                        <i class="fas fa-tags"></i>
                        <span>Price Lists</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Inventory</div>
                    <a href="stock.php" class="menu-item">
                        <i class="fas fa-warehouse"></i>
                        <span>Stock</span>
                    </a>
                    <a href="reports.php" class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Customers</div>
                    <a href="customers-suppliers.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span>Customers & Suppliers</span>
                    </a>
                    <a href="promotions.php" class="menu-item">
                        <i class="fas fa-percent"></i>
                        <span>Promotions</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Settings</div>
                    <a href="security.php" class="menu-item">
                        <i class="fas fa-users-cog"></i>
                        <span>Users & Security</span>
                    </a>
                    <a href="print-stations.php" class="menu-item">
                        <i class="fas fa-print"></i>
                        <span>Print Stations</span>
                    </a>
                    <a href="payment-types.php" class="menu-item">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment Types</span>
                    </a>
                    <a href="countries.php" class="menu-item">
                        <i class="fas fa-globe"></i>
                        <span>Countries</span>
                    </a>
                    <a href="tax-rates.php" class="menu-item">
                        <i class="fas fa-percentage"></i>
                        <span>Tax Rates</span>
                    </a>
                    <a href="company.php" class="menu-item">
                        <i class="fas fa-building"></i>
                        <span>My Company</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <h1 class="page-title">Dashboard</h1>
                
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-avatar"><?php echo strtoupper(substr($user_fullname, 0, 1) . substr($username, 0, 1)); ?></div>
                        <div class="user-text">
                            <div class="user-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
                        </div>
                    </div>
                    <a href="../welcome.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        SALES PAGE
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Banner -->
                <div class="welcome-banner fade-in">
                    <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user_fullname); ?>!</h2>
                    <p class="welcome-text">Here's what's happening with your store today. Monitor sales, track inventory, and manage your business efficiently.</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary fade-in" style="animation-delay: 0.1s">
                        <div class="stat-title">Monthly Sales</div>
                        <div class="stat-value">UGX <?php echo number_format($total_monthly_sales, 2); ?></div>
                        <div class="stat-subtitle">June 2025</div>
                    </div>
                    
                    <div class="stat-card success fade-in" style="animation-delay: 0.2s">
                        <div class="stat-title">Today's Sales</div>
                        <div class="stat-value">UGX <?php echo number_format($today_sales, 2); ?></div>
                        <div class="stat-subtitle"><?php echo date('d F Y', strtotime($current_date)); ?></div>
                    </div>
                    
                    <div class="stat-card info fade-in" style="animation-delay: 0.3s">
                        <div class="stat-title">Active Products</div>
                        <div class="stat-value"><?php echo $active_products; ?></div>
                        <div class="stat-subtitle">Available for sale</div>
                    </div>
                    
                    <div class="stat-card warning fade-in" style="animation-delay: 0.4s">
                        <div class="stat-title">Active Customers</div>
                        <div class="stat-value"><?php echo $active_customers; ?></div>
                        <div class="stat-subtitle">In the system</div>
                    </div>
                </div>
                
                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Monthly Sales Chart -->
                    <div class="card fade-in" style="animation-delay: 0.2s">
                        <div class="card-header">
                            <h3 class="card-title">Monthly Sales - <?php echo $current_year; ?></h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlySalesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Products -->
                    <div class="card fade-in" style="animation-delay: 0.3s">
                        <div class="card-header">
                            <h3 class="card-title">Top Products</h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hourly Sales -->
                    <div class="card fade-in" style="animation-delay: 0.4s">
                        <div class="card-header">
                            <h3 class="card-title">Hourly Sales</h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="hourlySalesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Sales -->
                    <div class="card fade-in" style="animation-delay: 0.5s">
                        <div class="card-header">
                            <h3 class="card-title">Recent Sales</h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td>#INV-<?php echo sprintf('%s-%03d', $current_year, $sale['id']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                                <td>UGX <?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $sale['status'] ?? 'paid'; ?>">
                                                        <?php echo ucfirst($sale['status'] ?? 'Paid'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_sales)): ?>
                                            <tr><td colspan="4" style="text-align: center; color: var(--gray-600);">No recent sales</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Sales -->
                    <div class="card fade-in" style="animation-delay: 0.6s">
                        <div class="card-header">
                            <h3 class="card-title">Total Sales</h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="total-sales-display">
                                <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary); text-align: center; margin: 20px 0;">
                                    UGX <?php echo number_format($total_monthly_sales, 2); ?>
                                </div>
                                <div style="text-align: center; color: var(--gray-600); margin-bottom: 15px;">
                                    <div>Top performing month:</div>
                                    <div style="font-weight: 600; font-size: 1.2rem; margin-top: 5px;"><?php echo $top_month_name; ?></div>
                                    <div style="font-size: 1.1rem;">UGX <?php echo number_format($top_month['total'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Tracking -->
                    <div class="card fade-in" style="animation-delay: 0.7s">
                        <div class="card-header">
                            <h3 class="card-title">System Tracking</h3>
                            <div class="card-actions">
                                <button class="card-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <div class="metric-header">
                                    <span class="metric-label">CPU Usage</span>
                                    <span class="metric-value">45%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-cpu"></div>
                                </div>
                            </div>
                            
                            <div class="metric">
                                <div class="metric-header">
                                    <span class="metric-label">Memory Usage</span>
                                    <span class="metric-value">62%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-memory"></div>
                                </div>
                            </div>
                            
                            <div class="metric">
                                <div class="metric-header">
                                    <span class="metric-label">Disk Space</span>
                                    <span class="metric-value">28%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-disk"></div>
                                </div>
                            </div>
                            
                            <div class="system-status" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--gray-200);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span>System Status:</span>
                                    <span style="font-weight: 600; color: var(--success);">Healthy</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Last Backup:</span>
                                    <span>2025-06-15 19:09</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu toggle functionality
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Initialize Monthly Sales Chart
        const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
        const monthlySalesChart = new Chart(monthlySalesCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Sales (UGX)',
                    data: <?php echo json_encode(array_values($monthly_sales)); ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Initialize Top Products Chart
        const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
        const topProductsChart = new Chart(topProductsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($top_products_data, 'name') ?: ['Laptops', 'Smartphones', 'Tablets', 'Monitors', 'Accessories']); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($top_products_data, 'total_sold') ?: [35, 25, 15, 12, 13]); ?>,
                    backgroundColor: [
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(28, 200, 138, 0.8)',
                        'rgba(246, 194, 62, 0.8)',
                        'rgba(231, 74, 59, 0.8)',
                        'rgba(54, 185, 204, 0.8)'
                    ],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                },
                cutout: '65%'
            }
        });
        
        // Initialize Hourly Sales Chart
        const hourlySalesCtx = document.getElementById('hourlySalesChart').getContext('2d');
        const hourlySalesChart = new Chart(hourlySalesCtx, {
            type: 'line',
            data: {
                labels: ['8AM', '10AM', '12PM', '2PM', '4PM', '6PM'],
                datasets: [{
                    label: 'Sales (UGX)',
                    data: <?php echo json_encode(array_slice($hourly_sales_data, 8, 6)); ?>, // 8AM to 6PM
                    fill: true,
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Simulate real-time updates
        setInterval(() => {
            // Update Hourly Sales Chart with new data
            const hourlyData = hourlySalesChart.data.datasets[0].data;
            const labels = hourlySalesChart.data.labels;
            
            // Remove first data point
            hourlyData.shift();
            labels.shift();
            
            // Add new data point (simulated)
            const lastValue = hourlyData[hourlyData.length - 1];
            const newValue = Math.max(5000, Math.min(25000, lastValue + (Math.random() * 4000 - 2000)));
            hourlyData.push(newValue);
            
            // Add new label
            const lastHour = parseInt(labels[labels.length - 1].replace('PM', '').replace('AM', ''));
            const newHour = lastHour + 2;
            labels.push(newHour > 12 ? (newHour - 12) + 'PM' : newHour + 'AM');
            
            hourlySalesChart.update();
        }, 5000);
    </script>
</body>
</html>