<?php
/**
 * MTECH UGANDA Logout Script
 * Handles both traditional and AJAX logout requests
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default content type to JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendResponse($success, $message = '', $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Clear all session variables
    $username = $_SESSION['username'] ?? null;
    $_SESSION = array();

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Log the logout action
    if ($username) {
        error_log("User logged out: " . $username);
    }

    // Return JSON response for AJAX requests
    if ($isAjax) {
        sendResponse(true, 'Logout successful');
    }
    
    // For non-AJAX requests, redirect to login page
    header('Location: login.php');
    exit();

} catch (Exception $e) {
    // Log the error
    error_log('Logout error: ' . $e->getMessage());
    
    // Return error response for AJAX
    if (!empty($isAjax)) {
        sendResponse(false, 'An error occurred during logout');
    }
    
    // For non-AJAX, still redirect but log the error
    header('Location: login.php?error=logout_failed');
    exit();
}
