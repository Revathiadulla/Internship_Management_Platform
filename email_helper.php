<?php
/**
 * email_helper.php
 * Centralized Gmail SMTP helper for Internship Management Platform.
 * Strategy:
 *   - Local (XAMPP): loads config/email_config.php which defines SMTP_* constants.
 *   - Live (Render): config file does NOT exist; SMTP settings are read from environment variables.
 * No fatal error is thrown when config/email_config.php is missing.
 */

// Safely load local config only when it exists (local dev environment)
$_emailConfigPath = __DIR__ . '/config/email_config.php';
if (file_exists($_emailConfigPath)) {
    require_once $_emailConfigPath;
}
unset($_emailConfigPath);
require_once __DIR__ . '/includes/PHPMailer/Exception.php';
require_once __DIR__ . '/includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getEnvVar(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        // Check $_ENV first (set by Render and most hosting platforms)
        if (isset($_ENV[$key]) && trim($_ENV[$key]) !== '') {
            return trim($_ENV[$key]);
        }
        // Fall back to getenv() (works with Apache SetEnv and CLI)
        $value = getenv($key);
        if ($value !== false && trim($value) !== '') {
            return trim($value);
        }
    }
    return $default;
}



/**
 * Retrieve configuration values from environment or defined constants.
 * The log is written to /logs/smtp_config_debug.log relative to project root.
 */
function getSmtpConfig(): array {
    // Retrieve configuration values from environment or defined constants
    $config = [
        'host' => getEnvVar(['SMTP_HOST'], defined('SMTP_HOST') ? SMTP_HOST : ''),
        'port' => getEnvVar(['SMTP_PORT'], defined('SMTP_PORT') ? (string)SMTP_PORT : ''),
        'username' => getEnvVar(['SMTP_USERNAME'], defined('SMTP_USERNAME') ? SMTP_USERNAME : ''),
        'password' => getEnvVar(['SMTP_PASSWORD'], defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''),
        // Support both SMTP_FROM and legacy SMTP_FROM_EMAIL
        'from_email' => getEnvVar(['SMTP_FROM', 'SMTP_FROM_EMAIL'], defined('SMTP_FROM') ? SMTP_FROM : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '')),
        'from_name' => getEnvVar(['SMTP_FROM_NAME'], defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Internship Management Platform'),
    ];
    // Fallback to Gmail SMTP defaults if not provided (Render may not set them explicitly)
    if (empty($config['host'])) {
        $config['host'] = 'smtp.gmail.com';
    }
    if (empty($config['port'])) {
        $config['port'] = '587';
    }


    // Determine missing required variables for diagnostics (after fallback defaults)
    $missing = [];
    if (empty($config['host'])) $missing[] = 'SMTP_HOST';
    if (empty($config['port'])) $missing[] = 'SMTP_PORT';
    if (empty($config['username'])) $missing[] = 'SMTP_USERNAME';
    if (empty($config['password'])) $missing[] = 'SMTP_PASSWORD';
    if (empty($config['from_email'])) $missing[] = 'SMTP_FROM';

    // Mask password before logging to avoid exposing it
    $logConfig = $config;
    if (!empty($logConfig['password'])) {
        $logConfig['password'] = '********';
    }
    logSmtpConfig($logConfig, $missing);

    return $config;

}
/**
 * Logs SMTP configuration values and missing variables for debugging.
 * The log is written to /logs/smtp_config_debug.log relative to project root.
 */
function logSmtpConfig(array $config, array $missing): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/smtp_config_debug.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $entry = $timestamp . ' SMTP Config: ' . json_encode($config);
    if (!empty($missing)) {
        $entry .= ' MISSING: ' . implode(', ', $missing);
    }
    $entry .= PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

function getSmtpDiagnostics(): array {
    $smtpConfig = getSmtpConfig();
    return [
        'host' => $smtpConfig['host'],
        'port' => $smtpConfig['port'],
        'username' => $smtpConfig['username'],
        'from_email' => $smtpConfig['from_email'],
        'from_name' => $smtpConfig['from_name'],
        'password_present' => !empty($smtpConfig['password']),
        'is_valid_from_email' => filter_var($smtpConfig['from_email'], FILTER_VALIDATE_EMAIL) !== false,
        'has_required_config' => !empty($smtpConfig['host']) && !empty($smtpConfig['port']) && !empty($smtpConfig['username']) && !empty($smtpConfig['password']) && !empty($smtpConfig['from_email']),
        'connection_status' => 'Not tested',
        'connection_debug' => '',
        'error' => '',
    ];
}

