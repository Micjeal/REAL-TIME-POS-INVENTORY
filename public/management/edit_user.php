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

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get user ID from query string
$userId = $_GET['id'] ?? 0;

// Fetch user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: security.php?error=user_not_found');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    try {
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $userId]);
        
        header('Location: security.php?success=user_updated');
        exit();
    } catch (PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - MTECH UGANDA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit User: <?= htmlspecialchars($user['name']) ?></h1>
                    <a href="security.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Users
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" id="editUserForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                <div class="invalid-feedback">Please provide a full name.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="cashier" <?= $user['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                                    <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div>
                                    <span class="badge bg-<?= $user['active'] ? 'success' : 'danger' ?>">
                                        <?= $user['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                    <a href="security.php?user_id=<?= $user['id'] ?>&status=<?= $user['active'] ? '0' : '1' ?>&update_status=1" class="ms-2">
                                        (Change)
                                    </a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Login</label>
                                <div>
                                    <?= !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                    <i class="bi bi-key"></i> Reset Password
                                </button>
                                <div>
                                    <a href="security.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="reset_password.php">
                    <div class="modal-body">
                        <p>Are you sure you want to reset the password for <strong><?= htmlspecialchars($user['name']) ?></strong>?</p>
                        <p>The new password will be: <code>Welcome123</code></p>
                        <p class="text-muted">User will be required to change it on next login.</p>
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            
            // Fetch the form we want to apply custom Bootstrap validation styles to
            const form = document.getElementById('editUserForm');
            
            // Prevent submission if form is invalid
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        })();
    </script>
</body>
</html>
