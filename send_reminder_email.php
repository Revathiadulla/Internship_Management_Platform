<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('applications');
include "db.php";
include_once __DIR__ . '/includes/mail_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
if ($application_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid application ID.']);
    exit;
}

// Fetch applicant details
$app_sql = "SELECT a.user_id, a.id as app_id, sp.full_name, sp.email FROM internship_applications a LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE a.id = $application_id LIMIT 1";
$app_res = mysqli_query($conn, $app_sql);
if (!$app_res || mysqli_num_rows($app_res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit;
}
$app = mysqli_fetch_assoc($app_res);
$fullName = $app['full_name'] ?? 'Applicant';
$email = $app['email'] ?? '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Applicant email is missing or invalid.']);
    exit;
}

// Build email content – reuse the premium template via sendEmailNotification
$subject = 'Reminder: Please complete your internship application';
$messageText = "Dear $fullName,\n\nWe noticed that your internship application (ID: {$app['app_id']}) is still pending some required steps. Please log in to the Internship Management Platform and complete the missing information as soon as possible.\n\nThank you,\nHR Team";
$metadata = [
    'event' => 'Application Reminder',
    'action_url' => "http://localhost/Internship_Management_Platform-student_module/view_application_status.php?app_id={$app['app_id']}",
    'action_label' => 'View Application',
];

$sent = sendEmailNotification($email, $subject, $messageText, $metadata);
if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Reminder email sent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send reminder email. Check logs for details.']);
}
?>