function testSmtpConnection(string &$debugOutput = null): bool {
    $smtpConfig = getSmtpConfig();
    $debugOutput = '';

    if (empty($smtpConfig['host']) || empty($smtpConfig['port'])) {
        $debugOutput = 'SMTP host or port is missing.';
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= '[' . $level . '] ' . trim($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 60;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->smtpConnect();
        $mail->smtpClose();

        return true;
    } catch (Exception $e) {
        $debugOutput .= 'Exception: ' . $e->getMessage();
        return false;
    }
}

function writeEmailLog(string $message): void {
    $logPath = __DIR__ . '/email_notifications.log';
    @file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function sendEmail($to, $subjectOrName, $messageOrSubject = null, $message = null, &$debugInfo = null): bool {
    if ($message === null) {
        $toEmail = trim((string)$to);
        $toName = '';
        $subject = trim((string)$subjectOrName);
        $body = trim((string)$messageOrSubject);
    } else {
        $toEmail = trim((string)$to);
        $toName = trim((string)$subjectOrName);
        $subject = trim((string)$messageOrSubject);
        $body = trim((string)$message);
    }

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $debugInfo = 'Invalid recipient email provided.';
        writeEmailLog("Invalid recipient email provided: $toEmail");
        return false;
    }

    $smtpConfig = getSmtpConfig();
    if (empty($smtpConfig['host']) || empty($smtpConfig['port']) || empty($smtpConfig['username']) || empty($smtpConfig['password']) || empty($smtpConfig['from_email'])) {
$debugInfo = 'Missing SMTP configuration. Please set SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM.';
        writeEmailLog('Missing SMTP configuration. Please set SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM.');
        return false;
    }

    try {
        $debugOutput = '';
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= '[' . $level . '] ' . trim($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        // Allow caller to override From name and provide Reply-To via global mail options
        $fromName = $smtpConfig['from_name'];
        if (!empty($GLOBALS['mail_options']) && is_array($GLOBALS['mail_options'])) {
            $opts = $GLOBALS['mail_options'];
            if (!empty($opts['from_name'])) {
                $fromName = $opts['from_name'];
            }
        }

        $mail->setFrom($smtpConfig['from_email'], $fromName);
        // Add Reply-To if provided
        if (!empty($GLOBALS['mail_options']) && is_array($GLOBALS['mail_options'])) {
            $opts = $GLOBALS['mail_options'];
            if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $replyName = !empty($opts['reply_to_name']) ? $opts['reply_to_name'] : '';
                $mail->addReplyTo($opts['reply_to'], $replyName);
            }
            // clear global options after consuming
            unset($GLOBALS['mail_options']);
        }

        $mail->addAddress($toEmail, $toName ?: '');
        
        // Add attachments if provided
        if (!empty($GLOBALS['mail_options_attachments']) && is_array($GLOBALS['mail_options_attachments'])) {
            foreach ($GLOBALS['mail_options_attachments'] as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $name = isset($attachment['name']) ? $attachment['name'] : '';
                    $mail->addAttachment($attachment['path'], $name);
                }
            }
            // clear global attachments after consuming
            unset($GLOBALS['mail_options_attachments']);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->send();
        if ($debugInfo !== null) {
            $debugInfo = $debugOutput;
        }
        return true;
    } catch (Exception $e) {
        $errorMessage = 'Failed to send email to ' . $toEmail . ' via ' . ($smtpConfig['host'] ?? 'unknown host') . '. Error: ' . $e->getMessage() . ' (' . $mail->ErrorInfo . ')';
        if ($debugOutput !== '') {
            $errorMessage .= ' Debug: ' . $debugOutput;
        }
        writeEmailLog($errorMessage);
        if ($debugInfo !== null) {
            $debugInfo = $errorMessage;
        }
        return false;
    }
}
