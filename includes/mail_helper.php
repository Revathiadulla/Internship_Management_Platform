<?php
/**
 * mail_helper.php
 * Centralized email sending and logging engine for IMP.
 */

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
        $fullName = 'User';

        // 1. Resolve recipient details
        if (is_numeric($recipient)) {
            $user_id = intval($recipient);
            $user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
            $res = mysqli_query($conn, $user_sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $email = $row['email'];
                $fullName = $row['full_name'] ?: 'User';
            } else {
                // If not found in users, check student profiles
                $prof_sql = "SELECT email, full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
                $prof_res = mysqli_query($conn, $prof_sql);
                if ($prof_res && $prof_row = mysqli_fetch_assoc($prof_res)) {
                    $email = $prof_row['email'];
                    $fullName = $prof_row['full_name'] ?: 'User';
                }
            }
        } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $email = trim($recipient);
            // Look up user_id and full_name in users table
            $esc_email = mysqli_real_escape_string($conn, $email);
            $user_sql = "SELECT id, full_name FROM users WHERE email = '$esc_email' LIMIT 1";
            $res = mysqli_query($conn, $user_sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $user_id = intval($row['id']);
                $fullName = $row['full_name'] ?: 'User';
            }
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
            border: 1px border #e2e8f0;
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

        // 3. Log to Database
        $esc_user_id = $user_id !== null ? $user_id : 'NULL';
        $esc_email = mysqli_real_escape_string($conn, $email);
        $esc_name = mysqli_real_escape_string($conn, $fullName);
        $esc_subject = mysqli_real_escape_string($conn, $subject);
        $esc_msg = mysqli_real_escape_string($conn, $messageText);
        $esc_html = mysqli_real_escape_string($conn, $htmlBody);

        $log_sql = "INSERT INTO email_notifications_log 
                    (user_id, recipient_email, recipient_name, subject, message_text, html_body, status) 
                    VALUES ($esc_user_id, '$esc_email', '$esc_name', '$esc_subject', '$esc_msg', '$esc_html', 'Sent')";
        mysqli_query($conn, $log_sql);

        // 4. Log to File System for easy sandbox verification
        $log_file = "c:/xampp/htdocs/IMP/email_notifications.log";
        $file_log = "========================================================================\n";
        $file_log .= "[" . date('Y-m-d H:i:s') . "] OUTGOING EMAIL\n";
        $file_log .= "TO: " . $email . " (" . $fullName . ") [ID: " . ($user_id ?: 'N/A') . "]\n";
        $file_log .= "SUBJECT: " . $subject . "\n";
        $file_log .= "MESSAGE TEXT: " . $messageText . "\n";
        $file_log .= "METADATA: " . json_encode($metadata) . "\n";
        $file_log .= "------------------------------------------------------------------------\n";
        $file_log .= "HTML body generated (" . strlen($htmlBody) . " bytes).\n";
        $file_log .= "========================================================================\n\n";
        file_put_contents($log_file, $file_log, FILE_APPEND);

        // 5. Send via native PHP mail function
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: IMP Notifications <no-reply@imp-platform.com>" . "\r\n";
        
        // Suppress warning in case sendmail is not set up on local Windows/XAMPP
        @mail($email, $subject, $htmlBody, $headers);

        return true;
    }
}
