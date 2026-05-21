<?php
session_start();
include "db.php";

$user_id = $_SESSION['user_id'];

$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$full_name = $_POST['full_name'];

$email = $_POST['email'];
$phone = $_POST['phone'];

$college_name = $_POST['college_name'];
$course = $_POST['course'];
$year_of_study = $_POST['year_of_study'];

$skills = $_POST['skills'];
$aadhaar_number = $_POST['aadhaar_number'];

$resume_name = $_FILES['resume']['name'];
$resume_tmp = $_FILES['resume']['tmp_name'];

$folder = "uploads/";

// Ensure uploads folder exists
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}

$new_resume = time() . "_" . $resume_name;

move_uploaded_file($resume_tmp, $folder . $new_resume);

$sql = "INSERT INTO applications
(user_id, first_name, last_name, full_name, email, phone, college_name, course, year_of_study, skills, resume_file, aadhaar_number)
VALUES
('$user_id', '$first_name', '$last_name', '$full_name', '$email', '$phone', '$college_name', '$course', '$year_of_study', '$skills', '$new_resume', '$aadhaar_number')";

if(mysqli_query($conn, $sql)){

    header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully"));
    exit();

}else{

    echo "Error: " . mysqli_error($conn);
}
