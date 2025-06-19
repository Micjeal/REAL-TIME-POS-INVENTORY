<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Get user data
$user_fullname = $_SESSION['full_name'] ?? $_SESSION['username'];
$user_role = $_SESSION['role'] ?? 'cashier';
$page_title = $page_title ?? 'MTECH UGANDA';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MTECH UGANDA</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <!-- Bootstrap 5.1.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6.0.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/dt-1.11.5/datatables.min.css"/>
    <!-- Custom styles -->
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            font-size: 0.9rem;
            background-color: #f8f9fc;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        
        .main-content {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.25rem 0.5rem;
            margin-left: 0.25rem;
            border-radius: 0.2rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary-color);
            color: white !important;
            border: 1px solid var(--primary-color);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            color: #4e73df !important;
            border: 1px solid #dddfeb;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        
        .alert {
            border: none;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            border-left-color: #0f5132;
            color: #0f5132;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-left-color: #842029;
            color: #842029;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .modal-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .modal-footer {
            background-color: #f8f9fc;
            border-top: 1px solid #e3e6f0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Alerts will be shown here -->
                <div id="alert-container" class="mt-3"></div>
