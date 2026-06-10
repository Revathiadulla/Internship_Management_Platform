<?php
// ─── Output buffering MUST be first to prevent "headers already sent" ────────
ob_start();
session_start();
include "db.php";

// ─── Auth guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

// ─── Only accept POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header("Location: student_profile_form.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// ─── Helper: safe redirect ───────────────────────────────────────────────────
function safe_redirect($url) {
    ob_end_clean();
    header("Location: " . $url);
    exit();
}

// ─── Collect & sanitize text fields ─────────────────────────────────────────
function sp($conn, $key, $default = '') {
    return isset($_POST[$key]) ? mysqli_real_escape_string($conn, trim($_POST[$key])) : $default;
}

// Build full_name: prefer posted full_name; fall back to first+last; then session
$first_name = sp($conn, 'first_name');
$last_name  = sp($conn, 'last_name');
$full_name  = sp($conn, 'full_name');
if (empty($full_name)) {
    $full_name = trim($first_name . ' ' . $last_name);
}
if (empty($full_name) && !empty($_SESSION['full_name'])) {
    $full_name = mysqli_real_escape_string($conn, $_SESSION['full_name']);
}

$email          = sp($conn, 'email',          isset($_SESSION['email']) ? $_SESSION['email'] : '');
$phone          = sp($conn, 'phone');
$dob            = sp($conn, 'dob');
$gender         = sp($conn, 'gender');
$college_name   = sp($conn, 'college_name');
$course         = sp($conn, 'course');
$year_of_study  = sp($conn, 'year_of_study'); // filled by JS hidden field
$skills         = sp($conn, 'skills');
$aadhaar_number = sp($conn, 'aadhaar_number');
$pan_number     = sp($conn, 'pan_number');
$hod_name       = sp($conn, 'hod_name');
$hod_phone      = sp($conn, 'hod_phone');
$hod_email      = sp($conn, 'hod_email');

$education_status = sp($conn, 'education_status') ?: null;
$department       = sp($conn, 'department')       ?: null;
$graduation_year  = sp($conn, 'graduation_year')  ?: null;
$previous_college = sp($conn, 'previous_college') ?: null;

// ─── Auto-migrate student_profiles table (idempotent) ────────────────────────
$required_cols = [
    'education_status' => "VARCHAR(50)  NULL",
    'department'       => "VARCHAR(100) NULL",
    'hod_name'         => "VARCHAR(100) NULL",
    'hod_phone'        => "VARCHAR(20)  NULL",
    'hod_email'        => "VARCHAR(100) NULL",
    'graduation_year'  => "VARCHAR(10)  NULL",
    'previous_college' => "VARCHAR(255) NULL",
    'resume_original_name'  => "VARCHAR(255) NULL",
    'aadhaar_original_name' => "VARCHAR(255) NULL",
    'pan_original_name'     => "VARCHAR(255) NULL",
    'resume_url'       => "VARCHAR(500) NULL",
];
foreach ($required_cols as $col => $def) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if ($col_check && mysqli_num_rows($col_check) === 0) {
        mysqli_query($conn, "ALTER TABLE student_profiles ADD COLUMN $col $def");
    }
}

// ─── Fetch existing profile to preserve old file data ────────────────────────
$check_res       = mysqli_query($conn, "SELECT * FROM student_profiles WHERE user_id = $user_id LIMIT 1");
$existing        = mysqli_fetch_assoc($check_res);

$new_resume      = $existing['resume_file']          ?? '';
$new_aadhaar     = $existing['aadhaar_file']         ?? '';
$new_pan         = $existing['pan_file']             ?? '';
$resume_url      = $existing['resume_url']           ?? '';
$resume_orig     = $existing['resume_original_name'] ?? '';
$aadhaar_orig    = $existing['aadhaar_original_name']?? '';
$pan_orig        = $existing['pan_original_name']    ?? '';

// ─── Determine whether Cloudinary is available ──────────────────────────────
$cloudinary_ok = false;
$cloud_name_env = getenv('CLOUDINARY_CLOUD_NAME');
$api_key_env    = getenv('CLOUDINARY_API_KEY');
$api_secret_env = getenv('CLOUDINARY_API_SECRET');
if (!empty($cloud_name_env) && !empty($api_key_env) && !empty($api_secret_env)) {
    $cloudinary_ok = true;
}

// ─── Upload helper: Cloudinary or local filesystem ──────────────────────────
function handle_file_upload($file_key, $allowed_exts, $local_subfolder, $cloudinary_folder, $is_raw) {
    global $cloudinary_ok;

    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null; // no file uploaded – caller keeps existing value
    }

    $original_name = $_FILES[$file_key]['name'];
    $tmp_path      = $_FILES[$file_key]['tmp_name'];
    $size          = $_FILES[$file_key]['size'];
    $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    // Validate extension
    if (!in_array($ext, $allowed_exts)) {
        return ['error' => "Invalid file format for $file_key. Allowed: " . implode(', ', array_map('strtoupper', $allowed_exts)) . "."];
    }
    // Validate size (5 MB)
    if ($size > 5 * 1024 * 1024) {
        return ['error' => "File size exceeds 5 MB limit for $file_key."];
    }

    // ── Try Cloudinary first ─────────────────────────────────────────────────
    if ($cloudinary_ok) {
        try {
            require_once __DIR__ . "/includes/cloudinary_config.php";
            $url = uploadToCloudinary($tmp_path, $cloudinary_folder, $is_raw, $original_name);
            return ['url' => $url, 'original_name' => $original_name];
        } catch (Exception $e) {
            // Cloudinary failed – fall through to local storage
            error_log("Cloudinary upload failed for $file_key: " . $e->getMessage());
        }
    }

    // ── Local filesystem fallback ────────────────────────────────────────────
    $upload_dir = __DIR__ . '/uploads/' . $local_subfolder . '/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    if (!is_writable($upload_dir)) {
        return ['error' => "Upload directory is not writable. Please contact the administrator."];
    }

    $safe_name  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    $dest       = $upload_dir . $safe_name;
    if (!move_uploaded_file($tmp_path, $dest)) {
        return ['error' => "Failed to save $file_key. Please try again."];
    }

    $relative_url = 'uploads/' . $local_subfolder . '/' . $safe_name;
    return ['url' => $relative_url, 'original_name' => $original_name];
}

