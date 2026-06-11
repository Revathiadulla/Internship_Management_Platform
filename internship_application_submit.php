<?php
session_start();
include "db.php";
include_once __DIR__ . "/includes/mail_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ── Common personal fields ──
$full_name      = mysqli_real_escape_string($conn, trim($_POST['full_name']  ?? ''));
$email          = mysqli_real_escape_string($conn, trim($_POST['email']      ?? ''));
$phone          = mysqli_real_escape_string($conn, trim($_POST['phone']      ?? ''));
$skills         = mysqli_real_escape_string($conn, trim($_POST['skills']     ?? ''));

// ── Aadhaar — strip spaces before storing ──
$aadhaar_number = mysqli_real_escape_string($conn, preg_replace('/\s+/', '', $_POST['aadhaar_number'] ?? ''));

// ── PAN — uppercase, validate, then mask for display ──
$pan_raw    = strtoupper(trim($_POST['pan_number'] ?? ''));
$pan_valid  = preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_raw);
$pan_number = $pan_valid ? mysqli_real_escape_string($conn, $pan_raw) : '';
// Masked version: ABCDE1234F → ABCDE****F
$pan_masked = $pan_valid ? substr($pan_raw, 0, 5) . '****' . substr($pan_raw, -1) : '';

// ── Application meta ──
$internship_id   = (int)($_POST['internship_id']   ?? 0);
$internship_name = mysqli_real_escape_string($conn, trim($_POST['internship_name'] ?? ''));
$applied_subtype = mysqli_real_escape_string($conn, trim($_POST['applied_subtype'] ?? ''));
$profile_id      = (int)($_POST['profile_id']      ?? 0);

// ── Education status ──
$education_status = mysqli_real_escape_string($conn, trim($_POST['education_status'] ?? ''));

// ── Pursuing-specific ──
$college_name  = mysqli_real_escape_string($conn, trim($_POST['college_name']  ?? ''));
$department    = mysqli_real_escape_string($conn, trim($_POST['department']    ?? ''));
$year_of_study = mysqli_real_escape_string($conn, trim($_POST['year_of_study'] ?? ''));
$hod_name      = mysqli_real_escape_string($conn, trim($_POST['hod_name']      ?? ''));
$hod_phone     = mysqli_real_escape_string($conn, trim($_POST['hod_phone']     ?? ''));
$hod_email     = mysqli_real_escape_string($conn, trim($_POST['hod_email']     ?? ''));

// ── Passed-out-specific ──
$graduation_year = mysqli_real_escape_string($conn, trim($_POST['graduation_year']   ?? ''));
$prev_college    = mysqli_real_escape_string($conn, trim($_POST['prev_college_name'] ?? ''));

require_once __DIR__ . "/includes/cloudinary_config.php";

// Fetch existing profile details for original names and Aadhaar card file
$profile_query = mysqli_query($conn, "SELECT * FROM student_profiles WHERE id = '$profile_id' AND user_id = '$user_id' LIMIT 1");
$profile = mysqli_fetch_assoc($profile_query);

$resume_orig_name = $profile ? ($profile['resume_original_name'] ?? '') : '';
$pan_orig_name = $profile ? ($profile['pan_original_name'] ?? '') : '';
$aadhaar_orig_name = $profile ? ($profile['aadhaar_original_name'] ?? '') : '';
$aadhaar_card_file = $profile ? ($profile['aadhaar_file'] ?? '') : '';

// ── Prevent Duplicate Applications ──
$dup_sql = "SELECT id FROM internship_applications WHERE user_id = '$user_id' AND internship_id = '$internship_id' AND (internship_id > 0 OR internship_name = '" . mysqli_real_escape_string($conn, $internship_name) . "') LIMIT 1";
$dup_result = mysqli_query($conn, $dup_sql);
if (mysqli_num_rows($dup_result) > 0) {
    header("Location: student_dashboard.php?error=" . urlencode("You have already applied for this internship."));
    exit();
}

// ── Workflow status ──
// Simplified workflow: Applied → HR Review → HOD Approval → Selected → Project Assignment
$app_status = 'Applied';

// ── Handle resume upload ──
$resume_filename = '';
if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
    $allowed_ext = ['pdf', 'doc', 'docx'];
    $resume_orig_name = $_FILES['resume_file']['name'];
    $ext = strtolower(pathinfo($resume_orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        die("Invalid resume file type. Only PDF and DOC/DOCX are allowed.");
    }
    if ($_FILES['resume_file']['size'] > 5 * 1024 * 1024) {
        die("Resume file too large. Maximum size is 5MB.");
    }
    try {
        $resume_filename = uploadToCloudinary($_FILES['resume_file']['tmp_name'], 'student_resumes', true, $resume_orig_name);
    } catch (Exception $e) {
        die("Resume upload failed: " . $e->getMessage());
    }
} elseif (!empty($_POST['existing_resume'])) {
    $resume_filename = mysqli_real_escape_string($conn, $_POST['existing_resume']);
}

