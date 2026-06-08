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

// ── Prevent Duplicate Applications ──
$dup_sql = "SELECT id FROM internship_applications WHERE user_id = '$user_id' AND internship_id = '$internship_id' AND (internship_id > 0 OR internship_name = '" . mysqli_real_escape_string($conn, $internship_name) . "') LIMIT 1";
$dup_result = mysqli_query($conn, $dup_sql);
if (mysqli_num_rows($dup_result) > 0) {
    header("Location: student_dashboard.php?error=" . urlencode("You have already applied for this internship."));
    exit();
}

// ── Workflow status ──
// Simplified workflow: Applied → Test Completed → HR Round → (Pursuing: HOD Approved) → Selected/Rejected
$app_status  = 'Applied';
$test_status = 'Pending';

// ── Handle resume upload ──
$resume_filename = '';
if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
    $allowed_ext = ['pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        die("Invalid resume file type. Only PDF and DOC/DOCX are allowed.");
    }
    if ($_FILES['resume_file']['size'] > 5 * 1024 * 1024) {
        die("Resume file too large. Maximum size is 5MB.");
    }
    try {
        $resume_filename = uploadToCloudinary($_FILES['resume_file']['tmp_name'], 'student_resumes', true);
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
    $ext = strtolower(pathinfo($_FILES['pan_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_pan_ext)) {
        die("Invalid PAN file type. Only PDF, JPG or PNG are allowed.");
    }
    if ($_FILES['pan_file']['size'] > 2 * 1024 * 1024) {
        die("PAN file too large. Maximum size is 2MB.");
    }
    try {
        $pan_filename = uploadToCloudinary($_FILES['pan_file']['tmp_name'], 'pan', false);
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
    " . ($resume_filename ? ", resume_file = '$resume_filename', resume_url = '$resume_filename'" : "") . "
    " . ($pan_filename    ? ", pan_file    = '$pan_filename'"    : "") . "
    WHERE id = '$profile_id' AND user_id = '$user_id'";
mysqli_query($conn, $update_profile_sql);


// ── Insert application ──
$insert_sql = "INSERT INTO internship_applications (
    user_id, internship_id, profile_id, internship_name,
    status, test_status,
    education_status,
    college_name, department, year_of_study, hod_name, hod_email,
    graduation_year, prev_college_name,
    aadhaar_number, pan_number, pan_masked, pan_file, resume_file
) VALUES (
    '$user_id', '$internship_id', '$profile_id', '$internship_name',
    '$app_status', '$test_status',
    '$education_status',
    '$college_name', '$department', '$year_of_study', '$hod_name', '$hod_email',
    '$graduation_year', '$prev_college',
    '$aadhaar_number', '$pan_number', '$pan_masked', '$pan_filename', '$resume_filename'
)";

if (mysqli_query($conn, $insert_sql)) {

    // ── Notify student ──
    $notif_title = 'Application Submitted';
    $notif_msg = "Your application for '$internship_name' has been submitted. Status: $app_status.";
    notifyUser($user_id, 'student', $email, $notif_title, $notif_msg, [
        'event' => 'Application Submitted',
        'internship_title' => $internship_name,
        'current_status' => $app_status,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ], 'application');

    // ── Notify coordinators ──
    $coord_res = mysqli_query($conn, "SELECT id, email, full_name FROM users WHERE LOWER(role) = 'coordinator'");
    if ($coord_res) {
        $c_title = 'New Student Application';
        $c_msg = "New application received from $full_name for '$internship_name'.";
        while ($c_row = mysqli_fetch_assoc($coord_res)) {
            $coord_id = intval($c_row['id']);
            $coord_email = trim($c_row['email']);
            notifyUser($coord_id, 'coordinator', $coord_email, $c_title, $c_msg, [
                'event' => 'New Internship Application',
                'student_name' => $full_name,
                'internship_title' => $internship_name,
                'action_url' => 'http://localhost/IMP/coordinator_applications.php',
                'action_label' => 'Review Application'
            ], 'new_application');
        }
    }

    // ── Notify admins of a new application as well
    $admin_res = mysqli_query($conn, "SELECT id, email FROM users WHERE LOWER(role) = 'admin'");
    if ($admin_res) {
        $admin_title = 'New Internship Application Submitted';
        $admin_msg = "A new application from $full_name has been submitted for '$internship_name'.";
        while ($admin_row = mysqli_fetch_assoc($admin_res)) {
            $admin_id = intval($admin_row['id']);
            $admin_email = trim($admin_row['email']);
            notifyUser($admin_id, 'admin', $admin_email, $admin_title, $admin_msg, [
                'event' => 'New Application Received',
                'student_name' => $full_name,
                'internship_title' => $internship_name,
                'action_url' => 'http://localhost/IMP/admin_applications.php',
                'action_label' => 'View Applications'
            ], 'new_application');
        }
    }

    // Send email notification for internship application
    $app_subject = "IMP Application Submitted: $internship_name";
    $app_message = "Dear " . $_POST['full_name'] . ",\n\nYour application for the \"$internship_name\" internship has been successfully submitted to the platform.\n\nYour current application status is: **$app_status**.\n\nPlease remember that you are required to complete your skills assessment test (if applicable) within 48 hours of application submission.\n\nThank you for choosing IMP!";
    sendStudentNotification($user_id, $_POST['full_name'] ?? '', $app_subject, $app_message, [
        'event' => 'Internship Application',
        'internship_position' => $internship_name,
        'education_status' => $education_status,
        'current_status' => $app_status,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ]);

    // Send exam invitation email only if a test is required
    if ($test_status !== 'N/A') {
        $exam_link = "https://internship-management-platform-1.onrender.com/student_test.php?application_id=" . mysqli_insert_id($conn);
        $exam_subject = "Exam Invitation for $internship_name";
        $exam_message = "Dear $full_name,\n\nYou are invited to take the skills assessment test for the '$internship_name' internship. Please complete the exam using the following link (valid for 48 hours): $exam_link\n\nBest regards,\nInternship Management Platform Team";
        sendEmailNotification($user_id, $exam_subject, $exam_message, [
            'event' => 'Exam Invitation',
            'action_url' => $exam_link,
            'action_label' => 'Take Exam',
        ]);
    }
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
