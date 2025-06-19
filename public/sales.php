<?php
// Start the session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Default date range (last 30 days)
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');

// Get filter values from GET parameters or use defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;
$pos_filter = isset($_GET['pos']) ? $_GET['pos'] : '';
$document_number = isset($_GET['document_number']) ? $_GET['document_number'] : '';

// Get sales data from database
try {
    // Make sure we have a function to get database connection
    if (!function_exists('get_db_connection')) {
        function get_db_connection() {
            $host = DB_HOST;
            $dbname = DB_NAME;
            $username = DB_USER;
            $password = DB_PASSWORD;
            
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new PDO($dsn, $username, $password, $options);
        }
    }
    
    $db = get_db_connection();
    
    // Get all cash registers for filter dropdown first (fewer dependencies)
    $stmt = $db->query("SELECT id, name FROM cash_registers WHERE active = 1 ORDER BY name");
    $cash_registers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Base query for documents (sales)
    $query = "SELECT d.id, d.document_number, d.external_document, d.document_type, 
             d.document_date, c.name AS customer_name, u.username AS user, 
             cr.name AS pos, d.total, d.paid_status, d.discount
             FROM documents d
             LEFT JOIN customers c ON d.customer_id = c.id
             LEFT JOIN users u ON d.user_id = u.id
             LEFT JOIN cash_registers cr ON d.cash_register_id = cr.id
             WHERE d.document_date BETWEEN :start_date AND :end_date";
    
    $params = [
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ];
    
    // Add POS filter if specified
    if (!empty($pos_filter)) {
        $query .= " AND cr.id = :pos_id";
        $params[':pos_id'] = $pos_filter;
    }
    
    // Add document number filter if specified
    if (!empty($document_number)) {
        $query .= " AND d.document_number = :document_number";
        $params[':document_number'] = $document_number;
    }
    
    $query .= " ORDER BY d.document_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $documents = [];
    $cash_registers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- jQuery UI for datepicker -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 10px 15px 10px 45px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s;
            cursor: pointer;
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
        
        .sidebar-footer {
            margin-top: auto;
            padding: 15px;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Main content area */
        .content-wrapper {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Sales History Specific Styles */
        .sales-history-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            background-color: var(--dark-bg);
            color: var(--text-light);
        }
        
        .sales-history-header {
            padding: 15px;
            background-color: var(--med-bg);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sales-history-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .close-btn {
            background-color: var(--accent-red);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filters-container {
            padding: 10px 15px;
            background-color: var(--med-bg);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .filter-input {
            background-color: var(--light-bg);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-light);
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .filter-select {
            background-color: var(--light-bg);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-light);
            padding: 5px 10px;
            border-radius: 4px;
            min-width: 120px;
        }
        
        .filter-select option {
            background-color: var(--med-bg);
            color: var(--text-light);
        }
        
        .filter-button {
            background-color: var(--accent-blue);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .sales-table-container {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px;
        }
        
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .sales-table th {
            background-color: var(--med-bg);
            padding: 10px 8px;
            text-align: left;
            font-weight: 500;
            color: var(--text-light);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sales-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--text-light);
        }
        
        .sales-table tr:hover {
            background-color: var(--light-bg);
            cursor: pointer;
        }
        
        .action-icons {
            display: flex;
            gap: 10px;
        }
        
        .action-icon {
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        
        .action-icon:hover {
            color: var(--text-light);
        }
        
        .sales-table-footer {
            padding: 15px;
            background-color: var(--med-bg);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .doc-count {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .total-amount {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-green);
        }
        
        /* Document Items Section */
        .document-items-container {
            margin-top: 10px;
            padding: 10px 15px;
            background-color: var(--med-bg);
            display: none;
        }
        
        .document-items-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .document-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .document-items-table th {
            background-color: var(--light-bg);
            padding: 8px;
            text-align: left;
            font-weight: 500;
            color: var(--text-light);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .document-items-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--text-light);
        }
        
        /* Loading overlay */
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
        
        .spinner {
            margin-bottom: 1rem;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Logout dialog styling */
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
            z-index: 9999;
        }
        
        .logout-content {
            background-color: var(--med-bg);
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        .logout-timer {
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
            color: var(--accent-red);
        }
        
        .logout-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
            }
            
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">MTECH UGANDA</div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="sidebar-menu">
                <a href="welcome.php" class="sidebar-menu-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fas fa-shopping-cart"></i>
                <span>POS</span>
            </div>
            <div class="sidebar-menu">
                <a href="pos/index.php" class="sidebar-menu-item">
                    <i class="fas fa-cash-register"></i>
                    <span>POS Screen</span>
                </a>
                <a href="sales.php" class="sidebar-menu-item active">
                    <i class="fas fa-history"></i>
                    <span>View Sales History</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
            </div>
            <div class="sidebar-menu">
                <a href="inventory/products.php" class="sidebar-menu-item">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="inventory/categories.php" class="sidebar-menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </div>
        </div>
        
        <?php if ($user_role == 'admin' || $user_role == 'manager'): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fas fa-cogs"></i>
                <span>Management</span>
            </div>
            <div class="sidebar-menu">
                <a href="management/users.php" class="sidebar-menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="management/reports.php" class="sidebar-menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="management/settings.php" class="sidebar-menu-item">
                    <i class="fas fa-wrench"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="sidebar-footer">
            <div>Logged in as: <?php echo htmlspecialchars($user_fullname); ?></div>
            <div class="mt-2">
                <a href="#" id="logoutLink" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="sales-history-container">
            <!-- Header -->
            <div class="sales-history-header">
                <div class="sales-history-title">
                    <i class="fas fa-history"></i> Sales History
                </div>
                <button class="close-btn" id="closeBtn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filters-container">
                <form action="sales.php" method="GET" class="w-100 d-flex flex-wrap align-items-center gap-2">
                    <div class="filter-group">
                        <label class="filter-label" for="document_number">Document #:</label>
                        <input type="text" id="document_number" name="document_number" class="filter-input" value="<?php echo htmlspecialchars($document_number); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="pos">POS:</label>
                        <select id="pos" name="pos" class="filter-select">
                            <option value="">All POS</option>
                            <?php foreach ($cash_registers as $cr): ?>
                                <option value="<?php echo $cr['id']; ?>" <?php echo ($pos_filter == $cr['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cr['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group ml-auto">
                        <button type="submit" class="filter-button">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Sales Table -->
            <div class="sales-table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Number</th>
                            <th>External Doc</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>POS</th>
                            <th>User</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($error_message)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                                </td>
                            </tr>
                        <?php elseif (empty($documents)): ?>
                            <tr>
                                <td colspan="10" class="text-center">
                                    <i class="fas fa-info-circle"></i> No documents found matching the criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $total_sales = 0;
                            foreach ($documents as $doc): 
                                $total_sales += $doc['total'];
                            ?>
                                <tr class="document-row" data-id="<?php echo $doc['id']; ?>">
                                    <td><?php echo $doc['id']; ?></td>
                                    <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['document_number']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['external_document'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($doc['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($doc['document_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($doc['pos']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['user']); ?></td>
                                    <td><?php echo number_format($doc['total'], 0); ?></td>
                                    <td>
                                        <?php if ($doc['paid_status'] == 'paid'): ?>
                                            <span class="badge bg-success text-white">Paid</span>
                                        <?php elseif ($doc['paid_status'] == 'partial'): ?>
                                            <span class="badge bg-warning text-dark">Partial</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Footer -->
            <div class="sales-table-footer">
                <div class="doc-count">
                    <?php if (isset($documents)): ?>
                        <?php echo count($documents); ?> document(s) found
                    <?php endif; ?>
                </div>
                <div class="total-amount">
                    <?php if (isset($total_sales)): ?>
                        Total: <?php echo number_format($total_sales, 0); ?> UGX
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Document Items -->
            <div class="document-items-container" id="documentItemsContainer" style="display: none;">
                <div class="document-items-header">Document Items</div>
                <div class="table-responsive">
                    <table class="document-items-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th>Price Before Tax</th>
                                <th>Tax</th>
                                <th>Price</th>
                                <th>Total Before Discount</th>
                                <th>Discount</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="documentItemsTableBody">
                            <!-- Items will be loaded here dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div id="statusMessage">Loading...</div>
    </div>
    
    <!-- Logout Dialog -->
    <div class="logout-dialog" id="logoutDialog">
        <div class="logout-content">
            <h4>Confirm Logout</h4>
            <p>Are you sure you want to log out?</p>
            <div class="logout-buttons">
                <button class="btn btn-danger" id="confirmLogout">Yes, Log out</button>
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- jQuery, Bootstrap & other scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Currency formatter function
            function formatCurrency(amount) {
                return new Intl.NumberFormat('en-UG', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(amount);
            }
            
            // Sidebar toggle
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('collapsed');
            });
            
            // Close button (return to welcome page)
            $('#closeBtn').click(function() {
                window.location.href = 'welcome.php';
            });
            
            // Document row click to show document items
            $('.document-row').click(function() {
                const documentId = $(this).data('id');
                console.log('Clicked document ID:', documentId);
                
                // Show loading overlay
                $('#loadingOverlay').show();
                $('#statusMessage').text('Loading document items...');
                
                // Actual AJAX call to get document items
                $.ajax({
                    url: 'management/ajax/get_document_items.php',
                    type: 'GET',
                    data: { document_id: documentId },
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX response:', response);
                        
                        // Clear existing items
                        $('#documentItemsTableBody').empty();
                        
                        if (response.success && response.items && response.items.length > 0) {
                            // Add items to table
                            response.items.forEach(function(item) {
                                $('#documentItemsTableBody').append(`
                                    <tr>
                                        <td>${item.id}</td>
                                        <td>${item.code || '-'}</td>
                                        <td>${item.name || '-'}</td>
                                        <td>${item.unit_of_measure || '-'}</td>
                                        <td>${item.quantity}</td>
                                        <td>${formatCurrency(item.price_before_tax)}</td>
                                        <td>${formatCurrency(item.tax)}</td>
                                        <td>${formatCurrency(item.price)}</td>
                                        <td>${formatCurrency(item.total_before_discount)}</td>
                                        <td>${formatCurrency(item.discount)}</td>
                                        <td>${formatCurrency(item.total)}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            // Show no items message
                            $('#documentItemsTableBody').append(`
                                <tr>
                                    <td colspan="11" style="text-align: center;">No items found for this document</td>
                                </tr>
                            `);
                        }
                        
                        // Show document items container
                        $('#documentItemsContainer').show();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        $('#documentItemsTableBody').empty();
                        $('#documentItemsTableBody').append(`
                            <tr>
                                <td colspan="11" style="text-align: center;">Error loading document items: ${error}</td>
                            </tr>
                        `);
                        $('#documentItemsContainer').show();
                    },
                    complete: function() {
                        // Hide loading overlay
                        $('#loadingOverlay').hide();
                    }
                });
            });
            
            // Logout dialog
            $('#logoutLink').click(function(e) {
                e.preventDefault();
                $('.logout-dialog').css('display', 'flex');
            });
            
            $('#confirmLogout').click(function() {
                window.location.href = 'logout.php';
            });
            
            $('#cancelLogout').click(function() {
                $('.logout-dialog').hide();
            });
        });
    </script>
</body>
</html>
