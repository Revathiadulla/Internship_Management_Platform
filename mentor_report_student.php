<?php
/**
 * mentor_report_student.php
 * AJAX endpoint for mentors to report inactive/non-performing students.
 * 
 * POST parameters:
 *   - student_id: ID of student to report
 *   - application_id: ID of internship application
 *   - reason: Report reason (dropdown value)
 *   - remarks: Detailed remarks (text)
 * 
 * Returns JSON response with success/failure status.
 */

session_start();
include_once __DIR__ . '/includes/auth.php';
require_role('mentor');
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/setup_discontinuation_schema.php';

header('Content-Type: application/json');

function json_response($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

$mentor_id = intval($_SESSION['user_id']);
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

// Validate inputs
if ($student_id <= 0 || $app_id <= 0 || empty($reason)) {
    json_response(false, 'Missing required fields (student_id, application_id, reason)');
}

$allowed_reasons = [
    'No Daily Log Submission',
    'Poor Performance',
    'Inactive for Long Period',
    'Not Attending Meetings',
    'Requested Withdrawal',
    'Other'
];

if (!in_array($reason, $allowed_reasons)) {
    json_response(false, 'Invalid report reason provided');
}

// Verify mentor is assigned to this student's internship (either direct or team assignment)
$check_sql = "SELECT 1 FROM (
                  SELECT 1 
                  FROM mentor_assignments ma 
                  WHERE ma.mentor_id = ? AND ma.application_id = ? AND ma.status = 'active'
                  
                  UNION ALL
                  
                  SELECT 1 
                  FROM project_teams t
                  JOIN project_team_members tm ON tm.project_team_id = t.id
                  JOIN internship_applications a ON a.user_id = tm.student_id
                  WHERE t.mentor_id = ? AND tm.student_id = ? AND a.id = ?
              ) as assignments LIMIT 1";

$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    json_response(false, 'Database error: ' . $conn->error);
}
$check_stmt->bind_param('iiiii', $mentor_id, $app_id, $mentor_id, $student_id, $app_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    json_response(false, 'You are not assigned to this student\'s internship');
}
$check_stmt->close();

// Fetch application and student details
$app_sql = "SELECT a.id, a.user_id, a.internship_id, a.internship_name, 
                   COALESCE(i.title, a.internship_name) as internship_title,
                   a.internship_status
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.id = ? AND a.user_id = ? LIMIT 1";
$app_stmt = $conn->prepare($app_sql);
if (!$app_stmt) {
    json_response(false, 'Database error: ' . $conn->error);
}
$app_stmt->bind_param('ii', $app_id, $student_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

if ($app_result->num_rows === 0) {
    json_response(false, 'Application not found');
}

$app = $app_result->fetch_assoc();
$app_stmt->close();

// Check if already reported
if ($app['internship_status'] === 'Reported by Mentor') {
    json_response(false, 'This student has already been reported by a mentor. Awaiting admin review.');
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // 1. Update application status and report fields
    $update_sql = "UPDATE internship_applications 
                   SET internship_status = 'Reported by Mentor',
                       report_reason = ?,
                       mentor_remarks = ?,
                       reported_by = ?,
                       reported_date = NOW()
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }
    $update_stmt->bind_param('ssii', $reason, $remarks, $mentor_id, $app_id);
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update application: ' . $update_stmt->error);
    }
    $update_stmt->close();

    // 2. Log to audit trail
    $audit_sql = "INSERT INTO internship_status_history 
                  (application_id, old_status, new_status, report_reason, remarks, changed_by, changed_by_role, change_type)
                  VALUES (?, ?, 'Reported by Mentor', ?, ?, ?, 'mentor', 'report')";
    $audit_stmt = $conn->prepare($audit_sql);
    if (!$audit_stmt) {
        throw new Exception('Failed to prepare audit statement: ' . $conn->error);
    }
    $old_status = $app['internship_status'] ?? 'Active';
    $audit_stmt->bind_param('isssi', $app_id, $old_status, $reason, $remarks, $mentor_id);
    if (!$audit_stmt->execute()) {
        throw new Exception('Failed to log audit: ' . $audit_stmt->error);
    }
    $audit_stmt->close();

    // 3. Get student and admin details for notifications
    $student_res = mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $student_id LIMIT 1");
    $student = mysqli_fetch_assoc($student_res);
    $student_name = $student['full_name'] ?? 'Student';
    $student_email = $student['email'] ?? '';

    $mentor_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $mentor_id LIMIT 1");
    $mentor = mysqli_fetch_assoc($mentor_res);
    $mentor_name = $mentor['full_name'] ?? 'Mentor';

    // 4. Create notification for admins
    $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE LOWER(role) = 'admin' LIMIT 10");
    if ($admin_res) {
        $a_title = 'Student Dropout Request';
        $a_msg = "Mentor " . ($_SESSION['full_name'] ?? 'Mentor') . " requested dropout for student " . ($student_name ?? 'Student') . " on '" . $app['internship_title'] . "'.";
        $a_type = 'alert';
        $a_link = "admin_student_reports.php";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'admin', ?, ?, ?, ?)");
        if ($notif_stmt) {
            while ($a_row = mysqli_fetch_assoc($admin_res)) {
                $a_id = intval($a_row['id']);
                $notif_stmt->bind_param("issss", $a_id, $a_title, $a_msg, $a_type, $a_link);
                $notif_stmt->execute();
            }
            $notif_stmt->close();
        }
    }

    // 5. Send email notification to admins
    $admin_emails_res = mysqli_query($conn, "SELECT email FROM users WHERE LOWER(role) = 'admin' LIMIT 10");
    if ($admin_emails_res && mysqli_num_rows($admin_emails_res) > 0) {
        $email_subject = "Student Report: $student_name - $reason";
        $email_message = "A mentor has submitted a report for student $student_name in internship '{$app['internship_title']}'.\n\nReason: $reason\nRemarks: $remarks\n\nPlease review and take appropriate action.";
        
        while ($admin_row = mysqli_fetch_assoc($admin_emails_res)) {
            sendStudentNotification($admin_row['email'], 'Admin', $email_subject, $email_message, [
                'event' => 'Student Report',
                'student' => $student_name,
                'internship' => $app['internship_title'],
                'reason' => $reason,
                'action_url' => 'http://localhost/IMP/admin_student_reports.php',
                'action_label' => 'Review Reports'
            ]);
        }
    }

    // 6. Create student notification (for record keeping, no email yet)
    $student_notif_msg = "Your internship status has been reported by your mentor for review by administration.";
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message) 
                         VALUES ($student_id, 'Internship Status Update', 'info', '$student_notif_msg')");

    mysqli_commit($conn);
    json_response(true, "Student successfully reported. Admin will review and take action.");

} catch (Exception $e) {
    mysqli_rollback($conn);
    json_response(false, 'Error: ' . $e->getMessage());
}
?>
