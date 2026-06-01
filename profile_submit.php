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
$hod_name = isset($_POST['hod_name']) ? mysqli_real_escape_string($conn, trim($_POST['hod_name'])) : '';
$hod_phone = isset($_POST['hod_phone']) ? mysqli_real_escape_string($conn, trim($_POST['hod_phone'])) : '';
$hod_email = isset($_POST['hod_email']) ? mysqli_real_escape_string($conn, trim($_POST['hod_email'])) : '';

$folder = sys_get_temp_dir() . "/imp_uploads/";
if (!is_dir($folder)) {
    if (!mkdir($folder, 0777, true)) {
        header("Location: student_profile_form.php?error=" . urlencode("Failed to create upload directory."));
        exit();
    }
    // Create an .htaccess file to restrict access
    $htaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents($folder . ".htaccess", $htaccess);
}

// Fetch existing profile data to preserve old files if new ones aren't uploaded
$check_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);
$existing_profile = mysqli_fetch_assoc($check_result);

$new_resume = $existing_profile ? $existing_profile['resume_file'] : '';
$new_aadhaar = $existing_profile ? $existing_profile['aadhaar_file'] : '';
$new_pan = $existing_profile ? $existing_profile['pan_file'] : '';

// 1. Resume File Upload Validation & Processing
if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
    $resume_name = $_FILES['resume']['name'];
    $resume_tmp = $_FILES['resume']['tmp_name'];
    $resume_ext = strtolower(pathinfo($resume_name, PATHINFO_EXTENSION));
    
    if (!in_array($resume_ext, ['pdf', 'doc', 'docx'])) {
        header("Location: student_profile_form.php?error=" . urlencode("Invalid resume file format. Allowed: PDF, DOC, DOCX."));
        exit();
    }
    if ($_FILES['resume']['size'] > 5 * 1024 * 1024) {
        header("Location: student_profile_form.php?error=" . urlencode("Resume file size exceeds the 5MB limit."));
        exit();
    }
    
    $clean_resume_name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $resume_name);
    $unique_resume = time() . "_resume_" . $clean_resume_name;
    
    if (move_uploaded_file($resume_tmp, $folder . $unique_resume)) {
        $new_resume = $unique_resume;
        if ($existing_profile && !empty($existing_profile['resume_file']) && file_exists($folder . $existing_profile['resume_file'])) {
            @unlink($folder . $existing_profile['resume_file']);
        }
    } else {
        header("Location: student_profile_form.php?error=" . urlencode("Failed to move uploaded resume file."));
        exit();
    }
} elseif (empty($new_resume)) {
    header("Location: student_profile_form.php?error=" . urlencode("Resume document is required."));
    exit();
}

// 2. Aadhaar File Upload Validation & Processing
if (isset($_FILES['aadhaar_file']) && $_FILES['aadhaar_file']['error'] === UPLOAD_ERR_OK) {
    $aadhaar_name = $_FILES['aadhaar_file']['name'];
    $aadhaar_tmp = $_FILES['aadhaar_file']['tmp_name'];
    $aadhaar_ext = strtolower(pathinfo($aadhaar_name, PATHINFO_EXTENSION));
    
    if (!in_array($aadhaar_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
        header("Location: student_profile_form.php?error=" . urlencode("Invalid Aadhaar file format. Allowed: PDF, JPG, JPEG, PNG."));
        exit();
    }
    if ($_FILES['aadhaar_file']['size'] > 5 * 1024 * 1024) {
        header("Location: student_profile_form.php?error=" . urlencode("Aadhaar file size exceeds the 5MB limit."));
        exit();
    }
    
    $clean_aadhaar_name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $aadhaar_name);
    $unique_aadhaar = time() . "_aadhaar_" . $clean_aadhaar_name;
    
    if (move_uploaded_file($aadhaar_tmp, $folder . $unique_aadhaar)) {
        $new_aadhaar = $unique_aadhaar;
        if ($existing_profile && !empty($existing_profile['aadhaar_file']) && file_exists($folder . $existing_profile['aadhaar_file'])) {
            @unlink($folder . $existing_profile['aadhaar_file']);
        }
    } else {
        header("Location: student_profile_form.php?error=" . urlencode("Failed to move uploaded Aadhaar file."));
        exit();
    }
} elseif (empty($new_aadhaar)) {
    header("Location: student_profile_form.php?error=" . urlencode("Aadhaar document is required."));
    exit();
}

// 3. PAN File Upload Validation & Processing
if (isset($_FILES['pan_file']) && $_FILES['pan_file']['error'] === UPLOAD_ERR_OK) {
    $pan_name = $_FILES['pan_file']['name'];
    $pan_tmp = $_FILES['pan_file']['tmp_name'];
    $pan_ext = strtolower(pathinfo($pan_name, PATHINFO_EXTENSION));
    
    if (!in_array($pan_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
        header("Location: student_profile_form.php?error=" . urlencode("Invalid PAN file format. Allowed: PDF, JPG, JPEG, PNG."));
        exit();
    }
    if ($_FILES['pan_file']['size'] > 5 * 1024 * 1024) {
        header("Location: student_profile_form.php?error=" . urlencode("PAN file size exceeds the 5MB limit."));
        exit();
    }
    
    $clean_pan_name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $pan_name);
    $unique_pan = time() . "_pan_" . $clean_pan_name;
    
    if (move_uploaded_file($pan_tmp, $folder . $unique_pan)) {
        $new_pan = $unique_pan;
        if ($existing_profile && !empty($existing_profile['pan_file']) && file_exists($folder . $existing_profile['pan_file'])) {
            @unlink($folder . $existing_profile['pan_file']);
        }
    } else {
        header("Location: student_profile_form.php?error=" . urlencode("Failed to move uploaded PAN file."));
        exit();
    }
} elseif (empty($new_pan)) {
    header("Location: student_profile_form.php?error=" . urlencode("PAN card is required."));
    exit();
}

// Save or Update Profile in Database
if ($existing_profile) {
    // Update
    $sql = "UPDATE student_profiles SET 
            full_name='$full_name', email='$email', phone='$phone', dob='$dob', gender='$gender',
            college_name='$college_name', course='$course', year_of_study='$year_of_study', skills='$skills',
            resume_file='$new_resume', aadhaar_number='$aadhaar_number', pan_number='$pan_number',
            aadhaar_file='$new_aadhaar', pan_file='$new_pan',
            hod_name='$hod_name', hod_phone='$hod_phone', hod_email='$hod_email'
            WHERE user_id='$user_id'";
} else {
    // Insert
    $sql = "INSERT INTO student_profiles 
            (user_id, full_name, email, phone, dob, gender, college_name, course, year_of_study, skills, resume_file, aadhaar_number, pan_number, aadhaar_file, pan_file, hod_name, hod_phone, hod_email) 
            VALUES 
            ('$user_id', '$full_name', '$email', '$phone', '$dob', '$gender', '$college_name', '$course', '$year_of_study', '$skills', '$new_resume', '$aadhaar_number', '$pan_number', '$new_aadhaar', '$new_pan', '$hod_name', '$hod_phone', '$hod_email')";
}

if (mysqli_query($conn, $sql)) {
    header("Location: student_dashboard.php?msg=profile_updated");
    exit();
} else {
    header("Location: student_profile_form.php?error=" . urlencode("Database Error: " . mysqli_error($conn)));
    exit();
}
