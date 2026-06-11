<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT password FROM users WHERE email='revathiadulla@gmail.com'");
$p = mysqli_fetch_row($r)[0];
echo "Hash: " . $p . "\n";
echo "Verify password123: " . (password_verify("password123", $p) ? "YES" : "NO") . "\n";
?>
