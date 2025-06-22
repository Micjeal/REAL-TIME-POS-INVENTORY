<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = get_db_connection();

// Get current user information
$user_fullname = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'Guest';

// Handle user status update
if (isset($_GET['update_status']) && isset($_GET['user_id']) && isset($_GET['status'])) {
    $userId = (int)$_GET['user_id'];
    $status = (int)$_GET['status'];
    
    try {
        $stmt = $db->prepare("UPDATE users SET active = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        
        $_SESSION['success'] = 'User status updated successfully';
        header('Location: security.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error updating user status: " . $e->getMessage();
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];
    
    try {
        // Don't allow deleting the current user
        if ($userId !== $_SESSION['user_id']) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['success'] = 'User deleted successfully';
        } else {
            $error = 'You cannot delete your own account';
        }
        header('Location: security.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Fetch all users
$users = $db->query("SELECT * FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users & Security - MTECH UGANDA</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --secondary: #6c757d;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark: #1a237e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
            margin: 0;
            padding: 0;
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
            background-color: #fff;
            margin-top: 70px; /* Offset to avoid being covered by the fixed top-nav (70px height) */
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .card-body {
            padding: 1.25rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
        }

        .table td {
            vertical-align: middle;
        }

        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
        }

        .role-admin { background-color: var(--primary); color: white; }
        .role-manager { background-color: var(--info-color); color: white; }
        .role-cashier { background-color: var(--success-color); color: white; }

        .user-active { color: var(--success-color); }
        .user-inactive { color: var(--danger-color); }

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
                <a href="customers-suppliers.php" class="menu-item"><i class="fas fa-users"></i> Customers & Suppliers</a>
                <a href="promotions.php" class="menu-item"><i class="fas fa-percent"></i> Promotions</a>
            </div>
            <?php if (in_array($user_role, ['admin', 'manager'])): ?>
            <div class="menu-section">
                <div class="menu-section-title">Settings</div>
                <a href="security.php" class="menu-item active"><i class="fas fa-users-cog"></i> Users & Security</a>
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
                <h1 class="page-title">Users & Security</h1>
                <div class="user-menu">
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user_fullname, 0, 1)); ?></div>
                </div>
            </nav>

            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">User Management</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="user-<?php echo $user['active'] ? 'active' : 'inactive'; ?>">
                                                <i class="fas fa-circle"></i> <?php echo $user['active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                    <a href="#" class="btn btn-outline-<?php echo $user['active'] ? 'warning' : 'success'; ?> toggle-status" 
                                                       data-user-id="<?php echo $user['id']; ?>" 
                                                       data-status="<?php echo $user['active'] ? '0' : '1'; ?>"
                                                       title="<?php echo $user['active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $user['active'] ? 'times-circle' : 'check-circle'; ?>"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-outline-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add User Modal -->
            <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addUserForm" action="add_user.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <div class="invalid-feedback" id="usernameFeedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name">
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="cashier">Cashier</option>
                                        <option value="manager">Manager</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Temporary Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">User will be required to change this on first login</small>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add User</button>
                            </div>
                        </form>
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
            // Toggle sidebar on mobile
            $('#sidebarToggle').on("click", function() {
                $('#sidebar').toggleClass("active");
            });

            // Toggle user status
            $('.toggle-status').on('click', function(e) {
                e.preventDefault();
                const userId = $(this).data('userId');
                const status = $(this).data('status');
                
                if (confirm(`Are you sure you want to ${status === '1' ? 'activate' : 'deactivate'} this user?`)) {
                    $('#loadingText').text('Updating user status...');
                    $('#loadingOverlay').show();
                    window.location.href = `security.php?update_status=1&user_id=${userId}&status=${status}`;
                }
            });

            // Check username availability
            const usernameInput = $('#username');
            const usernameFeedback = $('#usernameFeedback');
            
            usernameInput.on('blur', function() {
                const username = $(this).val().trim();
                if (username) {
                    $.get(`check_username.php?username=${encodeURIComponent(username)}`)
                        .done(function(data) {
                            if (data.exists) {
                                usernameInput.addClass('is-invalid');
                                usernameFeedback.text('Username already exists');
                            } else {
                                usernameInput.removeClass('is-invalid').addClass('is-valid');
                            }
                        });
                }
            });

            // Form validation
            $('#addUserForm').on('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                $(this).addClass('was-validated');
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