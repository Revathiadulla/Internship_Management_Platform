<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';
include_once __DIR__ . '/includes/mail_helper.php';

// Ensure only admins can access
require_role('admin'); // exits if not admin

$user_id = current_user_id();
// Fetch admin email and name (optional, for display)
$user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
$res = mysqli_query($conn, $user_sql);
if (!$res || mysqli_num_rows($res) === 0) {
    die('Admin user not found.');
}
$row = mysqli_fetch_assoc($res);
$admin_email = trim($row['email']);
$admin_name  = trim($row['full_name']) ?: 'Admin';

$status_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $recipient = filter_var($_POST['recipient'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject   = trim($_POST['subject'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    if (!$recipient) {
        $status_msg = 'Invalid recipient email.';
    } elseif ($subject === '' || $message === '') {
        $status_msg = 'Subject and message cannot be empty.';
    } else {
        $metadata = [
            'event' => 'Admin Sent Email',
            'action_label' => 'View in IMP',
            'action_url' => 'http://localhost/IMP/login.php',
            'sender_name' => $admin_name,
            'sender_email' => $admin_email
        ];
        $sent = sendEmailNotification($recipient, $subject, $message, $metadata);
        $status_msg = $sent ? 'Email sent successfully.' : 'Failed to send email. Check logs.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin – Send Email</title>
    <style>
        body {font-family: Arial, sans-serif; background:#f5f5f5; padding:30px;}
        .container {max-width:600px; margin:auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        h1 {color:#2c3e50;}
        label {display:block; margin-top:12px; font-weight:600;}
        input[type=text], textarea {width:100%; padding:8px; margin-top:4px; border:1px solid #ccc; border-radius:4px;}
        button {margin-top:16px; background:#2563eb; color:#fff; border:none; padding:10px 20px; cursor:pointer; border-radius:4px;}
        .status {margin-top:12px; padding:10px; border-radius:4px;}
        .success {background:#d4edda; color:#155724;}
        .error {background:#f8d7da; color:#721c24;}
    </style>
</head>
<body>
<div class="container">
    <h1>Send Email as Admin</h1>
    <p>Logged in as: <strong><?php echo htmlspecialchars($admin_name . ' <' . $admin_email . '>'); ?></strong></p>
    <?php if ($status_msg): ?>
        <div class="status <?php echo (strpos(strtolower($status_msg), 'success') !== false) ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($status_msg); ?></div>
    <?php endif; ?>
    <form method="POST">
        <label for="recipient">Recipient Email</label>
        <input type="text" id="recipient" name="recipient" required placeholder="recipient@example.com" />
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" required />
        <label for="message">Message (plain text)</label>
        <textarea id="message" name="message" rows="6" required></textarea>
        <button type="submit">Send Email</button>
    </form>
</div>
</body>
</html>
