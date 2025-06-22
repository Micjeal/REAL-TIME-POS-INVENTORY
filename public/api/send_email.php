<?php
/**
 * Email Sending Endpoint
 * Handles email notifications for user activities
 */

// Include configuration and database connection
require_once('../../includes/config.php');
require_once('../../includes/email_functions.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['type']) || !in_array($input['type'], ['login', 'logout', 'daily_report'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing email type']);
    exit;
}

// Get admin email from session or config
$adminEmail = 'admin@mtechuganda.com'; // Replace with actual admin email or get from config

// Prepare email data
$emailData = [
    'to' => $adminEmail,
    'subject' => '',
    'message' => '',
    'attachments' => []
];

// Set email content based on type
switch ($input['type']) {
    case 'login':
        $emailData['subject'] = 'User Login Notification';
        $emailData['message'] = "User {$input['username']} logged in at " . date('Y-m-d H:i:s');
        break;
        
    case 'logout':
        $emailData['subject'] = 'User Logout Notification';
        $emailData['message'] = "User {$input['username']} logged out at " . date('Y-m-d H:i:s');
        break;
        
    case 'daily_report':
        $emailData['subject'] = 'Daily Sales Report - ' . date('Y-m-d');
        $emailData['message'] = 'Please find attached the daily sales report.';
        // Add logic to generate and attach PDF report
        break;
}

// Send email
try {
    $result = sendEmail(
        $emailData['to'],
        $emailData['subject'],
        $emailData['message'],
        $emailData['attachments']
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        throw new Exception('Failed to send email');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
