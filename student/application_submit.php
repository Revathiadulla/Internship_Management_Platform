<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

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

require_once "includes/cloudinary_config.php";

$resume_name = $_FILES['resume']['name'];
$resume_tmp = $_FILES['resume']['tmp_name'];

try {
    $new_resume = uploadToCloudinary($resume_tmp, 'student_resumes', true, $resume_name);
} catch (Exception $e) {
    die("Resume upload failed: " . $e->getMessage());
}

$sql = "INSERT INTO applications
(user_id, first_name, last_name, full_name, email, phone, college_name, course, year_of_study, skills, resume_file, resume_original_name, aadhaar_number)
VALUES
('$user_id', '$first_name', '$last_name', '$full_name', '$email', '$phone', '$college_name', '$course', '$year_of_study', '$skills', '$new_resume', '" . mysqli_real_escape_string($conn, $resume_name) . "', '$aadhaar_number')";

if(mysqli_query($conn, $sql)){

    header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully"));
    exit();

}else{

    echo "Error: " . mysqli_error($conn);
}
?>
