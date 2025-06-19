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

    } catch (PDOException $e) {
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $error_message = 'Product code already exists. Please use a unique code.';
        } else {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - <?php echo htmlspecialchars(SITE_NAME ?? 'MTECH UGANDA'); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2196F3;
            --secondary-color: #1976D2;
            --background-color: #f8f9fa;
            --text-color: #212529;
            --sidebar-width: 250px;
            --dark-bg: #343a40;
            --white-bg: #ffffff;
            --light-bg: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-bg);
            color: #fff;
            height: 100vh;
            padding: 1rem 0;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .sidebar-menu {
            padding: 0.5rem 0;
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

        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 1.5rem;
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

        .category-pill {
            background-color: #e3f2fd;
            color: #1976D2;
            padding: 0.35em 0.65em;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .stock-warning {
            color: #ff9800;
        }

        .stock-danger {
            color: #f44336;
        }

        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: -2.5em;
        }

        .form-switch .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }

        .image-preview {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>MTECH UGANDA</h3>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-menu-item active">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="customers-suppliers.php" class="sidebar-menu-item">
                <i class="fas fa-users"></i>
                <span>Customers & Suppliers</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-tag"></i>
                <span>Price Lists</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-file-invoice"></i>
                <span>Documents</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percent"></i>
                <span>Promotions</span>
            </a>
            <a href="reports.php" class="sidebar-menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="../logout.php" class="sidebar-menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
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

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Product Management</h5>
                </div>
                <div class="d-flex">
                    <span class="text-white me-3"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user_fullname); ?></span>
                    <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
                </div>
            </div>

            <!-- Nav tabs -->
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                        <i class="fas fa-boxes me-2"></i>Products
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
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
                        <table id="productsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Cost Price</th>
                                    <th>Selling Price</th>
                                    <th>Tax Rate</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
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
                                    <td><?php echo number_format($product['cost'], 2); ?></td>
                                    <td><?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo $product['tax_rate'] ? number_format($product['tax_rate'], 2) . '%' : 'None'; ?></td>
                                    <td>
                                        <?php 
                                        $stock_class = '';
                                        if ($product['stock_quantity'] <= 0) {
                                            $stock_class = 'stock-danger';
                                        } elseif ($product['stock_quantity'] <= $product['min_stock']) {
                                            $stock_class = 'stock-warning';
                                        }
                                        ?>
                                        <span class="<?php echo $stock_class; ?>">
                                            <?php echo number_format($product['stock_quantity'], 2); ?> 
                                            <?php echo htmlspecialchars($product['unit_of_measure'] ?? 'pcs'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-product-btn" 
                                                data-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-product-btn" 
                                                data-id="<?php echo $product['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Categories Tab -->
                <div class="tab-pane fade" id="categories" role="tabpanel">
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Category List</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Add New Category
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="categoriesTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Products</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): 
                                    $product_count = 0;
                                    foreach ($products as $product) {
                                        if ($product['category_id'] == $category['id']) {
                                            $product_count++;
                                        }
                                    }
                                ?>
                                <tr data-id="<?php echo $category['id']; ?>">
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                    <td><?php echo $product_count; ?></td>
                                    <td>
                                        <?php if ($category['active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-category-btn" 
                                                data-id="<?php echo $category['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-category-btn" 
                                                data-id="<?php echo $category['id']; ?>" 
                                                data-products="<?php echo $product_count; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>">
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

    <!-- Add/Edit Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="product_action" value="add">
                        <input type="hidden" name="product_id" id="product_id" value="">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="product_code" class="form-label">Product Code* <small>(Unique)</small></label>
                                <input type="text" class="form-control" id="product_code" name="product_code" maxlength="50" required>
                            </div>
                            <div class="col-md-6">
                                <label for="product_name" class="form-label">Product Name*</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" maxlength="255" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="product_description" class="form-label">Description</label>
                            <textarea class="form-control" id="product_description" name="product_description" rows="3"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="product_category" class="form-label">Category</label>
                                <select class="form-select" id="product_category" name="product_category">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tax_rate_id" class="form-label">Tax Rate</label>
                                <select class="form-select" id="tax_rate_id" name="tax_rate_id">
                                    <option value="">-- Select Tax Rate --</option>
                                    <?php foreach ($tax_rates as $tax_rate): ?>
                                    <option value="<?php echo $tax_rate['id']; ?>">
                                        <?php echo htmlspecialchars($tax_rate['name'] . ' (' . $tax_rate['rate'] . '%)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="cost" class="form-label">Cost Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">UGX</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="cost" name="cost" value="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="price" class="form-label">Selling Price*</label>
                                <div class="input-group">
                                    <span class="input-group-text">UGX</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="unit_of_measure" class="form-label">Unit of Measure</label>
                                <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure" maxlength="20" placeholder="e.g., pcs, kg, liters" value="pcs">
                            </div>
                            <div class="col-md-3">
                                <label for="tax_included" class="form-label">Tax Included</label>
                                <select class="form-select" id="tax_included" name="tax_included">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="0">
                            </div>
                            <div class="col-md-4">
                                <label for="min_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="min_stock" name="min_stock" value="0">
                            </div>
                            <div class="col-md-4">
                                <label for="barcode" class="form-label">Barcode</label>
                                <input type="text" class="form-control" id="barcode" name="barcode" maxlength="50">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="image_path" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image_path" name="image_path" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp">
                            <small class="form-text text-muted">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</small>
                            <img id="image_preview" class="image-preview" src="" alt="Image Preview">
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm" method="post">
                        <input type="hidden" name="category_action" value="add">
                        <input type="hidden" name="category_id" id="category_id" value="">

                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name*</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" maxlength="100" required>
                        </div>

                        <div class="mb-3">
                            <label for="category_description" class="form-label">Description</label>
                            <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="category_is_active" name="category_is_active" value="1" checked>
                            <label class="form-check-label" for="category_is_active">Active</label>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Category</button>
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
                    <p id="deleteConfirmText">Are you sure you want to delete this item?</p>
                    <form id="deleteForm" method="post">
                        <input type="hidden" id="delete_type" name="delete_type" value="">
                        <input type="hidden" id="delete_id" name="delete_id" value="">
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
        <div class="loading-message">Processing...</div>
    </div>

    <!-- Required JS Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            const productsTable = $('#productsTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search products..."
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on actions column
                ]
            });

            const categoriesTable = $('#categoriesTable').DataTable({
                responsive: true,
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
                            $('#min_stock').val(product.min_stock || '0');
                            $('#barcode').val(product.barcode || '');
                            $('#is_active').prop('checked', parseInt(product.active) === 1);

                            // Show image preview if exists
                            if (product.image_path) {
                                $('#image_preview').attr('src', '../' + product.image_path).show();
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
            $('#confirmDeleteBtn').click(function() {
                const type = $('#delete_type').val();
                const id = $('#delete_id').val();

                let form = $('<form>', {
                    'method': 'post',
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