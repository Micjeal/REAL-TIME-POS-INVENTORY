<?php
date_default_timezone_set('UTC');
/**
 * PHPMailer exception handler
 * @package PHPMailer
 */
class phpmailerException extends Exception
{
    /**
     * Prettify error message output
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage()) . "</strong><br />\n";
    }
}
