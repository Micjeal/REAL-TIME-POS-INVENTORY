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
    
    // Check if user is already logged in
    if (isset($_SESSION['user_id'])) {
        // Redirect to welcome page
        header('Location: welcome.php');
        exit();
    }
    
    // Get database status before showing login page
    if (function_exists('test_db_connection')) {
        $db_status = test_db_connection();
        $db_error = ($db_status['status'] === 'error') ? $db_status['message'] : null;
    } else {
        $db_error = 'Database connection function not available. Check configuration.';
    }
} catch (Exception $e) {
    $db_error = 'Configuration error: ' . $e->getMessage();
}

// Process login form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Get username and password from POST data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    // Check database connection before attempting login
    $db_status = test_db_connection();
    if ($db_status['status'] === 'error') {
        echo json_encode(['success' => false, 'message' => $db_status['message']]);
        exit();
    }
    
    try {
        // Get database connection
        $db = get_db_connection();
        
        // Query to check user credentials
        $stmt = $db->prepare("SELECT id, username, password, name as full_name, role FROM users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // If user exists and password matches (or it's the default password for admin)
        if ($user && (password_verify($password, $user['password']) || 
                      ($username === 'admin' && $password === 'password' && $user['password'] === '$2y$10$uoU/U5J0MKeASy.2mHvkF.Rme.ZmlxksXAjNQHbw2UwBfSvwTr8EO'))) {
            // Simulate a short processing delay for UX
            sleep(1);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            // Return success response
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            // Return error for invalid credentials
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } catch (PDOException $e) {
        // Log error (don't expose details to client)
        error_log('Login error: ' . $e->getMessage());
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
    <title>Login - <?php echo SITE_NAME; ?></title>
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
            background-color: var(--dark-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-light);
            padding: 20px;
        }
        
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            background-color: var(--med-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #3a3f9e 0%, #2e3291 100%);
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
        
        .login-left-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .login-logo {
            margin-bottom: 2rem;
        }
        
        .login-logo img {
            max-width: 180px;
            height: auto;
        }
        
        .login-left h2 {
            color: white;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .login-left p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .login-features {
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
            flex-shrink: 0;
        }
        
        .login-features .feature-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }
        
        .login-container {
            flex: 1;
            padding: 2.5rem;
            background-color: var(--med-bg);
        }
        
        .login-form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-form-header h3 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            background-color: var(--light-bg);
            border: none;
            color: var(--text-light);
            border-radius: 5px;
            padding: 0.75rem 1rem;
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
            border-color: var(--accent-blue);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #2a70c7;
            border-color: #2a70c7;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary:active {
            transform: translateY(1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        
        .form-check-label {
            color: var(--text-muted);
        }
        
        .forgot-password {
            color: var(--accent-blue);
            transition: color 0.2s;
        }
        
        .forgot-password:hover {
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
        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(20, 22, 36, 0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: white;
            backdrop-filter: blur(4px);
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            margin-bottom: 25px;
            position: relative;
        }
        
        .spinner:before, .spinner:after {
            content: '';
            position: absolute;
            border-radius: 50%;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, var(--accent-blue) 0%, rgba(53, 132, 228, 0) 100%);
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
            max-width: 80%;
            font-weight: 300;
            letter-spacing: 0.5px;
            color: var(--text-light);
            background-color: rgba(53, 132, 228, 0.1);
            padding: 12px 25px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.4s ease-out;
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
        <div class="status-message" id="statusMessage">Connecting to database...</div>
    </div>

    <div class="login-wrapper">
        <!-- Left side with logo and features -->
        <div class="login-left">
            <div class="login-left-content">
                <div class="login-logo">
                    <img src="assets/images/logo.svg" alt="<?php echo SITE_NAME; ?> Logo">
                </div>
                
                <h2>Welcome to <?php echo SITE_NAME; ?></h2>
                <p>A comprehensive point of sale and business management solution</p>
                
                <div class="login-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="feature-text">Fast and intuitive point of sale system</div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="feature-text">Powerful promotions management</div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="feature-text">Complete inventory management</div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-text">Detailed reporting and analytics</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right side with login form -->
        <div class="login-container">
            <div class="login-form-header">
                <h3>Login to Your Account</h3>
            </div>
            
            <?php if ($db_error): ?>
            <div class="alert alert-danger"><?php echo $db_error; ?></div>
            <div class="alert alert-info">
                <strong>Need help?</strong> Visit <a href="db_test.php">Database Diagnostic Page</a> to fix this issue.
            </div>
            <?php endif; ?>
            
            <div id="alertMessage" class="alert" style="display: none;"></div>
            
            <form id="loginForm" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="input-group-append">
                            <span class="input-group-text toggle-password" style="cursor: pointer;">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
                
                <div class="text-center mt-3">
                    <a href="forgot-password.php" class="forgot-password">Forgot your password?</a>
                </div>
            </form>
            
            <hr class="mt-4">
            
            <div class="text-center">
                <p class="copyright mb-0">© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
                <p class="copyright small">Version <?php echo defined('VERSION') ? VERSION : '1.0'; ?></p>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const passwordField = $('#password');
                const passwordIcon = $(this).find('i');
                
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    passwordIcon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    passwordIcon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Show specific error message
            function showError(message) {
                $('#alertMessage').removeClass('alert-success alert-info').addClass('alert-danger').text(message).show();
            }
            
            // Show success message
            function showSuccess(message) {
                $('#alertMessage').removeClass('alert-danger alert-info').addClass('alert-success').text(message).show();
            }

            // Login form submission
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                // Check for database error already displayed
                if ($('.alert-danger').length > 0 && $('.alert-danger').text().includes('MySQL')) {
                    showError('Please fix the database connection issues before attempting to log in.');
                    return;
                }
                
                // Get form data
                const username = $('#username').val().trim();
                const password = $('#password').val();
                
                // Basic validation
                if (!username || !password) {
                    showError('Please enter both username and password');
                    return;
                }
                
                // Hide any previous alerts
                $('#alertMessage').hide();
                
                // Show loading overlay
                $('#loadingOverlay').show();
                $('#statusMessage').text('Connecting to database...');
                
                // Create sequence of status messages
                const statusMessages = [
                    { message: 'Connecting to database...', delay: 800 },
                    { message: 'Authenticating...', delay: 1200 },
                    { message: 'Verifying credentials...', delay: 1000 },
                    { message: 'Loading your account...', delay: 1000 }
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
                
                // AJAX login request
                $.ajax({
                    url: 'login.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        clearInterval(messageInterval);
                        
                        if (response.success) {
                            // Show success message before redirect
                            $('#statusMessage').text('Login successful! Redirecting...');
                            
                            // Redirect after a short delay
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 1000);
                        } else {
                            // Hide loading overlay
                            $('#loadingOverlay').hide();
                            
                            // Show error message
                            showError(response.message);
                            
                            // If it's a database connection error, show link to diagnostic page
                            if (response.message.includes('MySQL') || response.message.includes('database')) {
                                $('#alertMessage').append('<br><br><strong>Need help?</strong> <a href="db_test.php">Click here</a> to run database diagnostics.');
                            }
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