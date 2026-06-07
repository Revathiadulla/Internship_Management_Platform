<?php
ob_start();
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
$education_status = isset($_POST['education_status']) ? mysqli_real_escape_string($conn, trim($_POST['education_status'])) : null;
$department = isset($_POST['department']) ? mysqli_real_escape_string($conn, trim($_POST['department'])) : null;
$graduation_year = isset($_POST['graduation_year']) ? mysqli_real_escape_string($conn, trim($_POST['graduation_year'])) : null;
$previous_college = isset($_POST['previous_college']) ? mysqli_real_escape_string($conn, trim($_POST['previous_college'])) : null;
// Convert empty strings to NULL for optional fields
$education_status = $education_status === '' ? null : $education_status;
$department = $department === '' ? null : $department;
$graduation_year = $graduation_year === '' ? null : $graduation_year;
$previous_college = $previous_college === '' ? null : $previous_college;

// Ensure upload directories exist and are writable (if not, we skip uploads gracefully)
$baseUploadDir = __DIR__ . '/uploads/';
$uploads_writable = true;

$upload_dirs = [
    'aadhaar' => $baseUploadDir . 'aadhaar/',
    'pan' => $baseUploadDir . 'pan/',
    'resumes' => $baseUploadDir . 'resumes/',
    'profile' => $baseUploadDir . 'profile/'
];

foreach ($upload_dirs as $name => $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            $uploads_writable = false;
            break;
        }
    }
    if (!is_writable($dir)) {
        $uploads_writable = false;
        break;
    }
}

$upload_skipped = !$uploads_writable;

// Fetch existing profile data to preserve old files if new ones aren't uploaded
$check_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);
$existing_profile = mysqli_fetch_assoc($check_result);

$new_resume = $existing_profile ? $existing_profile['resume_file'] : '';
$new_aadhaar = $existing_profile ? $existing_profile['aadhaar_file'] : '';
$new_pan = $existing_profile ? $existing_profile['pan_file'] : '';

// 1. Resume File Upload Validation & Processing
$resume_url = isset($_POST['resume_url']) ? mysqli_real_escape_string($conn, trim($_POST['resume_url'])) : '';

if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
    if (!$uploads_writable) {
        $upload_skipped = true;
    } else {
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
        $resumes_dir = $baseUploadDir . 'resumes/';
        
        if (move_uploaded_file($resume_tmp, $resumes_dir . $unique_resume)) {
            $new_resume = 'uploads/resumes/' . $unique_resume;
            if ($existing_profile && !empty($existing_profile['resume_file'])) {
                $old_file = $existing_profile['resume_file'];
                if (strpos($old_file, 'uploads/') !== 0) {
                    @unlink(sys_get_temp_dir() . "/imp_uploads/" . $old_file);
                } else {
                    @unlink(__DIR__ . '/' . $old_file);
                }
            }
        } else {
            $upload_skipped = true;
        }
    }
}

// 2. Aadhaar File Upload Validation & Processing
if (isset($_FILES['aadhaar_file']) && $_FILES['aadhaar_file']['error'] === UPLOAD_ERR_OK) {
    if (!$uploads_writable) {
        $upload_skipped = true;
    } else {
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
        $aadhaar_dir = $baseUploadDir . 'aadhaar/';
        
        if (move_uploaded_file($aadhaar_tmp, $aadhaar_dir . $unique_aadhaar)) {
            $new_aadhaar = 'uploads/aadhaar/' . $unique_aadhaar;
            if ($existing_profile && !empty($existing_profile['aadhaar_file'])) {
                $old_file = $existing_profile['aadhaar_file'];
                if (strpos($old_file, 'uploads/') !== 0) {
                    @unlink(__DIR__ . '/uploads/secure/' . $old_file);
                    @unlink(sys_get_temp_dir() . "/imp_uploads/" . $old_file);
                } else {
                    @unlink(__DIR__ . '/' . $old_file);
                }
            }
        } else {
            $upload_skipped = true;
        }
    }
}

