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
    require_once __DIR__ . '/../email_helper.php';
}

if (isset($conn) && $conn instanceof mysqli) {
    @include_once __DIR__ . '/../ensure_extended_schema.php';
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
    function sendEmailNotification($recipient, $subject, $messageText, $metadata = [], &$errorOutput = null) {
        global $conn;
        $errorOutput = '';

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
            $errorOutput = 'Email failed: Invalid recipient email';
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
                <table class="details-table">';

            // If sender metadata exists, show it prominently
            if (!empty($metadata['sender_name']) || !empty($metadata['sender_role'])) {
                $senderDisplay = '';
                if (!empty($metadata['sender_name'])) {
                    $senderDisplay = htmlspecialchars($metadata['sender_name']);
                }
                if (!empty($metadata['sender_role'])) {
                    $senderDisplay .= ($senderDisplay !== '' ? ' — ' : '') . htmlspecialchars(ucfirst($metadata['sender_role']));
                }
                if ($senderDisplay !== '') {
                    $htmlBody .= '
                    <tr>
                        <td class="label">Sent By</td>
                        <td class="value">' . $senderDisplay . '</td>
                    </tr>';
                }
            }

            $htmlBody .= '
                    <tr>
                        <td class="label">Event</td>
                        <td class="value">' . htmlspecialchars($event_name) . '</td>
                    </tr>';

            foreach ($metadata as $key => $val) {
                if (in_array($key, ['event', 'action_url', 'action_label', 'sender_name', 'sender_role'])) {
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
        $smtp_logs = '';
        $sent = sendEmail($email, $fullName, $subject, $htmlBody, $smtp_logs);
        $final_status = $sent ? 'Sent' : 'Failed';
        $engine_info = "Sent via PHPMailer SMTP (smtp.gmail.com:587)";
               $clean_err = '';
        if (!$sent) {
            $errorInfo = '';
            // Expose actual PHPMailer error (inside parenthesized suffix of SMTP logs)
            if (preg_match('/Error: .*? \((.*?)\)/i', $smtp_logs, $matches)) {
                $errorInfo = trim($matches[1]);
            }
            if (empty($errorInfo)) {
                // Fallback exception search
                if (preg_match('/Exception: (.*?)(?:\(|Debug|$)/i', $smtp_logs, $matches)) {
                    $errorInfo = trim($matches[1]);
                }
            }
            if (empty($errorInfo)) {
                $errorInfo = str_replace(["\n", "\r"], ' ', substr(strip_tags($smtp_logs), 0, 100));
            }
            if (empty($errorInfo)) {
                $errorInfo = 'Unknown error occurred';
            }

            // Expose exact reasons: SMTP Authentication failed, Invalid credentials, Connection timeout, Invalid sender address, Recipient rejected
            if (stripos($smtp_logs, 'Username and Password not accepted') !== false || 
                stripos($smtp_logs, 'Password not accepted') !== false || 
                stripos($smtp_logs, 'Invalid credentials') !== false) {
                $clean_err = 'Email failed: Invalid credentials';
            } elseif (stripos($smtp_logs, 'authenticate') !== false || 
                      stripos($smtp_logs, 'authentication') !== false) {
                $clean_err = 'Email failed: SMTP Authentication failed';
            } elseif (stripos($smtp_logs, 'timeout') !== false || 
                      stripos($smtp_logs, 'timed out') !== false || 
                      stripos($smtp_logs, 'Connection timed out') !== false) {
                $clean_err = 'Email failed: Connection timeout';
            } elseif (stripos($smtp_logs, 'connect') !== false || 
                      stripos($smtp_logs, 'connection') !== false || 
                      stripos($smtp_logs, 'Could not connect') !== false) {
                $clean_err = 'Email failed: SMTP Connection failed';
            } elseif (stripos($smtp_logs, 'sender address') !== false || 
                      $errorInfo === 'Invalid address' ||
                      stripos($smtp_logs, 'sender rejected') !== false || 
                      stripos($smtp_logs, 'From address') !== false) {
                $clean_err = 'Email failed: Invalid sender address';
            } elseif (stripos($smtp_logs, 'recipient rejected') !== false || 
                      stripos($smtp_logs, 'Recipient address rejected') !== false || 
                      stripos($smtp_logs, 'Invalid recipient') !== false ||
                      stripos($smtp_logs, 'Address rejected') !== false) {
                $clean_err = 'Email failed: Recipient rejected';
            } else {
                $clean_err = 'Email failed: ' . $errorInfo;
            }
            $errorOutput = $clean_err;
        }

        // 3. Log to Database
        $esc_user_id = $user_id !== null ? $user_id : 'NULL';
        $esc_email = mysqli_real_escape_string($conn, $email);
        $esc_name = mysqli_real_escape_string($conn, $fullName);
        $esc_subject = mysqli_real_escape_string($conn, $subject);
        $esc_msg = mysqli_real_escape_string($conn, $messageText);
        $esc_html = mysqli_real_escape_string($conn, $htmlBody);
        $esc_status = mysqli_real_escape_string($conn, $final_status);

        $sender_id_val = 'NULL';
        $sender_role_val = 'NULL';
        if (session_status() === PHP_SESSION_NONE) {@session_start();}
        if (!empty($_SESSION['user_id'])) { $sender_id_val = intval($_SESSION['user_id']); }
        if (!empty($_SESSION['role'])) { $sender_role_val = "'" . mysqli_real_escape_string($conn, $_SESSION['role']) . "'"; }

        $email_notifications_has_user_col = false;
        $email_notifications_has_sender_cols = false;
        $log_columns = '(recipient_email, recipient_name, subject, message_text, html_body, status';
        $log_values = "('$esc_email', '$esc_name', '$esc_subject', '$esc_msg', '$esc_html', '$esc_status'";
        $notification_user_check = mysqli_query($conn, "SHOW COLUMNS FROM email_notifications_log LIKE 'user_id'");
        if ($notification_user_check && mysqli_num_rows($notification_user_check) > 0) {
            $email_notifications_has_user_col = true;
            $log_columns .= ', user_id';
            $log_values .= ", $esc_user_id";
        }
        $notification_check = mysqli_query($conn, "SHOW COLUMNS FROM email_notifications_log LIKE 'sender_id'");
        if ($notification_check && mysqli_num_rows($notification_check) > 0) {
            $notification_check2 = mysqli_query($conn, "SHOW COLUMNS FROM email_notifications_log LIKE 'sender_role'");
            if ($notification_check2 && mysqli_num_rows($notification_check2) > 0) {
                $email_notifications_has_sender_cols = true;
            }
        }
        if ($email_notifications_has_sender_cols) {
            $log_columns .= ', sender_id, sender_role';
            $log_values .= ", $sender_id_val, $sender_role_val";
        }
        $log_columns .= ')';
        $log_values .= ')';
        $log_sql = "INSERT INTO email_notifications_log $log_columns VALUES $log_values";
        mysqli_query($conn, $log_sql);

        // Also log to email_logs table when available
        $recipient_role = isset($metadata['recipient_role']) ? mysqli_real_escape_string($conn, $metadata['recipient_role']) : null;
        if (empty($recipient_role) && is_numeric($recipient)) {
            $role_query = mysqli_query($conn, "SELECT role FROM users WHERE id = " . intval($recipient) . " LIMIT 1");
            if ($role_query && $row = mysqli_fetch_assoc($role_query)) {
                $recipient_role = mysqli_real_escape_string($conn, $row['role']);
            }
        }
        $recipient_role_sql = $recipient_role ? "'" . mysqli_real_escape_string($conn, $recipient_role) . "'" : 'NULL';
        $err_msg_safe = $sent ? 'NULL' : "'" . mysqli_real_escape_string($conn, ($clean_err ? $clean_err . " | " : "") . $smtp_logs) . "'";
        // Capture sender details when available in session
        $sender_id_sql = 'NULL';
        $sender_role_sql = 'NULL';
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            $sender_id_sql = intval($_SESSION['user_id']);
        }
        if (!empty($_SESSION['role'])) {
            $sender_role_sql = "'" . mysqli_real_escape_string($conn, $_SESSION['role']) . "'";
        }

        $email_logs_has_user_col = false;
        $email_logs_has_sender_cols = false;
        $log_cols = '(recipient_email, recipient_role, subject, status, error_message';
        $log_vals = "('$esc_email', $recipient_role_sql, '$esc_subject', '$esc_status', $err_msg_safe";
        $email_log_user_check = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE 'user_id'");
        if ($email_log_user_check && mysqli_num_rows($email_log_user_check) > 0) {
            $email_logs_has_user_col = true;
            $log_cols .= ', user_id';
            $log_vals .= ", $esc_user_id";
        }
        $email_log_check = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE 'sender_id'");
        if ($email_log_check && mysqli_num_rows($email_log_check) > 0) {
            $email_log_check2 = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE 'sender_role'");
            if ($email_log_check2 && mysqli_num_rows($email_log_check2) > 0) {
                $email_logs_has_sender_cols = true;
            }
        }
        if ($email_logs_has_sender_cols) {
            $log_cols .= ', sender_id, sender_role';
            $log_vals .= ", $sender_id_sql, $sender_role_sql";
        }
        $log_cols .= ')';
        $log_vals .= ')';
        $email_log_sql = "INSERT INTO email_logs $log_cols VALUES $log_vals";
        @mysqli_query($conn, $email_log_sql);

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

if (!function_exists('sendStudentNotification')) {
    /**
     * Sends a student-facing email notification using the centralized email helper.
     *
     * @param mixed  $recipient      User ID or email address
     * @param string $recipientName  Student's display name
     * @param string $subject        Email subject
     * @param string $messageText    Plain text email body
     * @param array  $metadata       Optional metadata for the email summary card
     * @return bool
     */
    function sendStudentNotification($recipient, $recipientName, $subject, $messageText, $metadata = []) {
        if (!empty($recipientName)) {
            $metadata['recipient_name'] = trim($recipientName);
        }
        $metadata['event'] = $metadata['event'] ?? 'Student Notification';
        return sendEmailNotification($recipient, $subject, $messageText, $metadata);
    }
}

if (!function_exists('createNotification')) {
    /**
     * Create a dashboard notification record for the given user/role.
     */
    function createNotification($userId, $role, $title, $message, $notification_type = 'info', $type = 'general', $attachment_path = null, $attachment_name = null, $attachment_size = null, $attachment_type = null) {
        global $conn;
        $userId = intval($userId);
        $role = strtolower(trim((string) $role));
        $notifType = trim($notification_type ?: 'info');
        $titleStr = trim((string) $title);
        $messageStr = trim((string) $message);
        
        // Ensure type always has a default value
        $type = !empty($type) ? trim((string)$type) : 'general';

        // Only insert into the unified notifications table.
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
        if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
            // Get existing columns to avoid query failures on different database environments
            $columnsRes = mysqli_query($conn, "SHOW COLUMNS FROM notifications");
            $existingCols = [];
            if ($columnsRes) {
                while ($colRow = mysqli_fetch_assoc($columnsRes)) {
                    $existingCols[strtolower($colRow['Field'])] = true;
                }
            }

            $insertData = [];

            if (isset($existingCols['user_id'])) {
                $insertData['user_id'] = [intval($userId), 'i'];
            }

            if (isset($existingCols['user_role'])) {
                $insertData['user_role'] = [$role, 's'];
            } elseif (isset($existingCols['role'])) {
                $insertData['role'] = [$role, 's'];
            }

            if (isset($existingCols['type'])) {
                $insertData['type'] = [$type, 's'];
            }

            if (isset($existingCols['title'])) {
                $insertData['title'] = [$titleStr, 's'];
            }

            if (isset($existingCols['message'])) {
                $insertData['message'] = [$messageStr, 's'];
            }

            if (isset($existingCols['category'])) {
                $insertData['category'] = [$notifType, 's'];
            } elseif (isset($existingCols['notification_type'])) {
                $insertData['notification_type'] = [$notifType, 's'];
            }

            if (isset($existingCols['is_read'])) {
                $insertData['is_read'] = [0, 'i'];
            }

            if (isset($existingCols['created_at'])) {
                $insertData['created_at'] = [date('Y-m-d H:i:s'), 's'];
            }

            if (isset($existingCols['attachment_path'])) {
                $insertData['attachment_path'] = [$attachment_path, 's'];
            }

            if (isset($existingCols['attachment_name'])) {
                $insertData['attachment_name'] = [$attachment_name, 's'];
            }

            if (isset($existingCols['attachment_size'])) {
                $insertData['attachment_size'] = [$attachment_size !== null ? intval($attachment_size) : null, 'i'];
            }

            if (isset($existingCols['attachment_type'])) {
                $insertData['attachment_type'] = [$attachment_type, 's'];
            }

            if (!empty($insertData)) {
                $fields = array_keys($insertData);
                $placeholders = array_fill(0, count($fields), '?');
                
                $sql = "INSERT INTO notifications (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types = '';
                    $bindParams = [];
                    foreach ($insertData as $col => $info) {
                        $types .= $info[1];
                        $bindParams[] = &$insertData[$col][0];
                    }
                    array_unshift($bindParams, $types);
                    call_user_func_array([$stmt, 'bind_param'], $bindParams);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        return true;
    }
}

if (!function_exists('notifyUser')) {
    /**
     * Create both dashboard and email notification for a user.
     */
    function notifyUser($userId, $role, $email, $title, $message, $metadata = [], $notification_type = 'info') {
        global $conn;
        if (empty($email) && is_numeric($userId)) {
            $userQuery = mysqli_query($conn, "SELECT email FROM users WHERE id = " . intval($userId) . " LIMIT 1");
            if ($userQuery && $userRow = mysqli_fetch_assoc($userQuery)) {
                $email = trim($userRow['email']);
            }
        }

        $type = isset($metadata['type']) ? $metadata['type'] : 'general';
        createNotification($userId, $role, $title, $message, $notification_type, $type);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (empty($metadata['recipient_name']) && is_numeric($userId)) {
                $nameQuery = mysqli_query($conn, "SELECT full_name FROM users WHERE id = " . intval($userId) . " LIMIT 1");
                if ($nameQuery && $nameRow = mysqli_fetch_assoc($nameQuery)) {
                    $metadata['recipient_name'] = trim($nameRow['full_name']);
                }
            }
            $metadata['recipient_role'] = $role;
            return sendEmailNotification($email, $title, $message, $metadata);
        }
        return false;
    }
}

if (!function_exists('sendManualMessage')) {
    /**
     * Send a manual message from one user to another with optional dashboard notification and email.
     *
     * @param int    $senderId       Sender user ID
     * @param string $senderRole     Sender role
     * @param int    $recipientId    Recipient user ID
     * @param string $recipientRole  Recipient role
     * @param string $subject        Subject of the message
     * @param string $message        Message body
     * @param bool   $sendNotification Whether to create a dashboard notification
     * @param bool   $sendEmail      Whether to send the message by email
     * @return array Status details for the manual message delivery
     */
    function sendManualMessage($senderId, $senderRole, $recipientId, $recipientRole, $subject, $message, $sendNotification = true, $sendEmail = true, $attachment_path = null, $attachment_name = null, $attachment_size = null, $attachment_type = null) {
        global $conn;

        $senderId = intval($senderId);
        $recipientId = intval($recipientId);
        $senderRole = strtolower(trim((string) $senderRole));
        $recipientRole = strtolower(trim((string) $recipientRole));
        $subject = trim((string) $subject);
        $message = trim((string) $message);
        $sendNotification = $sendNotification ? 1 : 0;
        $sendEmail = $sendEmail ? 1 : 0;

        $emailStatus = 'not_selected';
        $emailError = null;

        if ($sendNotification) {
            createNotification($recipientId, $recipientRole, $subject, $message, 'info', 'message', $attachment_path, $attachment_name, $attachment_size, $attachment_type);
        }

        if ($sendEmail) {
            $recipientEmail = null;
            $metadata = [
                'recipient_role' => $recipientRole,
            ];

            $recipientQuery = mysqli_query($conn, "SELECT email, full_name FROM users WHERE id = $recipientId LIMIT 1");
            if ($recipientQuery && $recipientRow = mysqli_fetch_assoc($recipientQuery)) {
                $recipientEmail = trim($recipientRow['email']);
                if (!empty($recipientRow['full_name'])) {
                    $metadata['recipient_name'] = trim($recipientRow['full_name']);
                }
            }

            if (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                // Resolve sender details to set Reply-To and display name in email
                $senderQuery = mysqli_query($conn, "SELECT email, full_name, role FROM users WHERE id = " . intval($senderId) . " LIMIT 1");
                $senderName = '';
                $senderEmail = null;
                if ($senderQuery && $srow = mysqli_fetch_assoc($senderQuery)) {
                    $senderName = trim($srow['full_name'] ?: '');
                    $senderEmail = trim($srow['email'] ?: null);
                }
                // Include sender metadata in the email body
                if (!empty($senderName)) {
                    $metadata['sender_name'] = $senderName;
                }
                $metadata['sender_role'] = $senderRole;

                // Provide Reply-To and display From name via global mail options consumed by sendEmail()
                $fromName = trim(($senderName ? $senderName : ucfirst($senderRole)) . ' / ' . ucfirst($senderRole));
                $GLOBALS['mail_options'] = [
                    'reply_to' => $senderEmail,
                    'reply_to_name' => $senderName,
                    'from_name' => $fromName,
                ];

                // Set email attachment if provided
                if (!empty($attachment_path)) {
                    $fullPath = __DIR__ . '/../' . $attachment_path;
                    if (file_exists($fullPath)) {
                        $GLOBALS['mail_options_attachments'] = [[
                            'path' => $fullPath,
                            'name' => $attachment_name ?: basename($fullPath)
                        ]];
                    }
                }

                $emailError = '';
                $sent = sendEmailNotification($recipientEmail, $subject, $message, $metadata, $emailError);
                $emailStatus = $sent ? 'sent' : 'failed';
                if (!$sent && empty($emailError)) {
                    $emailError = 'Email failed: Unknown error occurred';
                }

                // Clear email attachments global state
                unset($GLOBALS['mail_options_attachments']);
            } else {
                $emailStatus = 'failed';
                $emailError = 'Recipient does not have a valid email address.';
            }
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO manual_messages (sender_id, sender_role, recipient_id, recipient_role, subject, message, send_notification, send_email, email_status, email_error, attachment_path, attachment_name, attachment_size, attachment_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('isisssiissssis', $senderId, $senderRole, $recipientId, $recipientRole, $subject, $message, $sendNotification, $sendEmail, $emailStatus, $emailError, $attachment_path, $attachment_name, $attachment_size, $attachment_type);
            $stmt->execute();
            $stmt->close();
        }

        return [
            'recipient_id' => $recipientId,
            'recipient_role' => $recipientRole,
            'send_notification' => (bool) $sendNotification,
            'send_email' => (bool) $sendEmail,
            'email_status' => $emailStatus,
            'email_error' => $emailError,
        ];
    }
}
