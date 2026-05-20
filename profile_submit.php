<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$phone = mysqli_real_escape_string($conn, $_POST['phone']);
$dob = mysqli_real_escape_string($conn, $_POST['dob']);
$gender = mysqli_real_escape_string($conn, $_POST['gender']);
$college_name = mysqli_real_escape_string($conn, $_POST['college_name']);
$course = mysqli_real_escape_string($conn, $_POST['course']);
$year_of_study = mysqli_real_escape_string($conn, $_POST['year_of_study']);
$skills = mysqli_real_escape_string($conn, $_POST['skills']);
$aadhaar_number = mysqli_real_escape_string($conn, $_POST['aadhaar_number']);
$pan_number = mysqli_real_escape_string($conn, $_POST['pan_number']);

$folder = "uploads/secure/";
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
    // Create an .htaccess file to restrict access
    $htaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents($folder . ".htaccess", $htaccess);
}

// File Uploads
$resume_name = $_FILES['resume']['name'];
$resume_tmp = $_FILES['resume']['tmp_name'];
$new_resume = time() . "_resume_" . $resume_name;
move_uploaded_file($resume_tmp, $folder . $new_resume);

$aadhaar_name = $_FILES['aadhaar_file']['name'];
$aadhaar_tmp = $_FILES['aadhaar_file']['tmp_name'];
$new_aadhaar = time() . "_aadhaar_" . $aadhaar_name;
move_uploaded_file($aadhaar_tmp, $folder . $new_aadhaar);

$pan_name = $_FILES['pan_file']['name'];
$pan_tmp = $_FILES['pan_file']['tmp_name'];
$new_pan = time() . "_pan_" . $pan_name;
move_uploaded_file($pan_tmp, $folder . $new_pan);

// Check if profile exists
$check_sql = "SELECT id FROM student_profiles WHERE user_id = '$user_id'";
$check_result = mysqli_query($conn, $check_sql);

if(mysqli_num_rows($check_result) > 0) {
    // Update
    $sql = "UPDATE student_profiles SET 
            full_name='$full_name', email='$email', phone='$phone', dob='$dob', gender='$gender',
            college_name='$college_name', course='$course', year_of_study='$year_of_study', skills='$skills',
            resume_file='$new_resume', aadhaar_number='$aadhaar_number', pan_number='$pan_number',
            aadhaar_file='$new_aadhaar', pan_file='$new_pan'
            WHERE user_id='$user_id'";
} else {
    // Insert
    $sql = "INSERT INTO student_profiles 
            (user_id, full_name, email, phone, dob, gender, college_name, course, year_of_study, skills, resume_file, aadhaar_number, pan_number, aadhaar_file, pan_file) 
            VALUES 
            ('$user_id', '$full_name', '$email', '$phone', '$dob', '$gender', '$college_name', '$course', '$year_of_study', '$skills', '$new_resume', '$aadhaar_number', '$pan_number', '$new_aadhaar', '$new_pan')";
}

if(mysqli_query($conn, $sql)){
    // Redirect to dashboard
    header("Location: student_dashboard.php?msg=" . urlencode("Profile Saved Successfully"));
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
