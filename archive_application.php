<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin']);
include 'db.php';
header('Content-Type: application/json');

function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

$app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
if ($app_id <= 0) {
    json_response(false, 'Invalid application ID.');
}

$app_res = mysqli_query($conn, "SELECT a.id, a.status, a.user_id, COALESCE(i.title, a.internship_name) AS internship_title, COALESCE(u.full_name, sp.full_name) AS student_name
                                FROM internship_applications a
                                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                                LEFT JOIN users u ON a.user_id = u.id
                                LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                                WHERE a.id = $app_id LIMIT 1");
if (!$app_res || mysqli_num_rows($app_res) === 0) {
    json_response(false, 'Application not found.');
}

$app = mysqli_fetch_assoc($app_res);
$protected_statuses = ['Applied', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approved', 'Selected', 'Project Assigned', 'Active Intern'];
if (in_array($app['status'], $protected_statuses, true)) {
    json_response(false, 'Only completed or closed applications can be archived.');
}

if (!mysqli_query($conn, "UPDATE internship_applications SET is_deleted = 1 WHERE id = $app_id")) {
    json_response(false, 'Failed to archive application.');
}

$user_id = current_user_id();
$user_role = current_user_role();
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

mysqli_query($conn, "INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                    VALUES ($app_id, '" . mysqli_real_escape_string($conn, $app['status']) . "', 'Archived', '$user_role', '" . mysqli_real_escape_string($conn, $updated_by_name) . "', 'Archived by HR.')");

json_response(true, 'Application archived successfully.');
