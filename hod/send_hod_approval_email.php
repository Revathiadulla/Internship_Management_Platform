<?php
// send_hod_approval_email.php
// Called via AJAX when HR triggers HOD approval for a pursuing student.

session_start();
include_once __DIR__ . '/../includes/auth.php';
if (!is_logged_in() || !has_role(['hr', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workflow_helper.php';

$app_id = intval($_POST['application_id'] ?? 0);
if ($app_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

// Fetch HOD email and student details
$stmt = $conn->prepare('SELECT a.education_status, a.hod_email, a.hod_approval_status, COALESCE(i.project_subtype, a.project_subtype, a.applied_subtype, \'Not specified\') AS applied_subtype, sp.full_name, sp.email AS student_email FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE a.id = ?');
$stmt->bind_param('i', $app_id);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$app_education = trim((string) ($app['education_status'] ?? ''));
if (!is_pursuing_student($app_education)) {
    echo json_encode(['success' => false, 'message' => 'HOD approval not required for this student']);
    exit();
}

$hod_email = $app['hod_email'];
if (empty($hod_email)) {
    echo json_encode(['success' => false, 'message' => 'HOD email not set for this application']);
    exit();
}

require_once __DIR__ . '/includes/hod_helpers.php';

// Generate a secure random token
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

// Store token in the application row
$status = 'Pending';
$update = $conn->prepare('UPDATE internship_applications SET hod_token = ?, hod_approval_status = ?, hod_status = \'pending\', hod_approved_at = NULL WHERE id = ?');
$update->bind_param('ssi', $token, $status, $app_id);
$update->execute();
$update->close();

// Prepare email
$approve_link = hod_approval_url($app_id, $token, 'approve');
$reject_link = hod_approval_url($app_id, $token, 'reject');
$subject = "HOD Approval Required for Internship Application #$app_id";
$message = "<html><body>
    <p>Dear HOD,</p>
    <p>The student <strong>{$app['full_name']}</strong> (<a href='mailto:{$app['student_email']}'>{$app['student_email']}</a>) has completed the test for the applied internship <strong>{$app['applied_subtype']}</strong>.
    <p>Please review and decide:</p>
    <p><a href='$approve_link'>Approve Application</a> | <a href='$reject_link'>Reject Application</a></p>
    <p>This link will expire in 7 days.</p>
    <p>Best regards,<br/>Internship Management System</p>
</body></html>";
$headers = "MIME-Version: 1.0\r\n" .
    "Content-type: text/html; charset=UTF-8\r\n" .
    "From: no-reply@internshipplatform.com\r\n";

$mail_sent = mail($hod_email, $subject, $message, $headers);

if ($mail_sent) {
    echo json_encode(['success' => true, 'message' => 'HOD approval email sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}
?>
