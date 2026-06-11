<?php
require_once __DIR__ . '/../db.php';
$password = password_hash('password123', PASSWORD_DEFAULT);
$update = mysqli_query($conn, "UPDATE users SET password = '$password' WHERE email = 'jaya@gmail.com'");
if ($update) {
    echo "Successfully updated password for jaya@gmail.com to password123\n";
} else {
    echo "Error updating password: " . mysqli_error($conn) . "\n";
}
