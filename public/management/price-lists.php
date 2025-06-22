<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Initialize variables
$db = null;
$price_lists = [];
$error_message = '';

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

// Check if user has management access
if (!in_array($user_role, ['admin', 'manager'])) {
    header('Location: ../welcome.php');
    exit();
}

/**
 * Get database connection with error handling
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/../config.php';
        try {
            $db = get_db_connection();
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->query('SELECT 1');
            error_log('Database connection established successfully');
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
            }
            die('Unable to connect to the database. Please try again later.');
        }
    }
    return $db;
}

// Initialize database connection
try {
    $db = getDB();

    // Verify price_lists table structure
    $stmt = $db->query("DESCRIBE price_lists");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $expected_columns = ['id', 'name', 'description', 'is_default', 'active', 'created_at', 'updated_at'];
    $found_columns = array_column($columns, 'Field');
    $missing_columns = array_diff($expected_columns, $found_columns);
    if (!empty($missing_columns)) {
        error_log('Missing columns in price_lists table: ' . implode(', ', $missing_columns));
    }

    // Get all active price lists
    $stmt = $db->prepare("SELECT * FROM price_lists WHERE active = 1 ORDER BY name");
    $stmt->execute();
    $price_lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'An error occurred while processing your request. Please try again later.';
    error_log('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Lists - MTECH UGANDA</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            padding: 2rem;
            z-index: 900;
        }

        .card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
    background-color: #fff;
    margin-top: 70px; /* Offset to avoid being covered by the fixed top-nav (70px height) */
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #e3e6f0;
    padding: 1rem 1.25rem;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-body {
    padding: 1.25rem;
}

.table-responsive {
    min-height: 200px;
    overflow-x: auto;
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
                <a href="price-lists.php" class="menu-item active"><i class="fas fa-tags"></i> Price Lists</a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Inventory</div>
                <a href="stock.php" class="menu-item"><i class="fas fa-warehouse"></i> Stock</a>
                <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reporting</a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Customers</div>
                <a href="customers-suppliers.php" class="menu-item"><i class="fas fa-users"></i> Customers & Suppliers</a>
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
                <h1 class="page-title">Price Lists</h1>
                <div class="user-menu">
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user_fullname, 0, 1)); ?></div>
                </div>
            </nav>

            <!-- Alerts -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">Price Lists Management</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPriceListModal">
                        <i class="fas fa-plus me-2"></i>New Price List
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="priceListsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($price_lists)): ?>
                                    <?php foreach ($price_lists as $list): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($list['name']); ?></td>
                                            <td><?php echo htmlspecialchars($list['description'] ?? 'No description'); ?></td>
                                            <td><?php echo $list['is_default'] ? 'Default' : 'Inactive'; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($list['updated_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $list['id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No price lists found. Create your first price list to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="input-group mt-3" style="max-width: 300px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchPriceLists" placeholder="Search price lists...">
                    </div>
                </div>
            </div>

            <!-- Add Price List Modal -->
            <div class="modal fade" id="addPriceListModal" tabindex="-1" aria-labelledby="addPriceListModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPriceListModalLabel">Add New Price List</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="priceListForm" method="post" action="price_lists_action.php">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description"></textarea>
                                </div>
                                <input type="hidden" name="action" value="add">
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </form>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const priceListsTable = $('#priceListsTable').DataTable({
                responsive: true,
                pageLength: 10,
                language: { searchPlaceholder: "Search..." }
            });

            // Search functionality
            $('#searchPriceLists').on('keyup', function() {
                priceListsTable.search(this.value).draw();
            });

            // Toggle sidebar on mobile
            $('#sidebarToggle').on("click", function() {
                $('#sidebar').toggleClass("active");
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                $('.alert-dismissible').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);

            // Form validation
            $('#priceListForm').on('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                $(this).addClass('was-validated');
            });
        });
    </script>
</body>
</html>