// ─── Process Resume ──────────────────────────────────────────────────────────
$resume_result = handle_file_upload('resume', ['pdf', 'doc', 'docx'], 'resumes', 'student_resumes', true);
if ($resume_result !== null) {
    if (isset($resume_result['error'])) {
        safe_redirect("student_profile_form.php?error=" . urlencode($resume_result['error']));
    }
    $new_resume  = $resume_result['url'];
    $resume_url  = $resume_result['url'];
    $resume_orig = $resume_result['original_name'];
} else {
    // No new file – check if a manual URL was pasted
    if (!empty(trim($_POST['resume_url'] ?? ''))) {
        $resume_url  = mysqli_real_escape_string($conn, trim($_POST['resume_url']));
        $new_resume  = $resume_url;
        $resume_orig = $resume_orig ?: basename($resume_url);
    }
}

// ─── Process Aadhaar ─────────────────────────────────────────────────────────
$aadhaar_result = handle_file_upload('aadhaar_file', ['pdf', 'jpg', 'jpeg', 'png'], 'aadhaar', 'aadhaar', false);
if ($aadhaar_result !== null) {
    if (isset($aadhaar_result['error'])) {
        safe_redirect("student_profile_form.php?error=" . urlencode($aadhaar_result['error']));
    }
    $new_aadhaar  = $aadhaar_result['url'];
    $aadhaar_orig = $aadhaar_result['original_name'];
}

// ─── Process PAN ─────────────────────────────────────────────────────────────
$pan_result = handle_file_upload('pan_file', ['pdf', 'jpg', 'jpeg', 'png'], 'pan', 'pan', false);
if ($pan_result !== null) {
    if (isset($pan_result['error'])) {
        safe_redirect("student_profile_form.php?error=" . urlencode($pan_result['error']));
    }
    $new_pan  = $pan_result['url'];
    $pan_orig = $pan_result['original_name'];
}

// ─── Save / Update Profile ───────────────────────────────────────────────────
if ($existing) {
    // UPDATE
    $stmt = $conn->prepare(
        "UPDATE student_profiles
         SET full_name=?, email=?, phone=?, dob=?, gender=?,
             college_name=?, course=?, year_of_study=?, skills=?,
             resume_file=?, resume_url=?,
             aadhaar_number=?, pan_number=?,
             aadhaar_file=?, pan_file=?,
             hod_name=?, hod_phone=?, hod_email=?,
             education_status=?, department=?,
             graduation_year=?, previous_college=?,
             resume_original_name=?, aadhaar_original_name=?, pan_original_name=?
         WHERE user_id=?"
    );
    if (!$stmt) {
        safe_redirect("student_profile_form.php?error=" . urlencode("Database error: " . $conn->error));
    }
    $stmt->bind_param(
        "sssssssssssssssssssssssssi",
        $full_name, $email, $phone, $dob, $gender,
        $college_name, $course, $year_of_study, $skills,
        $new_resume, $resume_url,
        $aadhaar_number, $pan_number,
        $new_aadhaar, $new_pan,
        $hod_name, $hod_phone, $hod_email,
        $education_status, $department,
        $graduation_year, $previous_college,
        $resume_orig, $aadhaar_orig, $pan_orig,
        $user_id
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        safe_redirect("student_profile_form.php?error=" . urlencode("Failed to update profile: $err"));
    }
    $stmt->close();

} else {
    // INSERT
    $stmt = $conn->prepare(
        "INSERT INTO student_profiles
         (user_id, full_name, email, phone, dob, gender,
          college_name, course, year_of_study, skills,
          resume_file, resume_url,
          aadhaar_number, pan_number,
          aadhaar_file, pan_file,
          hod_name, hod_phone, hod_email,
          education_status, department,
          graduation_year, previous_college,
          resume_original_name, aadhaar_original_name, pan_original_name)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (!$stmt) {
        safe_redirect("student_profile_form.php?error=" . urlencode("Database error: " . $conn->error));
    }
    $stmt->bind_param(
        "isssssssssssssssssssssssss",
        $user_id,
        $full_name, $email, $phone, $dob, $gender,
        $college_name, $course, $year_of_study, $skills,
        $new_resume, $resume_url,
        $aadhaar_number, $pan_number,
        $new_aadhaar, $new_pan,
        $hod_name, $hod_phone, $hod_email,
        $education_status, $department,
        $graduation_year, $previous_college,
        $resume_orig, $aadhaar_orig, $pan_orig
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        safe_redirect("student_profile_form.php?error=" . urlencode("Failed to save profile: $err"));
    }
    $stmt->close();
}

// ─── SUCCESS → redirect to dashboard ─────────────────────────────────────────
safe_redirect("student_dashboard.php?success=Profile+saved+successfully");
