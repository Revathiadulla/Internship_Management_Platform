<?php
// hod_approval_action.php
// Stateless HOD approval action handler for email links.

ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/includes/hod_helpers.php';

$application_id = isset($_REQUEST['application_id']) ? intval($_REQUEST['application_id']) : 0;
$action = isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : '';
$token = isset($_REQUEST['token']) ? trim($_REQUEST['token']) : '';

$allowed_actions = [
    'approve' => ['status' => 'HOD Approved', 'hod_status' => 'approved', 'final_status' => 'selected', 'message' => 'approved'],
    'reject' => ['status' => 'HOD Rejected', 'hod_status' => 'rejected', 'final_status' => 'rejected', 'message' => 'rejected']
];

if ($application_id <= 0 || empty($token) || !isset($allowed_actions[$action])) {
    echo render_page('Invalid Request', 'The approval link is invalid or malformed.', 'error');
    exit;
}

// 1. Fetch application details by ID only (without matching token in SQL to allow detailed mismatch debugging)
$stmt = $conn->prepare(
    'SELECT a.id, a.status, a.hod_approval_status, a.hod_status, a.hod_token, a.user_id, a.hod_name, a.hod_email, a.internship_id, COALESCE(i.project_subtype, a.project_subtype, a.applied_subtype, \'Not specified\') AS applied_subtype, u.full_name AS student_name, u.email AS student_email, sp.full_name AS profile_name, sp.hod_name AS profile_hod_name
     FROM internship_applications a
     LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
     WHERE a.id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$result = $stmt->get_result();
$app = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$app) {
    // Log invalid application ID lookup
    $log_err = sprintf(
        "%s | ERR: Application ID %d not found.\n",
        date('Y-m-d H:i:s'),
        $application_id
    );
    @file_put_contents(__DIR__ . '/hod_approval_action.log', $log_err, FILE_APPEND);

    echo render_page('Link Expired or Invalid', 'This approval link is invalid or has already been used.', 'error');
    exit;
}

// 2. Validate token manually and log token mismatches
$db_token = $app['hod_token'] ?? '';
if (empty($db_token) || $db_token !== $token) {
    // Log the token mismatch details
    $log_err = sprintf(
        "%s | ERR: Token mismatch for application_id=%d. URL Token='%s', DB Token='%s'\n",
        date('Y-m-d H:i:s'),
        $application_id,
        $token,
        $db_token
    );
    @file_put_contents(__DIR__ . '/hod_approval_action.log', $log_err, FILE_APPEND);

    echo render_page('Link Expired or Invalid', 'This approval link is invalid or has already been used.', 'error');
    exit;
}

$student_user_id = intval($app['user_id']);
$current_status = $app['status'] ?: 'Unknown';
$current_hod_status = !empty($app['hod_approval_status']) ? trim($app['hod_approval_status']) : (!empty($app['hod_status']) ? trim($app['hod_status']) : 'Pending');
$student_name = $app['profile_name'] ?: $app['student_name'] ?: 'Student';
$applied_subtype = $app['applied_subtype'] ?: 'Not specified';
$hod_name = $app['hod_name'] ?: $app['profile_hod_name'] ?: 'HOD';

// 3. Normalize current HOD approval status for decision check
$current_hod_status_lower = strtolower($current_hod_status);
if ($current_hod_status_lower !== 'pending') {
    $already = ($current_hod_status_lower === 'approved' || $current_hod_status_lower === 'hod approved') ? 'approved' : 'rejected';
    echo render_page('Already Responded', "This request has already been $already.", 'info');
    exit();
}

$new_status = $allowed_actions[$action]['status'];
$new_hod_status = $allowed_actions[$action]['hod_status'];
$new_final_status = $allowed_actions[$action]['final_status'];
$decision_text = $allowed_actions[$action]['message'];

// 4. Update the application status in a transaction-safe single query
if ($action === 'approve') {
    $set_clauses = ['status = ?', 'hod_approval_status = ?', 'hod_status = ?', 'hod_approved_at = NOW()', 'hod_token = NULL'];
    $update_sql = 'UPDATE internship_applications SET ' . implode(', ', $set_clauses) . ' WHERE id = ?';
    $update = $conn->prepare($update_sql);
    if (!$update) {
        echo render_page('Update Failed', 'Unable to prepare application update: ' . $conn->error, 'error');
        exit;
    }
    $update->bind_param('sssi', $new_status, $new_hod_status, $new_hod_status, $application_id);
} else {
    $set_clauses = ['status = ?', 'hod_approval_status = ?', 'hod_status = ?', 'hod_rejected_at = NOW()', 'hod_token = NULL'];
    $update_sql = 'UPDATE internship_applications SET ' . implode(', ', $set_clauses) . ' WHERE id = ?';
    $update = $conn->prepare($update_sql);
    if (!$update) {
        echo render_page('Update Failed', 'Unable to prepare application update: ' . $conn->error, 'error');
        exit;
    }
    $update->bind_param('sssi', $new_status, $new_hod_status, $new_hod_status, $application_id);
}

