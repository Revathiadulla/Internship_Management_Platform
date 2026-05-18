<?php
session_start();
include "db.php";

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){

    $user = mysqli_fetch_assoc($result);

    if(password_verify($password, $user['password'])){

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if($user['role'] == "student"){
            header("Location: student_dashboard.php");
        }
        elseif($user['role'] == "hr"){
            header("Location: hr_dashboard.php");
        }
        elseif($user['role'] == "mentor"){
            header("Location: mentor_dashboard.php");
        }
        elseif($user['role'] == "coordinator"){
            header("Location: coordinator_dashboard.php");
        }
        elseif($user['role'] == "company"){
            header("Location: company_dashboard.php");
        }

    } else {

        header("Location: login.html?error=" . urlencode("Invalid email or password"));
        exit();
    }

} else {

    header("Location: login.html?error=" . urlencode("Invalid email or password"));
    exit();
}
?>