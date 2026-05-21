<?php
session_start();
include "db.php";

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
$hod_email     = mysqli_real_escape_string($conn, trim($_POST['hod_email']     ?? ''));

// ── Passed-out-specific ──
$graduation_year = mysqli_real_escape_string($conn, trim($_POST['graduation_year']   ?? ''));
$prev_college    = mysqli_real_escape_string($conn, trim($_POST['prev_college_name'] ?? ''));

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
    $safe_name   = 'resume_' . $user_id . '_' . time() . '.' . $ext;
    $upload_dir  = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (move_uploaded_file($_FILES['resume_file']['tmp_name'], $upload_dir . $safe_name)) {
        $resume_filename = $safe_name;
    }
} elseif (!empty($_POST['existing_resume'])) {
    $resume_filename = mysqli_real_escape_string($conn, basename($_POST['existing_resume']));
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
    $safe_name  = 'pan_' . $user_id . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (move_uploaded_file($_FILES['pan_file']['tmp_name'], $upload_dir . $safe_name)) {
        $pan_filename = $safe_name;
    }
} elseif (!empty($_POST['existing_pan'])) {
    $pan_filename = mysqli_real_escape_string($conn, basename($_POST['existing_pan']));
}

// ── Update student profile ──
$update_profile_sql = "UPDATE student_profiles SET
    full_name      = '$full_name',
    email          = '$email',
    phone          = '$phone',
    skills         = '$skills',
    aadhaar_number = '$aadhaar_number',
    pan_number     = '$pan_number'
    " . ($resume_filename ? ", resume_file = '$resume_filename'" : "") . "
    " . ($pan_filename    ? ", pan_file    = '$pan_filename'"    : "") . "
    WHERE id = '$profile_id' AND user_id = '$user_id'";
mysqli_query($conn, $update_profile_sql);

// ── Ensure new columns exist in internship_applications ──
// (db.php handles most, but pan_number / pan_masked may need adding)
$pan_cols = ['pan_number' => "VARCHAR(10) DEFAULT NULL", 'pan_masked' => "VARCHAR(15) DEFAULT NULL", 'pan_file' => "VARCHAR(255) DEFAULT NULL"];
foreach ($pan_cols as $col => $def) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $col $def");
    }
}

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
    $notif_msg = mysqli_real_escape_string($conn,
        "Your application for '$internship_name' has been submitted. Status: $app_status.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, message)
                         VALUES ('$user_id', 'application', '$notif_msg')");

    // Send email notification for internship application
    $app_subject = "IMP Application Submitted: $internship_name";
    $app_message = "Dear " . $_POST['full_name'] . ",\n\nYour application for the \"$internship_name\" internship has been successfully submitted to the platform.\n\nYour current application status is: **$app_status**.\n\nPlease remember that you are required to complete your skills assessment test (if applicable) within 48 hours of application submission.\n\nThank you for choosing IMP!";
    sendEmailNotification($user_id, $app_subject, $app_message, [
        'event' => 'Internship Application',
        'internship_position' => $internship_name,
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
?>