// ── Handle PAN card upload ──
$pan_filename = '';
if (isset($_FILES['pan_file']) && $_FILES['pan_file']['error'] === UPLOAD_ERR_OK) {
    $allowed_pan_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    $pan_orig_name = $_FILES['pan_file']['name'];
    $ext = strtolower(pathinfo($pan_orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_pan_ext)) {
        die("Invalid PAN file type. Only PDF, JPG or PNG are allowed.");
    }
    if ($_FILES['pan_file']['size'] > 2 * 1024 * 1024) {
        die("PAN file too large. Maximum size is 2MB.");
    }
    try {
        $pan_filename = uploadToCloudinary($_FILES['pan_file']['tmp_name'], 'pan', false, $pan_orig_name);
    } catch (Exception $e) {
        die("PAN upload failed: " . $e->getMessage());
    }
} elseif (!empty($_POST['existing_pan'])) {
    $pan_filename = mysqli_real_escape_string($conn, $_POST['existing_pan']);
}

// ── Update student profile ──
$update_profile_sql = "UPDATE student_profiles SET
    full_name      = '$full_name',
    email          = '$email',
    phone          = '$phone',
    skills         = '$skills',
    aadhaar_number = '$aadhaar_number',
    pan_number     = '$pan_number',
    hod_name       = '$hod_name',
    hod_phone      = '$hod_phone',
    hod_email      = '$hod_email'
    " . ($resume_filename ? ", resume_file = '$resume_filename', resume_url = '$resume_filename', resume_original_name = '" . mysqli_real_escape_string($conn, $resume_orig_name) . "'" : "") . "
    " . ($pan_filename    ? ", pan_file    = '$pan_filename', pan_original_name = '" . mysqli_real_escape_string($conn, $pan_orig_name) . "'"    : "") . "
    WHERE id = '$profile_id' AND user_id = '$user_id'";
mysqli_query($conn, $update_profile_sql);


// ── Insert application ──
$insert_sql = "INSERT INTO internship_applications (
    user_id, internship_id, profile_id, internship_name, applied_subtype,
    status,
    education_status,
    college_name, department, year_of_study, hod_name, hod_email,
    graduation_year, prev_college_name,
    aadhaar_number, pan_number, pan_masked, pan_file, resume_file,
    aadhaar_card_file, resume_original_name, pan_original_name, aadhaar_original_name
) VALUES (
    '$user_id', '$internship_id', '$profile_id', '$internship_name', '$applied_subtype',
    '$app_status',
    '$education_status',
    '$college_name', '$department', '$year_of_study', '$hod_name', '$hod_email',
    '$graduation_year', '$prev_college',
    '$aadhaar_number', '$pan_number', '$pan_masked', '$pan_filename', '$resume_filename',
    '" . mysqli_real_escape_string($conn, $aadhaar_card_file) . "',
    '" . mysqli_real_escape_string($conn, $resume_orig_name) . "',
    '" . mysqli_real_escape_string($conn, $pan_orig_name) . "',
    '" . mysqli_real_escape_string($conn, $aadhaar_orig_name) . "'
)";

