<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
include "db.php";
require_once __DIR__ . "/includes/crypto_helper.php";

// CSRF & Rate Limit Checks
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    show_submission_error("Security token validation failed (CSRF check failed).");
}
if (!check_rate_limit('internship_application_submit', 10, 60)) {
    http_response_code(429);
    show_submission_error("Too many application submission attempts. Please wait a minute and try again.");
}

include_once __DIR__ . "/includes/mail_helper.php";

$user_id = current_user_id();

// ── Common personal fields ──
$full_name      = trim($_POST['full_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$skills         = trim($_POST['skills'] ?? '');

// ── Aadhaar — strip spaces before storing ──
$aadhaar_number = preg_replace('/\s+/', '', $_POST['aadhaar_number'] ?? '');
$aadhaar_number = encrypt_aadhaar($aadhaar_number);

// ── PAN — uppercase, validate, then mask for display ──
$pan_raw    = strtoupper(trim($_POST['pan_number'] ?? ''));
$pan_valid  = preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_raw);
$pan_number = $pan_valid ? $pan_raw : '';
$pan_masked = $pan_valid ? substr($pan_raw, 0, 5) . '****' . substr($pan_raw, -1) : '';

// ── Application meta ──
$internship_id   = (int)($_POST['internship_id']   ?? 0);
$internship_name = trim($_POST['internship_name'] ?? '');
$profile_id      = (int)($_POST['profile_id']      ?? 0);

// ── Education status ──
$education_status = trim($_POST['education_status'] ?? '');

// ── Pursuing-specific ──
$college_name  = trim($_POST['college_name']  ?? '');
$department    = trim($_POST['department']    ?? '');
$year_of_study = trim($_POST['year_of_study'] ?? '');
$hod_name      = trim($_POST['hod_name']      ?? '');
$hod_email     = trim($_POST['hod_email']     ?? '');

// ── Passed-out-specific ──
$graduation_year = trim($_POST['graduation_year']   ?? '');
$prev_college    = trim($_POST['prev_college_name'] ?? '');

// ── Workflow status ──
$app_status  = 'Applied';
$test_status = 'Pending';

function show_submission_error($message) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-slate-50 flex items-center justify-center min-h-screen font-sans'>
    <div class='bg-white rounded-2xl shadow-lg p-10 max-w-md text-center border border-red-100'>
        <div class='w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4'>
            <svg class='w-8 h-8 text-red-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
            </svg>
        </div>
        <h2 class='text-xl font-bold text-slate-800 mb-2'>Submission Failed</h2>
        <p class='text-slate-500 text-sm mb-6'>" . htmlspecialchars($message) . "</p>
        <a href='javascript:history.back()' class='px-6 py-2.5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-colors'>Go Back</a>
    </div></body></html>";
    exit();
}

function validate_and_upload($file_array, $allowed_exts, $allowed_mimes, $max_size, $folder, $prefix, $user_id) {
    if (!isset($file_array) || $file_array['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => 'no_file'];
    }
    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'Upload error code: ' . $file_array['error']];
    }
    
    $filename = $file_array['name'];
    $tmp_name = $file_array['tmp_name'];
    $size = $file_array['size'];
    
    // 1. Check size
    if ($size > $max_size) {
        return ['status' => 'error', 'message' => 'File size exceeds limit of ' . ($max_size / (1024 * 1024)) . 'MB.'];
    }
    
    // 2. Check extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return ['status' => 'error', 'message' => 'Invalid file extension. Allowed: ' . implode(', ', $allowed_exts)];
    }
    
    // 3. Check MIME type
    if (!file_exists($tmp_name)) {
        return ['status' => 'error', 'message' => 'Uploaded file not found on server.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['status' => 'error', 'message' => 'Invalid file type (MIME mismatch: ' . $mime . ').'];
    }
    
    // 4. Generate safe unique filename
    $unique_id = bin2hex(random_bytes(8));
    $safe_name = $prefix . '_' . $user_id . '_' . time() . '_' . $unique_id . '.' . $ext;
    
    // 5. Move file
    if (move_uploaded_file($tmp_name, $folder . $safe_name)) {
        return ['status' => 'success', 'filename' => $safe_name];
    } else {
        return ['status' => 'error', 'message' => 'Failed to move uploaded file.'];
    }
}

$folder = "uploads/";
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}

