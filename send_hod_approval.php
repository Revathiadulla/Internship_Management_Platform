<?php
// send_hod_approval.php
// Called via AJAX from HR dashboard when HR clicks "Send HOD Approval" for a pursuing student.

session_start();
include_once __DIR__ . '/includes/auth.php';
if (!is_logged_in() || !has_role(['hr', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';
require_once __DIR__ . '/includes/hod_helpers.php';

$app_id = intval($_POST['application_id'] ?? 0);
if ($app_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

// Fetch application and student details
$stmt = $conn->prepare('SELECT a.education_status, a.hod_email, a.hod_approval_status, i.title AS internship_title, sp.full_name, sp.email AS student_email, a.test_score, a.test_result FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE a.id = ?');
$stmt->bind_param('i', $app_id);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($app['education_status'] !== 'Pursuing') {
    echo json_encode(['success' => false, 'message' => 'HOD approval not required for this student']);
    exit();
}

$hod_email = $app['hod_email'];
if (empty($hod_email)) {
    echo json_encode(['success' => false, 'message' => 'HOD email not configured for this application']);
    exit();
}

// Generate token and store it
$token = generate_hod_token();
$update = $conn->prepare('UPDATE internship_applications SET hod_token = ?, hod_approval_status = ?, hod_approved_at = NULL WHERE id = ?');
$status = 'Pending';
$update->bind_param('ssi', $token, $status, $app_id);
$update->execute();
$update->close();

$approve_link = hod_approval_url($app_id, $token, 'approve');
$reject_link = hod_approval_url($app_id, $token, 'reject');
$subject = "HOD Approval Required for Internship Application #$app_id";
$message = "<html><body>\n<p>Dear HOD,</p>\n<p>The student <strong>{$app['full_name']}</strong> ({$app['student_email']}) has completed the test for the internship <strong>{$app['internship_title']}</strong>.\nTest Score: {$app['test_score']} (Result: {$app['test_result']}).</p>\n<p>Please review and make a decision:</p>\n<p><a href='$approve_link'>Approve Application</a> | <a href='$reject_link'>Reject Application</a></p>\n<p>This link will expire in 7 days.</p>\n<p>Best regards,<br/>Internship Management System</p>\n</body></html>";
$headers = "MIME-Version: 1.0\r\n".
    "Content-type: text/html; charset=UTF-8\r\n".
    "From: no-reply@internshipplatform.com\r\n";
$mail_sent = mail($hod_email, $subject, $message, $headers);

if ($mail_sent) {
    echo json_encode(['success' => true, 'message' => 'HOD approval email sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send HOD approval email']);
}
?>
