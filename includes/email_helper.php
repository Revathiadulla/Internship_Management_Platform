<?php
/**
 * email_helper.php
 * Centralized email helper for Internship Management Platform.
 *
 * Strategy (in priority order):
 *   1. PHPMailer over Gmail SMTP  — uses config/email_config.php constants (local) or
 *                                    SMTP_* environment variables (production).
 *   2. Brevo Transactional API    — fallback when BREVO_API_KEY env var is present and
 *                                    PHPMailer fails or SMTP credentials are missing.
 *
 * Dashboard notifications are NEVER blocked by email failures.
 */

// ── Load local SMTP config if present ────────────────────────────────────────
$_emailConfigPath = __DIR__ . '/config/email_config.php';
if (file_exists($_emailConfigPath)) {
    require_once $_emailConfigPath;
}
unset($_emailConfigPath);

// ── Load .env if present ─────────────────────────────────────────────────────
$_envPath = __DIR__ . '/../.env';
if (file_exists($_envPath)) {
    $lines = file($_envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
unset($_envPath);

// ── Load PHPMailer ────────────────────────────────────────────────────────────
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ── Helper: read env var OR PHP constant ─────────────────────────────────────
function getEnvVar(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        if (isset($_ENV[$key]) && trim($_ENV[$key]) !== '') {
            return trim($_ENV[$key]);
        }
        $value = getenv($key);
        if ($value !== false && trim($value) !== '') {
            return trim($value);
        }
    }
    return $default;
}

// ── Build SMTP config from env / constants ───────────────────────────────────
function getSmtpConfig(): array {
    $config = [
        'host'       => getEnvVar(['SMTP_HOST'],
                            defined('SMTP_HOST')      ? SMTP_HOST      : ''),
        'port'       => getEnvVar(['SMTP_PORT'],
                            defined('SMTP_PORT')      ? (string)SMTP_PORT : ''),
        'username'   => getEnvVar(['SMTP_USERNAME'],
                            defined('SMTP_USERNAME')  ? SMTP_USERNAME  : ''),
        'password'   => getEnvVar(['SMTP_PASSWORD'],
                            defined('SMTP_PASSWORD')  ? SMTP_PASSWORD  : ''),
        'from_email' => getEnvVar(['SMTP_FROM', 'SMTP_FROM_EMAIL'],
                            defined('SMTP_FROM')      ? SMTP_FROM      :
                           (defined('SMTP_FROM_EMAIL')? SMTP_FROM_EMAIL: '')),
        'from_name'  => getEnvVar(['SMTP_FROM_NAME'],
                            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME :
                            'Internship Management Platform'),
    ];

    // Sensible defaults
    if (empty($config['host'])) { $config['host'] = 'smtp.gmail.com'; }
    if (empty($config['port'])) { $config['port'] = '587'; }

    // Diagnostics log
    $missing = [];
    if (empty($config['username']))   $missing[] = 'SMTP_USERNAME';
    if (empty($config['password']))   $missing[] = 'SMTP_PASSWORD';
    if (empty($config['from_email'])) $missing[] = 'SMTP_FROM';

    $logCopy = $config;
    $logCopy['password'] = empty($logCopy['password']) ? '(not set)' : '********';
    logSmtpConfig($logCopy, $missing);

    return $config;
}

function logSmtpConfig(array $config, array $missing): void {
    $logDir  = __DIR__ . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    $entry = '[' . date('Y-m-d H:i:s') . '] SMTP Config: ' . json_encode($config);
    if (!empty($missing)) {
        $entry .= ' MISSING: ' . implode(', ', $missing);
    }
    @file_put_contents($logDir . '/smtp_config_debug.log', $entry . PHP_EOL, FILE_APPEND);
}

function getSmtpDiagnostics(): array {
    $c = getSmtpConfig();
    return [
        'host'             => $c['host'],
        'port'             => $c['port'],
        'username'         => $c['username'],
        'from_email'       => $c['from_email'],
        'from_name'        => $c['from_name'],
        'password_present' => !empty($c['password']),
        'is_valid_from_email' => filter_var($c['from_email'], FILTER_VALIDATE_EMAIL) !== false,
        'has_required_config' => !empty($c['host']) && !empty($c['port'])
                                  && !empty($c['username']) && !empty($c['password'])
                                  && !empty($c['from_email']),
        'connection_status'   => 'Not tested',
        'connection_debug'    => '',
        'error'               => '',
    ];
}

function testSmtpConnection(string &$debugOutput = null): bool {
    $cfg = getSmtpConfig();
    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function ($str, $level) use (&$debugOutput) {
            $debugOutput .= "[$level] " . trim($str) . "\n";
        };
        $mail->isSMTP();
        $mail->Host        = $cfg['host'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $cfg['username'];
        $mail->Password    = $cfg['password'];
        $mail->Port        = intval($cfg['port']);
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet     = 'UTF-8';
        $mail->Timeout     = 30;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->smtpConnect();
        $mail->smtpClose();
        return true;
    } catch (PHPMailerException $e) {
        $debugOutput .= 'Exception: ' . $e->getMessage();
        return false;
    }
}

function writeEmailLog(string $message): void {
    @file_put_contents(__DIR__ . '/email_notifications.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function ensureEmailLogColumns(): void {
    global $conn;
    if (empty($conn) || !($conn instanceof mysqli)) {
        return;
    }

    $requiredColumns = [
        'from_email' => 'VARCHAR(255) NULL',
        'to_email' => 'VARCHAR(255) NULL',
        'body' => 'TEXT NULL',
        'attachment_path' => 'VARCHAR(500) NULL',
        'application_id' => 'INT NULL',
        'sender_id' => 'INT NULL',
        'sender_role' => 'VARCHAR(50) NULL',
    ];

    foreach ($requiredColumns as $column => $definition) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE '$column'");
        if ($check && mysqli_num_rows($check) === 0) {
            @mysqli_query($conn, "ALTER TABLE email_logs ADD COLUMN $column $definition");
        }
    }
}

function logOutboundEmail(array $data = []): void {
    global $conn;
    if (empty($conn) || !($conn instanceof mysqli)) {
        return;
    }

    ensureEmailLogColumns();

    $senderId = isset($data['sender_id']) ? intval($data['sender_id']) : 0;
    $senderRole = trim((string) ($data['sender_role'] ?? ''));
    $fromEmail = trim((string) ($data['from_email'] ?? ''));
    $toEmail = trim((string) ($data['to_email'] ?? $data['recipient_email'] ?? ''));
    $subject = substr(trim((string) ($data['subject'] ?? '')), 0, 255);
    $body = trim((string) ($data['body'] ?? $data['message'] ?? ''));
    $attachmentPath = substr(trim((string) ($data['attachment_path'] ?? '')), 0, 500);
    $status = trim((string) ($data['status'] ?? 'sent'));
    $errorMessage = substr(trim((string) ($data['error_message'] ?? '')), 0, 65535);
    $sentAt = trim((string) ($data['sent_at'] ?? date('Y-m-d H:i:s')));
    $applicationId = isset($data['application_id']) ? intval($data['application_id']) : 0;

    $subjectEsc = mysqli_real_escape_string($conn, $subject);
    $bodyEsc = mysqli_real_escape_string($conn, $body);
    $errorEsc = mysqli_real_escape_string($conn, $errorMessage);
    $fromEmailEsc = mysqli_real_escape_string($conn, $fromEmail);
    $toEmailEsc = mysqli_real_escape_string($conn, $toEmail);
    $attachmentEsc = mysqli_real_escape_string($conn, $attachmentPath);
    $senderRoleEsc = mysqli_real_escape_string($conn, $senderRole);
    $statusEsc = mysqli_real_escape_string($conn, $status);

    $stmt = $conn->prepare("INSERT INTO email_logs (sender_id, sender_role, from_email, to_email, recipient_email, subject, body, message, attachment_path, status, error_message, sent_at, application_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('isssssssssssi', $senderId, $senderRoleEsc, $fromEmailEsc, $toEmailEsc, $toEmailEsc, $subjectEsc, $bodyEsc, $bodyEsc, $attachmentEsc, $statusEsc, $errorEsc, $sentAt, $applicationId);
    $stmt->execute();
    $stmt->close();
}

function isSmtpConfigured(): bool {
    $cfg = getSmtpConfig();
    return !empty($cfg['host']) && !empty($cfg['port']) && !empty($cfg['username']) && !empty($cfg['password']) && !empty($cfg['from_email']);
}

function normalizeEmailAddresses($value): array {
    if (is_array($value)) {
        $items = [];
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $items[] = (string) $item;
            }
        }
        $value = implode(', ', $items);
    }

    if ($value === null) {
        return [];
    }

    $parts = preg_split('/[\r\n,;]+/', trim((string) $value));
    $emails = [];
    foreach ($parts as $part) {
        $email = trim($part);
        if ($email === '') {
            continue;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }

    return array_values(array_unique($emails));
}

// ─────────────────────────────────────────────────────────────────────────────
// sendEmail()
// Tries PHPMailer/SMTP first; falls back to Brevo API if BREVO_API_KEY is set.
// Returns true on success, false on failure.
// ─────────────────────────────────────────────────────────────────────────────
function sendEmail($to, $subjectOrName, $messageOrSubject = null, $message = null, &$debugInfo = null): bool {
    $smtpErrDetail = null;

    // Helper to safely convert arrays or objects to strings based on user rules
    $convertParam = function($var, $name) {
        if (is_array($var)) {
            // Add debugging temporarily
            error_log("Array to string conversion debug for parameter '$name': " . print_r($var, true));
            
            // If variable is a user object/array
            if (isset($var['email'])) {
                return $var['email'];
            }
            if (isset($var['name'])) {
                return $var['name'];
            }
            
            // If variable is attachment metadata
            if (isset($var['path']) || isset($var['tmp_name']) || isset($var['file']) || isset($var['data']) || isset($var['content'])) {
                return json_encode($var);
            }
            
            // If variable is an email list
            $all_scalars = true;
            foreach ($var as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    $all_scalars = false;
                    break;
                }
            }
            if ($all_scalars) {
                return implode(', ', $var);
            }
            
            return json_encode($var);
        }
        return $var;
    };

    $to = $convertParam($to, 'to');
    $subjectOrName = $convertParam($subjectOrName, 'subjectOrName');
    $messageOrSubject = $convertParam($messageOrSubject, 'messageOrSubject');
    $message = $convertParam($message, 'message');

    // ── Normalize overloaded signature ────────────────────────────────────────
    if ($message === null) {
        $toEmail  = trim((string)$to);
        $toName   = '';
        $subject  = trim((string)$subjectOrName);
        $body     = trim((string)$messageOrSubject);
    } else {
        $toEmail  = trim((string)$to);
        $toName   = trim((string)$subjectOrName);
        $subject  = trim((string)$messageOrSubject);
        $body     = trim((string)$message);
    }

    $toAddresses = normalizeEmailAddresses($toEmail);
    if (empty($toAddresses)) {
        $msg = "Email failed: Invalid recipient email – '$toEmail'";
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    $cfg = getSmtpConfig();
    $ccAddresses = normalizeEmailAddresses($GLOBALS['mail_options']['cc'] ?? []);
    $bccAddresses = normalizeEmailAddresses($GLOBALS['mail_options']['bcc'] ?? []);

    // ── Attempt 1: PHPMailer SMTP ─────────────────────────────────────────────
    if (!empty($cfg['username']) && !empty($cfg['password']) && !empty($cfg['from_email'])) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host        = $cfg['host'];
            $mail->SMTPAuth    = true;
            $mail->Username    = $cfg['username'];
            $mail->Password    = trim($cfg['password']); // trim protects against stray whitespace
            $mail->Port        = intval($cfg['port']);
            $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet     = 'UTF-8';
            $mail->Timeout     = 30;
            $mail->SMTPDebug   = 0; // silence SMTP handshake output in HTTP context
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            // From / Reply-To
            $fromEmail = !empty($GLOBALS['mail_options']['from_email']) ? $GLOBALS['mail_options']['from_email'] : $cfg['from_email'];
            $fromName = $cfg['from_name'];
            if (!empty($GLOBALS['mail_options']['from_name'])) {
                $fromName = $GLOBALS['mail_options']['from_name'];
            }
            $mail->setFrom($fromEmail, $fromName);

            if (!empty($GLOBALS['mail_options']['reply_to']) &&
                filter_var($GLOBALS['mail_options']['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo(
                    $GLOBALS['mail_options']['reply_to'],
                    $GLOBALS['mail_options']['reply_to_name'] ?? ''
                );
            }

            $mail->addAddress($toAddresses[0], $toName ?: $toAddresses[0]);
            foreach (array_slice($toAddresses, 1) as $extraAddress) {
                $mail->addAddress($extraAddress, $extraAddress);
            }

            $ccAddresses = normalizeEmailAddresses($GLOBALS['mail_options']['cc'] ?? []);
            foreach ($ccAddresses as $ccAddress) {
                $mail->addCC($ccAddress, $ccAddress);
            }

            $bccAddresses = normalizeEmailAddresses($GLOBALS['mail_options']['bcc'] ?? []);
            foreach ($bccAddresses as $bccAddress) {
                $mail->addBCC($bccAddress, $bccAddress);
            }

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            // Attachments via global option
            if (!empty($GLOBALS['mail_options_attachments'])) {
                foreach ($GLOBALS['mail_options_attachments'] as $att) {
                    $path = $att['path'] ?? '';
                    $name = $att['name'] ?? basename($path);
                    if (!empty($path) && file_exists($path)) {
                        $mail->addAttachment($path, $name);
                    }
                }
            }

            $mail->send();

            $attachmentPath = '';
            if (!empty($GLOBALS['mail_options_attachments'])) {
                foreach ($GLOBALS['mail_options_attachments'] as $att) {
                    if (!empty($att['path'])) {
                        $attachmentPath = (string) $att['path'];
                        break;
                    }
                }
            }

            $mailContext = $GLOBALS['mail_context'] ?? [];
            logOutboundEmail([
                'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
                'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
                'from_email' => $fromEmail,
                'to_email' => $toAddresses[0],
                'recipient_email' => $toAddresses[0],
                'subject' => $subject,
                'body' => $body,
                'attachment_path' => $attachmentPath,
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'application_id' => $mailContext['application_id'] ?? 0,
            ]);

            writeEmailLog("PHPMailer SMTP: Sent OK to $toEmail | Subject: $subject");
            if ($debugInfo !== null) { $debugInfo = "Sent via PHPMailer SMTP to $toEmail"; }
            return true;

        } catch (PHPMailerException $e) {
            $smtpErrDetail = $e->getMessage() . ' | ' . $mail->ErrorInfo;
            $errDetail = $smtpErrDetail;
            error_log("Email failed (PHPMailer): $errDetail");
            $mailContext = $GLOBALS['mail_context'] ?? [];
            logOutboundEmail([
                'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
                'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
                'from_email' => $cfg['from_email'] ?? '',
                'to_email' => $toAddresses[0] ?? '',
                'recipient_email' => $toAddresses[0] ?? '',
                'subject' => $subject,
                'body' => $body,
                'attachment_path' => '',
                'status' => 'failed',
                'error_message' => $errDetail,
                'sent_at' => date('Y-m-d H:i:s'),
                'application_id' => $mailContext['application_id'] ?? 0,
            ]);
            writeEmailLog("PHPMailer SMTP error to $toEmail: $errDetail");
            if ($debugInfo !== null) { $debugInfo = "PHPMailer failed: $errDetail"; }
            // Fall through to Brevo
        }
    } else {
        $missing = [];
        if (empty($cfg['username']))   $missing[] = 'SMTP_USERNAME';
        if (empty($cfg['password']))   $missing[] = 'SMTP_PASSWORD';
        if (empty($cfg['from_email'])) $missing[] = 'SMTP_FROM';
        $msg = 'SMTP config missing: ' . implode(', ', $missing) . '. Please configure the portal SMTP sender before sending email.';
        $mailContext = $GLOBALS['mail_context'] ?? [];
        $mailContext = $GLOBALS['mail_context'] ?? [];
        logOutboundEmail([
            'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
            'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
            'from_email' => $cfg['from_email'] ?? '',
            'to_email' => $toAddresses[0] ?? '',
            'recipient_email' => $toAddresses[0] ?? '',
            'subject' => $subject,
            'body' => $body,
            'attachment_path' => '',
            'status' => 'failed',
            'error_message' => $msg,
            'sent_at' => date('Y-m-d H:i:s'),
            'application_id' => $mailContext['application_id'] ?? 0,
        ]);
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
    }

    // ── Attempt 2: Brevo Transactional API ───────────────────────────────────
    $apiKey = getenv('BREVO_API_KEY');
    if (empty($apiKey)) {
        if (!empty($smtpErrDetail)) {
            $msg = 'Email failed: ' . $smtpErrDetail;
        } else {
            $msg = 'Email failed: PHPMailer SMTP failed and BREVO_API_KEY is not set. No email engine available.';
        }
        $mailContext = $GLOBALS['mail_context'] ?? [];
        logOutboundEmail([
            'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
            'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
            'from_email' => $fromEmail ?? '',
            'to_email' => $toAddresses[0] ?? '',
            'recipient_email' => $toAddresses[0] ?? '',
            'subject' => $subject,
            'body' => $body,
            'attachment_path' => '',
            'status' => 'failed',
            'error_message' => $msg,
            'sent_at' => date('Y-m-d H:i:s'),
            'application_id' => $mailContext['application_id'] ?? 0,
        ]);
        error_log($msg);
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : 'noreply@example.com';
    $fromName  = !empty($cfg['from_name'])  ? $cfg['from_name']  : 'IMP Platform';

    $brevoTo = [];
    foreach ($toAddresses as $index => $address) {
        $brevoTo[] = ['email' => $address, 'name' => $index === 0 ? ($toName ?: $address) : $address];
    }

    $payload = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => $brevoTo,
        'subject'     => $subject,
        'htmlContent' => $body,
        'textContent' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)),
    ];

    if (!empty($ccAddresses)) {
        $payload['cc'] = array_map(function ($address) {
            return ['email' => $address, 'name' => $address];
        }, $ccAddresses);
    }

    if (!empty($bccAddresses)) {
        $payload['bcc'] = array_map(function ($address) {
            return ['email' => $address, 'name' => $address];
        }, $bccAddresses);
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'api-key: '    . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $responseBody = curl_exec($ch);
    $curlError    = curl_error($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        $msg = "Brevo request error to $toEmail: $curlError";
        $mailContext = $GLOBALS['mail_context'] ?? [];
        logOutboundEmail([
            'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
            'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
            'from_email' => $fromEmail ?? '',
            'to_email' => $toAddresses[0] ?? '',
            'recipient_email' => $toAddresses[0] ?? '',
            'subject' => $subject,
            'body' => $body,
            'attachment_path' => '',
            'status' => 'failed',
            'error_message' => $msg,
            'sent_at' => date('Y-m-d H:i:s'),
            'application_id' => $mailContext['application_id'] ?? 0,
        ]);
        error_log($msg);
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $mailContext = $GLOBALS['mail_context'] ?? [];
        logOutboundEmail([
            'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
            'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
            'from_email' => $fromEmail ?? '',
            'to_email' => $toAddresses[0] ?? '',
            'recipient_email' => $toAddresses[0] ?? '',
            'subject' => $subject,
            'body' => $body,
            'attachment_path' => '',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'application_id' => $mailContext['application_id'] ?? 0,
        ]);
        writeEmailLog("Brevo API: Sent OK to $toEmail | Subject: $subject (HTTP $httpCode)");
        if ($debugInfo !== null) { $debugInfo = "Sent via Brevo API (HTTP $httpCode)"; }
        return true;
    }

    $msg = "Brevo API error to $toEmail (HTTP $httpCode): $responseBody";
    $mailContext = $GLOBALS['mail_context'] ?? [];
    logOutboundEmail([
        'sender_id' => $mailContext['sender_id'] ?? ($_SESSION['user_id'] ?? 0),
        'sender_role' => $mailContext['sender_role'] ?? ($_SESSION['role'] ?? ''),
        'from_email' => $fromEmail ?? '',
        'to_email' => $toAddresses[0] ?? '',
        'recipient_email' => $toAddresses[0] ?? '',
        'subject' => $subject,
        'body' => $body,
        'attachment_path' => '',
        'status' => 'failed',
        'error_message' => $msg,
        'sent_at' => date('Y-m-d H:i:s'),
        'application_id' => $mailContext['application_id'] ?? 0,
    ]);
    error_log($msg);
    writeEmailLog($msg);
    if ($debugInfo !== null) { $debugInfo = $msg; }
    return false;
}
