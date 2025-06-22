<?php
/**
 * Test Email Script for MTECH UGANDA
 * This script tests the email sending functionality
 */

// Include configuration and email functions
require_once 'includes/config.php';
require_once 'includes/email_functions.php';

// Set the recipient email for testing
$test_email = 'admin@mtechuganda.com'; // Replace with actual test email

// Test email parameters
$subject = 'Test Email from MTECH UGANDA';
$message = '<h1>Test Email</h1>'
    . '<p>This is a test email sent from the MTECH UGANDA system.</p>'
    . '<p>If you are receiving this email, the email system is working correctly.</p>'
    . '<p>Time sent: ' . date('Y-m-d H:i:s') . '</p>';

// Send the test email
$result = sendEmail($test_email, $subject, $message);

// Output the result
echo '<!DOCTYPE html>
<html>
<head>
    <title>Email Test - MTECH UGANDA</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; }
        pre { background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email System Test</h1>';

if ($result) {
    echo '<div class="success">
            <h3>✅ Test Email Sent Successfully!</h3>
            <p>The test email was sent to: ' . htmlspecialchars($test_email) . '</p>
          </div>';
} else {
    echo '<div class="error">
            <h3>❌ Failed to Send Test Email</h3>
            <p>There was an error sending the test email to: ' . htmlspecialchars($test_email) . '</p>
            <p>Please check the error log for more details.</p>
          </div>';
}

// Display configuration (without sensitive data)
echo '<h3>Email Configuration</h3>
      <pre>SMTP Host: ' . htmlspecialchars(MAIL_HOST) . '
SMTP Port: ' . htmlspecialchars(MAIL_PORT) . '
SMTP Encryption: ' . htmlspecialchars(MAIL_ENCRYPTION) . '
From Email: ' . htmlspecialchars(MAIL_FROM_EMAIL) . '</pre>';

// Instructions for testing
echo '<h3>Next Steps</h3>
      <ol>
        <li>Check your email inbox (and spam folder) for the test email.</li>
        <li>If the email was not received, check the server error logs for details.</li>
        <li>Verify your SMTP settings in <code>includes/config.php</code> are correct.</li>
        <li>If using Gmail or similar, you may need to enable "Less secure app access" or use an App Password.</li>
      </ol>';

echo '</div>
</body>
</html>';
