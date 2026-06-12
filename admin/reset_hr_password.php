<?php
include __DIR__ . '/../includes/db.php';

$email = 'hr.priya@example.com';
$password = password_hash('Hr@2026#Test', PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ?, role = 'hr', status = 'active' WHERE email = ?");
$stmt->bind_param("ss", $password, $email);
$stmt->execute();

echo "HR password reset done";
?>