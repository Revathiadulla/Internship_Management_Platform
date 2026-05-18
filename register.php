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
        echo "<script>
                alert('Passwords do not match');
                window.location.href='register.html';
              </script>";
        exit();
    }

    $checkEmail = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $checkEmail);

    if (mysqli_num_rows($result) > 0) {
        echo "<script>
                alert('Email already registered');
                window.location.href='register.html';
              </script>";
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
            header("refresh:2;url=application_form.php");
            echo '
            <div style="
            max-width:500px;
            margin:100px auto;
            padding:20px;
            background:#dcfce7;
            border:1px solid #86efac;
            border-radius:10px;
            color:#166534;
            font-family:Arial;
            font-size:18px;
            text-align:center;
            ">
            Registration successful! Please complete your profile.
            </div>
            ';
            exit();
        } else {
            header("refresh:2;url=login.html");
            echo '
            <div style="
            max-width:500px;
            margin:100px auto;
            padding:20px;
            background:#dcfce7;
            border:1px solid #86efac;
            border-radius:10px;
            color:#166534;
            font-family:Arial;
            font-size:18px;
            text-align:center;
            ">
            Registration successful! Please log in.
            </div>
            ';
            exit();
        }
    } else {
        echo 'Error: ' . mysqli_error($conn);
    }
}
?>