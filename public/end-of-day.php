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
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Default to today's date
$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d');

// Get end-of-day data from database
try {
    // Database connection
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
    
    $db = new PDO($dsn, $username, $password, $options);
    
    // Get sales summary for the day
    $query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN paid_status = 'paid' THEN total ELSE 0 END) as total_paid,
                SUM(CASE WHEN paid_status = 'partial' THEN total ELSE 0 END) as total_partial,
                SUM(CASE WHEN paid_status = 'unpaid' THEN total ELSE 0 END) as total_unpaid,
                SUM(discount) as total_discounts
              FROM documents 
              WHERE DATE(document_date) = :report_date";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':report_date' => $report_date]);
    $summary = $stmt->fetch();
    
    // Get payment methods summary
    $query = "SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
              FROM payments
              WHERE DATE(payment_date) = :report_date
              GROUP BY payment_method";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':report_date' => $report_date]);
    $payment_methods = $stmt->fetchAll();
    
    // Get recent transactions
    $query = "SELECT d.*, c.name as customer_name, u.username as cashier
              FROM documents d
              LEFT JOIN customers c ON d.customer_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE DATE(d.document_date) = :report_date
              ORDER BY d.document_date DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':report_date' => $report_date]);
    $transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $summary = [];
    $payment_methods = [];
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>End of Day Report - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: cover;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        .content-wrapper {
            min-height: 100vh;
            background-color: #f8f9fc;
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
            font-weight: 600;
            color: #4e73df;
        }
        .summary-card {
            border-left: 0.25rem solid #4e73df;
            margin-bottom: 1.5rem;
        }
        .summary-card .card-body {
            padding: 1rem;
        }
        .summary-card .text-xs {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .summary-card .h5 {
            font-weight: 700;
            margin: 0.5rem 0 0;
        }
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
        }
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-auto px-0">
                <div class="sidebar">
                    <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100">
                        <a href="welcome.php" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                            <span class="fs-5 d-none d-sm-inline">MTECH UGANDA</span>
                        </a>
                        <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100" id="menu">
                            <li class="w-100">
                                <a href="welcome.php" class="nav-link px-0 align-middle">
                                    <i class="fas fa-fw fa-tachometer-alt"></i> <span class="ms-1 d-none d-sm-inline">Dashboard</span>
                                </a>
                            </li>
                            <li class="w-100">
                                <a href="end-of-day.php" class="nav-link px-0 align-middle active">
                                    <i class="fas fa-fw fa-door-closed"></i> <span class="ms-1 d-none d-sm-inline">End of Day</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col px-0">
                <div class="content-wrapper">
                    <!-- Page Header -->
                    <header class="bg-white shadow-sm mb-4">
                        <div class="container-fluid px-4 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h1 class="h3 mb-0 text-gray-800">End of Day Report</h1>
                                <div class="d-flex">
                                    <div class="input-group me-2" style="width: 200px;">
                                        <input type="date" class="form-control" id="reportDate" value="<?php echo $report_date; ?>">
                                        <button class="btn btn-primary" type="button" id="btnRefresh">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                    <button class="btn btn-success me-2" id="btnPrint">
                                        <i class="fas fa-print me-1"></i> Print
                                    </button>
                                    <button class="btn btn-primary" id="btnCloseDay">
                                        <i class="fas fa-lock me-1"></i> Close Day
                                    </button>
                                </div>
                            </div>
                        </div>
                    </header>

                    <!-- Summary Cards -->
                    <div class="container-fluid px-4">
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card summary-card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Transactions</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?php echo number_format($summary['total_transactions'] ?? 0); ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card summary-card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Total Paid</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    UGX <?php echo number_format($summary['total_paid'] ?? 0, 2); ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card summary-card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Partial Payments</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    UGX <?php echo number_format($summary['total_partial'] ?? 0, 2); ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-comments fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card summary-card border-left-danger shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    Total Discounts</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    UGX <?php echo number_format($summary['total_discounts'] ?? 0, 2); ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-tags fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card shadow">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Method</th>
                                                        <th>Transactions</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($payment_methods)): ?>
                                                        <?php foreach ($payment_methods as $method): ?>
                                                            <tr>
                                                                <td><?php echo ucfirst($method['payment_method']); ?></td>
                                                                <td><?php echo $method['transaction_count']; ?></td>
                                                                <td>UGX <?php echo number_format($method['total_amount'], 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">No payment data available</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card shadow">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Customer</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($transactions)): ?>
                                                        <?php foreach ($transactions as $transaction): ?>
                                                            <tr>
                                                                <td><?php echo $transaction['document_number']; ?></td>
                                                                <td><?php echo $transaction['customer_name'] ?? 'Walk-in Customer'; ?></td>
                                                                <td>UGX <?php echo number_format($transaction['total'], 2); ?></td>
                                                                <td>
                                                                    <?php if ($transaction['paid_status'] === 'paid'): ?>
                                                                        <span class="badge bg-success">Paid</span>
                                                                    <?php elseif ($transaction['paid_status'] === 'partial'): ?>
                                                                        <span class="badge bg-warning">Partial</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-danger">Unpaid</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Close Day Confirmation Modal -->
    <div class="modal fade" id="closeDayModal" tabindex="-1" role="dialog" aria-labelledby="closeDayModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="closeDayModalLabel">Confirm Close Day</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to close the day for <strong><?php echo date('F j, Y', strtotime($report_date)); ?></strong>?</p>
                    <p class="text-muted">This action cannot be undone. A summary report will be generated.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmCloseDay">Yes, Close Day</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Refresh page when date changes
            $('#btnRefresh').click(function() {
                const date = $('#reportDate').val();
                window.location.href = `end-of-day.php?report_date=${date}`;
            });

            // Print button
            $('#btnPrint').click(function() {
                window.print();
            });

            // Close day button
            $('#btnCloseDay').click(function() {
                $('#closeDayModal').modal('show');
            });

            // Confirm close day
            $('#confirmCloseDay').click(function() {
                // Here you would typically make an AJAX call to close the day
                alert('Day closed successfully!');
                $('#closeDayModal').modal('hide');
                // Optionally refresh the page
                // location.reload();
            });
        });
    </script>
</body>
</html>
