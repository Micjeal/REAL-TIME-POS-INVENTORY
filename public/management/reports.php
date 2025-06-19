<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = strtolower($_SESSION['role'] ?? 'cashier');

// Check if user has permission to access reports
$allowed_roles = ['admin', 'manager', 'accountant'];
if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['error_message'] = 'You do not have permission to access the reports section.';
    header('Location: index.php');
    exit();
}

// Initialize variables
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Set default date range (last 30 days)
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

// Get current date values from request or use defaults
$start_date = $_REQUEST['start_date'] ?? $default_start_date;
$end_date = $_REQUEST['end_date'] ?? $default_end_date;
$report_type = $_REQUEST['report_type'] ?? 'sales_summary';
$customer_id = $_REQUEST['customer_id'] ?? null;
$category_id = $_REQUEST['category_id'] ?? null;
$product_id = $_REQUEST['product_id'] ?? null;
$format = $_REQUEST['format'] ?? 'html';

// Initialize arrays
$users = [];
$products = [];
$categories = [];
$customers = [];
$suppliers = [];
$payment_methods = [];
$tax_rates = [];
$locations = [];

/**
 * Build category tree for dropdown
 */
function buildCategoryTree($parent_id, $grouped_categories, $level = 0, $selected_id = null) {
    if (!isset($grouped_categories[$parent_id])) return '';
    
    $html = '';
    foreach ($grouped_categories[$parent_id] as $category) {
        $selected = ($selected_id == $category['id']) ? 'selected' : '';
        $html .= sprintf(
            '<option value="%s" %s>%s%s</option>',
            $category['id'],
            $selected,
            str_repeat('  ', $level * 2) . htmlspecialchars($category['name']),
            !empty($category['product_count']) ? ' (' . $category['product_count'] . ')' : ''
        );
        $html .= buildCategoryTree($category['id'], $grouped_categories, $level + 1, $selected_id);
    }
    return $html;
}

