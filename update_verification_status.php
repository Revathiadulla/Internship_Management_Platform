<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin']);
include "db.php";
include "includes/mail_helper.php";

header('Content-Type: application/json');

$user_id = current_user_id();

$app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$verification_status = isset($_POST['verification_status']) ? trim($_POST['verification_status']) : '';
$verification_type = isset($_POST['verification_type']) ? trim($_POST['verification_type']) : 'all'; // 'aadhaar', 'pan', 'all'

$allowed_verification = ['Pending', 'Verified', 'Rejected'];

if ($app_id <= 0 || empty($verification_status) || !in_array($verification_status, $allowed_verification)) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID or verification status']);
    exit();
}

// Fetch current application
$app_sql = "SELECT id, verification_status, user_id, education_status, status FROM internship_applications WHERE id = $app_id AND is_deleted = 0 LIMIT 1";
$app_result = mysqli_query($conn, $app_sql);
if (!$app_result || mysqli_num_rows($app_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}
$app_row = mysqli_fetch_assoc($app_result);
$prev_status = $app_row['verification_status'];
$student_id = $app_row['user_id'];
$education_status = $app_row['education_status'];
$current_app_status = $app_row['status'];

$verification_status_escaped = mysqli_real_escape_string($conn, $verification_status);

// Prepare queries
if ($verification_type === 'aadhaar') {
    $update_app = "UPDATE internship_applications SET aadhaar_verification_status = '$verification_status_escaped' WHERE id = $app_id";
    $update_profile = "UPDATE student_profiles SET aadhaar_verification_status = '$verification_status_escaped' WHERE user_id = $student_id";
    mysqli_query($conn, $update_app);
    mysqli_query($conn, $update_profile);
    $msg = "Aadhaar verification status updated to $verification_status";
} elseif ($verification_type === 'pan') {
    $update_app = "UPDATE internship_applications SET pan_verification_status = '$verification_status_escaped' WHERE id = $app_id";
    $update_profile = "UPDATE student_profiles SET pan_verification_status = '$verification_status_escaped' WHERE user_id = $student_id";
    mysqli_query($conn, $update_app);
    mysqli_query($conn, $update_profile);
    $msg = "PAN verification status updated to $verification_status";
} else {
    // 'all' or default
    $update_app = "UPDATE internship_applications SET 
                    aadhaar_verification_status = '$verification_status_escaped',
                    pan_verification_status = '$verification_status_escaped',
                    verification_status = '$verification_status_escaped'
                   WHERE id = $app_id";
    $update_profile = "UPDATE student_profiles SET 
                        aadhaar_verification_status = '$verification_status_escaped',
                        pan_verification_status = '$verification_status_escaped',
                        verification_status = '$verification_status_escaped'
                       WHERE user_id = $student_id";
    mysqli_query($conn, $update_app);
    mysqli_query($conn, $update_profile);
    $msg = "Documents verification status updated to $verification_status";
}

// Check if BOTH are verified, and if so, auto-update the overall verification status to Verified and application status to "Documents Verified"
$check_both = mysqli_query($conn, "SELECT aadhaar_verification_status, pan_verification_status FROM internship_applications WHERE id = $app_id");
$both = mysqli_fetch_assoc($check_both);
if ($both['aadhaar_verification_status'] === 'Verified' && $both['pan_verification_status'] === 'Verified') {
    mysqli_query($conn, "UPDATE internship_applications SET verification_status = 'Verified' WHERE id = $app_id");
    mysqli_query($conn, "UPDATE student_profiles SET verification_status = 'Verified' WHERE user_id = $student_id");
    
    if ($current_app_status === 'Test Completed' || $current_app_status === 'Applied') {
        mysqli_query($conn, "UPDATE internship_applications SET status = 'Documents Verified' WHERE id = $app_id");
        // Log in status history
        mysqli_query($conn, "INSERT INTO application_status_history (application_id, status, changed_by, notes) VALUES ($app_id, 'Documents Verified', $user_id, 'Documents verified by HR')");
    }
} elseif ($verification_status === 'Verified' && $verification_type === 'all') {
    if ($current_app_status === 'Test Completed' || $current_app_status === 'Applied') {
        mysqli_query($conn, "UPDATE internship_applications SET status = 'Documents Verified' WHERE id = $app_id");
        // Log in status history
        mysqli_query($conn, "INSERT INTO application_status_history (application_id, status, changed_by, notes) VALUES ($app_id, 'Documents Verified', $user_id, 'Documents verified by HR')");
    }
}

// Insert audit log for verification status change
$audit_sql = "INSERT INTO verification_audit (application_id, previous_status, new_status, changed_by, changed_at) VALUES ($app_id, '$prev_status', '$verification_status', $user_id, NOW())";
mysqli_query($conn, $audit_sql);

// Notify student
$verif_type_map = [
    'Verified'  => 'verification',
    'Pending'   => 'verification',
    'Rejected'  => 'rejected',
];
$notif_type  = $verif_type_map[$verification_status] ?? 'verification';
$notif_title = mysqli_real_escape_string($conn, "Document Verification: $verification_status");
$notif_msg   = mysqli_real_escape_string($conn, "Your document verification status has been updated to \"$verification_status\" ($verification_type).");

mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, title, message) VALUES ($student_id, '$notif_type', '$notif_title', '$notif_msg')");

echo json_encode(['success' => true, 'message' => $msg]);
exit();
