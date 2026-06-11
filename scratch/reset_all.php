<?php
require_once __DIR__ . '/../db.php';
$h = password_hash('password123', PASSWORD_DEFAULT);
$res = mysqli_query($conn, "UPDATE users SET password='$h'");
if ($res) {
    echo "All passwords successfully reset to password123.\n";
} else {
    echo "Failed: " . mysqli_error($conn) . "\n";
}
?>