if (mysqli_query($conn, $insert_sql)) {

    $app_id_inserted = mysqli_insert_id($conn);
    
    // Fetch details for notifications
    $fetch_sql = "SELECT a.id AS application_id, u.full_name AS student_name, u.email AS student_email, jp.project_type, COALESCE(jp.project_subtype, a.project_subtype, a.applied_subtype, 'Not specified') AS applied_subtype, jp.duration, jp.mode FROM internship_applications a JOIN users u ON u.id = a.user_id LEFT JOIN internships jp ON jp.id = a.internship_id WHERE a.id = ?";
    $stmt = $conn->prepare($fetch_sql);
    $stmt->bind_param("i", $app_id_inserted);
    $stmt->execute();
    $res = $stmt->get_result();
    $app_data = $res->fetch_assoc();
    $stmt->close();
    
    $applied_subtype = $app_data['applied_subtype'] ?? 'Not specified';
    $project_type    = $app_data['project_type'] ?? 'Not specified';
    $duration        = $app_data['duration'] ?? 'Not specified';
    $mode            = $app_data['mode'] ?? 'Not specified';

    $shared_metadata = [
        'event'              => 'New Internship Application',
        'student_name'       => $full_name,
        'applied_internship' => $applied_subtype,
        'project_type'       => $project_type,
        'duration'           => $duration,
        'mode'               => $mode
    ];

    // ── Notify student ──
    $notif_title = 'Application Submitted';
    $notif_msg = "Your application for '$applied_subtype' has been submitted. Status: $app_status.";
    notifyUser($user_id, 'student', $email, $notif_title, $notif_msg, [
        'event' => 'Application Submitted',
        'applied_internship' => $applied_subtype,
        'current_status' => $app_status,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ], 'application');

    // ── Notify HR ──
    $hr_res = mysqli_query($conn, "SELECT id, email FROM users WHERE LOWER(role) = 'hr'");
    if ($hr_res) {
        $hr_title = 'New Internship Application Submitted';
        $hr_msg = "New application received from $full_name for '$applied_subtype'.";
        while ($hr_row = mysqli_fetch_assoc($hr_res)) {
            $hr_id = intval($hr_row['id']);
            $hr_email = trim($hr_row['email']);
            
            $hr_metadata = $shared_metadata;
            $hr_metadata['action_url'] = 'http://localhost/IMP/hr_applications.php';
            $hr_metadata['action_label'] = 'Review Application';
            
            notifyUser($hr_id, 'hr', $hr_email, $hr_title, $hr_msg, $hr_metadata, 'new_application');
        }
    }

    // ── Notify coordinators ──
    $coord_res = mysqli_query($conn, "SELECT id, email, full_name FROM users WHERE LOWER(role) = 'coordinator'");
    if ($coord_res) {
        $c_title = 'New Student Application';
        $c_msg = "New application received from $full_name for '$applied_subtype'.";
        while ($c_row = mysqli_fetch_assoc($coord_res)) {
            $coord_id = intval($c_row['id']);
            $coord_email = trim($c_row['email']);
            
            $coord_metadata = $shared_metadata;
            $coord_metadata['action_url'] = 'http://localhost/IMP/coordinator_applications.php';
            $coord_metadata['action_label'] = 'Review Application';
            
            notifyUser($coord_id, 'coordinator', $coord_email, $c_title, $c_msg, $coord_metadata, 'new_application');
        }
    }

    // ── Notify admins of a new application as well
    $admin_res = mysqli_query($conn, "SELECT id, email FROM users WHERE LOWER(role) = 'admin'");
    if ($admin_res) {
        $admin_title = 'New Internship Application Submitted';
        $admin_msg = "A new application from $full_name has been submitted for '$applied_subtype'.";
        while ($admin_row = mysqli_fetch_assoc($admin_res)) {
            $admin_id = intval($admin_row['id']);
            $admin_email = trim($admin_row['email']);
            
            $admin_metadata = $shared_metadata;
            $admin_metadata['action_url'] = 'http://localhost/IMP/admin_applications.php';
            $admin_metadata['action_label'] = 'View Applications';
            
            notifyUser($admin_id, 'admin', $admin_email, $admin_title, $admin_msg, $admin_metadata, 'new_application');
        }
    }

    // Send email notification for internship application
    $app_subject = "IMP Application Submitted: $applied_subtype";
    $app_message = "Dear " . $_POST['full_name'] . ",\n\nYour application for the \"$applied_subtype\" internship has been successfully submitted to the platform.\n\nYour current application status is: **$app_status**.\n\nPlease remember that you are required to complete your skills assessment test (if applicable) within 48 hours of application submission.\n\nThank you for choosing IMP!";
    sendStudentNotification($user_id, $_POST['full_name'] ?? '', $app_subject, $app_message, [
        'event' => 'Internship Application',
        'applied_internship' => $applied_subtype,
        'education_status' => $education_status,
        'current_status' => $app_status,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ]);

    header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully!"));
    exit();

} else {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-slate-50 flex items-center justify-center min-h-screen font-sans'>
    <div class='bg-white rounded-2xl shadow-lg p-10 max-w-md text-center border border-red-100'>
        <div class='w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4'>
            <svg class='w-8 h-8 text-red-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'/>
            </svg>
        </div>
        <h2 class='text-xl font-bold text-slate-800 mb-2'>Submission Failed</h2>
        <p class='text-slate-500 text-sm mb-1'>There was a database error. Please try again.</p>
        <p class='text-xs text-red-400 mb-6'>" . htmlspecialchars(mysqli_error($conn)) . "</p>
        <a href='javascript:history.back()' class='px-6 py-2.5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-colors'>Go Back</a>
    </div></body></html>";
}
