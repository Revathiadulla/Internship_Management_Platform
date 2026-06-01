<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin']);
include "db.php";
include "includes/mail_helper.php";

header('Content-Type: application/json');

$user_id = current_user_id();
$user_role = current_user_role();

$app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$verification_status = isset($_POST['verification_status']) ? trim($_POST['verification_status']) : '';

$allowed_verification = ['Pending', 'Verified', 'Rejected'];

if ($app_id <= 0 || empty($verification_status) || !in_array($verification_status, $allowed_verification)) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID or verification status']);
    exit();
}

// Ensure application record exists
    // Ensure application record exists and fetch current verification status
    $app_sql = "SELECT id, verification_status, user_id FROM internship_applications WHERE id = $app_id AND is_deleted = 0 LIMIT 1";
    $app_result = mysqli_query($conn, $app_sql);
    if (!$app_result || mysqli_num_rows($app_result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }
    $app_row = mysqli_fetch_assoc($app_result);
    $prev_status = $app_row['verification_status'];
    $user_id = $app_row['user_id'];
$app_result = mysqli_query($conn, $app_sql);
if (!$app_result || mysqli_num_rows($app_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$verification_status_escaped = mysqli_real_escape_string($conn, $verification_status);
        // Insert audit log for verification status change
        $audit_sql = "INSERT INTO verification_audit (application_id, previous_status, new_status, changed_by, changed_at) VALUES ($app_id, '$prev_status', '$verification_status', $user_id, NOW())";
        if (!mysqli_query($conn, $audit_sql)) {
            // Log but continue
            error_log("Failed to insert verification audit for application $app_id: " . mysqli_error($conn));
        }
if (mysqli_query($conn, $update_sql)) {
    // Notify the student about verification status change
    $verif_type_map = [
        'Verified'  => 'verification',
        'Pending'   => 'verification',
        'Rejected'  => 'rejected',
    ];
    $notif_type  = $verif_type_map[$verification_status] ?? 'verification';
    $notif_title = mysqli_real_escape_string($conn, "Document Verification: $verification_status");
    $notif_msg   = mysqli_real_escape_string($conn, "Your document verification status has been updated to \"$verification_status\".");
    
    // Insert audit log for verification status change (bulk)
    $audit_sql = "INSERT INTO verification_audit (application_id, previous_status, new_status, changed_by, changed_at) VALUES ($app_id, '$prev_status', '$verification_status', $user_id, NOW())";
    if (!mysqli_query($conn, $audit_sql)) {
        error_log("Failed to insert verification audit for application $app_id: " . mysqli_error($conn));
    }
    
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, title, message)
                         SELECT user_id, '$notif_type', '$notif_title', '$notif_msg'
                         FROM internship_applications WHERE id = $app_id");

    echo json_encode(['success' => true, 'message' => "Verification status updated to $verification_status"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update verification status: ' . mysqli_error($conn)]);
}
