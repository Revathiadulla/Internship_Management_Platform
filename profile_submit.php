<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
include "db.php";
require_once __DIR__ . "/includes/crypto_helper.php";

// CSRF & Rate Limit Checks
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    show_upload_error("Security token validation failed (CSRF check failed).");
}
if (!check_rate_limit('profile_submit', 10, 60)) {
    http_response_code(429);
    show_upload_error("Too many profile update requests. Please wait a minute and try again.");
}

$user_id = current_user_id();

// Inputs
$full_name      = trim($_POST['full_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$dob            = trim($_POST['dob'] ?? '');
$gender         = trim($_POST['gender'] ?? '');
$college_name   = trim($_POST['college_name'] ?? '');
$course         = trim($_POST['course'] ?? '');
$year_of_study  = trim($_POST['year_of_study'] ?? '');
$skills         = trim($_POST['skills'] ?? '');
$aadhaar_number = preg_replace('/\s+/', '', $_POST['aadhaar_number'] ?? '');
$aadhaar_number = encrypt_aadhaar($aadhaar_number);
$pan_number     = strtoupper(trim($_POST['pan_number'] ?? ''));

$folder = "uploads/";
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
    // Create an .htaccess file to restrict executable files
    $htaccess = "# Disable script execution in this upload folder for security\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|pl|py|jsp|asp|sh|cgi)$\">\n    ForceType text/plain\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes";
    file_put_contents($folder . ".htaccess", $htaccess);
}

// Fetch existing filenames in case new files aren't uploaded
$resume_file = '';
$aadhaar_file = '';
$pan_file = '';

$check_stmt = $conn->prepare("SELECT id, resume_file, aadhaar_file, pan_file FROM student_profiles WHERE user_id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$profile_exists = ($check_result->num_rows > 0);
if ($profile_exists) {
    $existing = $check_result->fetch_assoc();
    $resume_file = $existing['resume_file'];
    $aadhaar_file = $existing['aadhaar_file'];
    $pan_file = $existing['pan_file'];
}
$check_stmt->close();

function show_upload_error($message) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-slate-50 flex items-center justify-center min-h-screen font-sans'>
    <div class='bg-white rounded-2xl shadow-lg p-10 max-w-md text-center border border-red-100'>
        <div class='w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4'>
            <svg class='w-8 h-8 text-red-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
            </svg>
        </div>
        <h2 class='text-xl font-bold text-slate-800 mb-2'>Upload Failed</h2>
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

// Upload resume
$res = validate_and_upload($_FILES['resume'] ?? null, $resume_exts, $resume_mimes, 5 * 1024 * 1024, $folder, 'resume', $user_id);
if ($res['status'] === 'error') {
    show_upload_error("Resume upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $resume_file = $res['filename'];
}

// Upload Aadhaar
$res = validate_and_upload($_FILES['aadhaar_file'] ?? null, $identity_exts, $identity_mimes, 5 * 1024 * 1024, $folder, 'aadhaar', $user_id);
if ($res['status'] === 'error') {
    show_upload_error("Aadhaar upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $aadhaar_file = $res['filename'];
}

// Upload PAN
$res = validate_and_upload($_FILES['pan_file'] ?? null, $identity_exts, $identity_mimes, 2 * 1024 * 1024, $folder, 'pan', $user_id);
if ($res['status'] === 'error') {
    show_upload_error("PAN upload error: " . $res['message']);
} elseif ($res['status'] === 'success') {
    $pan_file = $res['filename'];
}

if ($profile_exists) {
    // Update using prepared statements
    $stmt = $conn->prepare("UPDATE student_profiles SET 
            full_name = ?, email = ?, phone = ?, dob = ?, gender = ?,
            college_name = ?, course = ?, year_of_study = ?, skills = ?,
            resume_file = ?, aadhaar_number = ?, pan_number = ?,
            aadhaar_file = ?, pan_file = ?
            WHERE user_id = ?");
    $stmt->bind_param("ssssssssssssssi", 
        $full_name, $email, $phone, $dob, $gender, 
        $college_name, $course, $year_of_study, $skills, 
        $resume_file, $aadhaar_number, $pan_number, 
        $aadhaar_file, $pan_file, $user_id
    );
} else {
    // Insert using prepared statements
    $stmt = $conn->prepare("INSERT INTO student_profiles 
            (user_id, full_name, email, phone, dob, gender, college_name, course, year_of_study, skills, resume_file, aadhaar_number, pan_number, aadhaar_file, pan_file) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssssss", 
        $user_id, $full_name, $email, $phone, $dob, $gender, 
        $college_name, $course, $year_of_study, $skills, 
        $resume_file, $aadhaar_number, $pan_number, 
        $aadhaar_file, $pan_file
    );
}

if ($stmt->execute()) {
    $stmt->close();
    // Log Activity
    log_activity($conn, 'Profile Update', 'Student completed/updated profile details.');
    header("Location: student_dashboard.php?msg=" . urlencode("Profile Saved Successfully"));
    exit();
} else {
    $err = $stmt->error;
    $stmt->close();
    show_upload_error("Database Error: " . $err);
}
?>
