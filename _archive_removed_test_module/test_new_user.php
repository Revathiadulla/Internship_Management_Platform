<?php
require __DIR__ . '/includes/db.php';

// 1. Simulate Registration
$email = 'testnewuser@example.com';
$password = 'TestPass123!';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// delete if exists
mysqli_query($conn, "DELETE FROM users WHERE email='$email'");

$sql = "INSERT INTO users (full_name, email, password, role, phone, status, is_active, approval_status)
        VALUES ('Test User', '$email', '$hashed_password', 'student', '1234567890', 'approved', 1, 'approved')";
mysqli_query($conn, $sql);
echo "Registered. Error? " . mysqli_error($conn) . "\n";

// 2. Simulate Login
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "Login: User not found.\n";
} else {
    echo "Login: User found.\n";
    $storedPassword = $user['password'];
    echo "Stored hash: $storedPassword\n";
    if (password_verify($password, $storedPassword)) {
        echo "Login: Password verify SUCCESS.\n";
    } else {
        echo "Login: Password verify FAILED.\n";
    }
}
?>