// 3. PAN File Upload Validation & Processing
if (isset($_FILES['pan_file']) && $_FILES['pan_file']['error'] === UPLOAD_ERR_OK) {
    if (!$uploads_writable) {
        $upload_skipped = true;
    } else {
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
        $pan_dir = $baseUploadDir . 'pan/';
        
        if (move_uploaded_file($pan_tmp, $pan_dir . $unique_pan)) {
            $new_pan = 'uploads/pan/' . $unique_pan;
            if ($existing_profile && !empty($existing_profile['pan_file'])) {
                $old_file = $existing_profile['pan_file'];
                if (strpos($old_file, 'uploads/') !== 0) {
                    @unlink(__DIR__ . '/uploads/secure/' . $old_file);
                    @unlink(sys_get_temp_dir() . "/imp_uploads/" . $old_file);
                } else {
                    @unlink(__DIR__ . '/' . $old_file);
                }
            }
        } else {
            $upload_skipped = true;
        }
    }
}

        // Auto-migrate student_profiles table to ensure required columns exist
        $required_cols = [
            'education_status' => "VARCHAR(50) NULL",
            'department' => "VARCHAR(100) NULL",
            'hod_name' => "VARCHAR(100) NULL",
            'hod_phone' => "VARCHAR(20) NULL",
            'hod_email' => "VARCHAR(100) NULL",
            'graduation_year' => "VARCHAR(10) NULL",
            'previous_college' => "VARCHAR(255) NULL"
        ];
        foreach ($required_cols as $col => $def) {
            $col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
            if ($col_check && mysqli_num_rows($col_check) === 0) {
                $alter_sql = "ALTER TABLE student_profiles ADD COLUMN $col $def";
                if (!mysqli_query($conn, $alter_sql)) {
                    require_once __DIR__ . '/utils/log_helper.php';
                    log_error("Failed to add column $col: " . mysqli_error($conn));
                    header('Location: student_profile_form.php?error=' . urlencode('Database schema update failed. Please try again later.'));
                    exit();
                }
            }
        }

        // Save or Update Profile in Database
if ($existing_profile) {
    // Update using prepared statement
    // DEBUG: placeholder_count=23, bind_type_count=23, bind_variable_count=23
    $stmt = $conn->prepare("UPDATE student_profiles SET full_name=?, email=?, phone=?, dob=?, gender=?, college_name=?, course=?, year_of_study=?, skills=?, resume_file=?, resume_url=?, aadhaar_number=?, pan_number=?, aadhaar_file=?, pan_file=?, hod_name=?, hod_phone=?, hod_email=?, education_status=?, department=?, graduation_year=?, previous_college=? WHERE user_id=?");
    $stmt->bind_param(
        "ssssssssssssssssssssssi",
        $full_name, $email, $phone, $dob, $gender, $college_name, $course, $year_of_study, $skills, $new_resume, $resume_url, $aadhaar_number, $pan_number, $new_aadhaar, $new_pan, $hod_name, $hod_phone, $hod_email, $education_status, $department, $graduation_year, $previous_college, $user_id
    );
    if ($stmt->execute()) {
        if ($upload_skipped) {
            header("Location: student_dashboard.php?warning=" . urlencode("Profile saved, but file upload was skipped."));
        } else {
            header("Location: student_dashboard.php?msg=profile_updated");
        }
        exit();
    } else {
        require_once __DIR__ . '/utils/log_helper.php';
        log_error('Profile update failed: ' . $stmt->error);
        header('Location: student_profile_form.php?error=' . urlencode('Failed to update profile. Please try again later.'));
        exit();
    }
} else {
    // Insert new profile using prepared statement
    // DEBUG: placeholder_count=23, bind_type_count=23, bind_variable_count=23
    $stmt = $conn->prepare("INSERT INTO student_profiles (user_id, full_name, email, phone, dob, gender, college_name, course, year_of_study, skills, resume_file, resume_url, aadhaar_number, pan_number, aadhaar_file, pan_file, hod_name, hod_phone, hod_email, education_status, department, graduation_year, previous_college) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->bind_param(
                            "issssssssssssssssssssss",
                            $user_id, $full_name, $email, $phone, $dob, $gender,
                            $college_name, $course, $year_of_study, $skills,
                            $new_resume, $resume_url, $aadhaar_number, $pan_number,
                            $new_aadhaar, $new_pan, $hod_name, $hod_phone, $hod_email,
                            $education_status, $department, $graduation_year, $previous_college
                        );
    if ($stmt->execute()) {
        if ($upload_skipped) {
            header("Location: student_dashboard.php?warning=" . urlencode("Profile saved, but file upload was skipped."));
        } else {
            header("Location: student_dashboard.php?msg=profile_updated");
        }
        exit();
    } else {
        require_once __DIR__ . '/utils/log_helper.php';
        log_error('Profile insert failed: ' . $stmt->error);
        header('Location: student_profile_form.php?error=' . urlencode('Failed to save profile. Please try again later.'));
        exit();
    }
}


