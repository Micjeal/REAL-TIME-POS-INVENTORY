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

// Get open sales data from database
try {
    $db = get_db_connection();
    
    // Get all cash registers for filter dropdown
    $stmt = $db->query("SELECT id, name FROM cash_registers WHERE active = 1 ORDER BY name");
    $cash_registers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Base query for open documents (sales that are unpaid or partially paid)
    $query = "SELECT d.id, d.document_number, d.external_document, d.document_type, 
             d.document_date, c.name AS customer_name, u.username AS user, 
             cr.name AS pos, d.total, d.paid_status, d.discount
             FROM documents d
             LEFT JOIN customers c ON d.customer_id = c.id
             LEFT JOIN users u ON d.user_id = u.id
             LEFT JOIN cash_registers cr ON d.cash_register_id = cr.id
             WHERE d.paid_status IN ('unpaid', 'partial')
             ORDER BY d.document_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $open_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $open_sales = [];
    $cash_registers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Sales - <?php echo SITE_NAME; ?></title>
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
            padding: 5px 0;
        }
        
        .sidebar-menu-item {
            position: relative;
            display: block;
            padding: 12px 15px 12px 45px;
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
        
        .sidebar-menu-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Content Area */
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--med-bg);
            overflow: hidden;
        }
        
        /* Top Bar */
        .top-bar {
            padding: 15px;
            background-color: var(--dark-bg);
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
        
        /* Sales Content */
        .sales-content {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        /* Sales Table */
        .sales-table-container {
            background-color: var(--dark-bg);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .sales-table th {
            background-color: var(--light-bg);
            color: var(--text-light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sales-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--text-muted);
        }
        
        .sales-table tbody tr:hover {
            background-color: var(--light-bg);
            cursor: pointer;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.paid {
            background-color: var(--accent-green);
            color: white;
        }
        
        .status-badge.unpaid {
            background-color: var(--accent-red);
            color: white;
        }
        
        .status-badge.partial {
            background-color: var(--accent-blue);
            color: white;
        }
        
        .status-badge.cancelled {
            background-color: var(--text-muted);
            color: white;
        }
        
        /* Document Items */
        .document-items-container {
            background-color: var(--dark-bg);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .document-items-header {
            padding: 15px;
            background-color: var(--light-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .document-items-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
        }
        
        .close-items-btn {
            background-color: var(--accent-red);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .document-items-table-wrapper {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .document-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .document-items-table th {
            background-color: var(--light-bg);
            color: var(--text-light);
            padding: 8px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
        }
        
        .document-items-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--text-light);
        }
        
        /* Action Icons */
        .action-icon {
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
            margin-right: 10px;
        }
        
        .action-icon:hover {
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
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .status-message {
            font-size: 1.2rem;
        }
        
        /* Logout dialog */
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
            justify-content: space-between;
            margin-top: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .sidebar-menu-item span,
            .sidebar-section-title span {
                display: none;
            }
            
            .sidebar-menu-item {
                padding: 12px 15px;
                text-align: center;
            }
            
            .sidebar-menu-item i {
                position: static;
                margin: 0;
            }
            
            .content-area {
                width: calc(100% - 60px);
            }
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">MTECH UGANDA</div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <!-- Main Menu -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">
                    <i class="fas fa-th-large"></i>
                    <span>Main Menu</span>
                </div>
                <div class="sidebar-menu">
                    <a href="welcome.php" class="sidebar-menu-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="sales.php" class="sidebar-menu-item">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Sales History</span>
                    </a>
                    <a href="open_sales.php" class="sidebar-menu-item active">
                        <i class="fas fa-shopping-cart"></i>
                        <span>View Open Sales</span>
                    </a>
                </div>
            </div>
            
            <!-- System Menu -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">
                    <i class="fas fa-cog"></i>
                    <span>System</span>
                </div>
                <div class="sidebar-menu">
                    <a href="#" id="logoutLink" class="sidebar-menu-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="sales-history-title">Open Sales</div>
                <button class="close-btn" onclick="window.location.href='welcome.php'">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <!-- Sales Content -->
            <div class="sales-content">
                <!-- Open Sales Table -->
                <div class="sales-table-container">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Document #</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>POS</th>
                                <th>User</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($open_sales)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No open sales found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($open_sales as $sale): ?>
                                    <tr class="document-row" data-id="<?php echo $sale['id']; ?>">
                                        <td><?php echo $sale['id']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($sale['document_date'])); ?></td>
                                        <td><?php echo $sale['document_number']; ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $sale['document_type'])); ?></td>
                                        <td><?php echo $sale['customer_name'] ?? 'Walk-in Customer'; ?></td>
                                        <td><?php echo $sale['pos'] ?? '-'; ?></td>
                                        <td><?php echo $sale['user'] ?? '-'; ?></td>
                                        <td><?php echo number_format($sale['total'], 0, '.', ','); ?> UGX</td>
                                        <td>
                                            <span class="status-badge <?php echo $sale['paid_status']; ?>">
                                                <?php echo ucfirst($sale['paid_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-eye action-icon view-document" title="View Details"></i>
                                            <i class="fas fa-credit-card action-icon process-payment" title="Process Payment" data-id="<?php echo $sale['id']; ?>"></i>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Document Items Container (hidden by default) -->
                <div id="documentItemsContainer" class="document-items-container" style="display: none;">
                    <div class="document-items-header">
                        <h4>Document Items</h4>
                        <button id="closeItemsBtn" class="close-items-btn">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div class="document-items-table-wrapper">
                        <table class="document-items-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Tax</th>
                                    <th>Price+Tax</th>
                                    <th>Total</th>
                                    <th>Discount</th>
                                    <th>Final Total</th>
                                </tr>
                            </thead>
                            <tbody id="documentItemsTableBody">
                                <!-- Items will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <div id="statusMessage" class="status-message">Loading...</div>
    </div>
    
    <!-- Logout Confirmation Dialog -->
    <div id="logoutDialog" class="logout-dialog">
        <div class="logout-content">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to log out?</p>
            <div class="logout-timer" id="logoutTimer">5</div>
            <p>Automatically logging out in <span id="logoutTimer">5</span> seconds</p>
            <div class="logout-buttons">
                <button id="cancelLogout" class="btn btn-secondary">Stay Logged In</button>
                <button id="confirmLogout" class="btn btn-danger">Confirm Logout</button>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- jQuery UI -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle sidebar
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('collapsed');
            });
            
            // Close document items view
            $('#closeItemsBtn').click(function() {
                $('#documentItemsContainer').hide();
            });
            
            // Format currency
            function formatCurrency(amount) {
                return new Intl.NumberFormat('en-UG', {
                    style: 'currency',
                    currency: 'UGX',
                    minimumFractionDigits: 0
                }).format(amount);
            }
            
            // Document row click to show document items
            $('.document-row').click(function() {
                const documentId = $(this).data('id');
                
                // Show loading overlay
                $('#loadingOverlay').show();
                $('#statusMessage').text('Loading document items...');
                
                // AJAX call to get document items
                $.ajax({
                    url: 'management/ajax/get_document_items.php',
                    type: 'GET',
                    data: { document_id: documentId },
                    dataType: 'json',
                    success: function(response) {
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
            
            // Process payment button click
            $('.process-payment').click(function(e) {
                e.stopPropagation(); // Prevent document row click event
                const documentId = $(this).data('id');
                // Redirect to payment processing page (to be implemented)
                alert('Payment processing will be implemented soon for document ID: ' + documentId);
            });
            
            // Logout dialog
            $('#logoutLink').click(function(e) {
                e.preventDefault();
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
            });
            
            $('#confirmLogout').click(function() {
                logout();
            });
            
            $('#cancelLogout').click(function() {
                // Clear the timer
                const timer = $('#logoutDialog').data('timer');
                if (timer) clearInterval(timer);
                
                // Hide the dialog
                $('#logoutDialog').hide();
            });
            
            // Logout function
            function logout() {
                // Show loading overlay
                $('#loadingOverlay').show();
                $('#statusMessage').text('Logging out...');
                
                // Redirect to logout page after a short delay
                setTimeout(function() {
                    window.location.href = 'logout.php';
                }, 1000);
            }
        });
    </script>
</body>
</html>
