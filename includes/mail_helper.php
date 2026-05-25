<?php
/**
 * mail_helper.php
 * Centralized email sending and logging engine for IMP.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

if (!class_exists('SimpleSMTP')) {
    class SimpleSMTP {
        private $host;
        private $port;
        private $user;
        private $password;
        private $secure; // 'tls', 'ssl', or ''
        private $timeout = 10;
        private $logs = [];

        public function __construct($host, $port, $user, $password, $secure = '') {
            $this->host = $host;
            $this->port = $port;
            $this->user = $user;
            $this->password = $password;
            $this->secure = strtolower($secure);
        }

        public function getLogs() {
            return implode("\n", $this->logs);
        }

        private function log($message) {
            $this->logs[] = $message;
        }

        public function send($to, $fromEmail, $fromName, $subject, $htmlBody, $plainText = '') {
            $host = $this->host;
            if ($this->secure === 'ssl') {
                $host = 'ssl://' . $host;
            }

            $socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            if (!$socket) {
                $this->log("Failed to connect to $host:{$this->port}. Error: $errstr ($errno)");
                return false;
            }

            $this->log("Connected to $host:{$this->port}");

            if (!$this->expect($socket, 220)) {
                fclose($socket);
                return false;
            }

            $hello = 'EHLO ' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
            fwrite($socket, $hello . "\r\n");
            $this->log("> " . $hello);
            if (!$this->expect($socket, 250)) {
                fclose($socket);
                return false;
            }

            // Handle STARTTLS
            if ($this->secure === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $this->log("> STARTTLS");
                if (!$this->expect($socket, 220)) {
                    fclose($socket);
                    return false;
                }

                // Enable crypto
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }

                if (!@stream_socket_enable_crypto($socket, true, $crypto_method)) {
                    $this->log("Failed to enable crypto/STARTTLS");
                    fclose($socket);
                    return false;
                }

                // EHLO again after TLS started
                fwrite($socket, $hello . "\r\n");
                $this->log("> " . $hello);
                if (!$this->expect($socket, 250)) {
                    fclose($socket);
                    return false;
                }
            }

            // Authenticate
            if ($this->user && $this->password) {
                fwrite($socket, "AUTH LOGIN\r\n");
                $this->log("> AUTH LOGIN");
                if (!$this->expect($socket, 334)) {
                    fclose($socket);
                    return false;
                }

                $user64 = base64_encode($this->user);
                fwrite($socket, $user64 . "\r\n");
                $this->log("> [username encoded]");
                if (!$this->expect($socket, 334)) {
                    fclose($socket);
                    return false;
                }

                $pass64 = base64_encode($this->password);
                fwrite($socket, $pass64 . "\r\n");
                $this->log("> [password encoded]");
                if (!$this->expect($socket, 235)) {
                    fclose($socket);
                    return false;
                }
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<" . $fromEmail . ">\r\n");
            $this->log("> MAIL FROM:<" . $fromEmail . ">");
            if (!$this->expect($socket, 250)) {
                fclose($socket);
                return false;
            }

            // RCPT TO
            fwrite($socket, "RCPT TO:<" . $to . ">\r\n");
            $this->log("> RCPT TO:<" . $to . ">");
            if (!$this->expect($socket, 250)) {
                fclose($socket);
                return false;
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            $this->log("> DATA");
            if (!$this->expect($socket, 354)) {
                fclose($socket);
                return false;
            }

            // Prepare MIME Content
            $boundary = 'imp_mail_boundary_' . md5(uniqid(time()));
            
            $headers = [];
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "From: " . $this->encodeHeader($fromName) . " <" . $fromEmail . ">";
            $headers[] = "To: <" . $to . ">";
            $headers[] = "Subject: " . $this->encodeHeader($subject);
            $headers[] = "Date: " . date('r');
            $headers[] = "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"";
            $headers[] = "X-Mailer: IMP PHP Mailer";
            
            if (empty($plainText)) {
                $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            }

            $bodyContent = "--" . $boundary . "\r\n";
            $bodyContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $bodyContent .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $bodyContent .= $plainText . "\r\n\r\n";
            
            $bodyContent .= "--" . $boundary . "\r\n";
            $bodyContent .= "Content-Type: text/html; charset=UTF-8\r\n";
            $bodyContent .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $bodyContent .= $htmlBody . "\r\n\r\n";
            $bodyContent .= "--" . $boundary . "--\r\n";

            $fullPayload = implode("\r\n", $headers) . "\r\n\r\n" . $bodyContent . "\r\n.\r\n";

            fwrite($socket, $fullPayload);
            $this->log("> [Sent Data Payload]");

            if (!$this->expect($socket, 250)) {
                fclose($socket);
                return false;
            }

            fwrite($socket, "QUIT\r\n");
            $this->log("> QUIT");
            $this->expect($socket, 221);

            fclose($socket);
            return true;
        }

        private function expect($socket, $expectedCode) {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                $this->log("< " . trim($line));
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            $code = intval(substr($response, 0, 3));
            return $code === $expectedCode;
        }

        private function encodeHeader($str) {
            return "=?UTF-8?B?" . base64_encode($str) . "?=";
        }
    }
}

if (!function_exists('sendEmail')) {
    /**
     * Reusable email sending function using PHPMailer SMTP.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $body
     * @return bool True on success, false on failure
     */
    function sendEmail($toEmail, $toName, $subject, $body) {
        $mail = new PHPMailer(true);
        $debugOutput = '';
        try {
            $mail->SMTPDebug  = 3; // Enable detailed debug output
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                $debugOutput .= "[$level] " . trim($str) . "\n";
            };

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // Set SMTP credentials (use environment variables if available, otherwise fallback to admin credentials)
            $smtpUser = getenv("MAIL_USERNAME") ?: 'imp.webportal2026@gmail.com';
            $smtpPass = getenv("MAIL_PASSWORD") ?: 'qpnnwehjawuxcvob';
            
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Timeout and connection settings to prevent blocking
            $mail->Timeout       = 10;
            $mail->SMTPKeepAlive = false;

            $mail->CharSet = 'UTF-8';

            $fromName = getenv("MAIL_FROM_NAME") ?: "Internship Management Platform";
            $mail->setFrom($smtpUser, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            $logPath = __DIR__ . "/../email_notifications.log";
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] PHPMailer sending failed to $toEmail. Error: " . $e->getMessage() . " (" . $mail->ErrorInfo . ")\n" .
                            "SMTP Debug Logs:\n" . $debugOutput . "\n";
            @file_put_contents($logPath, $errorMessage, FILE_APPEND);
            return false;
        }
    }
}