$update->execute();
$affected_rows = $update->affected_rows;
$update->close();

$log_message = sprintf(
    "%s | HOD Decision | application_id=%d | affected_rows=%d | updated_status=%s | hod_status=%s\n",
    date('Y-m-d H:i:s'),
    $application_id,
    $affected_rows,
    $new_status,
    $new_hod_status
);
@file_put_contents(__DIR__ . '/hod_approval_action.log', $log_message, FILE_APPEND);

if ($affected_rows <= 0) {
    echo render_page('Update Failed', 'Unable to update the student application status. Please try again later.', 'error');
    exit;
}

// Log in status history
$notes = sprintf('HOD %s via email link', $decision_text);
$notes_escaped = mysqli_real_escape_string($conn, $notes);
$current_status_escaped = mysqli_real_escape_string($conn, $current_status);
$new_status_escaped = mysqli_real_escape_string($conn, $new_status);
$hod_name_escaped = mysqli_real_escape_string($conn, $hod_name);

mysqli_query($conn, "INSERT INTO application_status_history
    (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
    VALUES ($application_id, '$current_status_escaped', '$new_status_escaped', 'hod', '$hod_name_escaped', '$notes_escaped')");

// Notify the student
$base_url = get_base_url();
$student_subject = "Your application status is now $new_status";
$student_message = "Your HOD has $decision_text your internship application for '$applied_subtype'.\n\nCurrent status: $new_status.";
notifyUser($student_user_id, 'student', $app['student_email'], $student_subject, $student_message, [
    'event' => 'HOD Decision',
    'applied_internship' => $applied_subtype,
    'status' => $new_status,
    'action_url' => rtrim($base_url, '/') . '/student_applications.php',
    'action_label' => 'View Application Status'
], 'info');

// Notify HR and Admin staff
$staff_subject = "Internship Application status updated to $new_status";
$staff_message = "The HOD has $decision_text the internship application for '$student_name' ($applied_subtype). Current status: $new_status.";
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
            'applied_internship' => $applied_subtype,
            'action_url' => rtrim($base_url, '/') . '/hr_applications.php',
            'action_label' => 'Review Applications'
        ], 'info');
    }
}

// 5. Output successful response matching HOD approval requirements
echo render_page('Thank you.', 'Your decision has been recorded successfully.', 'success');

function render_page(string $title, string $message, string $type = 'info'): string {
    $icon_map = [
        'success' => '✓',
        'warning' => '!',
        'error'   => '✗',
        'info'    => 'ℹ',
    ];
    $color_map = [
        'success' => '#10b981', // emerald-500
        'warning' => '#f59e0b', // amber-500
        'error'   => '#ef4444', // red-500
        'info'    => '#3b82f6', // blue-500
    ];
    $icon  = $icon_map[$type]  ?? 'ℹ';
    $color = $color_map[$type] ?? '#3b82f6';

    return "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>" . htmlspecialchars($title) . " – IMP</title>
  <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc, #f1f5f9); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #1e293b; }
    .card { background: #ffffff; border-radius: 24px; padding: 56px 40px; max-width: 480px; width: 100%; box-shadow: 0 25px 50px -12px rgba(15,23,42,0.08); text-align: center; border: 1px solid #e2e8f0; animation: scaleUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes scaleUp { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; font-size: 36px; font-weight: bold; transition: all 0.3s ease; }
    h1 { font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 14px; letter-spacing: -0.02em; }
    p { font-size: 15px; color: #475569; line-height: 1.625; font-weight: 400; }
    .brand { margin-top: 48px; padding-top: 24px; border-top: 1px solid #f1f5f9; }
    .brand span { font-size: 12px; color: #94a3b8; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
    .brand strong { color: #3b82f6; font-weight: 600; }
  </style>
</head>
<body>
  <div class='card'>
    <div class='icon' style='background: {$color}15; color: {$color}; border: 1px solid {$color}2a;'>$icon</div>
    <h1>" . htmlspecialchars($title) . "</h1>
    <p>$message</p>
    <div class='brand'><span>Powered by <strong>IMP</strong> – Internship Platform</span></div>
  </div>
</body>
</html>";
}
?>