// Initialize database connection and load data
try {
    $db = get_db_connection();
    
    // Load active users
    $stmt = $db->prepare("SELECT id, username, name as full_name, role, email, active as is_active 
                         FROM users 
                         WHERE active = 1 
                         ORDER BY name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load products
    $stmt = $db->prepare("SELECT p.id, p.code as sku, p.name, p.description, p.price, p.cost, 
                         p.stock_quantity, p.min_stock as reorder_level, p.barcode,
                         p.tax_rate_id as is_taxable, p.tax_rate_id, p.category_id, 
                         c.name as category_name, c.parent_id as category_parent_id
                         FROM products p
                         LEFT JOIN categories c ON p.category_id = c.id
                         WHERE p.active = 1
                         ORDER BY p.name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load categories
    $stmt = $db->prepare("SELECT id, name, parent_id, 
                         (SELECT COUNT(*) FROM products WHERE category_id = pc.id) as product_count
                         FROM categories pc
                         WHERE active = 1
                         ORDER BY parent_id, name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load customers
    $stmt = $db->prepare("SELECT id, name, email, phone, address, 
                         type as customer_type, tax_number, 0 as credit_limit, 0 as balance,
                         (SELECT COUNT(*) FROM documents WHERE customer_id = c.id AND document_type IN ('invoice', 'receipt')) as total_purchases,
                         (SELECT COALESCE(SUM(total), 0) FROM documents WHERE customer_id = c.id AND document_type IN ('invoice', 'receipt')) as total_spent,
                         (SELECT MAX(document_date) FROM documents WHERE customer_id = c.id AND document_type IN ('invoice', 'receipt')) as last_purchase_date
                         FROM customers c
                         WHERE type IN ('customer', 'both') AND active = 1
                         ORDER BY name");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load suppliers
    $stmt = $db->prepare("SELECT id, name, email, phone, address, 
                         tax_number as supplier_code, contact_person, notes, active
                         FROM customers
                         WHERE type IN ('supplier', 'both') AND active = 1
                         ORDER BY name");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define payment methods directly since there's no payment_methods table
    $payment_methods = [
        [
            'id' => 'cash',
            'name' => 'Cash',
            'description' => 'Cash payment',
            'is_cash' => 1,
            'is_credit_card' => 0,
            'is_active' => 1,
            'sort_order' => 1,
            'fee_percentage' => 0
        ],
        [
            'id' => 'card',
            'name' => 'Credit/Debit Card',
            'description' => 'Card payment',
            'is_cash' => 0,
            'is_credit_card' => 1,
            'is_active' => 1,
            'sort_order' => 2,
            'fee_percentage' => 2.5
        ],
        [
            'id' => 'mobile_money',
            'name' => 'Mobile Money',
            'description' => 'Mobile money payment',
            'is_cash' => 0,
            'is_credit_card' => 0,
            'is_active' => 1,
            'sort_order' => 3,
            'fee_percentage' => 1.5
        ]
    ];
    
    // Convert to array of objects for consistency with database results
    $payment_methods = array_map(function($method) {
        return (object)$method;
    }, $payment_methods);
    
    // Load tax rates
    $stmt = $db->prepare("SELECT id, name, rate, 0 as is_compound, active as is_active, 
                         '' as description, '' as tax_agency, '' as tax_account
                         FROM tax_rates 
                         WHERE active = 1 
                         ORDER BY rate DESC");
    $stmt->execute();
    $tax_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define default location since there's no locations table in the database
    $locations = [
        [
            'id' => 1,
            'name' => 'Main Store',
            'code' => 'MAIN',
            'address' => '123 Main Street',
            'phone' => '',
            'email' => '',
            'is_primary' => 1,
            'is_active' => 1,
            'manager_id' => null
        ]
    ];
    
    // Convert to array of objects for consistency with database results
    $locations = array_map(function($location) {
        return (object)$location;
    }, $locations);
    
    // Log report access (suppress errors if activity_log table doesn't exist)
    @$db->query("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    try {
        $log_stmt = $db->prepare("INSERT INTO activity_log 
                                 (user_id, action, details, ip_address, user_agent) 
                                 VALUES (?, 'view_report', ?, ?, ?)");
        $log_stmt->execute([
            $user_id,
            "Accessed reports page: " . ($report_type ?: 'Overview'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        // Silently fail if logging fails
        error_log('Failed to log activity: ' . $e->getMessage());
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    error_log('Database error in reports.php: ' . $e->getMessage());
} catch (Exception $e) {
    $error_message = 'An error occurred: ' . $e->getMessage();
    error_log('Error in reports.php: ' . $e->getMessage());
}

/**
 * Get report data with comprehensive filtering
 */
function getReportData($db, $report_type, $filters = []) {
    $query = "";
    $params = [];
    $where_conditions = [];

    // Set default date range
    $start_date = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $filters['end_date'] ?? date('Y-m-d');

    // Common where conditions for document-based reports
    if (!empty($start_date)) {
        $where_conditions[] = "d.document_date >= ?";
        $params[] = $start_date . ' 00:00:00';
    }
    if (!empty($end_date)) {
        $where_conditions[] = "d.document_date <= ?";
        $params[] = $end_date . ' 23:59:59';
    }
    if (!empty($filters['user_id'])) {
        $where_conditions[] = "d.user_id = ?";
        $params[] = $filters['user_id'];
    }
    if (!empty($filters['customer_id'])) {
        $where_conditions[] = "d.customer_id = ?";
        $params[] = $filters['customer_id'];
    }
    if (!empty($filters['product_id'])) {
        $where_conditions[] = "di.product_id = ?";
        $params[] = $filters['product_id'];
    }
    if (!empty($filters['category_id'])) {
        $where_conditions[] = "p.category_id = ?";
        $params[] = $filters['category_id'];
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    $where_clause .= " AND d.document_type IN ('invoice', 'receipt')";

    switch ($report_type) {
        case 'sales_summary':
            $query = "SELECT 
                DATE(d.document_date) as sale_date,
                COUNT(DISTINCT d.id) as total_orders,
                COUNT(DISTINCT d.customer_id) as unique_customers,
                SUM(di.price * di.quantity) as subtotal,
                SUM(di.tax) as tax_amount,
                SUM(di.discount) as discount_amount,
                SUM(di.total) as total_amount,
                SUM(di.quantity * p.cost) as total_cost,
                (SUM(di.total) - SUM(di.quantity * p.cost)) as gross_profit,
                ROUND(((SUM(di.total) - SUM(di.quantity * p.cost)) / 
                       NULLIF(SUM(di.total), 0)) * 100, 2) as profit_margin
                FROM documents d
                LEFT JOIN document_items di ON d.id = di.document_id
                LEFT JOIN products p ON di.product_id = p.id
                $where_clause
                GROUP BY DATE(d.document_date)
                ORDER BY sale_date DESC";
            break;

        case 'product_sales':
            $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.code as sku,
                c.name as category_name,
                COUNT(DISTINCT d.id) as order_count,
                SUM(di.quantity) as total_quantity,
                SUM(di.price * di.quantity) as subtotal,
                SUM(di.tax) as tax_amount,
                SUM(di.discount) as discount_amount,
                SUM(di.total) as total_amount,
                SUM(di.quantity * p.cost) as total_cost,
                (SUM(di.total) - SUM(di.quantity * p.cost)) as gross_profit,
                ROUND(((SUM(di.total) - SUM(di.quantity * p.cost)) / 
                       NULLIF(SUM(di.total), 0)) * 100, 2) as profit_margin
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN document_items di ON p.id = di.product_id
                LEFT JOIN documents d ON di.document_id = d.id
                $where_clause
                GROUP BY p.id, p.name, p.code, c.name
                HAVING total_quantity > 0
                ORDER BY total_amount DESC";
            break;

        case 'customer_orders':
            $query = "SELECT 
                c.id as customer_id,
                c.name as customer_name,
                c.email,
                c.phone,
                COUNT(DISTINCT d.id) as total_orders,
                SUM(di.total) as total_spent,
                AVG(di.total) as avg_order_value,
                MAX(d.document_date) as last_order_date,
                DATEDIFF(NOW(), MAX(d.document_date)) as days_since_last_order
                FROM customers c
                LEFT JOIN documents d ON c.id = d.customer_id
                LEFT JOIN document_items di ON d.id = di.document_id
                WHERE d.document_type IN ('invoice', 'receipt')
                " . (!empty($where_conditions) ? ' AND ' . implode(' AND ', $where_conditions) : '') . "
                GROUP BY c.id, c.name, c.email, c.phone
                HAVING total_orders > 0
                ORDER BY total_spent DESC";
            break;

        case 'inventory_summary':
            $query = "SELECT 
                p.id as product_id,
                p.code as sku,
                p.name as product_name,
                c.name as category_name,
                p.stock_quantity as current_stock,
                p.min_stock as reorder_level,
                p.price as selling_price,
                p.cost as cost_price,
                (p.price - p.cost) as profit_per_unit,
                ROUND(((p.price - p.cost) / NULLIF(p.cost, 0)) * 100, 2) as profit_margin,
                (p.stock_quantity * p.price) as stock_value,
                (SELECT COUNT(*) FROM document_items di 
                 JOIN documents d ON di.document_id = d.id 
                 WHERE di.product_id = p.id 
                 AND d.document_date BETWEEN ? AND ?) as times_sold
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.active = 1
                ORDER BY p.name";
            $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
            break;

        case 'tax_summary':
            $query = "SELECT 
                t.id as tax_id,
                t.name as tax_name,
                t.rate as tax_rate,
                COUNT(DISTINCT d.id) as transaction_count,
                SUM(di.quantity) as items_sold,
                SUM(di.tax) as total_tax_collected,
                SUM(di.price * di.quantity) as taxable_amount
                FROM tax_rates t
                LEFT JOIN document_items di ON t.id = di.tax_rate_id
                LEFT JOIN documents d ON di.document_id = d.id
                $where_clause
                GROUP BY t.id, t.name, t.rate
                ORDER BY total_tax_collected DESC";
            break;

        case 'user_performance':
            $query = "SELECT 
                u.id as user_id,
                u.username,
                u.name as full_name,
                COUNT(DISTINCT d.id) as total_orders,
                COUNT(DISTINCT d.customer_id) as unique_customers,
                SUM(di.total) as total_sales,
                AVG(di.total) as avg_order_value,
                SUM(di.quantity) as items_sold,
                (SELECT COUNT(*) FROM documents WHERE user_id = u.id AND DATE(document_date) = CURDATE() AND document_type IN ('invoice', 'receipt')) as today_orders,
                (SELECT COALESCE(SUM(total), 0) FROM documents WHERE user_id = u.id AND DATE(document_date) = CURDATE() AND document_type IN ('invoice', 'receipt')) as today_sales
                FROM users u
                LEFT JOIN documents d ON u.id = d.user_id
                LEFT JOIN document_items di ON d.id = di.document_id
                WHERE u.active = 1 
                AND d.document_type IN ('invoice', 'receipt')
                " . (!empty($where_conditions) ? ' AND ' . implode(' AND ', $where_conditions) : '') . "
                GROUP BY u.id, u.username, u.name
                ORDER BY total_sales DESC";
            break;

        default:
            throw new Exception("Invalid report type specified");
    }

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'report_type' => $report_type,
            'record_count' => count($results),
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters
        ];

        return [
            'success' => true,
            'data' => $results,
            'summary' => $summary,
            'query' => $query,
            'params' => $params
        ];
    } catch (PDOException $e) {
        error_log("Report generation error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error generating report: ' . $e->getMessage(),
            'query' => $query,
            'params' => $params
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MTECH UGANDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-hover: #3a5bc7;
            --secondary-color: #6c757d;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            
            /* Sidebar variables */
            --sidebar-bg: #1a1a2e;
            --sidebar-header: #0f3460;
            --sidebar-active: #16213e;
            --sidebar-hover: #0f3460;
            --sidebar-text: rgba(255, 255, 255, 0.85);
            --sidebar-text-hover: #ffffff;
            --sidebar-section: rgba(255, 255, 255, 0.1);
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
            --accent-color: #4e73df;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        .app-container {
            display: flex;
            width: 100%;
            position: relative;
        }

        /* Enhanced Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
            transition: all var(--transition-speed) ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1.5rem 1.2rem;
            background: var(--sidebar-header);
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
            margin: 0;
            letter-spacing: 0.5px;
            background: linear-gradient(90deg, #ffffff, #a0c4ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .sidebar-header .logo-icon {
            font-size: 1.8rem;
            margin-right: 12px;
            color: var(--accent-color);
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section-title {
            padding: 0.6rem 1.5rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.5rem;
            position: relative;
        }

        .menu-section-title:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 1.5rem;
            right: 1.5rem;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.5rem;
            margin: 0.25rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text-hover);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: var(--sidebar-active);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .menu-item.active:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 4px;
            background: var(--accent-color);
        }

        .menu-item i {
            width: 24px;
            font-size: 1.1rem;
            margin-right: 14px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .menu-item.active i {
            color: var(--accent-color);
            transform: scale(1.1);
        }

        .menu-item .notification-badge {
            background: var(--success-color);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: none;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .card-header {
            background-color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .filter-group {
            background-color: #f8f9fc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .filter-group h6 {
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .summary-card {
            height: 100%;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            border-left-width: 6px;
        }

        .summary-card .card-title {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .summary-card .card-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .report-card {
            cursor: pointer;
            transition: all 0.2s ease;
            height: 100%;
        }

        .report-card:hover {
            background-color: #f0f5ff;
            border-color: var(--primary-color);
        }

        .report-card.active {
            background-color: #e6f0ff;
            border-color: var(--primary-color);
        }

        .report-card .card-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .active-filter-badge {
            background-color: var(--primary-color);
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.75rem;
            margin-left: 5px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s;
            backdrop-filter: blur(4px);
        }

        .loading-overlay.show {
            visibility: visible;
            opacity: 1;
        }

        .loading-spinner {
            border: 4px solid rgba(78, 115, 223, 0.1);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .export-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .export-options {
                margin-top: 15px;
                width: 100%;
            }
            
            .filter-group {
                padding: 12px;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1100;
                background: var(--accent-color);
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                font-size: 1.2rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
        }

        /* Animation for sidebar menu items */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .menu-item {
            animation: fadeIn 0.3s ease forwards;
            opacity: 0;
        }

        .menu-item:nth-child(1) { animation-delay: 0.1s; }
        .menu-item:nth-child(2) { animation-delay: 0.15s; }
        .menu-item:nth-child(3) { animation-delay: 0.2s; }
        .menu-item:nth-child(4) { animation-delay: 0.25s; }
        .menu-item:nth-child(5) { animation-delay: 0.3s; }
        .menu-item:nth-child(6) { animation-delay: 0.35s; }
        .menu-item:nth-child(7) { animation-delay: 0.4s; }
        .menu-item:nth-child(8) { animation-delay: 0.45s; }
        .menu-item:nth-child(9) { animation-delay: 0.5s; }
        .menu-item:nth-child(10) { animation-delay: 0.55s; }
        .menu-item:nth-child(11) { animation-delay: 0.6s; }
        .menu-item:nth-child(12) { animation-delay: 0.65s; }
        .menu-item:nth-child(13) { animation-delay: 0.7s; }
        .menu-item:nth-child(14) { animation-delay: 0.75s; }
        .menu-item:nth-child(15) { animation-delay: 0.8s; }

        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .menu-toggle {
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Enhanced Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-chart-line logo-icon"></i>
                <h2>MTECH UGANDA</h2>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-section-title">Main Navigation</div>
                    <a href="dashboard.php" class="menu-item">
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
                    <a href="reports.php" class="menu-item active">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reporting</span>
                        <span class="notification-badge">New</span>
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
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="h3 mb-2"><i class="bi bi-bar-chart me-2"></i>Reports Dashboard</h1>
                    <p class="text-muted">Analyze and export business performance data</p>
                </div>
                <button class="btn btn-primary menu-toggle d-lg-none">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Filters Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Report Filters</h5>
                    <div class="export-options">
                        <button class="btn btn-sm btn-outline-primary" id="toggleFilters">
                            <i class="bi bi-chevron-up me-1"></i>Collapse
                        </button>
                    </div>
                </div>
                <div class="card-body" id="filtersBody">
                    <form id="reportForm" class="mb-4">
                        <div class="row">
                            <!-- Report Type -->
                            <div class="col-md-6 mb-4">
                                <div class="filter-group">
                                    <h6>Report Type</h6>
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <option value="">-- Select Report Type --</option>
                                        <optgroup label="Sales Reports">
                                            <option value="sales_summary" <?php echo $report_type === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                                            <option value="product_sales" <?php echo $report_type === 'product_sales' ? 'selected' : ''; ?>>Product Sales</option>
                                            <option value="customer_orders" <?php echo $report_type === 'customer_orders' ? 'selected' : ''; ?>>Customer Orders</option>
                                        </optgroup>
                                        <optgroup label="Inventory Reports">
                                            <option value="inventory_summary" <?php echo $report_type === 'inventory_summary' ? 'selected' : ''; ?>>Inventory Summary</option>
                                            <option value="low_stock" <?php echo $report_type === 'low_stock' ? 'selected' : ''; ?>>Low Stock Alerts</option>
                                        </optgroup>
                                        <optgroup label="Financial Reports">
                                            <option value="tax_summary" <?php echo $report_type === 'tax_summary' ? 'selected' : ''; ?>>Tax Summary</option>
                                            <option value="profits" <?php echo $report_type === 'profits' ? 'selected' : ''; ?>>Profit Analysis</option>
                                        </optgroup>
                                        <optgroup label="User Reports">
                                            <option value="user_performance" <?php echo $report_type === 'user_performance' ? 'selected' : ''; ?>>User Performance</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Date Range -->
                            <div class="col-md-6 mb-4">
                                <div class="filter-group">
                                    <h6>Date Range <span class="active-filter-badge">Last 30 Days</span></h6>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        <input type="text" class="form-control" id="date_range" name="date_range" value="<?php echo date('Y-m-d', strtotime($start_date)) . ' - ' . date('Y-m-d', strtotime($end_date)); ?>">
                                    </div>
                                    <input type="hidden" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                                    <input type="hidden" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Additional Filters -->
                            <div class="col-md-4 mb-4">
                                <div class="filter-group">
                                    <h6>User</h6>
                                    <select class="form-select" id="filter_user" name="user_id">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo ($user_id ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <h6>Category</h6>
                                    <select class="form-select" id="filter_category" name="category_id">
                                        <option value="">All Categories</option>
                                        <?php 
                                        // Build category tree for the dropdown
                                        $categories_tree = [];
                                        foreach ($categories as $category) {
                                            $parent_id = $category['parent_id'] ?? 0;
                                            if (!isset($categories_tree[$parent_id])) {
                                                $categories_tree[$parent_id] = [];
                                            }
                                            $categories_tree[$parent_id][] = $category;
                                        }
                                        echo buildCategoryTree(0, $categories_tree, 0, $category_id ?? null);
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="filter-group">
                                    <h6>Customer</h6>
                                    <select class="form-select" id="filter_customer" name="customer_id">
                                        <option value="">All Customers</option>
                                        <?php 
                                        foreach ($customers as $customer): 
                                            $customerName = htmlspecialchars($customer['name'] ?? '');
                                            $companyName = !empty($customer['company_name']) ? ' (' . htmlspecialchars($customer['company_name']) . ')' : '';
                                        ?>
                                            <option value="<?php echo $customer['id']; ?>" <?php echo (isset($customer_id) && $customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                                <?php echo $customerName . $companyName; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <h6>Payment Method</h6>
                                    <select class="form-select" id="filter_payment_method" name="payment_method">
                                        <option value="">All Methods</option>
                                        <option value="cash" <?php echo ($_REQUEST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="credit_card" <?php echo ($_REQUEST['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                        <option value="mobile_money" <?php echo ($_REQUEST['payment_method'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                        <option value="bank_transfer" <?php echo ($_REQUEST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="filter-group">
                                    <h6>Location</h6>
                                    <input type="text" class="form-control" id="filter_location" name="location" placeholder="Location" value="<?php echo htmlspecialchars($_REQUEST['location'] ?? ''); ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <h6>Additional Options</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="include_subgroups" name="include_subgroups" value="1" checked>
                                        <label class="form-check-label" for="include_subgroups">Include subcategories</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filters
                            </button>
                            <button type="submit" class="btn btn-primary" id="generateReport">
                                <i class="bi bi-lightning-charge me-1"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <!-- Total Sales Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted text-uppercase small">Total Sales</span>
                                    <h2 class="mt-2 mb-1">UGX <?php echo number_format($total_sales ?? 0, 0); ?></h2>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)); ?>
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-currency-dollar fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Orders Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted text-uppercase small">Total Orders</span>
                                    <h2 class="mt-2 mb-1"><?php echo number_format($total_orders ?? 0); ?></h2>
                                    <small class="text-muted">
                                        <i class="bi bi-arrow-up text-success me-1"></i>
                                        <span class="text-success">0%</span> from last month
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cart-check fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Customers Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted text-uppercase small">Total Customers</span>
                                    <h2 class="mt-2 mb-1"><?php echo number_format($unique_customers ?? 0); ?></h2>
                                    <small class="text-muted">
                                        <i class="bi bi-arrow-up text-success me-1"></i>
                                        <span class="text-success">0%</span> repeat rate
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-people fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Average Order Value Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted text-uppercase small">Avg. Order</span>
                                    <h2 class="mt-2 mb-1">UGX <?php echo number_format($avg_order ?? 0, 0); ?></h2>
                                    <small class="text-muted">
                                        <i class="bi bi-graph-up me-1"></i>
                                        <?php echo ($total_orders ?? 0) . ' orders' ?>
                                    </small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-graph-up fs-4 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Report Results Section -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <h5 class="mb-2 mb-md-0">
                            <i class="bi bi-table me-2"></i>Report Results: 
                            <span class="text-primary">
                                <?php 
                                $report_titles = [
                                    'sales_summary' => 'Sales Summary',
                                    'product_sales' => 'Product Sales',
                                    'customer_orders' => 'Customer Orders',
                                    'inventory_summary' => 'Inventory Summary',
                                    'tax_summary' => 'Tax Summary',
                                    'user_performance' => 'User Performance',
                                    'profits' => 'Profit Analysis'
                                ];
                                echo $report_titles[$report_type] ?? 'Report';
                                ?>
                            </span>
                            <small class="text-muted ms-2">
                                (<?php echo date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)); ?>)
                            </small>
                        </h5>
                        <div class="d-flex gap-2 mt-2 mt-md-0">
                            <button class="btn btn-sm btn-outline-primary" id="printReport">
                                <i class="bi bi-printer me-1"></i>Print
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" id="exportExcel"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel</a></li>
                                    <li><a class="dropdown-item" href="#" id="exportPdf"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</a></li>
                                    <li><a class="dropdown-item" href="#" id="exportCsv"><i class="bi bi-file-earmark-text me-2"></i>CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            // Get report data based on current filters
            $report_data = [];
            $report_summary = [];
            
            try {
                $filters = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'user_id' => $_GET['user_id'] ?? null,
                    'customer_id' => $_GET['customer_id'] ?? null,
                    'product_id' => $_GET['product_id'] ?? null,
                    'category_id' => $_GET['category_id'] ?? null
                ];
                
                $report_result = getReportData($db, $report_type, $filters);
                
                if ($report_result['success']) {
                    $report_data = $report_result['data'];
                    $report_summary = $report_result['summary'];
                    
                    // Calculate summary statistics
                    $total_sales = array_sum(array_column($report_data, 'total_amount'));
                    $total_orders = array_sum(array_column($report_data, 'total_orders'));
                    $unique_customers = count($report_data);
                    $avg_order = $total_orders > 0 ? $total_sales / $total_orders : 0;
                }
            } catch (Exception $e) {
                $error_message = 'Error generating report: ' . $e->getMessage();
                error_log($error_message);
            }
            ?>
            <div class="card-body p-0">
                <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif (empty($report_data)): ?>
                        <div class="alert alert-info m-3">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No data found for the selected criteria.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="reportTable">
                                <thead class="table-light">
                                    <tr>
                                        <?php
                                        // Define table headers based on report type
                                        $headers = [];
                                        $totals = [];
                                        
                                        switch ($report_type) {
                                            case 'sales_summary':
                                                $headers = [
                                                    'Date' => 'sale_date',
                                                    'Orders' => 'total_orders',
                                                    'Customers' => 'unique_customers',
                                                    'Subtotal' => 'subtotal',
                                                    'Tax' => 'tax_amount',
                                                    'Discount' => 'discount_amount',
                                                    'Total' => 'total_amount',
                                                    'Cost' => 'total_cost',
                                                    'Profit' => 'gross_profit',
                                                    'Margin' => 'profit_margin'
                                                ];
                                                break;
                                                
                                            case 'product_sales':
                                                $headers = [
                                                    'Product' => 'product_name',
                                                    'SKU' => 'sku',
                                                    'Category' => 'category_name',
                                                    'Orders' => 'order_count',
                                                    'Qty Sold' => 'total_quantity',
                                                    'Subtotal' => 'subtotal',
                                                    'Tax' => 'tax_amount',
                                                    'Discount' => 'discount_amount',
                                                    'Total' => 'total_amount',
                                                    'Profit' => 'gross_profit',
                                                    'Margin' => 'profit_margin'
                                                ];
                                                break;
                                                
                                            case 'customer_orders':
                                                $headers = [
                                                    'Customer' => 'customer_name',
                                                    'Email' => 'email',
                                                    'Phone' => 'phone',
                                                    'Orders' => 'total_orders',
                                                    'Total Spent' => 'total_spent',
                                                    'Avg. Order' => 'avg_order_value',
                                                    'Last Order' => 'last_order_date',
                                                    'Days Since Last Order' => 'days_since_last_order'
                                                ];
                                                break;
                                                
                                            case 'inventory_summary':
                                                $headers = [
                                                    'Product' => 'product_name',
                                                    'SKU' => 'sku',
                                                    'Category' => 'category_name',
                                                    'In Stock' => 'current_stock',
                                                    'Reorder Level' => 'reorder_level',
                                                    'Selling Price' => 'selling_price',
                                                    'Cost Price' => 'cost_price',
                                                    'Profit/Unit' => 'profit_per_unit',
                                                    'Margin' => 'profit_margin',
                                                    'Stock Value' => 'stock_value',
                                                    'Times Sold' => 'times_sold'
                                                ];
                                                break;
                                                
                                            case 'tax_summary':
                                                $headers = [
                                                    'Tax Name' => 'tax_name',
                                                    'Rate' => 'tax_rate',
                                                    'Transactions' => 'transaction_count',
                                                    'Items Sold' => 'items_sold',
                                                    'Taxable Amount' => 'taxable_amount',
                                                    'Tax Collected' => 'total_tax_collected'
                                                ];
                                                break;
                                                
                                            case 'user_performance':
                                                $headers = [
                                                    'User' => 'full_name',
                                                    'Username' => 'username',
                                                    'Orders' => 'total_orders',
                                                    'Unique Customers' => 'unique_customers',
                                                    'Total Sales' => 'total_sales',
                                                    'Avg. Order' => 'avg_order_value',
                                                    'Items Sold' => 'items_sold',
                                                    'Today\'s Orders' => 'today_orders',
                                                    'Today\'s Sales' => 'today_sales'
                                                ];
                                                break;
                                        }
                                        
                                        // Output table headers
                                        foreach ($headers as $label => $field) {
                                            echo "<th>$label</th>";
                                            // Initialize totals for numeric columns
                                            if (in_array($field, ['total_orders', 'unique_customers', 'subtotal', 'tax_amount', 'discount_amount', 
                                                               'total_amount', 'total_cost', 'gross_profit', 'total_quantity', 'order_count',
                                                               'total_spent', 'avg_order_value', 'transaction_count', 'items_sold', 'taxable_amount',
                                                               'total_tax_collected', 'today_orders', 'today_sales', 'items_sold', 'stock_value',
                                                               'times_sold'])) {
                                                $totals[$field] = 0;
                                            }
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $row_count = 0;
                                    foreach ($report_data as $row): 
                                        $row_count++;
                                        // Limit rows for pagination (client-side for now)
                                        if ($row_count > 100) break;
                                        
                                        echo '<tr>';
                                        foreach ($headers as $label => $field) {
                                            $value = $row[$field] ?? '';
                                            
                                            // Format values based on type
                                            if (strpos($field, '_date') !== false && !empty($value)) {
                                                $value = date('M d, Y', strtotime($value));
                                            } elseif (strpos($field, 'margin') !== false || strpos($field, 'rate') !== false) {
                                                if (is_numeric($value)) {
                                                    $value = number_format($value, 2) . '%';
                                                }
                                            } elseif (is_numeric($value) && $value != 0) {
                                                // Track totals for numeric columns
                                                if (isset($totals[$field])) {
                                                    $totals[$field] += $value;
                                                }
                                                
                                                // Format numeric values
                                                if (strpos($field, 'price') !== false || 
                                                    strpos($field, 'amount') !== false || 
                                                    strpos($field, 'total') !== false ||
                                                    strpos($field, 'cost') !== false ||
                                                    strpos($field, 'profit') !== false ||
                                                    strpos($field, 'spent') !== false ||
                                                    strpos($field, 'value') !== false) {
                                                    $value = 'UGX ' . number_format($value, 0);
                                                } elseif (is_float($value + 0)) {
                                                    $value = number_format($value, 2);
                                                }
                                            }
                                            
                                            echo "<td>$value</td>";
                                        }
                                        echo '</tr>';
                                    endforeach; 
                                    ?>
                                </tbody>
                                <?php if (!empty($totals)): ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <?php 
                                        foreach ($headers as $label => $field) {
                                            $value = '';
                                            if (isset($totals[$field])) {
                                                if (strpos($field, 'margin') !== false || strpos($field, 'rate') !== false) {
                                                    $value = number_format($totals[$field] / $row_count, 2) . '%';
                                                } elseif (strpos($field, 'price') !== false || 
                                                         strpos($field, 'amount') !== false || 
                                                         strpos($field, 'total') !== false ||
                                                         strpos($field, 'cost') !== false ||
                                                         strpos($field, 'profit') !== false ||
                                                         strpos($field, 'spent') !== false ||
                                                         strpos($field, 'value') !== false) {
                                                    $value = 'UGX ' . number_format($totals[$field], 0);
                                                } elseif (is_numeric($totals[$field])) {
                                                    $value = number_format($totals[$field]);
                                                }
                                                echo "<th>$value</th>";
                                            } else {
                                                echo '<th>' . ($label === array_key_first($headers) ? 'Total' : '') . '</th>';
                                            }
                                        }
                                        ?>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                Showing 1 to <?php echo min($row_count, 100); ?> of <?php echo count($report_data); ?> entries
                                <?php if (count($report_data) > 100): ?>
                                <span class="text-warning"> (showing first 100 records)</span>
                                <?php endif; ?>
                            </div>
                            <?php if (count($report_data) > 10): ?>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                                    <li class="page-item"><a class="page-link" href="#">5</a></li>
                                    <li class="page-item"><a class="page-link" href="#">Next</a></li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select an option',
                allowClear: true
            });
            
            // Initialize date range picker
            $('#date_range').daterangepicker({
                startDate: moment().subtract(29, 'days'),
                endDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                alwaysShowCalendars: true,
                autoUpdateInput: true,
                locale: {
                    format: 'YYYY-MM-DD',
                    cancelLabel: 'Clear'
                }
            });
            
            // Update hidden date fields
            $('#date_range').on('apply.daterangepicker', function(ev, picker) {
                $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
                $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
            });
            
            // Toggle filters collapse
            $('#toggleFilters').click(function() {
                const icon = $(this).find('i');
                const text = $(this).find('.btn-text');
                
                if ($('#filtersBody').is(':visible')) {
                    $('#filtersBody').slideUp();
                    icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                    $(this).html('<i class="bi bi-chevron-down me-1"></i>Expand');
                } else {
                    $('#filtersBody').slideDown();
                    icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                    $(this).html('<i class="bi bi-chevron-up me-1"></i>Collapse');
                }
            });
            
            // Report card selection
            $('.report-card').click(function() {
                $('.report-card').removeClass('active');
                $(this).addClass('active');
            });
            
            // Reset filters
            $('#resetFilters').click(function() {
                $('#reportForm')[0].reset();
                $('select').val(null).trigger('change');
                $('#date_range').data('daterangepicker').setStartDate(moment().subtract(29, 'days'));
                $('#date_range').data('daterangepicker').setEndDate(moment());
            });
            
            // Generate report
            $('#generateReport').click(function() {
                $('#loadingOverlay').addClass('show');
                
                // Simulate API call delay
                setTimeout(function() {
                    $('#loadingOverlay').removeClass('show');
                }, 1500);
            });
            
            // Toggle sidebar on mobile
            $('.menu-toggle').click(function() {
                $('.sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection after all operations are complete
if (isset($db)) {
    $db = null;
}
?>