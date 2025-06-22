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

// Initialize variables
$products = [];
$categories = [];
$tax_rates = [];
$error_message = '';
$success_message = '';

try {
    $db = get_db_connection();

    // Check if categories table exists
    $stmt = $db->query("SHOW TABLES LIKE 'categories'");
    $categories_table_exists = $stmt->rowCount() > 0;

    if (!$categories_table_exists) {
        $db->exec("
            CREATE TABLE categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $db->exec("INSERT INTO categories (name, description) VALUES ('General', 'Default product category')");
    }

    // Check if tax_rates table exists
    $stmt = $db->query("SHOW TABLES LIKE 'tax_rates'");
    $tax_rates_table_exists = $stmt->rowCount() > 0;

    if (!$tax_rates_table_exists) {
        $db->exec("
            CREATE TABLE tax_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                rate DECIMAL(5,2) NOT NULL,
                active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $db->exec("
            INSERT INTO tax_rates (name, rate) VALUES 
                ('VAT 18%', 18.00),
                ('Exempt', 0.00)
        ");
    }

    // Check if products table exists
    $stmt = $db->query("SHOW TABLES LIKE 'products'");
    $products_table_exists = $stmt->rowCount() > 0;

    if (!$products_table_exists) {
        $db->exec("
            CREATE TABLE products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                barcode VARCHAR(50),
                name VARCHAR(255) NOT NULL,
                description TEXT,
                category_id INT,
                unit_of_measure VARCHAR(20) DEFAULT 'pcs',
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate_id INT,
                tax_included TINYINT(1) DEFAULT 1,
                cost DECIMAL(10,2) DEFAULT 0,
                stock_quantity DECIMAL(10,2) DEFAULT 0,
                min_stock DECIMAL(10,2) DEFAULT 0,
                image_path VARCHAR(255),
                active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL
            )
        ");
    }

    // Get all categories
    $stmt = $db->query("SELECT * FROM categories WHERE active = 1 ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all tax rates
    $stmt = $db->query("SELECT * FROM tax_rates WHERE active = 1 ORDER BY name");
    $tax_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all products with category information
    $stmt = $db->query("
        SELECT p.*, c.name as category_name, t.rate as tax_rate
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN tax_rates t ON p.tax_rate_id = t.id
        ORDER BY p.name
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Handle category form submission (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    try {
        $db = get_db_connection();

        // Add new category
        if ($_POST['category_action'] === 'add') {
            $stmt = $db->prepare("INSERT INTO categories (name, description, active) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['category_name'],
                $_POST['category_description'] ?: null,
                isset($_POST['category_is_active']) ? 1 : 0
            ]);
            $success_message = 'Category added successfully!';
        }
        // Edit existing category
        elseif ($_POST['category_action'] === 'edit' && isset($_POST['category_id'])) {
            $stmt = $db->prepare("UPDATE categories SET name = ?, description = ?, active = ? WHERE id = ?");
            $stmt->execute([
                $_POST['category_name'],
                $_POST['category_description'] ?: null,
                isset($_POST['category_is_active']) ? 1 : 0,
                $_POST['category_id']
            ]);
            $success_message = 'Category updated successfully!';
        }
        // Delete category
        elseif ($_POST['category_action'] === 'delete' && isset($_POST['category_id'])) {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            $success_message = 'Category deleted successfully!';
        }

        // Refresh categories list
        $stmt = $db->query("SELECT * FROM categories WHERE active = 1 ORDER BY name");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Handle product form submission (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_action'])) {
    try {
        $db = get_db_connection();

        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $image_name = basename($_FILES['image_path']['name']);
            $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($image_ext, $allowed_exts)) {
                throw new Exception('Invalid image format. Supported formats: JPG, PNG, GIF, WEBP');
            }
            $new_image_name = uniqid('prod_') . '.' . $image_ext;
            $image_path = $upload_dir . $new_image_name;
            if (!move_uploaded_file($_FILES['image_path']['tmp_name'], $image_path)) {
                throw new Exception('Failed to upload image');
            }
            $image_path = 'uploads/products/' . $new_image_name; // Store relative path
        }

        // Add new product
        if ($_POST['product_action'] === 'add') {
            $stmt = $db->prepare("
                INSERT INTO products (
                    code, name, barcode, description, category_id, unit_of_measure,
                    price, tax_rate_id, tax_included, cost,
                    stock_quantity, min_stock, image_path, active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['product_code'],
                $_POST['product_name'],
                $_POST['barcode'] ?: null,
                $_POST['product_description'] ?: null,
                $_POST['product_category'] ?: null,
                $_POST['unit_of_measure'] ?: 'pcs',
                $_POST['price'],
                $_POST['tax_rate_id'] ?: null,
                isset($_POST['tax_included']) ? (int)$_POST['tax_included'] : 1,
                $_POST['cost'] ?: 0,
                $_POST['stock_quantity'] ?: 0,
                $_POST['min_stock'] ?: 0,
                $image_path,
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $success_message = 'Product added successfully!';
        }
        // Edit existing product
        elseif ($_POST['product_action'] === 'edit' && isset($_POST['product_id'])) {
            // Handle image update
            if ($image_path) {
                // Delete old image if exists
                $stmt = $db->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$_POST['product_id']]);
                $old_image = $stmt->fetchColumn();
                if ($old_image && file_exists($old_image)) {
                    unlink($old_image);
                }
            } else {
                // Keep existing image
                $stmt = $db->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$_POST['product_id']]);
                $image_path = $stmt->fetchColumn();
            }

            $stmt = $db->prepare("
                UPDATE products SET
                    code = ?, name = ?, barcode = ?, description = ?, category_id = ?,
                    unit_of_measure = ?, price = ?, tax_rate_id = ?, tax_included = ?,
                    cost = ?, stock_quantity = ?, min_stock = ?, image_path = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['product_code'],
                $_POST['product_name'],
                $_POST['barcode'] ?: null,
                $_POST['product_description'] ?: null,
                $_POST['product_category'] ?: null,
                $_POST['unit_of_measure'] ?: 'pcs',
                $_POST['price'],
                $_POST['tax_rate_id'] ?: null,
                isset($_POST['tax_included']) ? (int)$_POST['tax_included'] : 1,
                $_POST['cost'] ?: 0,
                $_POST['stock_quantity'] ?: 0,
                $_POST['min_stock'] ?: 0,
                $image_path,
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['product_id']
            ]);
            $success_message = 'Product updated successfully!';
        }
        // Delete product
        elseif ($_POST['product_action'] === 'delete' && isset($_POST['product_id'])) {
            // Delete product image if exists
            $stmt = $db->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmt->execute([$_POST['product_id']]);
            $old_image = $stmt->fetchColumn();
            if ($old_image && file_exists($old_image)) {
                unlink($old_image);
            }

            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['product_id']]);
            $success_message = 'Product deleted successfully!';
        }

        // Refresh products list
        $stmt = $db->query("
            SELECT p.*, c.name as category_name, t.rate as tax_rate
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN tax_rates t ON p.tax_rate_id = t.id
            ORDER BY p.name
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Close the database connection
        $db = null;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $error_message = 'Product code already exists. Please use a unique code.';
        } else {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $error_message = 'An error occurred: ' . $e->getMessage();
    }
}

// Set default empty array if products is not set
if (!isset($products)) {
    $products = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - MTECH UGANDA</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Custom fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }

        /* App Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Menu Toggle Button */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Sidebar Styles - Modern Glass Morphism */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: rgba(26, 35, 126, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.2);
            overflow-y: auto;
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1.5rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-align: center;
        }

        /* Menu Sections */
        .menu-section {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .menu-section:last-child {
            border-bottom: none;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            position: relative;
        }
        
        .menu-section-title:after {
            content: '';
            position: absolute;
            left: 1.5rem;
            right: 1.5rem;
            bottom: -0.25rem;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            border-left: 4px solid transparent;
            margin: 0.15rem 1rem;
            border-radius: 8px;
        }

        .menu-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(4px);
        }

        .menu-item.active {
            background: linear-gradient(90deg, rgba(67, 97, 238, 0.2), transparent);
            color: #fff;
            border-left-color: var(--primary);
            font-weight: 500;
        }

        .menu-item:hover i {
            color: #fff;
        }

        .menu-item.active i {
            color: var(--primary-light);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
            transition: all 0.3s ease;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 5rem 1.5rem 2rem;
            }

            .sidebar.active + .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }

            .top-nav {
                left: 0;
                width: 100%;
                padding-left: 1.5rem;
            }
        }

        /* Nav Link Styles */
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.15rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-transform: uppercase;
        }

        .user-details {
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
        }

        /* Card Styles */
        .card {
            background: #fff;
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .card-btn {
            background: none;
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .card-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--gray-100);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            border-bottom: none;
            white-space: nowrap;
        }

        .table td {
            padding: 14px 16px;
            vertical-align: middle;
            border-color: var(--gray-200);
            color: var(--gray-800);
        }

        .table tr:last-child td {
            border-bottom: 1px solid var(--gray-200);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }

        /* Badge Styles */
        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
            font-size: 0.75rem;
            border-radius: 50px;
            letter-spacing: 0.3px;
        }

        .badge.bg-success {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
        }

        .badge.bg-danger {
            background-color: #ffebee !important;
            color: #c62828 !important;
        }

        .badge.bg-warning {
            background-color: #fff8e1 !important;
            color: #f57f17 !important;
        }

        /* Button Styles */
        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn i {
            font-size: 0.9em;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #3a56d4;
            border-color: #3a56d4;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            color: var(--gray-600);
            border-color: var(--gray-300);
        }

        .btn-outline-secondary:hover {
            background-color: var(--gray-100);
            color: var(--dark);
            border-color: var(--gray-400);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Form Styles */
        .form-control, .form-select {
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Tab Styles */
        .nav-tabs {
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            color: var(--gray-600);
            font-weight: 500;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px 8px 0 0;
            margin-right: 0.5rem;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary);
            background-color: rgba(67, 97, 238, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom: 3px solid var(--primary);
            font-weight: 600;
        }

        .tab-content {
            padding: 1.5rem;
            background: #fff;
            border-radius: 0 0 12px 12px;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: var(--primary);
            color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1rem 1.5rem;
        }

        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Product Image */
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .action-btns .btn {
            padding: 0.35rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* Category Badge */
        .category-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 0.4em 0.8em;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.4em 0.8em;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge i {
            font-size: 0.7em;
        }

        .status-active {
            background-color: rgba(67, 160, 71, 0.1);
            color: #43a047;
            border: 1px solid rgba(67, 160, 71, 0.2);
        }

        .status-inactive {
            background-color: rgba(239, 83, 80, 0.1);
            color: #ef5350;
            border: 1px solid rgba(239, 83, 80, 0.2);
        }

        /* Search Box */
        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding-left: 2.5rem;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            z-index: 5;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }

        /* Menu Toggle Button */
        .menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1001;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .category-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            display: inline-block;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .data-table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            background-color: var(--gray-50);
            padding: 0.75rem 1rem;
        }

        .data-table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid var(--gray-100);
        }

        .data-table tr:last-child td {
            border-bottom: 1px solid var(--gray-100);
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
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
                    <a href="dashboard.php" class="menu-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="documents.php" class="menu-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Documents</span>
                    </a>
                    <a href="products.php" class="menu-item active">
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
                        <span>Company</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Account</div>
                    <a href="profile.php" class="menu-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="../logout.php" class="menu-item" id="logoutBtn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <h1 class="page-title">Products Management</h1>
            <div class="user-info">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <span class="user-role"><?php echo ucfirst($user_role); ?></span>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="dashboard-content">
            <div class="card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Product Management</h3>
                    <div class="card-actions">
                        <!--<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-tag me-2"></i>Add Category
                        </button>-->
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="productsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="true">
                                <i class="fas fa-boxes me-2"></i>Products
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">
                                <i class="fas fa-tags me-2"></i>Categories
                            </button>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content">
                        <!-- Products Tab -->
                        <div class="tab-pane fade show active" id="products" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Product List</h5>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                    <i class="fas fa-plus me-2"></i>Add New Product
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table id="productsTable" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th class="text-end">Cost Price</th>
                                            <th class="text-end">Selling Price</th>
                                            <th class="text-end">Tax Rate</th>
                                            <th class="text-end">Stock</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                        <tr data-id="<?php echo $product['id']; ?>">
                                            <td><?php echo htmlspecialchars($product['code']); ?></td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>
                                                <?php if (!empty($product['category_name'])): ?>
                                                    <span class="category-pill"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Uncategorized</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">UGX <?php echo number_format($product['cost'], 2); ?></td>
                                            <td class="text-end">UGX <?php echo number_format($product['price'], 2); ?></td>
                                            <td class="text-end"><?php echo $product['tax_rate'] ? number_format($product['tax_rate'], 2) . '%' : 'None'; ?></td>
                                            <td class="text-end">
                                                <?php 
                                                $stock_class = 'text-success';
                                                $icon = 'check-circle';
                                                if ($product['stock_quantity'] <= 0) {
                                                    $stock_class = 'text-danger';
                                                    $icon = 'times-circle';
                                                } elseif ($product['stock_quantity'] <= $product['min_stock']) {
                                                    $stock_class = 'text-warning';
                                                    $icon = 'exclamation-circle';
                                                }
                                                ?>
                                                <span class="d-flex align-items-center justify-content-end gap-1">
                                                    <i class="fas fa-<?php echo $icon; ?> <?php echo $stock_class; ?>"></i>
                                                    <?php echo number_format($product['stock_quantity'], 2); ?> 
                                                    <small class="text-muted"><?php echo htmlspecialchars($product['unit_of_measure'] ?? 'pcs'); ?></small>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($product['active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary edit-product-btn" 
                                                            data-id="<?php echo $product['id']; ?>"
                                                            data-bs-toggle="tooltip" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger delete-product-btn" 
                                                            data-id="<?php echo $product['id']; ?>" 
                                                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-bs-toggle="tooltip" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Categories Tab -->
                        <div class="tab-pane fade" id="categories" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Categories</h5>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus me-2"></i>Add Category
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table id="categoriesTable" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th class="text-center">Products</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Initialize categories array if not set
                                        if (!isset($categories)) {
                                            $categories = [];
                                        }
                                        
                                        if (!empty($categories)) {
                                            foreach ($categories as $category) {
                                                // Count products in this category
                                                $product_count = 0;
                                                if (isset($products)) {
                                                    foreach ($products as $product) {
                                                        if (isset($product['category_id']) && $product['category_id'] == $category['id']) {
                                                            $product_count++;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="category-color" style="background-color: <?php echo htmlspecialchars($category['color'] ?? '#4361ee'); ?>;"></span>
                                                            <span class="ms-2"><?php echo htmlspecialchars($category['name'] ?? ''); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($category['description'])): ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($category['description']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">No description</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary rounded-pill"><?php echo $product_count; ?> products</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (isset($category['active']) && $category['active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-primary edit-category-btn" 
                                                                    data-id="<?php echo $category['id'] ?? ''; ?>"
                                                                    data-bs-toggle="tooltip" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger delete-category-btn" 
                                                                    data-id="<?php echo $category['id'] ?? ''; ?>" 
                                                                    data-products="<?php echo $product_count; ?>"
                                                                    data-name="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                                                                    data-bs-toggle="tooltip" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                                    <p class="mb-0">No categories found. Add your first category to get started.</p>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Add/Edit Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title mb-0" id="addProductModalLabel">
                    <i class="fas fa-box me-2"></i>Add New Product
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="productForm" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="product_action" id="product_action" value="add">
                    <input type="hidden" name="product_id" id="product_id" value="">
                    <input type="hidden" name="current_image" id="current_image" value="">

                    <!-- Basic Information -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="product_code" class="form-label fw-medium">
                                Product Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control" id="product_code" name="product_code" maxlength="50" required>
                            </div>
                            <div class="invalid-feedback">Please enter a unique product code.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="product_name" class="form-label fw-medium">
                                Product Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-box"></i></span>
                                <input type="text" class="form-control" id="product_name" name="product_name" maxlength="255" required>
                            </div>
                            <div class="invalid-feedback">Please enter a product name.</div>
                        </div>
                    </div>

                    <!-- Description and Category -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label for="product_description" class="form-label fw-medium">
                                Description
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                <textarea class="form-control" id="product_description" name="product_description" rows="3" placeholder="Optional description"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="product_category" class="form-label fw-medium">
                                Category
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                <select class="form-select" id="product_category" name="product_category">
                                    <option value="">-- Select Category --</option>
                                    <?php
                                    // Function to build hierarchical category options
                                    function buildCategoryOptions($categories, $parent_id = null, $prefix = '') {
                                        foreach ($categories as $category) {
                                            if ($category['parent_id'] == $parent_id) {
                                                echo '<option value="' . $category['id'] . '">' . htmlspecialchars($prefix . $category['name']) . '</option>';
                                                buildCategoryOptions($categories, $category['id'], $prefix . '— ');
                                            }
                                        }
                                    }
                                    buildCategoryOptions($categories, null);
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing and Tax -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="cost" class="form-label fw-medium">
                                Cost Price
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-money-bill-wave"></i></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="cost" name="cost" value="0">
                                <span class="input-group-text">UGX</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="price" class="form-label fw-medium">
                                Selling Price <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
                                <span class="input-group-text">UGX</span>
                            </div>
                            <div class="invalid-feedback">Please enter a valid selling price.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="tax_rate_id" class="form-label fw-medium">
                                Tax Rate
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                                <select class="form-select" id="tax_rate_id" name="tax_rate_id">
                                    <option value="">-- Select Tax Rate --</option>
                                    <?php foreach ($tax_rates as $tax_rate): ?>
                                        <option value="<?php echo $tax_rate['id']; ?>">
                                            <?php echo htmlspecialchars($tax_rate['name']) . ' (' . number_format($tax_rate['rate'], 2) . '%)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="tax_included" class="form-label fw-medium">
                                Tax Included
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-receipt"></i></span>
                                <select class="form-select" id="tax_included" name="tax_included">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="stock_quantity" class="form-label fw-medium">
                                Stock Quantity
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="min_stock" class="form-label fw-medium">
                                Min. Stock Level
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-exclamation-triangle"></i></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="min_stock" name="min_stock" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="unit_of_measure" class="form-label fw-medium">
                                Unit of Measure
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-balance-scale"></i></span>
                                <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure" maxlength="20" placeholder="e.g., pcs, kg, liters" value="pcs">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="barcode" class="form-label fw-medium">
                                Barcode
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control" id="barcode" name="barcode" maxlength="50" placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <!-- Product Image -->
                    <div class="mb-4">
                        <label for="image_path" class="form-label fw-medium">
                            Product Image
                        </label>
                        <div class="image-upload-container border rounded p-3 text-center">
                            <div class="mb-3">
                                <img id="image_preview" class="img-thumbnail" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 150' width='200' height='150'%3E%3Crect width='100%25' height='100%25' fill='%23f8f9fa'/%3E%3Ctext x='50%25' y='50%25' font-family='Arial' font-size='14' text-anchor='middle' dominant-baseline='middle' fill='%236c757d'%3ENo image%3C/text%3E%3C/svg%3E" 
                                     alt="Image Preview" style="max-height: 200px;">
                            </div>
                            <div class="d-flex flex-column align-items-center">
                                <label class="btn btn-outline-primary btn-sm mb-2" for="image_path">
                                    <i class="fas fa-upload me-1"></i> Choose Image
                                </label>
                                <input type="file" class="d-none" id="image_path" name="image_path" 
                                       accept="image/png,image/jpeg,image/jpg,image/gif,image/webp">
                                <small class="form-text text-muted">
                                    Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label fw-medium" for="is_active">
                                <i class="fas fa-check-circle text-success me-1"></i> Product is Active
                            </label>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Product
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="icon-container bg-danger-soft rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                            <i class="fas fa-trash-alt text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="fw-bold mb-2" id="deleteConfirmTitle">Delete Item?</h5>
                        <p class="text-muted mb-0" id="deleteConfirmText">Are you sure you want to delete this item? This action cannot be undone.</p>
                        <div class="alert alert-warning mt-3 d-none" id="deleteWarningAlert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="deleteWarningText"></span>
                        </div>
                    </div>
                    <form id="deleteForm" method="post">
                        <input type="hidden" id="delete_type" name="delete_type" value="">
                        <input type="hidden" id="delete_id" name="delete_id" value="">
                    </form>
                </div>
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-message">Processing...</div>
    </div>

    <!-- Required JS Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    document.body.classList.toggle('sidebar-active');
                });
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 992) {
                        if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                            sidebar.classList.remove('active');
                            document.body.classList.remove('sidebar-active');
                        }
                    }
                });
                
                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 992) {
                        sidebar.classList.remove('active');
                        document.body.classList.remove('sidebar-active');
                    }
                });
            }
            
            // Enable tooltips
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Image preview for product image upload
            document.getElementById('image_path').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const preview = document.getElementById('image_preview');
                        preview.src = event.target.result;
                        preview.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Initialize form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Initialize DataTables with consistent styling
            // Initialize Products DataTable
            const productsTable = $('#productsTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                ajax: {
                    url: 'api/products.php',
                    type: 'GET',
                    data: function(d) {
                        d.action = 'list';
                    },
                    dataSrc: function(json) {
                        if (json && json.data) {
                            return json.data;
                        }
                        return [];
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Error loading products:', error);
                        showToast('danger', 'Error loading products. Please try again.');
                        return [];
                    }
                },
                language: {
                    search: "<i class='fas fa-search me-1'></i>",
                    searchPlaceholder: "Search products...",
                    lengthMenu: "_MENU_ items per page",
                    zeroRecords: "No matching products found",
                    info: "Showing _START_ to _END_ of _TOTAL_ products",
                    infoEmpty: "No products available",
                    infoFiltered: "(filtered from _MAX_ total products)",
                    paginate: {
                        first: "<i class='fas fa-angle-double-left'></i>",
                        last: "<i class='fas fa-angle-double-right'></i>",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    },
                    loadingRecords: "<div class='spinner-border text-primary' role='status'><span class='visually-hidden'>Loading...</span></div>",
                    processing: "<div class='spinner-border spinner-border-sm text-primary me-2' role='status'><span class='visually-hidden'>Loading...</span></div> Processing..."
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<div class='table-responsive'><table class='table table-hover' style='margin-bottom: 0;'><thead class='table-light'><tr>" +
                     "<th>Code</th>" +
                     "<th>Name</th>" +
                     "<th>Category</th>" +
                     "<th class='text-end'>Cost</th>" +
                     "<th class='text-end'>Price</th>" +
                     "<th class='text-end'>Tax</th>" +
                     "<th class='text-end'>Stock</th>" +
                     "<th class='text-center'>Status</th>" +
                     "<th class='text-center'>Actions</th>" +
                     "</tr></thead><tbody></tbody></table></div>" +
                     "<div class='row mt-3'><div class='col-sm-12 col-md-5'>" +
                     "<div class='dataTables_info' id='productsTable_info' role='status' aria-live='polite'></div></div>" +
                     "<div class='col-sm-12 col-md-7'><div class='dataTables_paginate paging_simple_numbers' id='productsTable_paginate'>" +
                     "<ul class='pagination justify-content-end mb-0'></ul></div></div></div>",
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                order: [[1, 'asc']],
                columnDefs: [
                    { orderable: false, targets: -1 }, // Disable sorting on action column
                    { className: 'align-middle', targets: '_all' } // Center align all cells
                ],
                initComplete: function() {
                    // Reinitialize tooltips after table is loaded
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                },
                drawCallback: function() {
                    // Handle any post-draw operations
                    $('.dataTables_processing').addClass('d-flex align-items-center justify-content-center');
                },
                columns: [
                    { data: 'code' },
                    { data: 'name' },
                    { 
                        data: 'category_name',
                        render: function(data, type, row) {
                            return data || '<span class="text-muted">Uncategorized</span>';
                        }
                    },
                    { 
                        data: 'cost',
                        className: 'text-end',
                        render: function(data) {
                            return 'UGX ' + parseFloat(data).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    },
                    { 
                        data: 'price',
                        className: 'text-end',
                        render: function(data) {
                            return 'UGX ' + parseFloat(data).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    },
                    { 
                        data: 'tax_rate',
                        className: 'text-end',
                        render: function(data) {
                            return data ? parseFloat(data).toFixed(2) + '%' : 'None';
                        }
                    },
                    { 
                        data: 'stock_quantity',
                        className: 'text-end',
                        render: function(data, type, row) {
                            const unit = row.unit_of_measure || 'pcs';
                            let stockClass = 'text-success';
                            let icon = 'check-circle';
                            
                            if (data <= 0) {
                                stockClass = 'text-danger';
                                icon = 'times-circle';
                            } else if (data <= (row.min_stock || 0)) {
                                stockClass = 'text-warning';
                                icon = 'exclamation-circle';
                            }
                            
                            return `
                                <span class="d-flex align-items-center justify-content-end gap-1">
                                    <i class="fas fa-${icon} ${stockClass}"></i>
                                    ${parseFloat(data).toLocaleString('en-US')}
                                    <small class="text-muted">${unit}</small>
                                </span>`;
                        }
                    },
                    { 
                        data: 'active',
                        className: 'text-center',
                        render: function(data) {
                            return data == 1 
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-danger">Inactive</span>';
                        }
                    },
                    {
                        data: 'id',
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary edit-product-btn" 
                                            data-id="${data}" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger delete-product-btn" 
                                            data-id="${data}" data-name="${row.name.replace(/"/g, '&quot;')}" 
                                            data-bs-toggle="tooltip" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                        }
                    }
                ]
            });

            // Categories DataTable
            const categoriesTable = $('#categoriesTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                ajax: {
                    url: 'api/categories.php',
                    type: 'GET',
                    data: function(d) {
                        d.action = 'list';
                    },
                    dataSrc: function(json) {
                        if (json && json.data) {
                            return json.data;
                        }
                        console.error('Error loading categories:', json && json.message ? json.message : 'Unknown error');
                        showToast('danger', 'Failed to load categories. Please try again.');
                        return [];
                    }
                },
                language: {
                    search: "<i class='fas fa-search me-1'></i>",
                    searchPlaceholder: "Search categories...",
                    lengthMenu: "_MENU_ items per page",
                    zeroRecords: "No matching categories found",
                    info: "Showing _START_ to _END_ of _TOTAL_ categories",
                    infoEmpty: "No categories available",
                    infoFiltered: "(filtered from _MAX_ total categories)",
                    paginate: {
                        first: "<i class='fas fa-angle-double-left'></i>",
                        last: "<i class='fas fa-angle-double-right'></i>",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<div class='table-responsive'><table class='table table-hover' style='margin-bottom: 0;'><thead class='table-light'><tr>" +
                     "<th>Name</th>" +
                     "<th>Description</th>" +
                     "<th class='text-center'>Products</th>" +
                     "<th class='text-center'>Status</th>" +
                     "<th class='text-center'>Actions</th>" +
                     "</tr></thead><tbody></tbody></table></div>" +
                     "<div class='row mt-3'><div class='col-sm-12 col-md-5'>" +
                     "<div class='dataTables_info' id='categoriesTable_info' role='status' aria-live='polite'></div></div>" +
                     "<div class='col-sm-12 col-md-7'><div class='dataTables_paginate paging_simple_numbers' id='categoriesTable_paginate'>" +
                     "<ul class='pagination justify-content-end mb-0'></ul></div></div></div>",
                lengthMenu: [10, 25, 50, 100],
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on action column
                ],
                columns: [
                    { 
                        data: 'name',
                        render: function(data, type, row) {
                            const color = row.color || '#4361ee';
                            return `
                                <div class="d-flex align-items-center">
                                    <div class="category-color" style="background-color: ${color}"></div>
                                    <span class="ms-2">${data}</span>
                                </div>`;
                        }
                    },
                    { 
                        data: 'description',
                        render: function(data) {
                            return data 
                                ? `<span class="text-muted">${data}</span>`
                                : '<span class="text-muted">No description</span>';
                        }
                    },
                    { 
                        data: 'product_count',
                        className: 'text-center',
                        render: function(data) {
                            return `<span class="badge bg-primary rounded-pill">${data || 0} products</span>`;
                        }
                    },
                    { 
                        data: 'active',
                        className: 'text-center',
                        render: function(data) {
                            return data == 1 
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-danger">Inactive</span>';
                        }
                    },
                    {
                        data: 'id',
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary edit-category-btn" 
                                            data-id="${data}" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger delete-category-btn" 
                                            data-id="${data}" data-name="${row.name.replace(/"/g, '&quot;')}" 
                                            data-products="${row.product_count || 0}"
                                            data-bs-toggle="tooltip" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                        }
                    }
                ],
                initComplete: function() {
                    // Re-initialize tooltips after table is loaded
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(tooltipTriggerEl => {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                },
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search categories...",
                    lengthMenu: "_MENU_ items per page",
                    zeroRecords: "No matching categories found",
                    info: "Showing _START_ to _END_ of _TOTAL_ categories",
                    infoEmpty: "No categories available",
                    infoFiltered: "(filtered from _MAX_ total categories)",
                    paginate: {
                        first: "<i class='fas fa-angle-double-left'></i>",
                        last: "<i class='fas fa-angle-double-right'></i>",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on action column
                ]
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search categories..."
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on actions column
                ]
            });

            // Show/hide loading overlay
            function toggleLoading(show) {
                $('#loadingOverlay').toggleClass('show', show);
            }

            // Show alert message
            function showAlert(message, type = 'success') {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i> ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $(alertHtml).prependTo('.main-content').delay(5000).fadeOut(500, function() {
                    $(this).remove();
                });
            }

            // Handle tab navigation to refresh DataTables
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                productsTable.columns.adjust().responsive.recalc();
                categoriesTable.columns.adjust().responsive.recalc();
            });

            // Image preview
            $('#image_path').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image_preview').attr('src', e.target.result).show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#image_preview').hide();
                }
            });

            // PRODUCT OPERATIONS

            // Reset product form
            function resetProductForm() {
                $('#productForm')[0].reset();
                $('#product_id').val('');
                $('#product_action').val('add');
                $('#addProductModalLabel').text('Add New Product');
                $('#image_preview').hide();
                $('#cost').val('0');
                $('#stock_quantity').val('0');
                $('#min_stock').val('0');
                $('#unit_of_measure').val('pcs');
                $('#tax_included').val('1');
                $('#is_active').prop('checked', true);
            }

            // Open add product modal
            $('#addProductModal').on('show.bs.modal', function(e) {
                if (!$(e.relatedTarget).hasClass('edit-product-btn')) {
                    resetProductForm();
                }
            });

            // Handle edit product button click
            $('.edit-product-btn').click(function() {
                const id = $(this).data('id');
                toggleLoading(true);

                $.ajax({
                    url: 'ajax/get_product_details.php',
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        try {
                            const product = JSON.parse(response);
                            if (product.error) {
                                showAlert(product.error, 'danger');
                                return;
                            }

                            $('#product_id').val(product.id);
                            $('#product_action').val('edit');
                            $('#product_code').val(product.code);
                            $('#product_name').val(product.name);
                            $('#product_description').val(product.description || '');
                            $('#product_category').val(product.category_id || '');
                            $('#tax_rate_id').val(product.tax_rate_id || '');
                            $('#cost').val(product.cost || '0');
                            $('#price').val(product.price || '0');
                            $('#unit_of_measure').val(product.unit_of_measure || 'pcs');
                            $('#tax_included').val(product.tax_included || '1');
                            $('#stock_quantity').val(product.stock_quantity || '0');
                            $('#min_stock').val(product.min_stock || '5');
                            $('#barcode').val(product.barcode || '');
                            $('#is_active').prop('checked', parseInt(product.active) === 1);

                            // Show image preview if exists
                            if (product.image_path) {
                                $('#image_preview').attr('src', '../' + product.image_path).removeClass('d-none');
                            } else {
                                $('#image_preview').hide();
                            }

                            $('#addProductModalLabel').text('Edit Product');
                            $('#addProductModal').modal('show');
                        } catch (e) {
                            showAlert('Error processing product data', 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Failed to load product details', 'danger');
                    },
                    complete: function() {
                        toggleLoading(false);
                    }
                });
            });

            // Handle delete product button click
            $('.delete-product-btn').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                $('#delete_type').val('product');
                $('#delete_id').val(id);
                $('#deleteConfirmText').html(`Are you sure you want to delete product <strong>${name}</strong>?<br>This action cannot be undone.`);

                $('#deleteConfirmModal').modal('show');
            });

            // CATEGORY OPERATIONS

            // Reset category form
            function resetCategoryForm() {
                $('#categoryForm')[0].reset();
                $('#category_id').val('');
                $('#category_action').val('add');
                $('#addCategoryModalLabel').text('Add New Category');
                $('#category_is_active').prop('checked', true);
            }

            // Open add category modal
            $('#addCategoryModal').on('show.bs.modal', function(e) {
                if (!$(e.relatedTarget).hasClass('edit-category-btn')) {
                    resetCategoryForm();
                }
            });

            // Handle edit category button click
            $('.edit-category-btn').click(function() {
                const id = $(this).data('id');

                $('#category_id').val(id);
                $('#category_action').val('edit');
                $('#category_name').val($(this).closest('tr').find('td:eq(1)').text());
                $('#category_description').val($(this).closest('tr').find('td:eq(2)').text());
                $('#category_is_active').prop('checked', $(this).closest('tr').find('.badge-success').length > 0);

                $('#addCategoryModalLabel').text('Edit Category');
                $('#addCategoryModal').modal('show');
            });

            // Handle delete category button click
            $('.delete-category-btn').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const productCount = $(this).data('products');

                $('#delete_type').val('category');
                $('#delete_id').val(id);

                let confirmText = `Are you sure you want to delete category <strong>${name}</strong>?`;
                if (productCount > 0) {
                    confirmText += `<br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> This category contains ${productCount} products that will be unassigned.</span>`;
                }
                confirmText += '<br>This action cannot be undone.';

                $('#deleteConfirmText').html(confirmText);
                $('#deleteConfirmModal').modal('show');
            });

            // Handle delete confirmation
            $('#confirmDeleteBtn').on('click', function() {
                const deleteType = $('#delete_type').val();
                const deleteId = $('#delete_id').val();
                
                // Show loading state
                const btn = $(this);
                const originalText = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Deleting...');
                
                // Determine endpoint based on delete type
                const endpoint = deleteType === 'product' ? 'api/products.php' : 'api/categories.php';
                
                // Send delete request
                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    data: {
                        action: 'delete',
                        id: deleteId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showToast('success', response.message || 'Item deleted successfully');
                            
                            // Close modal
                            $('#deleteConfirmModal').modal('hide');
                            
                            // Refresh the appropriate table
                            if (deleteType === 'product') {
                                productsTable.ajax.reload(null, false);
                            } else {
                                categoriesTable.ajax.reload(null, false);
                                // Refresh category dropdowns if needed
                                refreshCategoryDropdowns();
                            }
                        } else {
                            showToast('danger', response.message || 'Error deleting item');
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'An error occurred while deleting the item';
                        showToast('danger', errorMessage);
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });
{{ ... }}
                $(alertHtml).prependTo('.main-content').delay(5000).fadeOut(500, function() {
                    $(this).remove();
                });
            }

            // Handle tab change to refresh DataTables
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('href');
                if (target === '#products-tab') {
                    productsTable.columns.adjust().responsive.recalc();
                    // Re-initialize tooltips
                    $('[data-bs-toggle="tooltip"]').tooltip('dispose');
                    $('[data-bs-toggle="tooltip"]').tooltip();
                } else if (target === '#categories-tab') {
                    categoriesTable.columns.adjust().responsive.recalc();
                    // Re-initialize tooltips
                    $('[data-bs-toggle="tooltip"]').tooltip('dispose');
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }
            });
            
            // Initialize color picker for category color
            $('#category_color').colorpicker({
                format: 'hex',
                component: '.input-group-text',
                extensions: [
                    {
                        name: 'swatches',
                        options: {
                            colors: {
                                'primary': '#4361ee',
                                'secondary': '#6c757d',
                                'success': '#28a745',
                                'danger': '#dc3545',
                                'warning': '#ffc107',
                                'info': '#17a2b8',
                                'dark': '#343a40'
                            },
                            namesAsValues: true
                        }
                    }
                ]
            });
            
            // Handle form submissions with AJAX
            $('#productForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                
                // Check form validity
                if (!form[0].checkValidity()) {
                    e.stopPropagation();
                    form.addClass('was-validated');
                    return;
                }
                
                const formData = new FormData(this);
                const submitBtn = form.find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                // Show loading state
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');
                
                // Submit form via AJAX
                $.ajax({
                    url: 'api/products.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showToast('success', response.message || 'Product saved successfully');
                            
                            // Close modal and refresh table
                            $('#addProductModal').modal('hide');
                            productsTable.ajax.reload(null, false);
                            
                            // Reset form
                            form[0].reset();
                            form.removeClass('was-validated');
                        } else {
                            // Show error message
                            showToast('danger', response.message || 'Error saving product');
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'An error occurred while saving the product';
                        showToast('danger', errorMessage);
                    },
                    complete: function() {
                        // Reset button state
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });
            
            // Handle category form submission
            $('#categoryForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                
                // Check form validity
                if (!form[0].checkValidity()) {
                    e.stopPropagation();
                    form.addClass('was-validated');
                    return;
                }
                
                const formData = new FormData(this);
                const submitBtn = form.find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                // Show loading state
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');
                
                // Submit form via AJAX
                $.ajax({
                    url: 'api/categories.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showToast('success', response.message || 'Category saved successfully');
                            
                            // Close modal and refresh table
                            $('#addCategoryModal').modal('hide');
                            categoriesTable.ajax.reload(null, false);
                            
                            // Refresh category dropdowns in product form
                            refreshCategoryDropdowns();
                            
                            // Reset form
                            form[0].reset();
                            form.removeClass('was-validated');
                        } else {
                            // Show error message
                            showToast('danger', response.message || 'Error saving category');
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'An error occurred while saving the category';
                        showToast('danger', errorMessage);
                    },
                    complete: function() {
                        // Reset button state
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });
            
            // Function to refresh category dropdowns
            function refreshCategoryDropdowns() {
                $.ajax({
                    url: 'api/categories.php',
                    type: 'GET',
                    data: { 
                        action: 'get_active_categories',
                        for_dropdown: 1
                    },
                    dataType: 'json',
                    success: function(categories) {
                        // Update category dropdown in product form
                        const categorySelect = $('#category_id_select');
                        const currentValue = categorySelect.val();
                        
                        categorySelect.empty().append('<option value="">-- Select Category --</option>');
                        
                        categories.forEach(function(category) {
                            categorySelect.append(new Option(category.name, category.id));
                        });
                        
                        // Restore previous selection if it exists
                        if (currentValue) {
                            categorySelect.val(currentValue);
                        }
                    },
                    error: function() {
                        console.error('Failed to load categories');
                    }
                });
            }
            
            // Function to show toast notifications
            function showToast(type, message) {
                const toast = `
                    <div class="toast-container position-fixed bottom-0 end-0 p-3">
                        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                                    ${message}
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>`;
                
                $('.toast-container').remove(); // Remove any existing toasts
                $('body').append(toast);
                
                const toastEl = $('.toast');
                const toastBootstrap = new bootstrap.Toast(toastEl[0], { 
                    autohide: true, 
                    delay: 5000,
                    animation: true
                });
                
                toastBootstrap.show();
                
                // Remove toast from DOM after it's hidden
                toastEl.on('hidden.bs.toast', function() {
                    $(this).closest('.toast-container').remove();
                });
            }// Image preview
            $('#image_path').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
{{ ... }}
                    resetProductForm();
                }
            });

            // Handle edit product button click
            $(document).on('click', '.edit-product-btn', function() {
                const productId = $(this).data('id');
                const modal = $('#addProductModal');
                const form = modal.find('form');
                
                // Show loading state
                const originalBtnText = modal.find('.modal-title').html();
                modal.find('.modal-title').html('<i class="fas fa-spinner fa-spin me-2"></i>Loading...');
                
                // Fetch product data via AJAX
                $.ajax({
                    url: 'api/products.php',
                    type: 'GET',
                    data: { 
                        action: 'get_product',
                        id: productId 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            const product = response.data;
                            
                            // Populate form fields
                            form.find('input[name="action"]').val('edit');
                            form.find('input[name="product_id"]').val(product.id);
                            form.find('#code').val(product.code);
                            form.find('#name').val(product.name);
                            form.find('#description').val(product.description || '');
                            form.find('#category_id_select').val(product.category_id || '');
                            form.find('#cost').val(product.cost);
                            form.find('#price').val(product.price);
                            form.find('#tax_rate').val(product.tax_rate || '18.00');
                            form.find('#unit_of_measure').val(product.unit_of_measure || 'pcs');
                            form.find('#stock_quantity').val(product.stock_quantity || '0');
                            form.find('#min_stock').val(product.min_stock || '5');
                            form.find('#barcode').val(product.barcode || '');
                            form.find('#tax_included').prop('checked', product.tax_included == 1);
                            form.find('#is_active').prop('checked', product.active == 1);
                            
                            // Set image preview if exists
                            if (product.image_url) {
                                const preview = $('#image_preview');
                                preview.attr('src', product.image_url).removeClass('d-none');
                            }
                            
                            // Update modal title
                            modal.find('.modal-title').html('<i class="fas fa-edit me-2"></i>Edit Product');
                            
                            // Show modal
                            modal.modal('show');
                        } else {
                            showToast('danger', response.message || 'Failed to load product data');
                            modal.find('.modal-title').html(originalBtnText);
                        }
                    },
                    error: function() {
                        showToast('danger', 'Error loading product data');
                        modal.find('.modal-title').html(originalBtnText);
                    }
                });
            });        method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        try {
                            const product = JSON.parse(response);
                            if (product.error) {
{{ ... }}
                    }
                });
            });

            // Handle delete product button click
            $(document).on('click', '.delete-product-btn', function() {
                const productId = $(this).data('id');
                const productName = $(this).data('name');
                const modal = $('#deleteConfirmModal');
                
                // Update modal content
                modal.find('.modal-title').html('<i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Product?');
                modal.find('#deleteConfirmText').html(`
                    <p>You are about to delete the product <strong>${productName}</strong>.</p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This action cannot be undone. All associated data will be permanently removed.
                    </div>
                `);
                
                // Set form values
                $('#delete_type').val('product');
                $('#delete_id').val(productId);
                
                // Show modal
                modal.modal('show');
            });you want to delete product <strong>${name}</strong>?<br>This action cannot be undone.`);

                $('#deleteConfirmModal').modal('show');
            });

            // CATEGORY OPERATIONS
{{ ... }}
                if (!$(e.relatedTarget).hasClass('edit-category-btn')) {
                    resetCategoryForm();
                }
            });

            // Handle add new category button click
            $('#addCategoryBtn').on('click', function() {
                const modal = $('#addCategoryModal');
                const form = modal.find('form')[0];
                
                // Reset form and validation state
                form.reset();
                form.classList.remove('was-validated');
                
                // Set default values
                modal.find('.modal-title').html('<i class="fas fa-plus-circle me-2"></i>Add New Category');
                modal.find('input[name="category_action"]').val('add');
                modal.find('input[name="category_id"]').val('');
                modal.find('#category_color').val('#4361ee');
                modal.find('#category_is_active').prop('checked', true);
                
                // Show modal
                modal.modal('show');
            });    $('#category_description').val($(this).closest('tr').find('td:eq(2)').text());
                $('#category_is_active').prop('checked', $(this).closest('tr').find('.badge-success').length > 0);

                $('#addCategoryModalLabel').text('Edit Category');
                $('#addCategoryModal').modal('show');
            });

            // Handle delete category button click
            $(document).on('click', '.delete-category-btn', function() {
                const categoryId = $(this).data('id');
                const categoryName = $(this).data('name');
                const productCount = $(this).data('products');
                const modal = $('#deleteConfirmModal');
                
                // Update modal content
                modal.find('.modal-title').html('<i class="fas fa-exclamation-triangle text-warning me-2"></i>Delete Category?');
                
                let message = `
                    <p>You are about to delete the category <strong>${categoryName}</strong>.</p>`;
                
                if (productCount > 0) {
                    message += `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This category contains <strong>${productCount} product(s)</strong>. These products will be moved to "Uncategorized".
                    </div>`;
                } else {
                    message += `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This action cannot be undone.
                    </div>`;
                }
                
                modal.find('#deleteConfirmText').html(message);
                
                // Set form values
                $('#delete_type').val('category');
                $('#delete_id').val(categoryId);
                
                // Show modal
                modal.modal('show');
            });// Handle delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const type = $('#delete_type').val();
                const id = $('#delete_id').val();

                let form = $('<form>', {
                    'action': ''
                });

                if (type === 'product') {
                    form.append($('<input>', {
                        'name': 'product_action',
                        'value': 'delete',
                        'type': 'hidden'
                    }));
                    form.append($('<input>', {
                        'name': 'product_id',
                        'value': id,
                        'type': 'hidden'
                    }));
                } else if (type === 'category') {
                    form.append($('<input>', {
                        'name': 'category_action',
                        'value': 'delete',
                        'type': 'hidden'
                    }));
                    form.append($('<input>', {
                        'name': 'category_id',
                        'value': id,
                        'type': 'hidden'
                    }));
                }

                $('body').append(form);
                toggleLoading(true);
                form.submit();
            });

            // Client-side form validation
            $('#productForm').on('submit', function(e) {
                const code = $('#product_code').val().trim();
                const name = $('#product_name').val().trim();
                const price = parseFloat($('#price').val());

                if (code.length === 0) {
                    e.preventDefault();
                    showAlert('Product code is required', 'danger');
                    return;
                }
                if (name.length === 0) {
                    e.preventDefault();
                    showAlert('Product name is required', 'danger');
                    return;
                }
                if (isNaN(price) || price < 0) {
                    e.preventDefault();
                    showAlert('Selling price must be a valid non-negative number', 'danger');
                    return;
                }
                toggleLoading(true);
            });
        });
    </script>
</body>
</html>