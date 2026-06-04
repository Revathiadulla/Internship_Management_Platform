<?php
require_once __DIR__ . '/email_helper.php';

$diagnostics = getSmtpDiagnostics();
$connectionDebug = '';
$diagnostics['connection_status'] = testSmtpConnection($connectionDebug) ? 'Connected' : 'Connection failed';
$diagnostics['connection_debug'] = $connectionDebug;

$recipient = getenv('SMTP_FROM_EMAIL') ?: (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '');
$message = '';
$status = null;
$errorOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = trim($_POST['to_email'] ?? '');
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid recipient email address.';
        $status = 'error';
    } else {
        $subject = 'Internship Management Platform SMTP Test';
        $body = '<p>This is a test email from the Internship Management Platform.</p>' .
                '<p>If you received this email, Gmail SMTP configuration is working.</p>';
        if (sendEmail($recipient, '', $subject, $body, $errorOutput)) {
            $message = 'Test email sent successfully to ' . htmlspecialchars($recipient) . '.';
            $status = 'success';
        } else {
            $message = 'Test email failed: ' . htmlspecialchars($errorOutput);
            $status = 'error';
        }

        $diagnostics['connection_status'] = testSmtpConnection($connectionDebug) ? 'Connected' : 'Connection failed';
        $diagnostics['connection_debug'] = $connectionDebug;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test Email</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        label, input, button { display: block; width: 100%; max-width: 480px; margin-bottom: 12px; }
        input { padding: 10px; font-size: 1rem; }
        button { padding: 10px 16px; font-size: 1rem; cursor: pointer; }
        .message { margin-top: 16px; padding: 12px; border-radius: 6px; }
        .success { background: #e6ffed; border: 1px solid #1a7f37; color: #1f4d1b; }
        .error { background: #ffeded; border: 1px solid #bf2600; color: #60140d; }
    </style>
</head>
<body>
    <h1>SMTP Test Email</h1>
    <p>Use this page to verify Gmail SMTP configuration.</p>
    <form method="post">
        <label for="to_email">Recipient Email</label>
        <input id="to_email" name="to_email" type="email" value="<?php echo htmlspecialchars($recipient); ?>" placeholder="recipient@example.com" required>
        <button type="submit">Send Test Email</button>
    </form>

    <section style="margin-top: 24px; max-width: 720px;">
        <h2>SMTP Diagnostics</h2>
        <dl>
            <dt>SMTP Host</dt>
            <dd><?php echo htmlspecialchars($diagnostics['host'] ?: 'Not configured'); ?></dd>
            <dt>SMTP Port</dt>
            <dd><?php echo htmlspecialchars($diagnostics['port'] ?: 'Not configured'); ?></dd>
            <dt>Username configured</dt>
            <dd><?php echo htmlspecialchars($diagnostics['username'] ?: 'Not configured'); ?></dd>
            <dt>Password present</dt>
            <dd><?php echo $diagnostics['password_present'] ? 'Yes' : 'No'; ?></dd>
            <dt>From email</dt>
            <dd><?php echo htmlspecialchars($diagnostics['from_email'] ?: 'Not configured'); ?></dd>
            <dt>From name</dt>
            <dd><?php echo htmlspecialchars($diagnostics['from_name']); ?></dd>
            <dt>Connection status</dt>
            <dd><?php echo htmlspecialchars($diagnostics['connection_status']); ?></dd>
        </dl>
        <?php if (!empty($diagnostics['connection_debug'])): ?>
            <details style="margin-top: 12px;">
                <summary>Connection debug output</summary>
                <pre style="white-space: pre-wrap; background: #f8f8f8; padding: 12px; border: 1px solid #ddd; border-radius: 6px;"><?php echo htmlspecialchars($diagnostics['connection_debug']); ?></pre>
            </details>
        <?php endif; ?>
    </section>

    <?php if ($message !== ''): ?>
        <div class="message <?php echo $status === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <p>After this file is created, open <code>config/email_config.php</code> and paste your Gmail App Password into the <code>SMTP_PASSWORD</code> constant if needed.</p>
</body>
</html>
