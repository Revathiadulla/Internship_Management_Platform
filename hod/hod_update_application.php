<?php
session_start();
define('INCLUDE_CHECK', true);
include_once __DIR__ . '/../includes/auth.php';
require_role(['hod', 'admin']);
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/mail_helper.php';
include_once __DIR__ . '/../includes/workflow_helper.php';

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($app_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header("Location: /IMP/index.php?error=invalid_request");
    exit();
}

$user_id = current_user_id();

// Fetch application details
$query = "SELECT a.id, a.status, a.user_id, a.aadhaar_status, a.pan_status, a.education_status,
                 sp.student_type, u.full_name, u.email,
                 sp.hod_name, sp.hod_email, a.internship_name,
                 COALESCE(i.title, a.internship_name) AS internship_title
          FROM internship_applications a
          LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
          LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
          LEFT JOIN users u ON a.user_id = u.id
          WHERE a.id = ? AND a.is_deleted = 0 LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: /IMP/index.php?error=not_found");
    exit();
}
$app = $res->fetch_assoc();
$student_id = intval($app['user_id']);
$student_name = $app['full_name'] ?? 'Student';
$internship_title = $app['internship_title'] ?? 'Internship';

$hod_name = $app['hod_name'] ?? 'HOD';

if ($action === 'approve') {
    $new_status = 'Selected';
    $new_hod_status = 'approved';
    $new_final_status = 'selected';
    $msg = "approved";
} else {
    $new_status = 'Rejected';
    $new_hod_status = 'rejected';
    $new_final_status = 'rejected';
    $msg = "rejected";
}

// Update application with HOD action details
$update = $conn->prepare("UPDATE internship_applications SET status = ?, hod_status = ?, final_status = ?, hod_id = ?, hod_action_at = NOW() WHERE id = ?");
$update->bind_param("sssii", $new_status, $new_hod_status, $new_final_status, $user_id, $app_id);

if ($update->execute()) {
    log_status_change('internship_applications_hod', $app_id, $app['status'], $new_status, "HOD ($hod_name) $msg candidate from dashboard");
    
    // Notify student
    $notif_title = ($action === 'approve') ? "Internship Selected" : "Application Rejected";
    $notif_msg = ($action === 'approve') ? "Congratulations! Your HOD has approved and you have been selected for the internship." : "Your application has been rejected by HOD.";
    $notif_type = ($action === 'approve') ? "success" : "error";
    add_notification($student_id, 'student', $notif_title, $notif_msg, $notif_type);
    
    // Notify HR / Admins
    $staff_subject = "HOD Action: Application $new_status";
    $staff_msg = "HOD has $msg the internship application for $student_name ($internship_title).";
    $staff_query = "SELECT id, email, role FROM users WHERE LOWER(role) IN ('hr', 'admin')";
    $staff_result = mysqli_query($conn, $staff_query);
    if ($staff_result) {
        while ($row = mysqli_fetch_assoc($staff_result)) {
            $r = strtolower(trim($row['role']));
            add_notification(intval($row['id']), $r, $staff_subject, $staff_msg, 'info');
        }
    }
    
    // Send email to student
    $subject = "Update on your internship application - IMP";
    $email_body = "<p>Dear $student_name,</p><p>Your application status has been updated to <strong>$new_status</strong> after HOD review.</p><p>Best regards,<br>IMP Team</p>";
    if (function_exists('sendEmail')) {
        sendEmail($app['email'], $student_name, $subject, $email_body);
    } else {
        sendEmailNotification($app['email'], $subject, "Dear $student_name,\n\nYour application status has been updated to $new_status after HOD review.\n\nBest regards,\nIMP Team");
    }
}

header("Location: /IMP/index.php?success=" . $action);
exit();
