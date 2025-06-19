<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define a site name constant if it doesn't exist (in case config.php fails)
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'MTECH UGANDA');
}

// Include database configuration - in a try/catch to prevent fatal errors
try {
    require_once 'config.php';
    
    // Check database status before showing page
    if (function_exists('test_db_connection')) {
        $db_status = test_db_connection();
        $db_error = ($db_status['status'] === 'error') ? $db_status['message'] : null;
    } else {
        $db_error = 'Database connection function not available. Check configuration.';
    }
} catch (Exception $e) {
    $db_error = 'Configuration error: ' . $e->getMessage();
}

// Process password recovery form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Get username from POST data
    $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
    
    // Validate input
    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Username or email is required']);
        exit();
    }
    
    // Check database connection before proceeding
    $db_status = test_db_connection();
    if ($db_status['status'] === 'error') {
        echo json_encode(['success' => false, 'message' => $db_status['message']]);
        exit();
    }
    
    try {
        // Get database connection
        $db = get_db_connection();
        
        // Query to find user by username or email
        $stmt = $db->prepare("SELECT username, password, name as full_name, email FROM users WHERE (username = ? OR email = ?) AND active = 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        // Simulate a short processing delay for UX
        sleep(1);
        
        if ($user) {
            // Extract the hashed password
            $hashed_password = $user['password'];
            $username = $user['username'];
            $full_name = $user['full_name'];
            $email = $user['email'];
            
            // Return success with user details and password
            echo json_encode([
                'success' => true, 
                'username' => $username,
                'full_name' => $full_name,
                'email' => $email,
                'password' => $hashed_password
            ]);
        } else {
            // Return error for user not found
            echo json_encode(['success' => false, 'message' => 'No active user found with that username or email']);
        }
    } catch (PDOException $e) {
        // Log error (don't expose details to client)
        error_log('Password recovery error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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
            --danger-red: #e74c3c;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .login-wrapper {
            display: flex;
            max-width: 900px;
            width: 100%;
            background-color: var(--med-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .login-left {
            flex: 1;
            background-color: var(--accent-blue);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/pattern.png');
            opacity: 0.1;
        }
        
        .login-logo {
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .login-logo img {
            max-width: 180px;
            height: auto;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .login-subtitle {
            opacity: 0.8;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .login-features {
            position: relative;
            z-index: 1;
            text-align: left;
            margin-top: 2rem;
        }
        
        .login-features .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .login-features .feature-icon {
            color: white;
            margin-right: 10px;
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            background-color: var(--med-bg);
            border: none;
            border-radius: 5px;
            color: var(--text-light);
            padding: 12px 15px;
            height: auto;
        }
        
        .form-control:focus {
            background-color: var(--light-bg);
            color: var(--text-light);
            box-shadow: 0 0 0 3px rgba(53, 132, 228, 0.3);
            border: none;
        }
        
        .input-group-text {
            background-color: var(--light-bg);
            border: none;
            color: var(--text-muted);
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            border: none;
            padding: 12px 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #2b6fc7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        .form-footer a {
            color: var(--accent-blue);
        }
        
        .form-footer a:hover {
            color: #2a70c7;
            text-decoration: none;
        }
        
        .copyright {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-left {
                padding: 2rem;
            }
            
            .login-features {
                display: none;
            }
        }
        
        /* Alert styles */
        .alert {
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(30, 33, 48, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .spinner {
            position: relative;
            width: 60px;
            height: 60px;
            margin-bottom: 20px;
        }
        
        .spinner:before, .spinner:after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: var(--accent-blue);
            opacity: 0.6;
            animation: pulse 2s ease-in-out infinite alternate;
        }
        
        .spinner:after {
            animation-delay: 1s;
            opacity: 0.5;
        }
        
        @keyframes pulse {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(1.2); opacity: 0; }
        }
        
        .status-message {
            font-size: 1.2rem;
            text-align: center;
            margin-top: 20px;
            color: var(--text-light);
            max-width: 80%;
        }
        
        /* Password result styling */
        .password-result {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background-color: var(--light-bg);
            border-radius: 5px;
            animation: fadeInUp 0.4s ease-out;
        }
        
        .password-field {
            font-family: monospace;
            padding: 10px;
            background-color: var(--dark-bg);
            border-radius: 3px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        .user-info {
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        
        .user-info p {
            margin-bottom: 5px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="status-message" id="statusMessage">Searching for user account...</div>
    </div>

    <div class="login-wrapper">
        <!-- Left side with logo and features -->
        <div class="login-left">
            <div class="login-logo">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo">
            </div>
            <h1 class="login-title"><?php echo SITE_NAME; ?></h1>
            <p class="login-subtitle">Business Management System</p>
            
            <div class="login-features">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <div>Secure user account recovery</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-database"></i></div>
                    <div>Direct database password retrieval</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-lock"></i></div>
                    <div>Instant account access</div>
                </div>
            </div>
        </div>
        
        <!-- Right side with password recovery form -->
        <div class="login-right">
            <h2 class="login-form-title">Password Recovery</h2>
            
            <?php if (isset($db_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Database Error: <?php echo $db_error; ?>
                <br><br>
                <strong>Troubleshooting:</strong>
                <ol>
                    <li>Check if MySQL service is running</li>
                    <li>Verify database credentials in config.php</li>
                    <li>Ensure database schema is properly set up</li>
                </ol>
                <a href="db_test.php" class="btn btn-sm btn-outline-danger mt-2">Run Database Diagnostic</a>
            </div>
            <?php endif; ?>
            
            <div id="alertMessage" class="alert" style="display: none;"></div>
            
            <div id="passwordForm">
                <form id="recoverPasswordForm">
                    <div class="form-group">
                        <label for="identifier">Username or Email</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" id="identifier" name="identifier" placeholder="Enter your username or email">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Find My Password
                    </button>
                </form>
                
                <div class="password-result" id="passwordResult">
                    <h4><i class="fas fa-user-check"></i> Account Found</h4>
                    <div class="user-info">
                        <p><strong>Username:</strong> <span id="usernameResult"></span></p>
                        <p><strong>Full Name:</strong> <span id="fullNameResult"></span></p>
                        <p><strong>Email:</strong> <span id="emailResult"></span></p>
                    </div>
                    <h5><i class="fas fa-key"></i> Your Password</h5>
                    <div class="password-field" id="passwordField"></div>
                    <p class="text-muted small">This is the encrypted password hash stored in our database.</p>
                    <p class="mt-3">If you need to reset your password, please contact the system administrator.</p>
                </div>
                
                <div class="form-footer">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    <p class="copyright mt-4">© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Function to show error message
            function showError(message) {
                $('#alertMessage').removeClass('alert-success').addClass('alert-danger').html('<i class="fas fa-exclamation-circle"></i> ' + message).show();
            }
            
            // Function to show success message
            function showSuccess(message) {
                $('#alertMessage').removeClass('alert-danger').addClass('alert-success').html('<i class="fas fa-check-circle"></i> ' + message).show();
            }
            
            // Handle form submission
            $('#recoverPasswordForm').on('submit', function(e) {
                e.preventDefault();
                
                // Get form data
                const identifier = $('#identifier').val().trim();
                
                // Basic validation
                if (!identifier) {
                    showError('Please enter your username or email');
                    return;
                }
                
                // Hide any previous alerts
                $('#alertMessage').hide();
                $('#passwordResult').hide();
                
                // Show loading overlay
                $('#loadingOverlay').show();
                $('#statusMessage').text('Searching for user account...');
                
                // Create sequence of status messages
                const statusMessages = [
                    { message: 'Searching for user account...', delay: 800 },
                    { message: 'Connecting to database...', delay: 1000 },
                    { message: 'Retrieving account information...', delay: 1200 },
                    { message: 'Fetching password data...', delay: 1000 }
                ];
                
                // Display status messages sequence
                let messageIndex = 0;
                const messageInterval = setInterval(function() {
                    if (messageIndex < statusMessages.length) {
                        $('#statusMessage').text(statusMessages[messageIndex].message);
                        messageIndex++;
                    } else {
                        clearInterval(messageInterval);
                    }
                }, 1000);
                
                // AJAX request
                $.ajax({
                    url: 'forgot-password.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        clearInterval(messageInterval);
                        $('#loadingOverlay').hide();
                        
                        if (response.success) {
                            // Populate and show password result
                            $('#usernameResult').text(response.username);
                            $('#fullNameResult').text(response.full_name || 'Not available');
                            $('#emailResult').text(response.email || 'Not available');
                            $('#passwordField').text(response.password);
                            $('#passwordResult').show();
                            
                            // Show success message
                            showSuccess('Account found! Your password is displayed below.');
                        } else {
                            // Show error message
                            showError(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        clearInterval(messageInterval);
                        $('#loadingOverlay').hide();
                        
                        // Show a more helpful error message
                        showError('Connection error. Please check your internet connection or server status.');
                    }
                });
            });
        });
    </script>
</body>
</html>
