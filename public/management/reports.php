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

// Check if user has reporting access
if (!in_array($user_role, ['admin', 'manager'])) {
    header('Location: ../welcome.php');
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';
$report_data = [];
$filters = [
    'date_start' => date('Y-m-d', strtotime('-30 days')),
    'date_end' => date('Y-m-d'),
    'report_type' => 'sales_summary'
];

// Initialize database connection
$db = get_db_connection();

// Process filters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_filters') {
    $filters['date_start'] = filter_input(INPUT_POST, 'date_start', FILTER_SANITIZE_STRING) ?: $filters['date_start'];
    $filters['date_end'] = filter_input(INPUT_POST, 'date_end', FILTER_SANITIZE_STRING) ?: $filters['date_end'];
    $filters['report_type'] = filter_input(INPUT_POST, 'report_type', FILTER_SANITIZE_STRING) ?: $filters['report_type'];
    
    // Validate dates
    if (!DateTime::createFromFormat('Y-m-d', $filters['date_start']) || 
        !DateTime::createFromFormat('Y-m-d', $filters['date_end']) ||
        $filters['date_start'] > $filters['date_end']) {
        $error_message = 'Invalid date range selected.';
        $filters['date_start'] = date('Y-m-d', strtotime('-30 days'));
        $filters['date_end'] = date('Y-m-d');
    }
}

