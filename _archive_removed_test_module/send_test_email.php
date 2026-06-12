<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
include_once __DIR__ . '/includes/mail_helper.php';

// Ensure only admins can access
require_role('admin'); // Assuming this function checks current user's role and exits if not admin

$user_id = current_user_id();

// Fetch admin email and name
$user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
$res = mysqli_query($conn, $user_sql);
if (!$res || mysqli_num_rows($res) === 0) {
    die('Admin user not found.');
}
$row = mysqli_fetch_assoc($res);
$admin_email = trim($row['email']);
$admin_name  = trim($row['full_name']) ?: 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send test email
    $subject = 'IMP Test Email';
    $message = "Hello $admin_name,\n\nThis is a test email sent from the Internship Management Platform (IMP) to verify SMTP configuration.\n\nIf you received this email, the email system is working correctly.\n\nRegards,\nIMP System";
    $metadata = [
        'event' => 'Test Email',
        'action_label' => 'Login to IMP',
        'action_url' => 'http://localhost/IMP/login.php',
    ];
    $sent = sendEmailNotification($admin_email, $subject, $message, $metadata);
    $status_msg = $sent ? 'Test email sent successfully.' : 'Failed to send test email. Check logs.';
    echo "<p>{$status_msg}</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IMP – Send Test Email</title>
    <style>
        body {font-family: Arial, sans-serif; background:#f5f5f5; padding:30px;}
        .container {max-width:600px; margin:auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        h1 {color:#2c3e50;}
        button {background:#2563eb; color:#fff; border:none; padding:10px 20px; cursor:pointer; border-radius:4px;}
    </style>
</head>
<body>
<div class="container">
    <h1>Send Test Email</h1>
    <p>Click the button below to send a test email to your registered admin address (<strong><?php echo htmlspecialchars($admin_email); ?></strong>).</p>
    <form method="POST">
        <button type="submit">Send Test Email</button>
    </form>
</div>
</body>
</html>
