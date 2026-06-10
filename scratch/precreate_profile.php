<?php
$conn = mysqli_connect('localhost', 'root', '', 'imp_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$email = "testonboarding@imp.local";
$user_res = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
$user = mysqli_fetch_assoc($user_res);

if (!$user) {
    die("Test user not found.\n");
}

$user_id = $user['id'];

// Delete existing profile
mysqli_query($conn, "DELETE FROM student_profiles WHERE user_id = $user_id");

// Insert a profile with dummy file paths so they aren't 'required' on the form
$sql = "INSERT INTO student_profiles (
    user_id, full_name, email, phone, dob, gender, college_name, course, 
    year_of_study, skills, resume_file, resume_url, aadhaar_number, pan_number, 
    aadhaar_file, pan_file, education_status, department, graduation_year, 
    previous_college, resume_original_name, aadhaar_original_name, pan_original_name, student_type
) VALUES (
    $user_id, 'Test Onboarding Student', '$email', '9999999999', '2000-01-01', 'Male', 'Test College', 'B.Tech', 
    '3rd Year (Graduating 2027)', 'HTML, CSS, JS', 'uploads/resumes/dummy_resume.pdf', '', '123456789012', 'ABCDE1234F', 
    'uploads/aadhaar/dummy_aadhaar.pdf', 'uploads/pan/dummy_pan.pdf', 'Pursuing', 'B.Tech', '', 
    '', 'dummy_resume.pdf', 'dummy_aadhaar.pdf', 'dummy_pan.pdf', 'pursuing'
)";

if (mysqli_query($conn, $sql)) {
    echo "Successfully pre-created profile for test student user.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