// Fetch report data based on report type
try {
    switch ($filters['report_type']) {
        case 'sales_summary':
            // Sales summary
            $stmt = $db->prepare("SELECT 
                COUNT(s.id) as total_sales,
                SUM(s.total_amount) as total_revenue,
                SUM(s.tax_amount) as total_tax,
                SUM(s.discount_amount) as total_discount,
                COUNT(DISTINCT s.customer_id) as unique_customers
                FROM sales s
                WHERE s.date BETWEEN :date_start AND :date_end");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Top selling products
            $stmt = $db->prepare("SELECT 
                p.name, p.code, 
                SUM(si.quantity) as total_quantity,
                SUM(si.subtotal) as total_sales
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE s.date BETWEEN :date_start AND :date_end
                GROUP BY p.id
                ORDER BY total_sales DESC
                LIMIT 5");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'inventory_movements':
            // Stock movements summary
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN sm.type = 'out' THEN sm.quantity ELSE 0 END) as total_out,
                SUM(CASE WHEN sm.type = 'adjustment' THEN sm.quantity ELSE 0 END) as total_adjustments,
                COUNT(DISTINCT sm.product_id) as products_affected
                FROM stock_movements sm
                WHERE sm.created_at BETWEEN :date_start AND :date_end");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Recent movements
            $stmt = $db->prepare("SELECT 
                sm.*, p.name as product_name, p.code as product_code,
                u.username as user_name
                FROM stock_movements sm
                JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.created_at BETWEEN :date_start AND :date_end
                ORDER BY sm.created_at DESC
                LIMIT 10");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['recent_movements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'customer_activity':
            // Customer purchase history
            $stmt = $db->prepare("SELECT 
                c.name as customer_name,
                COUNT(s.id) as purchase_count,
                SUM(s.total_amount) as total_spent,
                MAX(s.date) as last_purchase
                FROM sales s
                JOIN customers c ON s.customer_id = c.id
                WHERE s.date BETWEEN :date_start AND :date_end
                GROUP BY c.id
                ORDER BY total_spent DESC
                LIMIT 10");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'financial_summary':
            // Financial summary
            $stmt = $db->prepare("SELECT 
                SUM(s.total_amount) as total_revenue,
                SUM(s.tax_amount) as total_tax,
                SUM(s.discount_amount) as total_discount,
                COUNT(s.id) as total_transactions,
                SUM(CASE WHEN s.payment_type = 'cash' THEN s.total_amount ELSE 0 END) as cash_payments,
                SUM(CASE WHEN s.payment_type = 'card' THEN s.total_amount ELSE 0 END) as card_payments,
                SUM(CASE WHEN s.payment_type = 'mobile_money' THEN s.total_amount ELSE 0 END) as mobile_payments
                FROM sales s
                WHERE s.date BETWEEN :date_start AND :date_end");
            $stmt->execute([
                'date_start' => $filters['date_start'] . ' 00:00:00',
                'date_end' => $filters['date_end'] . ' 23:59:59'
            ]);
            $report_data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
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
    <title>Reports - <?php echo SITE_NAME; ?></title>
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
            margin-top: 70px;
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
                <a href="stock.php" class="menu-item"><i class="fas fa-warehouse"></i> Stock</a>
                <a href="reports.php" class="menu-item active"><i class="fas fa-chart-bar"></i> Reporting</a>
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
                <h1 class="page-title">Reports</h1>
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

            <!-- Report Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="apply_filters">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="report_type" class="form-label">Report Type</label>
                                        <select class="form-select" id="report_type" name="report_type" required>
                                            <option value="sales_summary" <?php echo $filters['report_type'] === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                                            <option value="inventory_movements" <?php echo $filters['report_type'] === 'inventory_movements' ? 'selected' : ''; ?>>Inventory Movements</option>
                                            <option value="customer_activity" <?php echo $filters['report_type'] === 'customer_activity' ? 'selected' : ''; ?>>Customer Activity</option>
                                            <option value="financial_summary" <?php echo $filters['report_type'] === 'financial_summary' ? 'selected' : ''; ?>>Financial Summary</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="date_start" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo $filters['date_start']; ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="date_end" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo $filters['date_end']; ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-filter me-2"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Summary Cards -->
            <div class="row mb-4">
                <?php if ($filters['report_type'] === 'sales_summary' && !empty($report_data['summary'])): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted mb-0">Total Sales</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['total_sales']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-primary text-white">
                                        <i class="fas fa-shopping-cart"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Total Revenue</h6>
                                        <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($report_data['summary']['total_revenue'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-success text-white">
                                        <i class="fas fa-money-bill-wave"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Total Tax</h6>
                                        <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($report_data['summary']['total_tax'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-info text-white">
                                        <i class="fas fa-percentage"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Unique Customers</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['unique_customers']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-warning text-dark">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($filters['report_type'] === 'inventory_movements' && !empty($report_data['summary'])): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted mb-0">Stock In</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['total_in']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-success text-white">
                                        <i class="fas fa-arrow-up"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Stock Out</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['total_out']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-danger text-white">
                                        <i class="fas fa-arrow-down"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Adjustments</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['total_adjustments']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-warning text-dark">
                                        <i class="fas fa-adjust"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Products Affected</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['products_affected']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-info text-white">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($filters['report_type'] === 'financial_summary' && !empty($report_data['summary'])): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted mb-0">Total Revenue</h6>
                                        <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($report_data['summary']['total_revenue'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-success text-white">
                                        <i class="fas fa-money-bill-wave"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Total Transactions</h6>
                                        <h2 class="mb-0 mt-2"><?php echo number_format($report_data['summary']['total_transactions']); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-primary text-white">
                                        <i class="fas fa-receipt"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Cash Payments</h6>
                                        <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($report_data['summary']['cash_payments'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-info text-white">
                                        <i class="fas fa-money-bill-alt"></i>
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
                                        <h6 class="text-uppercase text-muted mb-0">Mobile Payments</h6>
                                        <h2 class="mb-0 mt-2"><?php echo 'UGX ' . number_format($report_data['summary']['mobile_payments'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-warning text-dark">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Report Details -->
            <div class="row">
                <?php if ($filters['report_type'] === 'sales_summary'): ?>
                    <!-- Top Selling Products -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold">Top Selling Products</h6>
                                <span class="badge bg-primary"><?php echo count($report_data['top_products']); ?> items</span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($report_data['top_products'])): ?>
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Code</th>
                                                <th>Quantity</th>
                                                <th>TotalSales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['top_products'] as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['code']); ?></td>
                                                    <td><?php echo number_format($product['total_quantity']); ?></td>
                                                    <td><?php echo 'UGX ' . number_format($product['total_sales'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                        <p class="mb-0">No sales data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="modalDetailedReportModal" onclick="loadDetailedReport('top_products')">
                                    View Detailed Report
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Trend Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Sales Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php elseif ($filters['report_type'] === 'inventory_movements'): ?>
                    <!-- Recent Stock Movements -->
                    <div class="col-lg-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold">Recent Stock Movements</h6>
                                <a href="stock_movements.php" class="btn btn-sm btn-link">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($report_data['recent_movements'])): ?>
                                    <table class="table table-hover mb-0" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>User</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['recent_movements'] as $movement):
                                                $movement_class = [
                                                    'in' => 'success',
                                                    'out' => 'danger',
                                                    'adjustment' => 'warning'
                                                ][$movement['type']] ?? 'secondary';
                                                $movement_icon = [
                                                    'in' => 'plus-circle',
                                                    'out' => 'minus-circle',
                                                    'adjustment' => 'sliders-h'
                                                ][$movement['type']] ?? 'exchange-alt';
                                                $movement_date = new DateTime($movement['created_at']);
                                            ?>
                                                <tr>
                                                    <td><?php echo $movement_date->format('M d, H:i'); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="me-2">
                                                                <i class="fas fa-<?php echo $movement_icon; ?> text-<?php echo $movement_class; ?>"></i>
                                                            </span>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($movement['product_code']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $movement_class; ?>">
                                                            <?php echo ucfirst($movement['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($movement['quantity']); ?></td>
                                                    <td><?php echo htmlspecialchars($movement['user_name'] ?? 'System'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                        <p class="mb-0">No stock movements found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($filters['report_type'] === 'customer_activity'): ?>
                    <!-- Top Customers -->
                    <div class="col-lg-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold">Top Customers</h6>
                                <span class="badge bg-primary"><?php echo count($report_data['top_customers']); ?> customers</span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($report_data['top_customers'])): ?>
                                    <table class="table table-hover mb-0" id="customersTable">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Purchases</th>
                                                <th>Total Spent</th>
                                                <th>Last Purchase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['top_customers'] as $customer):
                                                $last_purchase_date = new DateTime($customer['last_purchase']);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                <td><?php echo number_format($customer['purchase_count']); ?></td>
                                                <td><?php echo 'UGX ' . number_format($customer['total_spent'], 2); ?></td>
                                                <td><?php echo $last_purchase_date->format('M d, Y'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <p class="mb-0">No customer activity found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailedReportModal" onclick="loadDetailedReport('customer_report')">
                                    View Detailed Report
                                </button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($filters['report_type'] === 'financial_summary'): ?>
                    <!-- Payment Methods Chart -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Payment Methods Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentMethodsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- Financial Metrics -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Financial Metrics</h6>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Total Revenue</th>
                                        <td><?php echo 'UGX ' . number_format($report_data['summary']['total_revenue'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Tax</th>
                                        <td><?php echo 'UGX ' . number_format($report_data['summary']['total_tax'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Discounts</th>
                                        <td><?php echo 'UGX ' . number_format($report_data['summary']['total_discount'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Card Payments</th>
                                        <td><?php echo 'UGX ' . number_format($report_data['summary']['card_payments'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Detailed Report Modal -->
            <div class="modal fade" id="detailedReportModal" tabindex="-1" aria-labelledby="detailedReportModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="detailedReportModalLabel">Detailed Report</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="detailedReportContent">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-2x text-muted fa-spin"></i>
                                <p>Loading report details...</p>
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
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Initialize DataTables
        $(document).ready(function() {
            $('#movementsSummary, #customersummary').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 10,
                "responsive": true,
                "dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'print'
                ]
            });
        });

        // Load detailed report via AJAX
        function loadDetailedReport(reportType) {
            $('#detailedReportContent').html('<div class="text-center"><i class="fas fa-spinner fa-2x text-muted fa-spin"></i><p>Loading report details...</p></div>');
            $.ajax({
                url: 'generate_detailed_report.php',
                type: 'POST',
                data: {
                    report_type: reportType,
                    date_start: '<?php echo $filters['date_start']; ?>',
                    date_end: '<?php echo $filters['date_end']; ?>',
                    _token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                success: function(response) {
                    $('#detailedReportContent').html(response);
                },
                error: function() {
                    $('#detailedReportContent').html('<div class="alert alert-danger">Failed to load report details.</div>');
                }
            });
        }

        // Sales Trend Chart
        <?php if ($filters['report_type'] === 'sales_summary'): ?>
            const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
            new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'], // Replace with dynamic data
                    datasets: [{
                        label: 'Revenue (UGX)',
                        data: [1000000, 1200000, 800000, 1500000, 1100000], // Replace with dynamic data
                        borderColor: 'rgba(67, 97, 238, 1)',
                        backgroundColor: 'rgba(67, 97, 238, 0.2)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue (UGX)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Payment Methods Chart
        <?php if ($filters['report_type'] === 'financial_summary' && !empty($report_data['summary'])): ?>
            const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
            new Chart(paymentMethodsCtx, {
                type: 'pie',
                data: {
                    labels: ['Cash', 'Card', 'Mobile Money'],
                    datasets: [{
                        data: [
                            <?php echo $report_data['summary']['cash_payments']; ?>,
                            <?php echo $report_data['summary']['card_payments']; ?>,
                            <?php echo $report_data['summary']['mobile_payments']; ?>
                        ],
                        backgroundColor: [
                            'rgba(67, 97, 238, 0.8)',
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        <?php endif; ?>

        // Show loading overlay on form submission
        $('form').on('submit', function() {
            $('#loadingOverlay').show();
        });

        // Auto-hide alerts
        $('.alert-dismissible').delay(5000).fadeOut('slow', function() {
            $(this).remove();
        });
    </script>

    <!-- Logout Link -->
    <div class="text-center mt-4">
        <a href="../logout.php" class="btn btn-outline-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</body>
</html>