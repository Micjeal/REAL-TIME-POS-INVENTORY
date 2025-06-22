<?php
/**
 * Email Functions for MTECH UGANDA
 * Handles sending emails for various system events
 */

// Include PHPMailer classes manually
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Send an email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML supported)
 * @param array $attachments Optional array of file paths to attach
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $attachments = []) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = MAIL_DEBUG;  // Set debug level from config
        $mail->isSMTP();                // Send using SMTP
        $mail->Host = MAIL_HOST;        // Set the SMTP server
        $mail->Port = MAIL_PORT;        // Set the SMTP port
        
        // Authentication
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME; // SMTP username
        $mail->Password = MAIL_PASSWORD; // SMTP password
        
        // Encryption
        if (!empty(MAIL_ENCRYPTION)) {
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' ? 
                PHPMailer::ENCRYPTION_STARTTLS : 
                PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Character set
        $mail->CharSet = 'UTF-8';
        
        // Set timeout
        $mail->Timeout = 30; // 30 seconds
        
        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, SITE_NAME);
        $mail->addAddress($to);
        
        // Add reply-to as from address
        $mail->addReplyTo(MAIL_FROM_EMAIL, SITE_NAME);

        // Attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }


        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log detailed error information
        $errorMsg = sprintf(
            "Email sending failed to %s: %s. Mailer Error: %s",
            $to,
            $e->getMessage(),
            $mail->ErrorInfo
        );
        
        error_log($errorMsg);
        
        // Log the full email for debugging (without sensitive data)
        error_log(sprintf(
            "Email details - To: %s, Subject: %s, Attachments: %s",
            $to,
            $subject,
            !empty($attachments) ? implode(', ', $attachments) : 'None'
        ));
        
        return false;
    }
}

/**
 * Log user activity to the database
 * 
 * @param int $userId User ID
 * @param string $action Action performed (login, logout, etc.)
 * @param string $details Additional details about the action
 * @return bool True on success, false on failure
 */
function logUserActivity($userId, $action, $details = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $userId, $action, $details);
    
    return $stmt->execute();
}

/**
 * Generate daily sales report
 * 
 * @return string Path to the generated PDF report
 */
function generateDailySalesReport() {
    // This function would generate a PDF report of daily sales
    // Implementation depends on your sales data structure
    
    // For now, return an empty string as a placeholder
    return '';
}
