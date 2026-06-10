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

// ── Load PHPMailer ────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/PHPMailer/Exception.php';
require_once __DIR__ . '/includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/includes/PHPMailer/SMTP.php';

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

// ─────────────────────────────────────────────────────────────────────────────
// sendEmail()
// Tries PHPMailer/SMTP first; falls back to Brevo API if BREVO_API_KEY is set.
// Returns true on success, false on failure.
// ─────────────────────────────────────────────────────────────────────────────
function sendEmail($to, $subjectOrName, $messageOrSubject = null, $message = null, &$debugInfo = null): bool {

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

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $msg = "Email failed: Invalid recipient email – '$toEmail'";
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    $cfg = getSmtpConfig();

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
            $fromName = $cfg['from_name'];
            if (!empty($GLOBALS['mail_options']['from_name'])) {
                $fromName = $GLOBALS['mail_options']['from_name'];
            }
            $mail->setFrom($cfg['from_email'], $fromName);

            if (!empty($GLOBALS['mail_options']['reply_to']) &&
                filter_var($GLOBALS['mail_options']['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo(
                    $GLOBALS['mail_options']['reply_to'],
                    $GLOBALS['mail_options']['reply_to_name'] ?? ''
                );
            }

            $mail->addAddress($toEmail, $toName ?: $toEmail);
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

            writeEmailLog("PHPMailer SMTP: Sent OK to $toEmail | Subject: $subject");
            if ($debugInfo !== null) { $debugInfo = "Sent via PHPMailer SMTP to $toEmail"; }
            return true;

        } catch (PHPMailerException $e) {
            $errDetail = $e->getMessage() . ' | ' . $mail->ErrorInfo;
            error_log("Email failed (PHPMailer): $errDetail");
            writeEmailLog("PHPMailer SMTP error to $toEmail: $errDetail");
            if ($debugInfo !== null) { $debugInfo = "PHPMailer failed: $errDetail"; }
            // Fall through to Brevo
        }
    } else {
        $missing = [];
        if (empty($cfg['username']))   $missing[] = 'SMTP_USERNAME';
        if (empty($cfg['password']))   $missing[] = 'SMTP_PASSWORD';
        if (empty($cfg['from_email'])) $missing[] = 'SMTP_FROM';
        $msg = 'SMTP config missing: ' . implode(', ', $missing) . '. Trying Brevo fallback.';
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
    }

    // ── Attempt 2: Brevo Transactional API ───────────────────────────────────
    $apiKey = getenv('BREVO_API_KEY');
    if (empty($apiKey)) {
        $msg = 'Email failed: PHPMailer SMTP failed and BREVO_API_KEY is not set. No email engine available.';
        error_log($msg);
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : 'noreply@example.com';
    $fromName  = !empty($cfg['from_name'])  ? $cfg['from_name']  : 'IMP Platform';

    $payload = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $body,
        'textContent' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)),
    ];

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
        error_log($msg);
        writeEmailLog($msg);
        if ($debugInfo !== null) { $debugInfo = $msg; }
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        writeEmailLog("Brevo API: Sent OK to $toEmail | Subject: $subject (HTTP $httpCode)");
        if ($debugInfo !== null) { $debugInfo = "Sent via Brevo API (HTTP $httpCode)"; }
        return true;
    }

    $msg = "Brevo API error to $toEmail (HTTP $httpCode): $responseBody";
    error_log($msg);
    writeEmailLog($msg);
    if ($debugInfo !== null) { $debugInfo = $msg; }
    return false;
}
