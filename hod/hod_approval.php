<?php
/**
 * hod_approval.php
 * Stateless HOD approval page - no login required.
 * HOD clicks approve/reject link from email → this page handles it.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/mail_helper.php';

$token    = isset($_GET['token'])    ? trim($_GET['token'])    : '';
$decision = isset($_GET['decision']) ? trim($_GET['decision']) : '';

$allowed_decisions = ['approve', 'reject'];

// Validate inputs
if (empty($token) || !in_array($decision, $allowed_decisions)) {
    die(render_page('Invalid Request', 'The approval link is invalid or malformed. Please contact IMP support.', 'error'));
}

$esc_token = mysqli_real_escape_string($conn, $token);

// Fetch the application by token
$sql = "SELECT a.id, a.status, a.hod_approval_status, a.hod_token, a.hod_name, a.hod_email,
               a.user_id, COALESCE(i.project_subtype, a.project_subtype, a.applied_subtype, 'Not specified') AS applied_subtype,
               u.full_name AS student_name, u.email AS student_email,
               sp.full_name AS sp_name, sp.hod_name AS sp_hod_name
        FROM internship_applications a
        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        WHERE a.hod_token = '$esc_token'
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    die(render_page('Link Expired or Invalid', 'This approval link was not found or has already been used. Please contact IMP support.', 'error'));
}

$row = mysqli_fetch_assoc($result);
$app_id           = intval($row['id']);
$current_status   = $row['status'];
$hod_appr_status  = $row['hod_approval_status'] ?? 'Pending';
$student_name     = $row['sp_name'] ?: $row['student_name'] ?: 'Student';
$applied_subtype = $row['applied_subtype'] ?: 'Not specified';
$student_user_id  = intval($row['user_id']);
$hod_name         = $row['hod_name'] ?: $row['sp_hod_name'] ?: 'HOD';

// Prevent re-use of token
if ($hod_appr_status !== 'Pending') {
    $msg = $hod_appr_status === 'Approved' ? 'You have already approved this request.' : 'You have already rejected this request.';
    die(render_page('Already Responded', $msg, 'info'));
}

// Apply the decision
if ($decision === 'approve') {
    $new_status     = 'HOD Approved';
    $new_hod_status = 'Approved';
    $notif_msg      = "Your HOD has approved your internship application for \"$applied_subtype\". HR will contact you shortly with next steps.";
    $page_title     = 'Approval Submitted';
    $page_msg       = "You have successfully <strong>approved</strong> the internship application for <strong>" . htmlspecialchars($student_name) . "</strong>.<br>The student and HR team have been notified.";
    $page_type      = 'success';
} else {
    $new_status     = 'HOD Rejected';
    $new_hod_status = 'Rejected';
    $notif_msg      = "Unfortunately, your HOD has rejected your internship application for \"$applied_subtype\".";
    $page_title     = 'Rejection Submitted';
    $page_msg       = "You have <strong>rejected</strong> the internship application for <strong>" . htmlspecialchars($student_name) . "</strong>.<br>The student has been notified.";
    $page_type      = 'warning';
}

// Update the application
$esc_notif = mysqli_real_escape_string($conn, $notif_msg);
$update_sql = "UPDATE internship_applications SET
    status = '$new_status',
    hod_approval_status = '$new_hod_status',
    hod_approved_at = NOW(),
    hod_token = NULL
    WHERE id = $app_id";

if (!mysqli_query($conn, $update_sql)) {
    die(render_page('Database Error', 'An error occurred while saving your response: ' . mysqli_error($conn), 'error'));
}

// Log in status history
$notes_escaped = mysqli_real_escape_string($conn, ($decision === 'approve' ? 'HOD approved via email link' : 'HOD rejected via email link'));
mysqli_query($conn, "INSERT INTO application_status_history
    (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
    VALUES ($app_id, '$current_status', '$new_status', 'hod', '$hod_name', '$notes_escaped')");

// Notify the student
mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
    VALUES ($student_user_id, 'HOD Decision', 'Application Update', '$esc_notif')");

// Email the student
$subj = ($decision === 'approve')
    ? "IMP – HOD Approved Your Internship Application: $applied_subtype"
    : "IMP – HOD Rejected Your Internship Application: $applied_subtype";
sendStudentNotification($student_user_id, $student_name, $subj, $notif_msg, [
    'event'        => 'HOD Decision',
    'applied_internship' => $applied_subtype,
    'hod_decision' => ucfirst($decision) . 'd',
    'action_url'   => 'http://localhost/IMP/student_applications.php',
    'action_label' => 'View Application Status'
]);

echo render_page($page_title, $page_msg, $page_type);

// ── Helper: render a minimal branded response page ────────────────────────
function render_page(string $title, string $message, string $type = 'info'): string {
    $icon_map = [
        'success' => '✓',
        'warning' => '!',
        'error'   => '✗',
        'info'    => 'ℹ',
    ];
    $color_map = [
        'success' => '#16a34a',
        'warning' => '#d97706',
        'error'   => '#dc2626',
        'info'    => '#2563eb',
    ];
    $icon  = $icon_map[$type]  ?? 'ℹ';
    $color = $color_map[$type] ?? '#2563eb';

    return "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>$title – IMP</title>
  <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap' rel='stylesheet'>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { background: #fff; border-radius: 20px; padding: 48px 40px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.08); text-align: center; }
    .icon { width: 72px; height: 72px; border-radius: 50%; background: {$color}1a; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 32px; color: {$color}; }
    h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 12px; }
    p { font-size: 15px; color: #475569; line-height: 1.6; }
    .brand { margin-top: 36px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
    .brand span { font-size: 13px; color: #94a3b8; }
    .brand strong { color: #2563eb; }
  </style>
</head>
<body>
  <div class='card'>
    <div class='icon' style='background:{$color}1a;color:{$color};'>$icon</div>
    <h1>$title</h1>
    <p>$message</p>
    <div class='brand'><span>Powered by <strong>IMP</strong> – Internship Management Platform</span></div>
  </div>
</body>
</html>";
}
