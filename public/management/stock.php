<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
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
    // Redirect to welcome page if not authorized
    header('Location: ../welcome.php');
    exit();
}

// Initialize variables
$products = [];
$error_message = '';
$success_message = '';
$low_stock_items = [];
$recent_movements = [];
$stock_summary = [
    'total_products' => 0,
    'total_quantity' => 0,
    'total_value' => 0,
    'low_stock_count' => 0
];

// Get current date for filtering
$current_date = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

// Initialize database connection
$db = get_db_connection();

// Check if required tables exist
$stock_movements_table_exists = false;
try {
    $db->query("SELECT 1 FROM stock_movements LIMIT 1");
    $stock_movements_table_exists = true;
} catch (PDOException $e) {
    // Table doesn't exist or can't be accessed
    $stock_movements_table_exists = false;
    $tables = ['products', 'categories', 'stock_movements'];
    $tables_exist = true;
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $tables_exist = false;
            break;
        }
    }
    
    if (!$tables_exist) {
        throw new Exception("Required database tables are missing. Please run the database setup.");
    }
    
    // Get stock summary
    $stmt = $db->query("SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(p.stock_quantity), 0) as total_quantity,
        COALESCE(SUM(p.stock_quantity * p.purchase_price), 0) as total_value,
        SUM(CASE WHEN p.stock_quantity <= p.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count
        FROM products p");
    $stock_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get low stock items
    $stmt = $db->prepare("SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.stock_quantity <= p.low_stock_threshold 
        ORDER BY p.stock_quantity ASC 
        LIMIT 10");
    $stmt->execute();
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent stock movements
    $stmt = $db->prepare("SELECT sm.*, p.name as product_name, p.code as product_code,
        u.username as user_name, p.stock_quantity as current_stock
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        LEFT JOIN users u ON sm.user_id = u.id
        ORDER BY sm.created_at DESC
        LIMIT 10");
    $stmt->execute();
    $recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stock movement summary for the current month
    $stmt = $db->prepare("SELECT 
        SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
        COUNT(DISTINCT product_id) as products_affected
        FROM stock_movements 
        WHERE DATE(created_at) >= :month_start");
    $stmt->execute(['month_start' => $month_start]);
    $movement_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top moving products
    $stmt = $db->prepare("SELECT p.id, p.name, p.code, 
        SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_out
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        WHERE sm.created_at >= :month_start
        GROUP BY p.id
        ORDER BY (total_in + total_out) DESC
        LIMIT 5");
    $stmt->execute(['month_start' => $month_start]);
    $top_moving_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db = get_db_connection();
        $action = $_POST['action'];
        
        // Common variables
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $reference = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_STRING) ?: '';
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING) ?: '';
        
        // Validate CSRF token
        if (!isset($_POST['_token']) || $_POST['_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }
        
        // Validate product ID
        if (!$product_id) {
            throw new Exception('Invalid product selected');
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Handle different actions
        switch ($action) {
            case 'add_stock':
                $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
                if ($quantity <= 0) {
                    throw new Exception('Invalid quantity');
                }
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id");
                $stmt->execute(['quantity' => $quantity, 'id' => $product_id]);
                
                // Record stock movement
                $stmt = $db->prepare("INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference, notes, user_id, created_at)
                    VALUES (:product_id, 'in', :quantity, :reference, :notes, :user_id, NOW())");
                $stmt->execute([
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'reference' => 'MANUAL-' . time(),
                    'notes' => $notes,
                    'user_id' => $user_id
                ]);
                
                $success_message = "Successfully added $quantity items to stock";
                break;
                
            case 'adjust_stock':
                // Handle stock adjustment
                $new_quantity = filter_input(INPUT_POST, 'new_quantity', FILTER_VALIDATE_INT);
                $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
                
                if ($new_quantity === false) {
                    throw new Exception('Invalid quantity');
                }
                
                // Get current stock
                $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock === false) {
                    throw new Exception('Product not found');
                }
                
                $difference = $new_quantity - $current_stock;
                $movement_type = $difference > 0 ? 'in' : 'out';
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_quantity WHERE id = :id");
                $stmt->execute(['new_quantity' => $new_quantity, 'id' => $product_id]);
                
                // Record movement
                $stmt = $db->prepare("INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference, notes, user_id, created_at)
                    VALUES (:product_id, :movement_type, :quantity, :reference, :notes, :user_id, NOW())");
                $stmt->execute([
                    'product_id' => $product_id,
                    'movement_type' => 'adjustment',
                    'quantity' => abs($difference),
                    'reference' => 'ADJ-' . time(),
                    'notes' => $reason ? "$reason (Adjusted from $current_stock to $new_quantity)" : "Adjusted from $current_stock to $new_quantity",
                    'user_id' => $user_id
                ]);
                
                $success_message = "Successfully adjusted stock to $new_quantity";
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        $db->commit();
        
        // Refresh the page to show updated data
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Title -->
    <title>Stock Management - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Buttons -->
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    
    <!-- JSZip for Excel export -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    
    <!-- Button HTML5 export -->
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-light: #7a9ff5;
            --primary-dark: #2a4b8c;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --lighter-color: #fbfbfb;
            --dark-color: #5a5c69;
            --darker-color: #3a3b45;
            --border-color: #e3e6f0;
            --border-radius: 0.5rem;
            --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            --sidebar-width: 250px;
            --dark-bg: #343a40;
            --dark-text: #f8f9fa;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --success-color: #28a745;
            --white-bg: #ffffff;
            --light-bg: #f8f9fa;
            --medium-bg: #e9ecef;
        }
        
        body {
            background-color: var(--background-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--darker-color) 0%, var(--dark-color) 100%);
            color: var(--light-color);
            padding: 1.5rem 0;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            margin: 0.25rem 1rem;
            border-radius: 0.25rem;
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-left-color: var(--primary-color);
        }
        
        .sidebar-menu-item i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-header {
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .sidebar-menu {
            padding: 0;
            list-style: none;
            margin-top: 20px;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            color: var(--dark-text);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--primary-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            background: #fff;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--darker-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background: var(--lighter-color);
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
        }
        
        .stat-card {
            border-left: 0.25rem solid var(--primary-color);
            border-radius: 0.35rem;
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            font-size: 0.875rem;
            color: var(--dark-color);
        }
        
        /* Table styles */
        .table {
            width: 100%;
            margin-bottom: 0;
            color: var(--dark-color);
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: #f8f9fc;
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem 1.25rem;
            border: none;
            white-space: nowrap;
        }
        
        .table td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-top: 1px solid #f0f2f5;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.03);
        }
        
        .table tbody tr:last-child td {
            border-bottom: 1px solid var(--border-color);
        }
        
        /* Status badges */
        .badge {
            padding: 0.4em 0.8em;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 50px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .badge-in-stock {
            background-color: rgba(28, 200, 138, 0.15);
            color: var(--success-color);
        }
        
        .badge-low-stock {
            background-color: rgba(246, 194, 62, 0.15);
            color: var(--warning-color);
        }
        
        .badge-out-of-stock {
            background-color: rgba(231, 74, 59, 0.15);
            color: var(--danger-color);
        }
        
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            font-size: 75%;
        }
        
        .badge-success {
            background-color: #1cc88a;
        }
        
        .badge-warning {
            background-color: #f6c23e;
            color: #1a1a1a;
        }
        
        .badge-danger {
            background-color: #e74a3b;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(78, 115, 223, 0.3);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all var(--transition-speed) ease;
        }
        
        .sidebar-header {
            padding: 1.5rem 1.5rem 0.5rem;
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .sidebar-menu {
            padding: 0;
            list-style: none;
        }
        
        .sidebar-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu-item:hover, .sidebar-menu-item.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }
        
        .sidebar-menu-item i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            min-height: 100vh;
            background-color: var(--background-color);
            transition: all var(--transition-speed) ease;
        }
        
        .top-bar {
            background-color: #fff;
            padding: 1rem 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #4e73df;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .user-name {
            margin-right: 0.5rem;
            font-weight: 600;
        }
        
        .alert {
            border: none;
            border-left: 0.25rem solid;
            border-radius: 0.25rem;
        }
        
        .alert-success {
            border-left-color: #1cc88a;
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            border-left-color: #e74a3b;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-collapse-btn {
                display: block;
            }
        }
        
        /* Animation for sidebar toggle */
        .sidebar, .main-content {
            transition: all var(--transition-speed) ease;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none;
        }
        
        .loading-spinner {
            width: 3rem;
            height: 3rem;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            MTECH UGANDA
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="sidebar-menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="stock.php" class="sidebar-menu-item active">
                    <i class="fas fa-warehouse"></i>
                    <span>Stock Management</span>
                </a>
            </li>
            <li>
                <a href="products.php" class="sidebar-menu-item">
                    <i class="fas fa-boxes"></i>
                    <span>Products</span>
                </a>
            </li>
            <li>
                <a href="sales.php" class="sidebar-menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="sidebar-menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php if (in_array($user_role, ['admin', 'manager'])): ?>
            <li>
                <a href="../users.php" class="sidebar-menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="sidebar-menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="profile.php" class="sidebar-menu-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="../logout.php" class="sidebar-menu-item" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="btn btn-link d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Stock Management</h1>
            <div class="user-menu">
                <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_fullname, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Stock Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-muted mb-0">Total Products</h6>
                                <h2 class="mb-0 mt-2"><?php echo number_format($stock_summary['total_products']); ?></h2>
                            </div>
                            <div class="icon-circle bg-primary text-white">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-muted mb-0">Total Quantity</h6>
                                <h2 class="mb-0 mt-2"><?php echo number_format($stock_summary['total_quantity']); ?></h2>
                            </div>
                            <div class="icon-circle bg-success text-white">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-muted mb-0">Low Stock Items</h6>
                                <h2 class="mb-0 mt-2"><?php echo number_format($stock_summary['low_stock_count']); ?></h2>
                            </div>
                            <div class="icon-circle bg-warning text-dark">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-muted mb-0">Total Value</h6>
                                <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($stock_summary['total_value'], 2); ?></h2>
                            </div>
                            <div class="icon-circle bg-info text-white">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Stock
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                                <i class="fas fa-adjust me-2"></i>Adjust Stock
                            </button>
                            <button type="button" class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#stockTransferModal">
                                <i class="fas fa-exchange-alt me-2"></i>Transfer Stock
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#stockTakeModal">
                                <i class="fas fa-clipboard-check me-2"></i>Stock Take
                            </button>
                            <a href="stock_reports.php" class="btn btn-secondary">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Stock Items -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold">Current Stock Levels</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                <i class="fas fa-plus"></i> Add Stock
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="stockTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Min Level</th>
                                        <th>Unit Price</th>
                                        <th>Stock Value</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_value = 0;
                                    foreach ($products as $index => $product): 
                                        $stock_value = $product['stock_quantity'] * $product['purchase_price'];
                                        $total_value += $stock_value;
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        if ($product['stock_quantity'] <= 0) {
                                            $status_class = 'danger';
                                            $status_text = 'Out of Stock';
                                        } elseif ($product['stock_quantity'] <= $product['low_stock_threshold']) {
                                            $status_class = 'warning';
                                            $status_text = 'Low Stock';
                                        } else {
                                            $status_class = 'success';
                                            $status_text = 'In Stock';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="fas fa-box text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($product['code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                        <td class="text-end"><?php echo number_format($product['stock_quantity']); ?></td>
                                        <td class="text-end"><?php echo number_format($product['low_stock_threshold']); ?></td>
                                        <td class="text-end"><?php echo number_format($product['purchase_price'], 2); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($stock_value, 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                    onclick="showAddStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                    onclick="showAdjustStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)">
                                                    <i class="fas fa-adjust"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="6" class="text-end">Total Stock Value:</th>
                                        <th class="text-end"><?php echo number_format($total_value, 2); ?></th>
                                        <th colspan="2">
                                            <span class="badge bg-primary">
                                                <?php echo count($products); ?> Products
                                            </span>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Last updated: <?php echo date('M d, Y h:i A'); ?>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="refreshStock">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <a href="stock_movements.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-history"></i> View All Movements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Low Stock Items -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Low Stock Items</h6>
                        <span class="badge bg-danger"><?php echo count($low_stock_items); ?> items</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($low_stock_items)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Current Stock</th>
                                            <th>Min. Level</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($low_stock_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2">
                                                            <i class="fas fa-box text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['code']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo number_format($item['stock_quantity']); ?></td>
                                                <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                                                <td>
                                                    <?php if ($item['stock_quantity'] <= 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="mb-0">All items are well stocked!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="products.php?filter=low_stock" class="btn btn-sm btn-link">View All Low Stock Items</a>
                    </div>
                </div>
            </div>

            <!-- Recent Stock Movements -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Recent Stock Movements</h6>
                        <a href="stock_movements.php" class="btn btn-sm btn-link">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_movements)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Type</th>
                                            <th>Qty</th>
                                            <th>By</th>
                                            <th>Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_movements as $movement): 
                                            $movement_type_class = [
                                                'in' => 'success',
                                                'out' => 'danger',
                                                'adjustment' => 'warning',
                                                'transfer_in' => 'info',
                                                'transfer_out' => 'secondary'
                                            ][$movement['movement_type']] ?? 'secondary';
                                            
                                            $movement_icon = [
                                                'in' => 'plus-circle',
                                                'out' => 'minus-circle',
                                                'adjustment' => 'sliders-h',
                                                'transfer_in' => 'sign-in-alt',
                                                'transfer_out' => 'sign-out-alt'
                                            ][$movement['movement_type']] ?? 'exchange-alt';
                                            
                                            $movement_type_text = ucfirst($movement['movement_type']);
                                            $movement_date = new DateTime($movement['created_at']);
                                            ?>
                                            <tr>
                                                <td><?php echo $movement_date->format('M j, H:i'); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2">
                                                            <i class="fas fa-<?php echo $movement_icon; ?> text-<?php echo $movement_type_class; ?>"></i>
                                                        </span>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($movement['product_code']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $movement_type_class; ?>">
                                                        <?php echo $movement_type_text; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($movement['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($movement['user_name'] ?? 'System'); ?></td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($movement['reference']); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No recent stock movements found</p>
                                <small class="text-muted">Stock movements will appear here when they occur</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Moving Products -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Top Moving Products (This Month)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_moving_products)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Code</th>
                                            <th>Stock In</th>
                                            <th>Stock Out</th>
                                            <th>Net Movement</th>
                                            <th>Current Stock</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_moving_products as $product): 
                                            $net_movement = $product['total_in'] - $product['total_out'];
                                            $status_class = $product['stock_quantity'] <= 0 ? 'danger' : 
                                                          ($product['stock_quantity'] <= $product['low_stock_threshold'] ? 'warning' : 'success');
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['code']); ?></td>
                                                <td class="text-success">+<?php echo number_format($product['total_in']); ?></td>
                                                <td class="text-danger">-<?php echo number_format($product['total_out']); ?></td>
                                                <td class="fw-bold <?php echo $net_movement >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $net_movement >= 0 ? '+' : ''; ?><?php echo number_format($net_movement); ?>
                                                </td>
                                                <td><?php echo number_format($product['stock_quantity']); ?></td>
                                                <td>
                                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php elseif ($product['stock_quantity'] <= $product['low_stock_threshold']): ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No product movement data available</p>
                                <small class="text-muted">Product movement data will appear here when available</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addStockForm" method="post" action="">
                    <input type="hidden" name="_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="add_stock">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStockModalLabel">Add Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="productSelect" class="form-label">Product</label>
                            <select class="form-select" id="productSelect" name="product_id" required>
                                <option value="">Select a product</option>
                                <?php
                                try {
                                    $db = get_db_connection();
                                    $stmt = $db->query("SELECT id, name, code FROM products ORDER BY name");
                                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($products as $product) {
                                        echo "<option value=\"{$product['id']}\">{$product['name']} ({$product['code']})</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Silently handle error
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity to Add</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i> Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adjustStockForm" method="post" action="">
                    <input type="hidden" name="_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="adjust_stock">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="adjustStockModalLabel">Adjust Stock Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="adjustProductSelect" class="form-label">Product</label>
                            <select class="form-select" id="adjustProductSelect" name="product_id" required>
                                <option value="">Select a product</option>
                                <?php
                                try {
                                    $db = get_db_connection();
                                    $stmt = $db->query("SELECT id, name, code, stock_quantity, low_stock_threshold FROM products ORDER BY name");
                                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($products as $product) {
                                        $stockStatus = $product['stock_quantity'] <= 0 ? ' (Out of Stock)' : 
                                                      ($product['stock_quantity'] <= $product['low_stock_threshold'] ? ' (Low Stock)' : '');
                                        echo "<option value=\"{$product['id']}\" data-current=\"{$product['stock_quantity']}\">{$product['name']} ({$product['code']}) - Current: {$product['stock_quantity']}{$stockStatus}</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Silently handle error
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="currentStock" class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="currentStock" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="newQuantity" class="form-label">New Stock Level</label>
                            <input type="number" class="form-control" id="newQuantity" name="new_quantity" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Adjustment</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">Select a reason</option>
                                <option value="Damaged">Damaged Goods</option>
                                <option value="Lost">Lost/Stolen</option>
                                <option value="Expired">Expired</option>
                                <option value="Donated">Donated</option>
                                <option value="Miscount">Miscount</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-adjust me-1"></i> Adjust Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Transfer Modal -->
    <div class="modal fade" id="stockTransferModal" tabindex="-1" aria-labelledby="stockTransferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockTransferModalLabel">Transfer Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Stock transfer functionality is coming soon. This will allow you to transfer stock between different locations.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Take Modal -->
    <div class="modal fade" id="stockTakeModal" tabindex="-1" aria-labelledby="stockTakeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockTakeModalLabel">Start Stock Take</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Stock take functionality is coming soon. This will help you perform physical stock counts and reconcile with system records.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary loading-spinner" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Handle product selection in adjust stock modal
        document.getElementById('adjustProductSelect').addEventListener('change', function() {
            const currentStock = this.options[this.selectedIndex].getAttribute('data-current');
            document.getElementById('currentStock').value = currentStock || '0';
            document.getElementById('newQuantity').value = currentStock || '0';
        });

        // Show loading overlay on form submission
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Logout confirmation with countdown
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const logoutUrl = this.href;
            let seconds = 5;
            
            // Create and show logout confirmation modal
            const modalHtml = `
                <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <i class="fas fa-sign-out-alt fa-4x text-primary mb-3"></i>
                                <h5>Are you sure you want to log out?</h5>
                                <p class="mb-0">You will be redirected to the login page in <span id="countdown">${seconds}</span> seconds.</p>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Stay Logged In
                                </button>
                                <a href="${logoutUrl}" class="btn btn-primary">
                                    <i class="fas fa-sign-out-alt me-2"></i>Confirm Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>`;
                
            // Add modal to the DOM
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer);
            
            // Show the modal
            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
            
            // Start countdown
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = logoutUrl;
                }
            }, 1000);
            
            // Clean up when modal is closed
            document.getElementById('logoutModal').addEventListener('hidden.bs.modal', function () {
                clearInterval(countdownInterval);
                document.body.removeChild(modalContainer);
            });
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    
    <!-- Stock Management JavaScript -->
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#stockTable').DataTable({
                "order": [[3, "desc"]], // Sort by stock quantity by default
                "pageLength": 25,
                "responsive": true,
                "dom": '<"top"f>rt<"bottom"lip><"clear">',
                "language": {
                    "search": "Search products:",
                    "lengthMenu": "Show _MENU_ items per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ items",
                    "infoEmpty": "No items found",
                    "infoFiltered": "(filtered from _MAX_ total items)"
                }
            });

            // Refresh button
            $('#refreshStock').click(function() {
                showLoading();
                location.reload();
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Handle add stock form submission
            $('#addStockForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const submitBtn = $(form).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                // Show loading state
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
                
                // Submit form via AJAX
                $.ajax({
                    url: 'stock.php',
                    type: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            $('#addStockModal').modal('hide');
                            location.reload();
                        } else {
                            showAlert('danger', response.message || 'An error occurred');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('danger', 'Error: ' + (xhr.responseJSON?.message || 'Failed to process request'));
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });

            // Handle adjust stock form submission
            $('#adjustStockForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const submitBtn = $(form).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                // Show loading state
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
                
                // Submit form via AJAX
                $.ajax({
                    url: 'stock.php',
                    type: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            $('#adjustStockModal').modal('hide');
                            location.reload();
                        } else {
                            showAlert('danger', response.message || 'An error occurred');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('danger', 'Error: ' + (xhr.responseJSON?.message || 'Failed to process request'));
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });
        });

        // Show Add Stock Modal
        function showAddStockModal(productId, productName) {
            $('#addStockProductId').val(productId);
            $('#addStockProductName').text(productName);
            $('#addStockQuantity').val('');
            $('#addStockNotes').val('');
            $('#addStockModal').modal('show');
        }

        // Show Adjust Stock Modal
        function showAdjustStockModal(productId, productName, currentStock) {
            $('#adjustStockProductId').val(productId);
            $('#adjustStockProductName').text(productName);
            $('#adjustCurrentStock').text(currentStock);
            $('#adjustNewQuantity').val(currentStock);
            $('#adjustReason').val('');
            $('#adjustStockModal').modal('show');
        }

        // Show loading overlay
        function showLoading() {
            $('#loadingOverlay').show();
        }

        // Hide loading overlay
        function hideLoading() {
            $('#loadingOverlay').hide();
        }

        // Show alert message
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
            
            // Remove any existing alerts
            $('.alert').remove();
            
            // Add new alert
            $('.main-content').prepend(alertHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }

        
        // Show success/error messages if any
        <?php if (!empty($success_message)): ?>
            $(document).ready(function() {
                showAlert('success', '<?php echo addslashes($success_message); ?>');
            });
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            $(document).ready(function() {
                showAlert('danger', '<?php echo addslashes($error_message); ?>');
            });
        <?php endif; ?>
    </script>
    
    <!-- Logout Link -->
    <div class="text-center mt-4">
        <a href="../logout.php" class="btn btn-outline-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</body>
</html>
