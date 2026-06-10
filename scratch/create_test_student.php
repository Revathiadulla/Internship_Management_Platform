<?php
$conn = mysqli_connect('localhost', 'root', '', 'imp_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
$email = "testonboarding@imp.local";
$password = password_hash("student123", PASSWORD_DEFAULT);
$name = "Test Onboarding Student";

// Delete existing if any
mysqli_query($conn, "DELETE FROM student_profiles WHERE email = '$email'");
mysqli_query($conn, "DELETE FROM users WHERE email = '$email'");

$sql = "INSERT INTO users (full_name, email, password, role, status) VALUES ('$name', '$email', '$password', 'student', 'approved')";
if (mysqli_query($conn, $sql)) {
    echo "Successfully created test student user:\n";
    echo "Email: $email\n";
    echo "Password: student123\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