// Setup validation rules
$resume_exts = ['pdf', 'doc', 'docx'];
$resume_mimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip'
];
$identity_exts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
$identity_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/pjpeg',
    'image/png',
    'image/webp'
];

// ── Handle resume upload ──
$resume_filename = '';
$res = validate_and_upload($_FILES['resume_file'] ?? null, $resume_exts, $resume_mimes, 5 * 1024 * 1024, $folder, 'resume', $user_id);
if ($res['status'] === 'error') {
    show_submission_error("Resume upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $resume_filename = $res['filename'];
} elseif (!empty($_POST['existing_resume'])) {
    $resume_filename = basename($_POST['existing_resume']);
}

// ── Handle PAN card upload ──
$pan_filename = '';
$res = validate_and_upload($_FILES['pan_file'] ?? null, $identity_exts, $identity_mimes, 2 * 1024 * 1024, $folder, 'pan', $user_id);
if ($res['status'] === 'error') {
    show_submission_error("PAN upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $pan_filename = $res['filename'];
} elseif (!empty($_POST['existing_pan'])) {
    $pan_filename = basename($_POST['existing_pan']);
}

// ── Update student profile using prepared statement ──
$update_profile_sql = "UPDATE student_profiles SET
    full_name      = ?,
    email          = ?,
    phone          = ?,
    skills         = ?,
    aadhaar_number = ?,
    pan_number     = ?";
$types = "ssssss";
$params = [&$full_name, &$email, &$phone, &$skills, &$aadhaar_number, &$pan_number];

if ($resume_filename) {
    $update_profile_sql .= ", resume_file = ?";
    $types .= "s";
    $params[] = &$resume_filename;
}
if ($pan_filename) {
    $update_profile_sql .= ", pan_file = ?";
    $types .= "s";
    $params[] = &$pan_filename;
}

$update_profile_sql .= " WHERE id = ? AND user_id = ?";
$types .= "ii";
$params[] = &$profile_id;
$params[] = &$user_id;

$stmt = $conn->prepare($update_profile_sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

// ── Insert application using prepared statement ──
$insert_sql = "INSERT INTO internship_applications (
    user_id, internship_id, profile_id, internship_name,
    status, test_status,
    education_status,
    college_name, department, year_of_study, hod_name, hod_email,
    graduation_year, prev_college_name,
    aadhaar_number, pan_number, pan_masked, pan_file, resume_file,
    full_name, email, phone, skills
) VALUES (
    ?, ?, ?, ?,
    ?, ?,
    ?,
    ?, ?, ?, ?, ?,
    ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?
)";

$stmt = $conn->prepare($insert_sql);
if ($stmt) {
    $stmt->bind_param("iiiisssssssssssssssssss",
        $user_id, $internship_id, $profile_id, $internship_name,
        $app_status, $test_status,
        $education_status,
        $college_name, $department, $year_of_study, $hod_name, $hod_email,
        $graduation_year, $prev_college,
        $aadhaar_number, $pan_number, $pan_masked, $pan_filename, $resume_filename,
        $full_name, $email, $phone, $skills
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // ── Notify student ──
        $notif_msg = "Your application for '" . $internship_name . "' has been submitted. Status: " . $app_status . ".";
        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, type, message) VALUES (?, 'application', ?)");
        if ($notif_stmt) {
            $notif_stmt->bind_param("is", $user_id, $notif_msg);
            $notif_stmt->execute();
            $notif_stmt->close();
        }

        // Send email notification for internship application
        $app_subject = "IMP Application Submitted: $internship_name";
        $app_message = "Dear " . $full_name . ",\n\nYour application for the \"$internship_name\" internship has been successfully submitted to the platform.\n\nYour current application status is: **$app_status**.\n\nPlease remember that you are required to complete your skills assessment test (if applicable) within 48 hours of application submission.\n\nThank you for choosing IMP!";
        sendEmailNotification($user_id, $app_subject, $app_message, [
            'event' => 'Internship Application',
            'internship_position' => $internship_name,
            'education_status' => $education_status,
            'current_status' => $app_status,
            'action_url' => 'http://localhost/IMP/student_applications.php',
            'action_label' => 'View Application Status'
        ]);

        // Log activity
        log_activity($conn, 'Application Submission', "Student submitted application for '$internship_name'.");

        header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully!"));
        exit();
    } else {
        $err = $stmt->error;
        $stmt->close();
        show_submission_error("Database Error: " . $err);
    }
} else {
    show_submission_error("Database Error: Failed to prepare statement.");
}
?>
