<?php
require_once __DIR__ . '/../db.php';

$test_users = [
    'student'     => 'revathiadulla@gmail.com',
    'hr'          => 'revathiadulla24@gmail.com',
    'coordinator' => 'jaya@gmail.com',
    'mentor'      => 'mentor.rajesh@example.com',
    'admin'       => 'imp.webportal2026@gmail.com'
];

$hashed = password_hash('password123', PASSWORD_DEFAULT);

foreach ($test_users as $role => $email) {
    // 1. Ensure user exists
    $res = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($res) === 0) {
        // Insert user
        $name = ucfirst($role) . " Test User";
        mysqli_query($conn, "INSERT INTO users (full_name, email, password, role, status, is_active, approval_status) VALUES ('$name', '$email', '$hashed', '$role', 'Active', 1, 'approved')");
        echo "Inserted $role user: $email\n";
    } else {
        // Update password and status
        mysqli_query($conn, "UPDATE users SET password = '$hashed', status = 'Active', is_active = 1, approval_status = 'approved' WHERE email = '$email'");
        echo "Updated $role user: $email\n";
    }
}
