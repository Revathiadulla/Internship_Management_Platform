<?php
/**
 * send_test_email_smtp.php
 * Quick diagnostic — send a test email via the updated email_helper.php
 * Access: http://localhost/IMP/send_test_email_smtp.php
 * DELETE THIS FILE after confirming email works.
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

$to      = isset($_GET['to']) ? trim($_GET['to']) : 'imp.webportal2026@gmail.com';
$result  = '';
$debug   = '';

if (isset($_GET['send'])) {
    // sendEmail() signature (5-param overload):
    //   sendEmail($to, $toName, $subject, $htmlBody, &$debugInfo)
    $debug = '';   // must be a variable — passed by reference as 5th arg
    $ok = sendEmail(
        $to,
        'IMP Diagnostic',
        'SMTP Test — IMP',
        '<h2>SMTP Test from IMP</h2><p>If you see this, PHPMailer email sending is working correctly!</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>',
        $debug
    );
    $result = $ok
        ? '✅ Email sent successfully to ' . htmlspecialchars($to) . '!'
        : '❌ Email failed. Reason: ' . htmlspecialchars($debug);
}

$cfg = getSmtpConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>SMTP Test — IMP</title>
<style>
body{font-family:sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#f8fafc;}
.ok{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;}
pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;overflow:auto;font-size:13px;}
h2{color:#0f172a;}
.cfg td{padding:4px 8px;} .cfg td:first-child{color:#64748b;font-weight:600;width:180px;}
</style>
</head>
<body>
<h2>📧 IMP Email (SMTP) Diagnostic</h2>

<h3>Current SMTP Config</h3>
<table class="cfg" border="0">
  <tr><td>Host</td><td><?= htmlspecialchars($cfg['host']) ?></td></tr>
  <tr><td>Port</td><td><?= htmlspecialchars($cfg['port']) ?></td></tr>
  <tr><td>Username</td><td><?= htmlspecialchars($cfg['username']) ?></td></tr>
  <tr><td>Password Set?</td><td><?= !empty($cfg['password']) ? '✅ Yes' : '❌ No' ?></td></tr>
  <tr><td>From Email</td><td><?= htmlspecialchars($cfg['from_email']) ?></td></tr>
  <tr><td>From Name</td><td><?= htmlspecialchars($cfg['from_name']) ?></td></tr>
  <tr><td>BREVO_API_KEY</td><td><?= !empty(getenv('BREVO_API_KEY')) ? '✅ Set' : '—  Not set (PHPMailer will be used)' ?></td></tr>
</table>

<hr>
<h3>Send Test Email</h3>
<form method="GET">
  <label>Send to: <input type="email" name="to" value="<?= htmlspecialchars($to) ?>" style="padding:6px;border:1px solid #ccc;border-radius:6px;width:300px;"></label>
  <input type="hidden" name="send" value="1">
  <button type="submit" style="margin-left:8px;padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;">Send Test Email</button>
</form>

<?php if ($result): ?>
  <p style="margin-top:16px;font-size:16px;"><?= $result ?></p>
<?php endif; ?>

<?php if ($debug): ?>
  <h3>Debug Output</h3>
  <pre><?= htmlspecialchars($debug) ?></pre>
<?php endif; ?>

<p style="color:#94a3b8;font-size:12px;margin-top:40px;">⚠️ Delete <code>send_test_email_smtp.php</code> after testing.</p>
</body>
</html>
