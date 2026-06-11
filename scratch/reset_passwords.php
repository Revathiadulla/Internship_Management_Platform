<?php
require_once __DIR__ . '/../db.php';

$users = [
    'revathiadulla@gmail.com',  // student
    'revathiadulla24@gmail.com', // hr
    'imp.webportal2026@gmail.com' // admin
];

foreach ($users as $email) {
    $esc_email = mysqli_real_escape_string($conn, $email);
    // Setting as plain text so the login.php rehash flow can hash it on first login,
    // or we can set it to a hashed password directly.
    $hashed = password_hash('password123', PASSWORD_DEFAULT);
    $q = mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE email = '$esc_email'");
    if ($q) {
        echo "Updated password for $email to hashed 'password123'\n";
    } else {
        echo "Failed to update password for $email: " . mysqli_error($conn) . "\n";
    }
}