if (!function_exists('sendEmailNotification')) {
    /**
     * Sends a premium HTML email notification and logs it to db/file system.
     *
     * @param mixed  $recipient   User ID (int) or Email address (string)
     * @param string $subject     Subject of the email
     * @param string $messageText Body message in plain text/markdown
     * @param array  $metadata    Key-value pairs to display in the email body summary card
     * @return bool True if logged and sent, false otherwise
     */
    function sendEmailNotification($recipient, $subject, $messageText, $metadata = []) {
        global $conn;

        $user_id = null;
        $email = '';
        $fullName = '';

        // 1. Check metadata first for recipient_name, full_name, or registered_name
        if (!empty($metadata)) {
            if (isset($metadata['recipient_name']) && trim($metadata['recipient_name']) !== '') {
                $fullName = trim($metadata['recipient_name']);
            } elseif (isset($metadata['full_name']) && trim($metadata['full_name']) !== '') {
                $fullName = trim($metadata['full_name']);
            } elseif (isset($metadata['registered_name']) && trim($metadata['registered_name']) !== '') {
                $fullName = trim($metadata['registered_name']);
            }
        }

        // 2. Resolve recipient details
        if (is_numeric($recipient)) {
            $user_id = intval($recipient);
            $user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
            $res = mysqli_query($conn, $user_sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $email = trim($row['email']);
                if (empty($fullName) && !empty($row['full_name'])) {
                    $fullName = trim($row['full_name']);
                }
            }
            // Fall back to student_profiles if name or email is empty
            if (empty($fullName) || empty($email)) {
                $prof_sql = "SELECT email, full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
                $prof_res = mysqli_query($conn, $prof_sql);
                if ($prof_res && $prof_row = mysqli_fetch_assoc($prof_res)) {
                    if (empty($email)) {
                        $email = trim($prof_row['email']);
                    }
                    if (empty($fullName) && !empty($prof_row['full_name'])) {
                        $fullName = trim($prof_row['full_name']);
                    }
                }
            }
        } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $email = trim($recipient);
            $esc_email = mysqli_real_escape_string($conn, $email);
            $user_sql = "SELECT id, full_name FROM users WHERE email = '$esc_email' LIMIT 1";
            $res = mysqli_query($conn, $user_sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $user_id = intval($row['id']);
                if (empty($fullName) && !empty($row['full_name'])) {
                    $fullName = trim($row['full_name']);
                }
            }
            // Fall back to student profiles using user_id if we have it, or email otherwise
            if (empty($fullName)) {
                if ($user_id) {
                    $prof_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
                } else {
                    $prof_sql = "SELECT user_id, full_name FROM student_profiles WHERE email = '$esc_email' LIMIT 1";
                }
                $prof_res = mysqli_query($conn, $prof_sql);
                if ($prof_res && $prof_row = mysqli_fetch_assoc($prof_res)) {
                    if (empty($user_id) && !empty($prof_row['user_id'])) {
                        $user_id = intval($prof_row['user_id']);
                    }
                    if (empty($fullName) && !empty($prof_row['full_name'])) {
                        $fullName = trim($prof_row['full_name']);
                    }
                }
            }
        }

        if (empty($fullName)) {
            $fullName = 'User';
        }

        if (empty($email)) {
            return false;
        }

        // Apply metadata default values
        $event_name = isset($metadata['event']) ? $metadata['event'] : 'Platform Notification';
        $action_url = isset($metadata['action_url']) ? $metadata['action_url'] : 'http://localhost/IMP/login.php';
        $action_label = isset($metadata['action_label']) ? $metadata['action_label'] : 'Go to Dashboard';

        // 2. Build premium HTML Body
        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0f172a;
            padding: 32px 40px;
            text-align: center;
            border-bottom: 4px solid #2563eb;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .header p {
            color: #94a3b8;
            margin: 4px 0 0 0;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .content {
            padding: 40px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .message {
            font-size: 15px;
            color: #334155;
            margin-bottom: 28px;
        }
        .details-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .details-card h3 {
            margin: 0 0 12px 0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.05em;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td {
            padding: 6px 0;
            font-size: 14px;
            vertical-align: top;
        }
        .details-table td.label {
            color: #64748b;
            font-weight: 600;
            width: 35%;
        }
        .details-table td.value {
            color: #0f172a;
            font-weight: 700;
        }
        .cta-wrapper {
            text-align: center;
            margin: 32px 0 12px 0;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 32px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: background-color 0.2s;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        .footer p {
            margin: 4px 0;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>IMP</h1>
            <p>Internship Management Platform</p>
        </div>
        <div class="content">
            <div class="greeting">Hello ' . htmlspecialchars($fullName) . ',</div>
            <div class="message">' . nl2br($messageText) . '</div>';

        if (!empty($metadata)) {
            $htmlBody .= '
            <div class="details-card">
                <h3>Transaction Details</h3>
                <table class="details-table">
                    <tr>
                        <td class="label">Event</td>
                        <td class="value">' . htmlspecialchars($event_name) . '</td>
                    </tr>';
            foreach ($metadata as $key => $val) {
                if (in_array($key, ['event', 'action_url', 'action_label'])) {
                    continue;
                }
                $htmlBody .= '
                    <tr>
                        <td class="label">' . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . '</td>
                        <td class="value">' . htmlspecialchars($val) . '</td>
                    </tr>';
            }
            $htmlBody .= '
                </table>
            </div>';
        }

        $htmlBody .= '
            <div class="cta-wrapper">
                <a href="' . htmlspecialchars($action_url) . '" class="btn">' . htmlspecialchars($action_label) . '</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated system notification from the IMP Platform.</p>
            <p>&copy; ' . date('Y') . ' Internship Management Platform. All rights reserved.</p>
            <p><a href="http://localhost/IMP/login.php">Log In to Account</a> &bull; <a href="#">Support Center</a></p>
        </div>
    </div>
</body>
</html>';

        $sent = sendEmail($email, $fullName, $subject, $htmlBody);
        $final_status = $sent ? 'Sent' : 'Failed';
        $engine_info = "Sent via PHPMailer SMTP (smtp.gmail.com:587)";
        $smtp_logs = $sent ? "PHPMailer sent successfully." : "PHPMailer sending failed. Check email_notifications.log.";

        // 3. Log to Database
        $esc_user_id = $user_id !== null ? $user_id : 'NULL';
        $esc_email = mysqli_real_escape_string($conn, $email);
        $esc_name = mysqli_real_escape_string($conn, $fullName);
        $esc_subject = mysqli_real_escape_string($conn, $subject);
        $esc_msg = mysqli_real_escape_string($conn, $messageText);
        $esc_html = mysqli_real_escape_string($conn, $htmlBody);
        $esc_status = mysqli_real_escape_string($conn, $final_status);

        $log_sql = "INSERT INTO email_notifications_log 
                    (user_id, recipient_email, recipient_name, subject, message_text, html_body, status) 
                    VALUES ($esc_user_id, '$esc_email', '$esc_name', '$esc_subject', '$esc_msg', '$esc_html', '$esc_status')";
        mysqli_query($conn, $log_sql);

        // 4. Log to File System for easy sandbox verification only on failure
        if (!$sent) {
            $log_file = __DIR__ . "/../email_notifications.log";
            $file_log = "========================================================================\n";
            $file_log .= "[" . date('Y-m-d H:i:s') . "] OUTGOING EMAIL - STATUS: " . $final_status . "\n";
            $file_log .= "ENGINE: " . $engine_info . "\n";
            $file_log .= "TO: " . $email . " (" . $fullName . ") [ID: " . ($user_id ?: 'N/A') . "]\n";
            $file_log .= "SUBJECT: " . $subject . "\n";
            $file_log .= "MESSAGE TEXT: " . $messageText . "\n";
            $file_log .= "METADATA: " . json_encode($metadata) . "\n";
            $file_log .= "------------------------------------------------------------------------\n";
            $file_log .= "SMTP LOGS:\n" . $smtp_logs . "\n";
            $file_log .= "------------------------------------------------------------------------\n";
            $file_log .= "HTML body generated (" . strlen($htmlBody) . " bytes).\n";
            $file_log .= "========================================================================\n\n";
            @file_put_contents($log_file, $file_log, FILE_APPEND);
        }

        return $sent;
    }
}
