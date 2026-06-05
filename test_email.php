<?php
/**
 * test_email.php
 * Simple script to verify SMTP configuration on Render or local environment.
 * It attempts to send a test email using the sendEmail function defined in email_helper.php.
 */

require_once __DIR__ . '/email_helper.php';

// Load environment variables from .env if present (optional for local dev)
if (file_exists(__DIR__ . '/.env')) {
    // Simple parsing (key=value lines)
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

// Test parameters – adjust to a real reachable email address.
$toEmail = getenv('SMTP_TEST_RECIPIENT') ?: 'youremail@example.com';
$subject = 'IMP SMTP Configuration Test';
$message = "This is a test email sent from the Internship Management Platform (IMP).\n\nIf you received this, the SMTP configuration works correctly.";

$debugInfo = '';
$sent = sendEmail($toEmail, $subject, $message, null, $debugInfo);

if ($sent) {
    echo "✅ Test email sent successfully to {$toEmail}.\n";
} else {
    echo "❌ Failed to send test email.\n";
    echo "Debug info: \n" . $debugInfo . "\n";
    // Also output the SMTP config for troubleshooting.
    $config = getSmtpConfig();
    echo "\nCurrent SMTP configuration (masked passwords):\n";
    $masked = $config;
    if (!empty($masked['password'])) $masked['password'] = '********';
    print_r($masked);
}
?>
