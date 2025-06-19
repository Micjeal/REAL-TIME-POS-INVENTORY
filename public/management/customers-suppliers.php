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
    // Function to check if a column exists
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

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTECH UGANDA - Customers & Suppliers</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-color: #ecf0f1;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        /* Base styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Modal Styles */
        .modal-content {
            background-color: var(--secondary-color);
            color: var(--text-color);
            border: none;
            border-radius: 0.5rem;
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Form Elements in Dark Theme */
        .modal-content .form-control, 
        .modal-content .form-select,
        .modal-content .input-group-text {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .modal-content .form-control:focus, 
        .modal-content .form-select:focus {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .modal-content .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .modal-content .form-label,
        .modal-content .form-check-label {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .modal-content .text-muted {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        
        /* DataTables Styling */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
            padding-left: 15px;
            border: 1px solid rgba(0,0,0,0.2);
        }
        
        .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .action-buttons .btn {
            margin-right: 5px;
        }
        
        /* Badge styling */
        .badge.badge-success {
            background-color: var(--success-color);
        }
        
        .badge.badge-danger {
            background-color: var(--danger-color);
        }
        
        /* Sidebar Styles */
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --dark-bg: #1e2130;
            --white-bg: #ffffff;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--dark-bg);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }

        .sidebar-header {
            padding: 1.25rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
            background-color: rgba(0,0,0,0.2);
        }
        
        .sidebar-logo {
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: center;
        }
        
        .logo-fallback {
            width: 60px;
            height: 60px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .sidebar.collapsed .logo-fallback {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        .sidebar-title {
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .sidebar-title {
            display: none;
        }
        
        .sidebar-menu {
            padding: 0.5rem 0;
        }
        
        .sidebar-divider {
            height: 1px;
            background-color: rgba(255,255,255,0.1);
            margin: 1rem 1rem;
        }
        
        .logout-item {
            margin-top: 1rem;
            color: #ff6b6b !important;
        }

        .sidebar-menu-item {
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .sidebar-menu-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            text-decoration: none;
        }

        .sidebar-menu-item.active {
            background-color: var(--primary-color);
            color: #fff;
        }

        .sidebar-menu-item i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content Adjustment */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 1.5rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 15px;
            left: calc(var(--sidebar-width) - 15px);
            z-index: 1001;
            width: 30px;
            height: 30px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .sidebar-toggle:hover {
            background-color: #2980b9;
        }
        
        .sidebar.collapsed + .main-content .sidebar-toggle {
            left: calc(var(--sidebar-collapsed-width) - 15px);
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background-color: var(--white-bg);
        }

        .card-header {
            background-color: var(--dark-bg);
            color: white;
            padding: 15px 20px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tab-content {
            padding: 20px;
        }

        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            padding: 0 20px;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link {
            margin-bottom: -1px;
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            padding: 0.75rem 1rem;
            color: #495057;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: 500;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 0.375rem 0.75rem;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 4px;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge-danger {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .badge-warning {
            background-color: #fff8e1;
            color: #f57f17;
        }

        .modal-content {
            border-radius: 8px;
            border: none;
        }

        .modal-header {
            background-color: var(--dark-bg);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            border-bottom: none;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .action-buttons button {
            margin-right: 5px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s;
        }

        .loading-overlay.show {
            visibility: visible;
            opacity: 1;
        }

        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .form-check-input {
            cursor: pointer;
        }
        
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.25em;
        }
        
        .form-switch .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .contact-type-pill {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.85em;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 50rem;
            margin-right: 0.5rem;
        }
        
        .contact-type-customer {
            background-color: #e3f2fd;
            color: #1976D2;
        }
        
        .contact-type-supplier {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid var(--medium-bg);
        }
        
        table th {
            background-color: var(--light-bg);
            font-weight: 600;
        }
        
        .btn-icon {
            padding: 6px 10px;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border-radius: 4px;
            border: 1px solid var(--medium-bg);
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            background-color: var(--dark-bg);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--medium-bg);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--medium-bg);
        }
        
        .form-check-label {
            font-weight: normal;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .loading-spinner {
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        .loading-text {
            color: white;
            font-size: 18px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-fallback">MTU</div>
            </div>
            <div class="sidebar-title">MTECH UGANDA</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="../welcome.php" class="sidebar-menu-item">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-menu-item">
                <i class="fas fa-box"></i>
                <span class="menu-text">Products</span>
            </a>
            <a href="customers-suppliers.php" class="sidebar-menu-item active">
                <i class="fas fa-users"></i>
                <span class="menu-text">Contacts</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percentage"></i>
                <span class="menu-text">Promotions</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span class="menu-text">Invoices</span>
            </a>
            <a href="reports.php" class="sidebar-menu-item">
                <i class="fas fa-chart-line"></i>
                <span class="menu-text">Reports</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
            <div class="sidebar-divider"></div>
            <a href="../logout.php" class="sidebar-menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Contacts Management</h5>
                </div>
                <div class="d-flex">
                    <span class="text-white me-3"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user_fullname); ?></span>
                    <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
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
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
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
                
                <!-- Suppliers Tab -->
                <div class="tab-pane fade" id="suppliers" role="tabpanel">
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
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
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
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Processing...</div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
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
            
            // Toggle sidebar with animation and icon change
            $(".sidebar-toggle").on("click", function() {
                $(".sidebar").toggleClass("collapsed");
                $(".main-content").toggleClass("expanded");
                
                // Change the toggle icon
                if($(".sidebar").hasClass("collapsed")) {
                    $(this).find("i").removeClass("fa-chevron-left").addClass("fa-chevron-right");
                    // Save the collapsed state to localStorage
                    localStorage.setItem("sidebarCollapsed", "true");
                } else {
                    $(this).find("i").removeClass("fa-chevron-right").addClass("fa-chevron-left");
                    // Save the expanded state to localStorage
                    localStorage.setItem("sidebarCollapsed", "false");
                }
            });
            
            // Check localStorage for sidebar state on page load
            $(document).ready(function() {
                if(localStorage.getItem("sidebarCollapsed") === "true") {
                    $(".sidebar").addClass("collapsed");
                    $(".main-content").addClass("expanded");
                    $(".sidebar-toggle i").removeClass("fa-chevron-left").addClass("fa-chevron-right");
                }
            });
            
            // Handle tab changes
            $('#contactTabs button').on('shown.bs.tab', function (e) {
                // Adjust DataTables when switching tabs
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
            
            // Handle form submission validation
            $('#contactForm').on('submit', function(e) {
                // Form validation
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
            
            // Select All checkboxes
            $('.select-all').on('change', function() {
                const isChecked = $(this).prop('checked');
                const table = $(this).attr('id').replace('selectAll', '').toLowerCase();
                
                // Select/deselect all checkboxes
                $(`.row-checkbox[data-table="${table}"]`).prop('checked', isChecked).trigger('change');
                
                // Update counter and toggle bulk actions panel
                updateBulkActionsPanel(table);
            });
            
            // Individual checkbox selection
            $('.row-checkbox').on('change', function() {
                const table = $(this).data('table');
                
                // Update counter and toggle bulk actions panel
                updateBulkActionsPanel(table);
                
                // Check/uncheck 'select all' based on all checkboxes status
                const totalCheckboxes = $(`.row-checkbox[data-table="${table}"]`).length;
                const checkedCheckboxes = $(`.row-checkbox[data-table="${table}"]:checked`).length;
                
                $(`#selectAll${table.charAt(0).toUpperCase() + table.slice(1)}`).prop('checked', 
                    totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
            });
            
            // Update bulk actions panel visibility and counter
            function updateBulkActionsPanel(table) {
                const checkedCount = $(`.row-checkbox[data-table="${table}"]:checked`).length;
                $(`#${table}SelectedCount`).text(checkedCount);
                
                if (checkedCount > 0) {
                    $(`#${table}BulkActions`).removeClass('d-none');
                } else {
                    $(`#${table}BulkActions`).addClass('d-none');
                }
            }
            
            // Bulk action button click
            $('.bulk-action-btn').on('click', function() {
                const action = $(this).data('action');
                const type = $(this).data('type');
                const selectedIds = [];
                
                // Get all selected IDs
                $(`.row-checkbox[data-table="${type}"]:checked`).each(function() {
                    selectedIds.push($(this).val());
                });
                
                if (selectedIds.length === 0) {
                    return;
                }
                
                // Confirmation for delete action
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
                        if (result.isConfirmed) {
                            performBulkAction(action, selectedIds, type);
                        }
                    });
                } else {
                    performBulkAction(action, selectedIds, type);
                }
            });
            
            // Perform bulk action via AJAX
            function performBulkAction(action, ids, type) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send AJAX request
                $.ajax({
                    url: 'ajax/bulk_action.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: action,
                        ids: ids
                    }),
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                timer: 2000
                            }).then(() => {
                                // Reload the page to refresh the data
                                window.location.reload();
                            });
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
                
                // Show loading while fetching contact data
                Swal.fire({
                    title: 'Loading Contact Data',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Fetch contact data via AJAX
                $.ajax({
                    url: 'ajax/get_contact.php',
                    type: 'GET',
                    data: { id: contactId },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            const contact = response.data;
                            
                            // Fill form with contact data
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
                            
                            // Set checkboxes
                            $('#is_active').prop('checked', parseInt(contact.is_active) === 1);
                            $('#is_customer').prop('checked', parseInt(contact.is_customer) === 1);
                            $('#is_supplier').prop('checked', parseInt(contact.is_supplier) === 1);
                            $('#is_tax_exempt').prop('checked', parseInt(contact.is_tax_exempt) === 1);
                            
                            // Update form for editing
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
                                    alert(response.message || 'Failed to delete customer/supplier');
                                }
                                $('#loadingOverlay').hide();
                            },
                            error: function() {
                                alert('Failed to delete customer/supplier');
                                $('#loadingOverlay').hide();
                            }
                        });
                    }
                }
            });
            
            // Save button
            $('#saveButton').click(function() {
                if ($('#customerForm')[0].checkValidity()) {
                    const formData = {
                        id: $('#customerId').val(),
                        code: $('#code').val(),
                        name: $('#name').val(),
                        tax_number: $('#taxNumber').val(),
                        address: $('#address').val(),
                        country: $('#country').val(),
                        phone: $('#phone').val(),
                        email: $('#email').val(),
                        is_active: $('#isActive').is(':checked') ? 1 : 0,
                        is_customer: $('#isCustomer').is(':checked') ? 1 : 0,
                        is_tax_exempt: $('#isTaxExempt').is(':checked') ? 1 : 0
                    };
                    
                    // Show loading overlay
                    $('#loadingText').text('Saving customer/supplier data...');
                    $('#loadingOverlay').show();
                    
                    $.ajax({
                        url: 'ajax/save_customer_supplier.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#customerModal').modal('hide');
                                location.reload(); // Reload to show updated data
                            } else {
                                alert(response.message || 'Failed to save customer/supplier');
                                $('#loadingOverlay').hide();
                            }
                        },
                        error: function() {
                            alert('Failed to save customer/supplier');
                            $('#loadingOverlay').hide();
                        }
                    });
                } else {
                    $('#customerForm')[0].reportValidity();
                }
            });
        });
    </script>
</body>
</html>
