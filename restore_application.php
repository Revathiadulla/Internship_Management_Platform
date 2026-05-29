<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin']);
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
header('Content-Type: application/json');

$app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
if ($app_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
    exit();
}

$app_sql = "SELECT a.id, a.user_id, a.status, a.verification_status, COALESCE(i.title, a.internship_name) AS internship_title, COALESCE(u.full_name, sp.full_name) AS student_name
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE a.id = $app_id AND a.is_deleted = 1
            LIMIT 1";
$app_result = mysqli_query($conn, $app_sql);

if (!$app_result || mysqli_num_rows($app_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Archived application not found.']);
    exit();
}

$app = mysqli_fetch_assoc($app_result);

$update_sql = "UPDATE internship_applications SET is_deleted = 0";
$deleted_at_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_at'");
if ($deleted_at_check && mysqli_num_rows($deleted_at_check) > 0) {
    $update_sql .= ", deleted_at = NULL";
}
$deleted_by_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_by'");
if ($deleted_by_check && mysqli_num_rows($deleted_by_check) > 0) {
    $update_sql .= ", deleted_by = NULL";
}
$update_sql .= " WHERE id = $app_id";
if (!mysqli_query($conn, $update_sql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to restore application.']);
    exit();
}

$user_role = current_user_role();
$user_id = current_user_id();
$updated_by_name = 'HR';
if ($user_id !== null) {
    $name_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $user_id LIMIT 1");
    if ($name_res && $name_row = mysqli_fetch_assoc($name_res)) {
        $updated_by_name = $name_row['full_name'] ?: $updated_by_name;
    } else {
        $name_res = mysqli_query($conn, "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1");
        if ($name_res && $name_row = mysqli_fetch_assoc($name_res)) {
            $updated_by_name = $name_row['full_name'] ?: $updated_by_name;
        }
    }
}

$history_sql = "INSERT INTO application_status_history
                  (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                  VALUES ($app_id, 'Deleted', 'Restored', '$user_role', '" . mysqli_real_escape_string($conn, $updated_by_name) . "', 'Application restored by HR.')";
mysqli_query($conn, $history_sql);

$notif_title = mysqli_real_escape_string($conn, 'Application Restored');
$notif_msg = mysqli_real_escape_string($conn, "Your application for \"{$app['internship_title']}\" has been restored and is active again.");
mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, title, message) VALUES ({$app['user_id']}, 'info', '$notif_title', '$notif_msg')");

sendEmailNotification(intval($app['user_id']), "IMP Application Restored: {$app['internship_title']}", "Dear {$app['student_name']},\n\nYour application has been restored by HR and is now active again.\n\nPlease log in to continue reviewing your application.", [
    'event' => 'Application Restored',
    'action_url' => 'http://localhost/IMP/student_applications.php',
    'action_label' => 'View Application Status'
]);

echo json_encode(['success' => true, 'message' => 'Application restored successfully.']);
exit();
?>