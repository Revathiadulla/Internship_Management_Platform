<?php
require_once __DIR__ . '/../db.php';
$emails = ['revathiadulla24@gmail.com', 'revathiadulla@gmail.com', 'imp.webportal2026@gmail.com', 'jaya@gmail.com'];
foreach ($emails as $email) {
    $res = mysqli_query($conn, "SELECT email, status, is_active, approval_status FROM users WHERE email = '$email'");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        echo "Email: {$row['email']} | status: {$row['status']} | is_active: {$row['is_active']} | approval_status: {$row['approval_status']}\n";
    }
}
