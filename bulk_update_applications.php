<?php
/**
 * bulk_update_applications.php
 * Endpoint to handle bulk verification status updates for HR.
 * Expects POST parameters:
 *   - application_ids: array of integer IDs
 *   - verification_status: one of ['Pending', 'Verified', 'Rejected']
 *
 * Performs:
 *   1. Validate input and permissions (HR role).
 *   2. Update internship_applications.verification_status for each ID.
 *   3. Insert notifications into student_notifications.
 *   4. Send email notification to each affected student via sendEmailNotification.
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin']);
require_once __DIR__ . '/db.php';
include_once __DIR__ . '/includes/mail_helper.php'; // provides sendEmailNotification

header('Content-Type: application/json');

// Helper to send JSON response and exit
function json_response($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Validate POST data
if (!isset($_POST['application_ids']) || !is_array($_POST['application_ids']) || empty($_POST['application_ids'])) {
    json_response(false, 'No application IDs provided');
}
if (!isset($_POST['verification_status'])) {
    json_response(false, 'Verification status not provided');
}
$allowed_status = ['Pending', 'Verified', 'Rejected'];
$verification_status = trim($_POST['verification_status']);
if (!in_array($verification_status, $allowed_status, true)) {
    json_response(false, 'Invalid verification status');
}

$app_ids = array_map('intval', $_POST['application_ids']);
$placeholders = implode(',', array_fill(0, count($app_ids), '?'));

// Begin transaction
mysqli_autocommit($conn, false);
$all_success = true;
$failed_ids = [];

// Prepare update statement
$update_stmt = mysqli_prepare($conn, "UPDATE internship_applications SET verification_status = ? WHERE id = ? AND is_deleted = 0");
if (!$update_stmt) {
    json_response(false, 'Failed to prepare statement: ' . mysqli_error($conn));
}

foreach ($app_ids as $app_id) {
    // Update status
    mysqli_stmt_bind_param($update_stmt, 'si', $verification_status, $app_id);
    if (!mysqli_stmt_execute($update_stmt) || mysqli_stmt_affected_rows($update_stmt) === 0) {
        $all_success = false;
        $failed_ids[] = $app_id;
        continue;
    }
    // Fetch student user_id for notification/email
    $select_res = mysqli_query($conn, "SELECT user_id FROM internship_applications WHERE id = $app_id");
    $user_id = null;
    if ($select_res && $row = mysqli_fetch_assoc($select_res)) {
        $user_id = (int)$row['user_id'];
    }
    // Insert into student_notifications table
    $notif_type = ($verification_status === 'Rejected') ? 'rejected' : 'verification';
    $notif_title = mysqli_real_escape_string($conn, "Document Verification: $verification_status");
    $notif_msg = mysqli_real_escape_string($conn, "Your document verification status has been updated to \"$verification_status\".");
    $insert_sql = "INSERT INTO student_notifications (user_id, type, title, message) VALUES ($user_id, '$notif_type', '$notif_title', '$notif_msg')";
    if (!mysqli_query($conn, $insert_sql)) {
        $all_success = false;
        $failed_ids[] = $app_id;
        continue;
    }
    // Send email notification via student helper
    $student_name = '';
    $user_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $user_id LIMIT 1");
    if ($user_res && $user_row = mysqli_fetch_assoc($user_res)) {
        $student_name = $user_row['full_name'];
    }
    $email_sent = sendStudentNotification($user_id, $student_name, $notif_title, $notif_msg, [
        'event' => 'Verification Status Update',
        'verification_status' => $verification_status,
        'action_url' => 'http://localhost/IMP/student_dashboard.php',
        'action_label' => 'View Dashboard'
    ]);
    if (!$email_sent) {
        // Log failure but do not mark whole batch as failed
        error_log("Failed to send verification email for application $app_id");
    }
}

mysqli_stmt_close($update_stmt);

if ($all_success) {
    mysqli_commit($conn);
    json_response(true, 'All selected applications updated successfully');
} else {
    mysqli_rollback($conn);
    $failed_list = implode(', ', $failed_ids);
    json_response(false, "Failed to update some applications: $failed_list");
}
?>
