<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = isset($_POST['full_name']) ? $_POST['full_name'] : (isset($_POST['fullname']) ? $_POST['fullname'] : '');
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if ($password !== $confirm_password) {
        $params = http_build_query(['error' => 'Passwords do not match. Please try again.', 'full_name' => $full_name, 'email' => $email]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    $checkEmail = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $checkEmail);

    if (mysqli_num_rows($result) > 0) {
        $params = http_build_query(['error' => 'This email is already registered. Please log in instead.', 'full_name' => $full_name, 'email' => $email]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, email, password, role)
            VALUES ('$full_name', '$email', '$hashed_password', '$role')";

    if (mysqli_query($conn, $sql)) {
        // Automatically log in the user after successful registration
        $user_id = mysqli_insert_id($conn);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;

        if ($role === 'student') {
            header("Location: login.php?success=" . urlencode("Account created! Please log in to continue."));
            exit();
        } else {
            header("Location: login.php?success=" . urlencode("Account created! Please wait for admin approval, then log in."));
            exit();
        }
    } else {
        echo 'Error: ' . mysqli_error($conn);
    }
}
?>
