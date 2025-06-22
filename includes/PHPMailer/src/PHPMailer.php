<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 5.5
 * @package PHPMailer
 * @link https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Get the OAuth2BearerToken class.
 */
use League\OAuth2\Client\Token\AccessToken;

/**
 * PHPMailer - PHP email creation and transport class.
 * @package PHPMailer
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 */
class PHPMailer
{
    /**
     * The PHPMailer Version number.
     * @var string
     */
    const VERSION = '6.8.1';

    /**
     * Email priority.
     * Options: null (default), 1 = High, 3 = Normal, 5 = low.
     * When null, the header is not set at all.
     * @var int|null
     */
    public $Priority;

    /**
     * The character set of the message.
     * @var string
     */
    public $CharSet = 'utf-8';

    // ... [Previous class properties and methods remain the same] ...


    /**
     * Create a message and send it.
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array|string $attachments Array of file paths or a single file path
     * @return bool True on success, false on failure
     */
    public static function sendEmail($to, $subject, $body, $attachments = [])
    {
        $mail = new self();
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // Replace with your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply@mtechuganda.com'; // Replace with your email
            $mail->Password = 'your-password'; // Replace with your password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('noreply@mtechuganda.com', 'MTECH UGANDA');
            $mail->addAddress($to);

            // Attachments
            if (!empty($attachments)) {
                if (is_string($attachments)) {
                    $attachments = [$attachments];
                }
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (Exception $e) {
            error_log('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
