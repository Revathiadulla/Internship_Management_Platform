<?php
// hod_approval_action.php
// Stateless HOD approval action handler for email links.

include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/includes/hod_helpers.php';

$application_id = isset($_REQUEST['application_id']) ? intval($_REQUEST['application_id']) : 0;
$action = isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : '';
$token = isset($_REQUEST['token']) ? trim($_REQUEST['token']) : '';

$allowed_actions = [
    'approve' => ['status' => 'Selected', 'hod_status' => 'approved', 'final_status' => 'selected', 'message' => 'approved'],
    'reject' => ['status' => 'Rejected', 'hod_status' => 'rejected', 'final_status' => 'rejected', 'message' => 'rejected']
];

if ($application_id <= 0 || empty($token) || !isset($allowed_actions[$action])) {
    echo render_page('Invalid Request', 'The approval link is invalid or malformed.');
    exit;
}

$stmt = $conn->prepare(
    'SELECT a.id, a.status, a.hod_approval_status, a.hod_token, a.user_id, a.hod_name, a.hod_email, a.internship_id, COALESCE(i.title, a.internship_name) AS internship_title, u.full_name AS student_name, u.email AS student_email, sp.full_name AS profile_name, sp.hod_name AS profile_hod_name
     FROM internship_applications a
     LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
     WHERE a.id = ? AND a.hod_token = ?
     LIMIT 1'
);
$stmt->bind_param('is', $application_id, $token);
$stmt->execute();
$result = $stmt->get_result();
$app = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$app) {
    echo render_page('Link Expired or Invalid', 'This approval link is invalid or has already been used.');
    exit;
}

$student_user_id = intval($app['user_id']);
$current_status = $app['status'] ?: 'Unknown';
$current_hod_status = $app['hod_status'] ?: 'Pending';
$student_name = $app['profile_name'] ?: $app['student_name'] ?: 'Student';
$internship_title = $app['internship_title'] ?: 'Internship';
$hod_name = $app['hod_name'] ?: $app['profile_hod_name'] ?: 'HOD';

if ($current_hod_status !== 'Pending') {
    $already = $current_hod_status === 'approved' ? 'approved' : 'rejected';
    echo render_page('Already Responded', "This request has already been $already.");
    exit();
}

$new_status = $allowed_actions[$action]['status'];
$new_hod_status = $allowed_actions[$action]['hod_status'];
$new_final_status = $allowed_actions[$action]['final_status'];
$decision_text = $allowed_actions[$action]['message'];

$set_clauses = ['status = ?', 'hod_status = ?', 'final_status = ?', 'hod_approved_at = NOW()', 'hod_token = NULL'];
$update_sql = 'UPDATE internship_applications SET ' . implode(', ', $set_clauses) . ' WHERE id = ?';
$update = $conn->prepare($update_sql);
if (!$update) {
    echo render_page('Update Failed', 'Unable to prepare application update: ' . $conn->error);
    exit;
}
$update->bind_param('sssi', $new_status, $new_hod_status, $new_final_status, $application_id);

$update->execute();
$affected_rows = $update->affected_rows;
$update->close();

$log_message = sprintf(
    "%s | application_id=%d | affected_rows=%d | updated_status=%s | hod_approval_status=%s\n",
    date('Y-m-d H:i:s'),
    $application_id,
    $affected_rows,
    $new_status,
    $new_hod_status
);
@file_put_contents(__DIR__ . '/hod_approval_action.log', $log_message, FILE_APPEND);

if ($affected_rows <= 0) {
    echo render_page('Update Failed', 'Unable to update the application status. Please try again later.');
    exit;
}

$refresh_stmt = $conn->prepare("SELECT status, hod_approval_status, hod_status, application_status FROM internship_applications WHERE id = ? LIMIT 1");
$refresh_stmt->bind_param('i', $application_id);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result();
$updated_app = $refresh_result ? $refresh_result->fetch_assoc() : null;
$refresh_stmt->close();

$final_status = $updated_app['status'] ?? $new_status;
$final_hod_approval_status = $updated_app['hod_approval_status'] ?? $new_hod_status;
$final_hod_status = $updated_app['hod_status'] ?? $new_hod_status;
$final_application_status = $updated_app['application_status'] ?? $final_status;

$notes = sprintf('HOD %s via email link', $decision_text);
$notes_escaped = mysqli_real_escape_string($conn, $notes);
$current_status_escaped = mysqli_real_escape_string($conn, $current_status);
$new_status_escaped = mysqli_real_escape_string($conn, $final_status);
$hod_name_escaped = mysqli_real_escape_string($conn, $hod_name);

mysqli_query($conn, "INSERT INTO application_status_history
    (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
    VALUES ($application_id, '$current_status_escaped', '$new_status_escaped', 'hod', '$hod_name_escaped', '$notes_escaped')");

$base_url = get_base_url();
$student_subject = "Your application status is now $final_status";
$student_message = "Your HOD has $decision_text your internship application for '$internship_title'.\n\nCurrent status: $final_status.";
notifyUser($student_user_id, 'student', $app['student_email'], $student_subject, $student_message, [
    'event' => 'HOD Decision',
    'internship' => $internship_title,
    'status' => $final_status,
    'hod_approval_status' => $final_hod_approval_status,
    'hod_status' => $final_hod_status,
    'application_status' => $final_application_status,
    'action_url' => rtrim($base_url, '/') . '/student_applications.php',
    'action_label' => 'View Application Status'
], 'info');

$staff_subject = "Internship Application status updated to $final_status";
$staff_message = "The HOD has $decision_text the internship application for '$student_name' ($internship_title). Current status: $final_status.";
$staff_query = "SELECT id, email, full_name, role FROM users WHERE LOWER(role) IN ('hr', 'admin')";
$staff_result = mysqli_query($conn, $staff_query);
if ($staff_result) {
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $role = strtolower(trim($row['role']));
        $email = trim($row['email']);
        $userId = intval($row['id']);
        if ($userId <= 0) {
            continue;
        }
        notifyUser($userId, $role === 'admin' ? 'admin' : 'hr', $email, $staff_subject, $staff_message, [
            'event' => 'HOD Decision',
            'internship' => $internship_title,
            'action_url' => rtrim($base_url, '/') . '/hr_applications.php',
            'action_label' => 'Review Applications'
        ], 'info');
    }
}

echo render_page('Application status updated successfully.', 'Your decision has been recorded.');

function render_page(string $title, string $message): string {
    return "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>" . htmlspecialchars($title) . "</title><style>body{font-family:Arial,sans-serif;background:#f3f4f6;color:#111;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;} .card{max-width:520px;width:100%;background:#fff;padding:32px 28px;border-radius:18px;box-shadow:0 20px 60px rgba(15,23,42,.08);} h1{font-size:22px;margin:0 0 14px;color:#0f172a;} p{font-size:15px;line-height:1.7;color:#475569;} a{color:#2563eb;text-decoration:none;}</style></head><body><div class='card'><h1>" . htmlspecialchars($title) . "</h1><p>" . htmlspecialchars($message) . "</p></div></body></html>";
}
