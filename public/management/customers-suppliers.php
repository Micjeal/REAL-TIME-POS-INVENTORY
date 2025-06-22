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

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Check if user has permission to access this page
if (!in_array($user_role, ['admin', 'manager'])) {
    header("Location: ../welcome.php");
    exit();
}

// Process form submissions
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = get_db_connection();
        
        // Handle contact actions (add, edit, delete)
        if (isset($_POST['contact_action'])) {
            switch ($_POST['contact_action']) {
                case 'add':
                    // Add new contact
                    $stmt = $db->prepare("INSERT INTO customers_suppliers (
                        code, name, tax_number, address, city, postal_code, country, 
                        phone, email, website, credit_limit, payment_terms, notes, 
                        is_active, is_customer, is_supplier, is_tax_exempt
                    ) VALUES (
                        :code, :name, :tax_number, :address, :city, :postal_code, :country, 
                        :phone, :email, :website, :credit_limit, :payment_terms, :notes, 
                        :is_active, :is_customer, :is_supplier, :is_tax_exempt
                    )");
                    
                    // Bind parameters
                    $stmt->bindValue(':code', $_POST['code']);
                    $stmt->bindValue(':name', $_POST['name']);
                    $stmt->bindValue(':tax_number', $_POST['tax_number'] ?? null);
                    $stmt->bindValue(':address', $_POST['address'] ?? null);
                    $stmt->bindValue(':city', $_POST['city'] ?? null);
                    $stmt->bindValue(':postal_code', $_POST['postal_code'] ?? null);
                    $stmt->bindValue(':country', $_POST['country'] ?? null);
                    $stmt->bindValue(':phone', $_POST['phone'] ?? null);
                    $stmt->bindValue(':email', $_POST['email'] ?? null);
                    $stmt->bindValue(':website', $_POST['website'] ?? null);
                    $stmt->bindValue(':credit_limit', $_POST['credit_limit'] ?? 0);
                    $stmt->bindValue(':payment_terms', $_POST['payment_terms'] ?? null);
                    $stmt->bindValue(':notes', $_POST['notes'] ?? null);
                    $stmt->bindValue(':is_active', isset($_POST['is_active']) ? 1 : 0);
                    $stmt->bindValue(':is_customer', isset($_POST['is_customer']) ? 1 : 0);
                    $stmt->bindValue(':is_supplier', isset($_POST['is_supplier']) ? 1 : 0);
                    $stmt->bindValue(':is_tax_exempt', isset($_POST['is_tax_exempt']) ? 1 : 0);
                    
                    $stmt->execute();
                    $success_message = "Contact successfully added!";
                    break;
                    
                case 'edit':
                    // Update existing contact
                    if (!isset($_POST['contact_id']) || empty($_POST['contact_id'])) {
                        throw new Exception("Contact ID is required for edit operation.");
                    }
                    
                    $stmt = $db->prepare("UPDATE customers_suppliers SET 
                        code = :code, name = :name, tax_number = :tax_number, address = :address, 
                        city = :city, postal_code = :postal_code, country = :country, phone = :phone, 
                        email = :email, website = :website, credit_limit = :credit_limit, 
                        payment_terms = :payment_terms, notes = :notes, is_active = :is_active, 
                        is_customer = :is_customer, is_supplier = :is_supplier, is_tax_exempt = :is_tax_exempt 
                        WHERE id = :id");
                    
                    // Bind parameters
                    $stmt->bindValue(':id', $_POST['contact_id']);
                    $stmt->bindValue(':code', $_POST['code']);
                    $stmt->bindValue(':name', $_POST['name']);
                    $stmt->bindValue(':tax_number', $_POST['tax_number'] ?? null);
                    $stmt->bindValue(':address', $_POST['address'] ?? null);
                    $stmt->bindValue(':city', $_POST['city'] ?? null);
                    $stmt->bindValue(':postal_code', $_POST['postal_code'] ?? null);
                    $stmt->bindValue(':country', $_POST['country'] ?? null);
                    $stmt->bindValue(':phone', $_POST['phone'] ?? null);
                    $stmt->bindValue(':email', $_POST['email'] ?? null);
                    $stmt->bindValue(':website', $_POST['website'] ?? null);
                    $stmt->bindValue(':credit_limit', $_POST['credit_limit'] ?? 0);
                    $stmt->bindValue(':payment_terms', $_POST['payment_terms'] ?? null);
                    $stmt->bindValue(':notes', $_POST['notes'] ?? null);
                    $stmt->bindValue(':is_active', isset($_POST['is_active']) ? 1 : 0);
                    $stmt->bindValue(':is_customer', isset($_POST['is_customer']) ? 1 : 0);
                    $stmt->bindValue(':is_supplier', isset($_POST['is_supplier']) ? 1 : 0);
                    $stmt->bindValue(':is_tax_exempt', isset($_POST['is_tax_exempt']) ? 1 : 0);
                    
                    $stmt->execute();
                    $success_message = "Contact successfully updated!";
                    break;
                    
                case 'delete':
                    // Delete contact
                    if (!isset($_POST['contact_id']) || empty($_POST['contact_id'])) {
                        throw new Exception("Contact ID is required for delete operation.");
                    }
                    
                    $stmt = $db->prepare("DELETE FROM customers_suppliers WHERE id = :id");
                    $stmt->bindValue(':id', $_POST['contact_id']);
                    $stmt->execute();
                    $success_message = "Contact successfully deleted!";
                    break;
                    
                default:
                    throw new Exception("Invalid action specified.");
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

try {
    // Get the database connection
    $db = get_db_connection();
    
    // Create the table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS customers_suppliers (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        name VARCHAR(255) NOT NULL,
        tax_number VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        postal_code VARCHAR(20),
        country VARCHAR(100),
        phone VARCHAR(50),
        email VARCHAR(100),
        website VARCHAR(255),
        credit_limit DECIMAL(15,2) DEFAULT 0,
        payment_terms VARCHAR(100),
        notes TEXT,
        is_active TINYINT(1) DEFAULT 1,
        is_customer TINYINT(1) DEFAULT 1,
        is_supplier TINYINT(1) DEFAULT 0,
        is_tax_exempt TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Check if columns exist and add them if they don't
    function column_exists($db, $table, $column) {
        $column = $db->quote($column);
        $query = "SHOW COLUMNS FROM $table LIKE $column";
        $result = $db->query($query);
        return $result && $result->rowCount() > 0;
    }
    
    // Check and add is_supplier column if it doesn't exist
    if (!column_exists($db, 'customers_suppliers', 'is_supplier')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN is_supplier TINYINT(1) DEFAULT 0");
    }
    
    // Check and add other possibly missing columns
    if (!column_exists($db, 'customers_suppliers', 'city')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN city VARCHAR(100) AFTER address");
    }
    
    if (!column_exists($db, 'customers_suppliers', 'postal_code')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN postal_code VARCHAR(20) AFTER city");
    }
    
    if (!column_exists($db, 'customers_suppliers', 'website')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN website VARCHAR(255) AFTER email");
    }
    
    if (!column_exists($db, 'customers_suppliers', 'credit_limit')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN credit_limit DECIMAL(15,2) DEFAULT 0 AFTER website");
    }
    
    if (!column_exists($db, 'customers_suppliers', 'payment_terms')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN payment_terms VARCHAR(100) AFTER credit_limit");
    }
    
    if (!column_exists($db, 'customers_suppliers', 'notes')) {
        $db->exec("ALTER TABLE customers_suppliers ADD COLUMN notes TEXT AFTER payment_terms");
    }
    
    // Get all customers (is_customer = 1)
    $stmt = $db->query("SELECT * FROM customers_suppliers WHERE is_customer = 1 ORDER BY code");
    $customers = $stmt->fetchAll();
    
    // Get all suppliers (is_supplier = 1)
    $stmt = $db->query("SELECT * FROM customers_suppliers WHERE is_supplier = 1 ORDER BY code");
    $suppliers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Handle form submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_action'])) {
    try {
        $db = get_db_connection();
        
        // Add new contact
        if ($_POST['contact_action'] === 'add') {
            $stmt = $db->prepare("INSERT INTO customers_suppliers (
                code, name, tax_number, address, city, postal_code, country, 
                phone, email, website, credit_limit, payment_terms, notes, 
                is_active, is_customer, is_supplier, is_tax_exempt
            ) VALUES (
                :code, :name, :tax_number, :address, :city, :postal_code, :country, 
                :phone, :email, :website, :credit_limit, :payment_terms, :notes, 
                :is_active, :is_customer, :is_supplier, :is_tax_exempt
            )");
            
            // Bind parameters
            $stmt->bindValue(':code', $_POST['code']);
            $stmt->bindValue(':name', $_POST['name']);
            $stmt->bindValue(':tax_number', $_POST['tax_number'] ?? null);
            $stmt->bindValue(':address', $_POST['address'] ?? null);
            $stmt->bindValue(':city', $_POST['city'] ?? null);
            $stmt->bindValue(':postal_code', $_POST['postal_code'] ?? null);
            $stmt->bindValue(':country', $_POST['country'] ?? null);
            $stmt->bindValue(':phone', $_POST['phone'] ?? null);
            $stmt->bindValue(':email', $_POST['email'] ?? null);
            $stmt->bindValue(':website', $_POST['website'] ?? null);
            $stmt->bindValue(':credit_limit', $_POST['credit_limit'] ?? 0);
            $stmt->bindValue(':payment_terms', $_POST['payment_terms'] ?? null);
            $stmt->bindValue(':notes', $_POST['notes'] ?? null);
            $stmt->bindValue(':is_active', isset($_POST['is_active']) ? 1 : 0);
            $stmt->bindValue(':is_customer', isset($_POST['is_customer']) ? 1 : 0);
            $stmt->bindValue(':is_supplier', isset($_POST['is_supplier']) ? 1 : 0);
            $stmt->bindValue(':is_tax_exempt', isset($_POST['is_tax_exempt']) ? 1 : 0);
            
            $stmt->execute();
            $success_message = 'Contact added successfully!';
        }
        
        // Edit existing contact
        else if ($_POST['contact_action'] === 'edit' && isset($_POST['contact_id'])) {
            $stmt = $db->prepare("UPDATE customers_suppliers SET 
                code = :code, name = :name, tax_number = :tax_number, address = :address, 
                city = :city, postal_code = :postal_code, country = :country, phone = :phone, 
                email = :email, website = :website, credit_limit = :credit_limit, 
                payment_terms = :payment_terms, notes = :notes, is_active = :is_active, 
                is_customer = :is_customer, is_supplier = :is_supplier, is_tax_exempt = :is_tax_exempt 
                WHERE id = :id");
            
            // Bind parameters
            $stmt->bindValue(':id', $_POST['contact_id']);
            $stmt->bindValue(':code', $_POST['code']);
            $stmt->bindValue(':name', $_POST['name']);
            $stmt->bindValue(':tax_number', $_POST['tax_number'] ?? null);
            $stmt->bindValue(':address', $_POST['address'] ?? null);
            $stmt->bindValue(':city', $_POST['city'] ?? null);
            $stmt->bindValue(':postal_code', $_POST['postal_code'] ?? null);
            $stmt->bindValue(':country', $_POST['country'] ?? null);
            $stmt->bindValue(':phone', $_POST['phone'] ?? null);
            $stmt->bindValue(':email', $_POST['email'] ?? null);
            $stmt->bindValue(':website', $_POST['website'] ?? null);
            $stmt->bindValue(':credit_limit', $_POST['credit_limit'] ?? 0);
            $stmt->bindValue(':payment_terms', $_POST['payment_terms'] ?? null);
            $stmt->bindValue(':notes', $_POST['notes'] ?? null);
            $stmt->bindValue(':is_active', isset($_POST['is_active']) ? 1 : 0);
            $stmt->bindValue(':is_customer', isset($_POST['is_customer']) ? 1 : 0);
            $stmt->bindValue(':is_supplier', isset($_POST['is_supplier']) ? 1 : 0);
            $stmt->bindValue(':is_tax_exempt', isset($_POST['is_tax_exempt']) ? 1 : 0);
            
            $stmt->execute();
            $success_message = 'Contact updated successfully!';
        }
        // Delete contact
        else if ($_POST['contact_action'] === 'delete' && isset($_POST['contact_id'])) {
            $stmt = $db->prepare("DELETE FROM customers_suppliers WHERE id = ?");
            $stmt->execute([$_POST['contact_id']]);
            $success_message = 'Contact deleted successfully!';
        }
        
        // Refresh the contacts lists
        $stmt = $db->query("SELECT * FROM customers_suppliers ORDER BY name");
        $all_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clear previous arrays
        $customers = [];
        $suppliers = [];
        
        // Separate customers and suppliers again
        foreach ($all_contacts as $contact) {
            if ($contact['is_customer']) {
                $customers[] = $contact;
            }
            if ($contact['is_supplier'] || !$contact['is_customer']) {
                $suppliers[] = $contact;
            }
        }
        
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers & Suppliers - MTECH UGANDA</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: #fff;
            margin-top: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reporting</a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Customers</div>
                <a href="customers-suppliers.php" class="menu-item active"><i class="fas fa-users"></i> Customers & Suppliers</a>
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
            <div class="menu-section">
                <div class="menu-section-title">Account</div>
                <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
                <a href="../logout.php" class="menu-item" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="top-nav">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Customers & Suppliers</h1>
                <div class="user-menu">
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user_fullname, 0, 1)); ?></div>
                </div>
            </nav>

            <!-- Alerts -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">Contacts Management</h5>
                    <div>
                        <span class="badge bg-info me-2"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
                    </div>
                </div>

                <!-- Nav tabs -->
                <ul class="nav nav-tabs" id="contactTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab">
                            <i class="fas fa-user-tie me-2"></i>Customers
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#suppliers" type="button" role="tab">
                            <i class="fas fa-truck me-2"></i>Suppliers
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content">
                    <!-- Customers Tab -->
                    <div class="tab-pane fade show active" id="customers" role="tabpanel">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Customer List</h5>
                                <div class="d-flex gap-2">
                                    <a href="ajax/export_contacts.php?type=customers" class="btn btn-success">
                                        <i class="fas fa-file-export me-2"></i>Export CSV
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal" id="addCustomerBtn">
                                        <i class="fas fa-plus me-2"></i>Add New Customer
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bulk-actions mb-3 d-none" id="customersBulkActions">
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><span id="customersSelectedCount">0</span> items selected</span>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-success bulk-action-btn" data-action="activate" data-type="customers">
                                            <i class="fas fa-check-circle me-1"></i>Activate
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning bulk-action-btn" data-action="deactivate" data-type="customers">
                                            <i class="fas fa-ban me-1"></i>Deactivate
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger bulk-action-btn" data-action="delete" data-type="customers">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table id="customersTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="form-check">
                                                    <input class="form-check-input select-all" type="checkbox" id="selectAllCustomers">
                                                </div>
                                            </th>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Country</th>
                                            <th>Tax Number</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                        <tr data-id="<?php echo $customer['id']; ?>">
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $customer['id']; ?>" data-table="customers">
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['code']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['phone']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($customer['email']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($customer['country']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($customer['tax_number']) ?: '(none)'; ?></td>
                                            <td>
                                                <?php if ($customer['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-contact-btn" 
                                                        data-id="<?php echo $customer['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-contact-btn" 
                                                        data-id="<?php echo $customer['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Suppliers Tab -->
                    <div class="tab-pane fade" id="suppliers" role="tabpanel">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Supplier List</h5>
                                <div class="d-flex gap-2">
                                    <a href="ajax/export_contacts.php?type=suppliers" class="btn btn-success">
                                        <i class="fas fa-file-export me-2"></i>Export CSV
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal" id="addSupplierBtn">
                                        <i class="fas fa-plus me-2"></i>Add New Supplier
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bulk-actions mb-3 d-none" id="suppliersBulkActions">
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><span id="suppliersSelectedCount">0</span> items selected</span>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-success bulk-action-btn" data-action="activate" data-type="suppliers">
                                            <i class="fas fa-check-circle me-1"></i>Activate
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning bulk-action-btn" data-action="deactivate" data-type="suppliers">
                                            <i class="fas fa-ban me-1"></i>Deactivate
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger bulk-action-btn" data-action="delete" data-type="suppliers">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table id="suppliersTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="form-check">
                                                    <input class="form-check-input select-all" type="checkbox" id="selectAllSuppliers">
                                                </div>
                                            </th>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Country</th>
                                            <th>Tax Number</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($suppliers as $supplier): ?>
                                        <tr data-id="<?php echo $supplier['id']; ?>">
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $supplier['id']; ?>" data-table="suppliers">
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($supplier['code']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['phone']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($supplier['email']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($supplier['country']) ?: '(none)'; ?></td>
                                            <td><?php echo htmlspecialchars($supplier['tax_number']) ?: '(none)'; ?></td>
                                            <td>
                                                <?php if ($supplier['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-contact-btn" 
                                                        data-id="<?php echo $supplier['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-contact-btn" 
                                                        data-id="<?php echo $supplier['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($supplier['name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Contact Modal -->
            <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addContactModalLabel">Add New Contact</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="contactForm" method="post">
                                <input type="hidden" name="contact_action" id="contact_action" value="add">
                                <input type="hidden" name="contact_id" id="contact_id" value="">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="code" class="form-label">Code*</label>
                                        <input type="text" class="form-control" id="code" name="code" required>
                                        <small class="text-muted">Unique identifier for this contact</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Name*</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" name="country">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="postal_code" class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tax_number" class="form-label">Tax Number</label>
                                        <input type="text" class="form-control" id="tax_number" name="tax_number">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" placeholder="https://">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="credit_limit" class="form-label">Credit Limit</label>
                                        <div class="input-group">
                                            <span class="input-group-text">UGX</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="credit_limit" name="credit_limit" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="payment_terms" class="form-label">Payment Terms</label>
                                        <input type="text" class="form-control" id="payment_terms" name="payment_terms" placeholder="e.g., Net 30">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_customer" name="is_customer" value="1" checked>
                                            <label class="form-check-label" for="is_customer">Customer</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_supplier" name="is_supplier" value="1">
                                            <label class="form-check-label" for="is_supplier">Supplier</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_tax_exempt" name="is_tax_exempt" value="1">
                                            <label class="form-check-label" for="is_tax_exempt">Tax Exempt</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Contact</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p id="deleteConfirmText">Are you sure you want to delete this contact?</p>
                            <form id="deleteForm" method="post">
                                <input type="hidden" name="contact_action" value="delete">
                                <input type="hidden" name="contact_id" id="delete_contact_id" value="">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div class="loading-overlay" id="loadingOverlay">
                <div class="loading-spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="loading-text" id="loadingText">Processing...</div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTables for customers and suppliers
            const customersTable = $('#customersTable').DataTable({
                responsive: true,
                dom: '<"top"fl>rt<"bottom"ip>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search customers..."
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on action column
                ]
            });
            
            const suppliersTable = $('#suppliersTable').DataTable({
                responsive: true,
                dom: '<"top"fl>rt<"bottom"ip>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search suppliers..."
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on action column
                ]
            });
            
            // Toggle sidebar on mobile
            $('#sidebarToggle').on("click", function() {
                $('#sidebar').toggleClass("active");
            });

            // Handle tab changes
            $('#contactTabs button').on('shown.bs.tab', function (e) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
            
            // Handle form submission validation
            $('#contactForm').on('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                $(this).addClass('was-validated');
            });
            
            // Function to reset the contact form
            function resetContactForm() {
                $('#contactForm')[0].reset();
                $('#contactForm').removeClass('was-validated');
                $('#contact_id').val('');
                $('#contact_action').val('add');
            }
            
            // Bulk Actions Functionality
            $('.select-all').on('change', function() {
                const isChecked = $(this).prop('checked');
                const table = $(this).attr('id').replace('selectAll', '').toLowerCase();
                
                $(`.row-checkbox[data-table="${table}"]`).prop('checked', isChecked).trigger('change');
                updateBulkActionsPanel(table);
            });
            
            $('.row-checkbox').on('change', function() {
                const table = $(this).data('table');
                updateBulkActionsPanel(table);
                
                const totalCheckboxes = $(`.row-checkbox[data-table="${table}"]`).length;
                const checkedCheckboxes = $(`.row-checkbox[data-table="${table}"]:checked`).length;
                $(`#selectAll${table.charAt(0).toUpperCase() + table.slice(1)}`).prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
            });
            
            function updateBulkActionsPanel(table) {
                const checkedCount = $(`.row-checkbox[data-table="${table}"]:checked`).length;
                $(`#${table}SelectedCount`).text(checkedCount);
                checkedCount > 0 ? $(`#${table}BulkActions`).removeClass('d-none') : $(`#${table}BulkActions`).addClass('d-none');
            }
            
            $('.bulk-action-btn').on('click', function() {
                const action = $(this).data('action');
                const type = $(this).data('type');
                const selectedIds = [];
                
                $(`.row-checkbox[data-table="${type}"]:checked`).each(function() {
                    selectedIds.push($(this).val());
                });
                
                if (selectedIds.length === 0) return;
                
                if (action === 'delete') {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: `You are about to delete ${selectedIds.length} ${type}. This cannot be undone!`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete them!'
                    }).then((result) => {
                        if (result.isConfirmed) performBulkAction(action, selectedIds, type);
                    });
                } else {
                    performBulkAction(action, selectedIds, type);
                }
            });
            
            function performBulkAction(action, ids, type) {
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                $.ajax({
                    url: 'ajax/bulk_action.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: action, ids: ids }),
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                timer: 2000
                            }).then(() => window.location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'An error occurred'
                            });
                        }
                    },
                    error: function() {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while processing your request'
                        });
                    }
                });
            }
            
            // Add Customer Button
            $('#addCustomerBtn').on('click', function() {
                resetContactForm();
                $('#addContactModalLabel').text('Add New Customer');
                $('#is_customer').prop('checked', true);
                $('#is_supplier').prop('checked', false);
                $('#contact_action').val('add');
            });
            
            // Add Supplier Button
            $('#addSupplierBtn').on('click', function() {
                resetContactForm();
                $('#addContactModalLabel').text('Add New Supplier');
                $('#is_customer').prop('checked', false);
                $('#is_supplier').prop('checked', true);
                $('#contact_action').val('add');
            });
            
            // Edit Contact Button Click
            $('.edit-contact-btn').on('click', function() {
                const contactId = $(this).data('id');
                
                Swal.fire({
                    title: 'Loading Contact Data',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                $.ajax({
                    url: 'ajax/get_contact.php',
                    type: 'GET',
                    data: { id: contactId },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            const contact = response.data;
                            $('#contact_id').val(contact.id);
                            $('#code').val(contact.code);
                            $('#name').val(contact.name);
                            $('#phone').val(contact.phone);
                            $('#email').val(contact.email);
                            $('#address').val(contact.address);
                            $('#country').val(contact.country);
                            $('#city').val(contact.city);
                            $('#postal_code').val(contact.postal_code);
                            $('#tax_number').val(contact.tax_number);
                            $('#website').val(contact.website);
                            $('#credit_limit').val(contact.credit_limit);
                            $('#payment_terms').val(contact.payment_terms);
                            $('#notes').val(contact.notes);
                            $('#is_active').prop('checked', parseInt(contact.is_active) === 1);
                            $('#is_customer').prop('checked', parseInt(contact.is_customer) === 1);
                            $('#is_supplier').prop('checked', parseInt(contact.is_supplier) === 1);
                            $('#is_tax_exempt').prop('checked', parseInt(contact.is_tax_exempt) === 1);
                            $('#contact_action').val('edit');
                            $('#addContactModalLabel').text('Edit Contact: ' + contact.name);
                            $('#addContactModal').modal('show');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to load contact data'
                            });
                        }
                    },
                    error: function() {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while fetching contact data'
                        });
                    }
                });
            });
            
            // Delete Contact Button Click
            $('.delete-contact-btn').on('click', function() {
                const contactId = $(this).data('id');
                const contactName = $(this).data('name');
                $('#delete_contact_id').val(contactId);
                $('#deleteConfirmText').text(`Are you sure you want to delete "${contactName}"?`);
                $('#deleteConfirmModal').modal('show');
            });
            
            $('#confirmDeleteBtn').on('click', function() {
                $('#deleteForm').submit();
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                $('.alert-dismissible').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        });
    </script>
</body>
</html>