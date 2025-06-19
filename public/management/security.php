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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --top-nav-height: 60px;
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
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #4e73df;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1.5rem 0.5rem;
            font-weight: 800;
            font-size: 1.2rem;
            letter-spacing: 0.05rem;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-menu {
            padding: 0.5rem 0;
        }

        .sidebar-menu-header {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            padding: 0 1.5rem 0.5rem;
            margin-top: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu-item i {
            margin-right: 0.5rem;
            width: 1.2rem;
            text-align: center;
        }

        .sidebar-menu-item:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu-item.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--top-nav-height);
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 900;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }

        .top-nav-title {
            font-weight: 700;
            color: var(--dark-color);
            font-size: 1.2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-info .badge {
            font-weight: 500;
            text-transform: capitalize;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--top-nav-height);
            padding: 1.5rem;
            width: calc(100% - var(--sidebar-width));
            min-height: calc(100vh - var(--top-nav-height));
            background-color: #f8f9fc;
        }

        /* Card Styles */
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
            font-weight: 700;
            color: #4e73df;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #4e73df;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
        }

        .table td {
            vertical-align: middle;
        }

        /* Badge Styles */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        /* Utility Classes */
        .text-xs {
            font-size: 0.7rem;
        }

        .text-uppercase {
            letter-spacing: 0.08em;
        }

        /* User Status */
        .user-active { color: var(--success-color); }
        .user-inactive { color: var(--danger-color); }
        
        /* Role Badges */
        .role-badge {
            font-size: 0.8em;
            padding: 0.25em 0.6em;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        
        .role-admin { 
            background-color: var(--primary-color);
            color: white; 
        }
        
        .role-manager { 
            background-color: var(--info-color);
            color: white; 
        }
        
        .role-cashier { 
            background-color: var(--success-color);
            color: white; 
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content,
            .top-nav {
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
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>MTECH UGANDA</h3>
            <i class="fas fa-bars d-md-none"></i>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-menu-header">MAIN NAVIGATION</div>
            <a href="dashboard.php" class="sidebar-menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-file-invoice"></i>
                <span>Documents</span>
            </a>
            <a href="products.php" class="sidebar-menu-item">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="price-lists.php" class="sidebar-menu-item">
                <i class="fas fa-tags"></i>
                <span>Price Lists</span>
            </a>
            <a href="security.php" class="sidebar-menu-item active">
                <i class="fas fa-users-cog"></i>
                <span>Users & Security</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percent"></i>
                <span>Promotions</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-truck"></i>
                <span>Suppliers</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Reports</span>
            </a>
            
            <div class="sidebar-menu-header">SETTINGS</div>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-print"></i>
                <span>Print Stations</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Payment Types</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-globe"></i>
                <span>Countries</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percentage"></i>
                <span>Tax Rates</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-building"></i>
                <span>My Company</span>
            </a>
        </div>
    </div>
    
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="top-nav-title">Users & Security</div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user_fullname); ?></span>
            <span class="badge bg-primary"><?php echo htmlspecialchars($user_role); ?></span>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Users & Security</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg"></i> Add New User
                    </button>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>">
                                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="user-<?= $user['active'] ? 'active' : 'inactive' ?>">
                                            <i class="bi bi-circle-fill"></i> <?= $user['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <a href="#" class="btn btn-outline-<?= $user['active'] ? 'warning' : 'success' ?> toggle-status" 
                                                   data-user-id="<?= $user['id'] ?>" 
                                                   data-status="<?= $user['active'] ? '0' : '1' ?>"
                                                   title="<?= $user['active'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="bi bi-<?= $user['active'] ? 'x-circle' : 'check-circle' ?>"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
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
            </main>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle user status
        document.querySelectorAll('.toggle-status').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.dataset.userId;
                const status = this.dataset.status;
                
                if (confirm(`Are you sure you want to ${status === '1' ? 'activate' : 'deactivate'} this user?`)) {
                    window.location.href = `security.php?update_status=1&user_id=${userId}&status=${status}`;
                }
            });
        });

        // Check username availability
        const usernameInput = document.getElementById('username');
        const usernameFeedback = document.getElementById('usernameFeedback');
        
        if (usernameInput) {
            usernameInput.addEventListener('blur', function() {
                const username = this.value.trim();
                if (username) {
                    fetch(`check_username.php?username=${encodeURIComponent(username)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                usernameInput.classList.add('is-invalid');
                                usernameFeedback.textContent = 'Username already exists';
                            } else {
                                usernameInput.classList.remove('is-invalid');
                                usernameInput.classList.add('is-valid');
                            }
                        });
                }
            });
        }

        // Form validation
        const form = document.getElementById('addUserForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        }
    </script>
</body>
</html>
