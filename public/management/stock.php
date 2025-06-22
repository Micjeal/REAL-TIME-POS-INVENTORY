<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';
require_once '../../includes/audit_log.php'; // Include audit logging functions

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
                
                // Get product details before update
                $stmt = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_quantity = $product['stock_quantity'];
                $new_quantity = $old_quantity + $quantity;
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_quantity WHERE id = :id");
                $stmt->execute(['new_quantity' => $new_quantity, 'id' => $product_id]);
                
                // Log the stock addition
                log_activity($db, $user_id, 'stock_add', 'Added stock to product: ' . $product['product_name'], 
                    ['product_id' => $product_id, 'old_quantity' => $old_quantity, 'new_quantity' => $new_quantity, 'added_quantity' => $quantity]);
                
                // Record stock movement
                $reference = 'MANUAL-' . time();
                $stmt = $db->prepare("INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference, notes, user_id, created_at)
                    VALUES (:product_id, 'in', :quantity, :reference, :notes, :user_id, NOW())");
                $stmt->execute([
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'reference' => $reference,
                    'notes' => $notes,
                    'user_id' => $user_id
                ]);
                
                // Log the stock movement
                log_activity($db, $user_id, 'stock_movement', 'Recorded stock movement', 
                    ['movement_id' => $db->lastInsertId(), 'reference' => $reference, 'type' => 'in', 'quantity' => $quantity]);
                
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
                
                // Get product name before update
                $stmt = $db->prepare("SELECT product_name FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product_name = $stmt->fetchColumn();
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_quantity WHERE id = :id");
                $stmt->execute(['new_quantity' => $new_quantity, 'id' => $product_id]);
                
                // Log the stock adjustment
                $adjustment_note = $reason ? "$reason (Adjusted from $current_stock to $new_quantity)" : "Adjusted from $current_stock to $new_quantity";
                log_activity($db, $user_id, 'stock_adjust', 'Adjusted stock for product: ' . $product_name, 
                    ['product_id' => $product_id, 'old_quantity' => $current_stock, 'new_quantity' => $new_quantity, 'reason' => $reason]);
                
                // Record movement
                $reference = 'ADJ-' . time();
                $stmt = $db->prepare("INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference, notes, user_id, created_at)
                    VALUES (:product_id, 'adjustment', :quantity, :reference, :notes, :user_id, NOW())");
                $stmt->execute([
                    'product_id' => $product_id,
                    'quantity' => abs($difference),
                    'reference' => $reference,
                    'notes' => $adjustment_note,
                    'user_id' => $user_id
                ]);
                
                // Log the stock movement
                log_activity($db, $user_id, 'stock_movement', 'Recorded stock adjustment', 
                    ['movement_id' => $db->lastInsertId(), 'reference' => $reference, 'type' => 'adjustment', 'quantity' => abs($difference)]);
                
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - <?php echo SITE_NAME; ?></title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --secondary: #6c757d;
            --dark: #1a237e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: rgba(26, 35, 126, 0.9);
            backdrop-filter: blur(10px);
            color: #fff;
            position: fixed;
            height: 100vh;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            text-align: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .menu-section {
            padding: 0.5rem 0;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: background 0.2s;
        }

        .menu-item.active {
            background: rgba(67, 97, 238, 0.2);
            color: #fff;
            font-weight: 500;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
            transition: margin-left 0.3s ease;
        }

        .top-nav {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            box-shadow: 0 1px 15px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 900;
        }

        .card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
    background-color: #fff;
    margin-top: 70px; /* Offset to avoid being covered by the fixed top-nav (70px height) */
}
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 1rem;
            transition: box-shadow 0.3s;
        }

        .stat-card:hover {
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }

        .icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
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
                width: 100%;
            }
            .top-nav {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>MTECH UGANDA</h2>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Main Navigation</div>
                <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="documents.php" class="menu-item"><i class="fas fa-file-invoice"></i> Documents</a>
                <a href="products.php" class="menu-item"><i class="fas fa-box"></i> Products</a>
                <a href="price-lists.php" class="menu-item"><i class="fas fa-tags"></i> Price Lists</a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Inventory</div>
                <a href="stock.php" class="menu-item active"><i class="fas fa-warehouse"></i> Stock</a>
                <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reporting</a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Customers</div>
                <a href="customers-suppliers.php" class="menu-item"><i class="fas fa-users"></i> Customers & Suppliers</a>
                <a href="promotions.php" class="menu-item"><i class="fas fa-percent"></i> Promotions</a>
            </div>
            <?php if (in_array($user_role, ['admin', 'manager'])): ?>
            <div class="menu-section">
                <div class="menu-section-title">Settings</div>
                <a href="security.php" class="menu-item"><i class="fas fa-users-cog"></i> Users & Security</a>
                <a href="print-stations.php" class="menu-item"><i class="fas fa-print"></i> Print Stations</a>
                <a href="payment-types.php" class="menu-item"><i class="fas fa-credit-card"></i> Payment Types</a>
                <a href="countries.php" class="menu-item"><i class="fas fa-globe"></i> Countries</a>
                <a href="tax-rates.php" class="menu-item"><i class="fas fa-percentage"></i> Tax Rates</a>
                <a href="company.php" class="menu-item"><i class="fas fa-building"></i> My Company</a>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="top-nav">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Stock Management</h1>
                <div class="user-menu">
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user_fullname, 0, 1)); ?></div>
                </div>
            </nav>

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

            <div class="row">
                <!-- Top Moving Products -->
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
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
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
        document.querySelector('.btn-outline-danger').addEventListener('click', function(e) {
            e.preventDefault();
            const logoutUrl = this.href;
            let seconds = 5;
            
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
                
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer);
            
            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
            
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = logoutUrl;
                }
            }, 1000);
            
            document.getElementById('logoutModal').addEventListener('hidden.bs.modal', function () {
                clearInterval(countdownInterval);
                document.body.removeChild(modalContainer);
            });
        });

        // Initialize DataTable
        $(document).ready(function() {
            $('#stockTable').DataTable({
                "order": [[3, "desc"]],
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

            // Handle add stock form submission
            $('#addStockForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const submitBtn = $(form).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
                
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
                
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
                
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
            $('.main-content').prepend(alertHtml);
            setTimeout(() => {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }

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