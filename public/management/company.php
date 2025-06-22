<?php
session_start();

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Replace with your database username
define('DB_PASS', ''); // Replace with your database password
define('DB_NAME', 'mtech-uganda');

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize variables
$company = [];
$error = null;
$success = null;

// Fetch company information
try {
    $stmt = $pdo->query("SELECT * FROM company WHERE id = 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = "Failed to fetch company information: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $data = [
        'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
        'tax_number' => filter_input(INPUT_POST, 'tax_number', FILTER_SANITIZE_STRING),
        'street_name' => filter_input(INPUT_POST, 'street_name', FILTER_SANITIZE_STRING),
        'building_number' => filter_input(INPUT_POST, 'building_number', FILTER_SANITIZE_STRING),
        'additional_street_name' => filter_input(INPUT_POST, 'additional_street_name', FILTER_SANITIZE_STRING),
        'plot_identification' => filter_input(INPUT_POST, 'plot_identification', FILTER_SANITIZE_STRING),
        'district' => filter_input(INPUT_POST, 'district', FILTER_SANITIZE_STRING),
        'postal_code' => filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING),
        'city' => filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING),
        'state_province' => filter_input(INPUT_POST, 'state_province', FILTER_SANITIZE_STRING),
        'country' => filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING),
        'phone_number' => filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'bank_account' => filter_input(INPUT_POST, 'bank_account', FILTER_SANITIZE_STRING),
        'bank_acc_number' => filter_input(INPUT_POST, 'bank_acc_number', FILTER_SANITIZE_STRING),
        'bank_details' => filter_input(INPUT_POST, 'bank_details', FILTER_SANITIZE_STRING),
    ];

    // Validate required fields
    if (empty($data['name'])) {
        $error = "Company name is required.";
    } elseif (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Handle logo upload
            $logo_path = $company['logo_path'] ?? '';
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPEG, PNG, and GIF are allowed.");
                }
                if ($_FILES['logo']['size'] > $max_size) {
                    throw new Exception("File size exceeds 2MB limit.");
                }

                $upload_dir = 'uploads/company/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_name = 'logo_' . time() . '_' . basename($_FILES['logo']['name']);
                $logo_path = $upload_dir . $file_name;

                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    throw new Exception("Failed to upload logo.");
                }

                // Delete old logo if exists
                if (!empty($company['logo_path']) && file_exists($company['logo_path'])) {
                    unlink($company['logo_path']);
                }
            }

            // Update or insert company information
            $sql = $company ? 
                "UPDATE company SET name = :name, tax_number = :tax_number, street_name = :street_name, 
                building_number = :building_number, additional_street_name = :additional_street_name, 
                plot_identification = :plot_identification, district = :district, postal_code = :postal_code, 
                city = :city, state_province = :state_province, country = :country, phone_number = :phone_number, 
                email = :email, bank_account = :bank_account, bank_acc_number = :bank_acc_number, 
                bank_details = :bank_details, logo_path = :logo_path WHERE id = 1" :
                "INSERT INTO company (name, tax_number, street_name, building_number, additional_street_name, 
                plot_identification, district, postal_code, city, state_province, country, phone_number, 
                email, bank_account, bank_acc_number, bank_details, logo_path) 
                VALUES (:name, :tax_number, :street_name, :building_number, :additional_street_name, 
                :plot_identification, :district, :postal_code, :city, :state_province, :country, 
                :phone_number, :email, :bank_account, :bank_acc_number, :bank_details, :logo_path)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($data, ['logo_path' => $logo_path]));

            $success = "Company information updated successfully.";
            // Refresh company data
            $stmt = $pdo->query("SELECT * FROM company WHERE id = 1");
            $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Company - MTECH UGANDA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --primary-dark: #3a56d4;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #f6c23e;
            --danger: #e74a3b;
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
            --white: #ffffff;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            --font-mono: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background-color: #f5f7fb;
            color: var(--gray-800);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles - Modern Glass Morphism */
        .sidebar {
            width: 280px;
            background: rgba(26, 35, 126, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
            margin-bottom: 10px;
        }

        .sidebar-header h2 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 20px;
        }

        .menu-section-title {
            padding: 15px 25px 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.95rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            margin: 2px 15px;
            border-radius: 8px;
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition);
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .menu-item:hover i {
            color: white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-left-color: white;
            font-weight: 600;
        }

        .menu-item.active i {
            color: white;
        }

        /* Top Navigation */
        .top-nav {
            background: var(--white);
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title i {
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--info));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .user-text {
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
            margin-top: 2px;
        }

        .back-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.2);
        }

        .back-btn i {
            font-size: 0.9rem;
        }

        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* Dashboard Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            background-color: #f5f7fb;
            transition: var(--transition);
        }

        .container-fluid {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Card Styles */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--primary);
            font-size: 1rem;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            height: auto;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .btn {
            padding: 0.65rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn i {
            font-size: 0.9rem;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* Color Utilities */
        .text-primary { color: var(--primary) !important; }
        .text-secondary { color: var(--secondary) !important; }
        .text-success { color: var(--success) !important; }
        .text-info { color: var(--info) !important; }
        .text-warning { color: var(--warning) !important; }
        .text-danger { color: var(--danger) !important; }
        .text-light { color: var(--light) !important; }
        .text-dark { color: var(--dark) !important; }
        .text-white { color: var(--white) !important; }
        .text-muted { color: var(--gray-600) !important; }

        .bg-primary { background-color: var(--primary) !important; color: white; }
        .bg-secondary { background-color: var(--secondary) !important; color: white; }
        .bg-success { background-color: var(--success) !important; color: white; }
        .bg-info { background-color: var(--info) !important; color: white; }
        .bg-warning { background-color: var(--warning) !important; color: var(--dark); }
        .bg-danger { background-color: var(--danger) !important; color: white; }
        .bg-light { background-color: var(--light) !important; color: var(--dark); }
        .bg-dark { background-color: var(--dark) !important; color: white; }
        .bg-white { background-color: var(--white) !important; color: var(--dark); }

        /* Gradient Backgrounds */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .bg-gradient-warning {
            background: linear-gradient(135deg, var(--warning), #f6ad55);
            color: var(--dark);
        }

        .bg-gradient-danger {
            background: linear-gradient(135deg, var(--danger), #f87171);
            color: white;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
        }

        .badge-pill {
            padding-right: 0.6em;
            padding-left: 0.6em;
            border-radius: 10rem;
        }

        .badge-primary { background-color: var(--primary); color: white; }
        .badge-secondary { background-color: var(--secondary); color: white; }
        .badge-success { background-color: var(--success); color: white; }
        .badge-info { background-color: var(--info); color: white; }
        .badge-warning { background-color: var(--warning); color: var(--dark); }
        .badge-danger { background-color: var(--danger); color: white; }
        .badge-light { background-color: var(--light); color: var(--dark); }
        .badge-dark { background-color: var(--dark); color: white; }

        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-active { background-color: var(--success); }
        .status-inactive { background-color: var(--danger); }
        .status-pending { background-color: var(--warning); }

        /* Alert Styles */
        .alert {
            position: relative;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }

        .alert-primary {
            color: #1e3a8a;
            background-color: #dbeafe;
            border-color: #bfdbfe;
        }

        .alert-success {
            color: #166534;
            background-color: #dcfce7;
            border-color: #bbf7d0;
        }

        .alert-warning {
            color: #854d0e;
            background-color: #fef9c3;
            border-color: #fef08a;
        }

        .alert-danger {
            color: #991b1b;
            background-color: #fee2e2;
            border-color: #fecaca;
        }

        .alert-info {
            color: #1e40af;
            background-color: #dbeafe;
            border-color: #bfdbfe;
        }

        /* Table Striping */
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Hover effects */
        .hover-shadow:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }

        .hover-scale:hover {
            transform: scale(1.02);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex;
            }

            .welcome-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .top-nav {
                padding: 0 20px;
                flex-direction: column;
                height: auto;
                padding: 15px;
            }

            .page-title {
                margin-bottom: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .container-fluid {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .card-header, 
            .card-body {
                padding: 16px;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* Menu toggle button - Floating Action Button */
        .menu-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1100;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
            transition: var(--transition);
        }

        .menu-toggle:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
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
                <a href="reports.php" class="menu-item">
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
                <a href="company.php" class="menu-item active">
                    <i class="fas fa-building"></i>
                    <span>My Company</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">
                                <i class="fas fa-building me-2"></i>Company Information
                            </h4>
                        
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Company Name</label>
                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Tax Number</label>
                                            <input type="text" name="tax_number" class="form-control" value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Street Name</label>
                                            <input type="text" name="street_name" class="form-control" value="<?php echo htmlspecialchars($company['street_name'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Building Number</label>
                                            <input type="text" name="building_number" class="form-control" value="<?php echo htmlspecialchars($company['building_number'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Additional Street Name</label>
                                            <input type="text" name="additional_street_name" class="form-control" value="<?php echo htmlspecialchars($company['additional_street_name'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Plot Identification</label>
                                            <input type="text" name="plot_identification" class="form-control" value="<?php echo htmlspecialchars($company['plot_identification'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>District</label>
                                            <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($company['district'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Postal Code</label>
                                            <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>City</label>
                                            <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>State/Province</label>
                                            <input type="text" name="state_province" class="form-control" value="<?php echo htmlspecialchars($company['state_province'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Country</label>
                                            <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Phone Number</label>
                                            <input type="tel" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($company['phone_number'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Bank Account</label>
                                            <input type="text" name="bank_account" class="form-control" value="<?php echo htmlspecialchars($company['bank_account'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Bank Account Number</label>
                                            <input type="text" name="bank_acc_number" class="form-control" value="<?php echo htmlspecialchars($company['bank_acc_number'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Bank Details</label>
                                            <textarea name="bank_details" class="form-control" rows="3"><?php echo htmlspecialchars($company['bank_details'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Company Logo</label>
                                            <?php if (!empty($company['logo_path'])): ?>
                                                <div class="mb-2">
                                                    <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Company Logo" style="max-width: 200px;" class="img-thumbnail">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="logo" class="form-control-file" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-right mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script> <!-- Adjust path to your JS file -->
</body>
</html>