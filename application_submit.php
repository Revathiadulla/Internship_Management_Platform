<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
include "db.php";

$user_id = current_user_id();

$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$full_name      = trim($_POST['full_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$college_name   = trim($_POST['college_name'] ?? '');
$course         = trim($_POST['course'] ?? '');
$year_of_study  = trim($_POST['year_of_study'] ?? '');
$skills         = trim($_POST['skills'] ?? '');
$aadhaar_number = preg_replace('/\s+/', '', $_POST['aadhaar_number'] ?? '');

$folder = "uploads/";
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}

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
    
    if ($size > $max_size) {
        return ['status' => 'error', 'message' => 'File size exceeds limit of ' . ($max_size / (1024 * 1024)) . 'MB.'];
    }
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return ['status' => 'error', 'message' => 'Invalid file extension. Allowed: ' . implode(', ', $allowed_exts)];
    }
    
    if (!file_exists($tmp_name)) {
        return ['status' => 'error', 'message' => 'Uploaded file not found on server.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['status' => 'error', 'message' => 'Invalid file type (MIME mismatch: ' . $mime . ').'];
    }
    
    $unique_id = bin2hex(random_bytes(8));
    $safe_name = $prefix . '_' . $user_id . '_' . time() . '_' . $unique_id . '.' . $ext;
    
    if (move_uploaded_file($tmp_name, $folder . $safe_name)) {
        return ['status' => 'success', 'filename' => $safe_name];
    } else {
        return ['status' => 'error', 'message' => 'Failed to move uploaded file.'];
    }
}

$resume_exts = ['pdf', 'doc', 'docx'];
$resume_mimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip'
];

$new_resume = '';
$res = validate_and_upload($_FILES['resume'] ?? null, $resume_exts, $resume_mimes, 5 * 1024 * 1024, $folder, 'resume', $user_id);
if ($res['status'] === 'error') {
    show_submission_error("Resume upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $new_resume = $res['filename'];
}

// Check if applications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'applications'");
if ($table_check && mysqli_num_rows($table_check) === 0) {
    // Legacy applications table does not exist, use internship_applications instead
    // This maintains backward compatibility if someone hits this endpoint.
    $stmt = $conn->prepare("INSERT INTO internship_applications 
        (user_id, full_name, email, phone, college_name, course, year_of_study, skills, resume_file, aadhaar_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssssssss", $user_id, $full_name, $email, $phone, $college_name, $course, $year_of_study, $skills, $new_resume, $aadhaar_number);
        if ($stmt->execute()) {
            $stmt->close();
            log_activity($conn, 'Application Submission', "Student submitted application (legacy fallback).");
            header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully"));
            exit();
        } else {
            $err = $stmt->error;
            $stmt->close();
            show_submission_error("Database Error: " . $err);
        }
    } else {
        show_submission_error("Database Error: Failed to prepare statement.");
    }
} else {
    // If it does exist
    $stmt = $conn->prepare("INSERT INTO applications
        (user_id, first_name, last_name, full_name, email, phone, college_name, course, year_of_study, skills, resume_file, aadhaar_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssssssssss", $user_id, $first_name, $last_name, $full_name, $email, $phone, $college_name, $course, $year_of_study, $skills, $new_resume, $aadhaar_number);
        if ($stmt->execute()) {
            $stmt->close();
            log_activity($conn, 'Application Submission', "Student submitted application.");
            header("Location: student_dashboard.php?msg=" . urlencode("Application Submitted Successfully"));
            exit();
        } else {
            $err = $stmt->error;
            $stmt->close();
            show_submission_error("Database Error: " . $err);
        }
    } else {
        show_submission_error("Database Error: Failed to prepare statement.");
    }
}
?>
