<?php
// Start the session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$user_created_at = $_SESSION['created_at'] ?? '';

// Get additional user details from database if needed
$user_details = [];
try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session variables with latest data
    if ($user_details) {
        $_SESSION['full_name'] = $user_details['full_name'] ?? $username;
        $_SESSION['email'] = $user_details['email'] ?? '';
        $_SESSION['phone'] = $user_details['phone'] ?? '';
        $_SESSION['created_at'] = $user_details['created_at'] ?? '';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Check for success/error messages from update operations
if (isset($_SESSION['profile_update_success'])) {
    $success_message = $_SESSION['profile_update_success'];
    unset($_SESSION['profile_update_success']);
} elseif (isset($_SESSION['password_update_success'])) {
    $success_message = $_SESSION['password_update_success'];
    unset($_SESSION['password_update_success']);
}

if (isset($_SESSION['profile_update_error'])) {
    $error_message = $_SESSION['profile_update_error'];
    unset($_SESSION['profile_update_error']);
} elseif (isset($_SESSION['password_update_error'])) {
    $error_message = $_SESSION['password_update_error'];
    unset($_SESSION['password_update_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Information - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1e2130;
            --med-bg: #2a2e43;
            --light-bg: #3a3f55;
            --text-light: #f0f0f0;
            --text-muted: #a0a0a0;
            --accent-blue: #3584e4;
            --accent-green: #2fac66;
            --accent-red: #e35d6a;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            margin: 0;
            padding: 0;
        }
        
        .user-profile-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: var(--med-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-bg);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 25px;
            font-size: 40px;
            color: var(--accent-blue);
            border: 3px solid var(--accent-blue);
        }
        
        .profile-info h2 {
            margin: 0;
            color: var(--text-light);
        }
        
        .profile-role {
            display: inline-block;
            padding: 3px 10px;
            background-color: var(--accent-blue);
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }
        
        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-card {
            background-color: var(--light-bg);
            padding: 20px;
            border-radius: 6px;
            transition: transform 0.2s;
        }
        
        .detail-card:hover {
            transform: translateY(-3px);
        }
        
        .detail-card h4 {
            color: var(--accent-blue);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        
        .detail-card h4 i {
            margin-right: 10px;
        }
        
        .detail-item {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 8px;
        }
        
        .detail-label {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .detail-value {
            color: var(--text-light);
            font-weight: 500;
            text-align: right;
        }
        
        .btn-edit {
            background-color: var(--accent-blue);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            margin-top: 20px;
        }
        
        .btn-edit:hover {
            background-color: #2a6fc9;
            color: white;
            text-decoration: none;
        }
        
        .btn-edit i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin: 0 auto 15px;
            }
            
            .profile-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="max-width: 1000px; margin: 20px auto 0;">
        <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="max-width: 1000px; margin: 20pxpx auto 0;">
        <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="user-profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user_fullname); ?></h2>
                <span class="profile-role"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
            </div>
        </div>
        
        <div class="profile-details">
            <div class="detail-card">
                <h4><i class="fas fa-id-card"></i> Personal Information</h4>
                <div class="detail-item">
                    <span class="detail-label">Username:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($username); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Full Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($user_fullname); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo !empty($user_email) ? htmlspecialchars($user_email) : 'Not set'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?php echo !empty($user_phone) ? htmlspecialchars($user_phone) : 'Not set'; ?></span>
                </div>
                
                <button type="button" class="btn-edit" data-toggle="modal" data-target="#editProfileModal">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
            </div>
            
            <div class="detail-card">
                <h4><i class="fas fa-shield-alt"></i> Account Security</h4>
                <div class="detail-item">
                    <span class="detail-label">Account Status:</span>
                    <span class="detail-value text-success">Active</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Member Since:</span>
                    <span class="detail-value">
                        <?php 
                        if (!empty($user_created_at)) {
                            $date = new DateTime($user_created_at);
                            echo $date->format('M d, Y');
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Login:</span>
                    <span class="detail-value">
                        <?php 
                        if (!empty($_SESSION['last_login'])) {
                            $date = new DateTime($_SESSION['last_login']);
                            echo $date->format('M d, Y H:i:s');
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">IP Address:</span>
                    <span class="detail-value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'N/A'; ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn-edit" data-toggle="modal" data-target="#changePasswordModal">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    <a href="password-history.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-history"></i> View History
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="background-color: var(--med-bg); color: var(--text-light);">
                <div class="modal-header" style="border-bottom: 1px solid var(--light-bg);">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-light);">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="update-password.php" method="POST" id="changePasswordForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#current_password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#new_password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--light-bg);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="background-color: var(--med-bg); color: var(--text-light);">
                <div class="modal-header" style="border-bottom: 1px solid var(--light-bg);">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile Information</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-light);">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="update-profile.php" method="POST" id="editProfileForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--light-bg);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Show success/error messages if they exist
            <?php if (!empty($success_message)): ?>
            showAlert('success', '<?php echo addslashes($success_message); ?>');
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            showAlert('danger', '<?php echo addslashes($error_message); ?>');
            <?php endif; ?>

            // Toggle password visibility
            $('.toggle-password').click(function() {
                const target = $($(this).data('target'));
                const type = target.attr('type') === 'password' ? 'text' : 'password';
                target.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // Form validation for change password
            $('#changePasswordForm').on('submit', function(e) {
                const newPassword = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showAlert('danger', 'New password and confirm password do not match.');
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    showAlert('danger', 'Password must be at least 8 characters long.');
                    return false;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
                
                return true;
            });
            
            // Close modal on successful password update
            <?php if (isset($_SESSION['password_update_success'])): ?>
            $('#changePasswordModal').modal('hide');
            <?php endif; ?>
            
            // Form validation for edit profile
            $('#editProfileForm').on('submit', function(e) {
                const email = $('#email').val();
                const phone = $('#phone').val();
                
                if (email && !isValidEmail(email)) {
                    e.preventDefault();
                    showAlert('danger', 'Please enter a valid email address.');
                    return false;
                }
                
                if (phone && !isValidPhone(phone)) {
                    e.preventDefault();
                    showAlert('danger', 'Please enter a valid phone number.');
                    return false;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                
                return true;
            });
            
            // Close modal on successful profile update
            <?php if (isset($_SESSION['profile_update_success'])): ?>
            $('#editProfileModal').modal('hide');
            <?php endif; ?>
            
            // Clear form fields when modal is closed
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $(this).find('button[type="submit"]').prop('disabled', false).html('Save Changes');
            });
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function isValidPhone(phone) {
                const re = /^[+]?[\s\-\(]?[0-9]{1,4}[\)\s\-]?[0-9]{6,14}$/;
                return re.test(phone);
            }
            
            function showAlert(type, message) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="max-width: 1000px; margin: 20px auto 0;">
                        ${message}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
                
                // Remove any existing alerts
                $('.alert').alert('close');
                
                // Add new alert and scroll to top
                $('body').prepend(alertHtml);
                $('html, body').animate({ scrollTop: 0 }, 'fast');
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    $('.alert').alert('close');
                }, 5000);
            }
        });
    </script>
</body>
</html>
