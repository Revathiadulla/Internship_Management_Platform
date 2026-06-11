<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    session_start();
    include "db.php";
    include_once __DIR__ . "/includes/mail_helper.php";
    include_once __DIR__ . "/includes/workflow_helper.php";

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

$user_id = $_SESSION['user_id'];
$user_role = strtolower(trim($_SESSION['role'] ?? ''));

// Only HR, Coordinator and Admin can update status
if ($user_role !== 'hr' && $user_role !== 'coordinator' && $user_role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update application status']);
    exit();
}

// Get POST data
$app_id   = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$notes    = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($app_id <= 0 || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID or status']);
    exit();
}

// Fetch current application details (including HOD info and student profile)
$app_sql = "SELECT a.id, a.status, a.education_status, a.hod_name, a.hod_email, a.hod_phone,
                   a.hod_approval_status, a.user_id, a.internship_id, a.internship_name,
                   COALESCE(i.title, a.internship_name) AS internship_title,
                   u.full_name AS student_name, u.email AS student_email,
                   sp.hod_name AS sp_hod_name, sp.hod_email AS sp_hod_email, sp.hod_phone AS sp_hod_phone,
                   sp.student_type
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE a.id = $app_id LIMIT 1";
$app_result = mysqli_query($conn, $app_sql);

if (!$app_result || mysqli_num_rows($app_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$app = mysqli_fetch_assoc($app_result);
$old_status       = $app['status'];
$old_status_key   = normalize_workflow_status($old_status);
$education_status = $app['education_status'] ?? '';
$student_user_id  = intval($app['user_id']);
$internship_title = $app['internship_title'] ?? 'Internship';
$student_name     = $app['student_name'] ?? 'Student';

// Resolve HOD details (prefer application data, fallback to student_profiles)
$hod_name  = !empty($app['hod_name'])  ? $app['hod_name']  : ($app['sp_hod_name']  ?? '');
$hod_email = !empty($app['hod_email']) ? $app['hod_email'] : ($app['sp_hod_email'] ?? '');
$hod_phone = !empty($app['hod_phone']) ? $app['hod_phone'] : ($app['sp_hod_phone'] ?? '');

$allowed_statuses = [];
if ($user_role === 'hr') {
    $allowed_statuses = ['Applied', 'Shortlisted', 'Exam Mail Sent', 'Exam Qualified', 'HOD Pending', 'HOD Approved', 'Selected', 'Rejected', 'Exam Link Sent', 'Exam Completed', 'HR Review'];
} elseif ($user_role === 'coordinator' || $user_role === 'admin') {
    $allowed_statuses = ['Applied', 'Shortlisted', 'Exam Mail Sent', 'Exam Qualified', 'HOD Pending', 'HOD Approved', 'Selected', 'Project Assigned', 'Active Intern', 'Rejected', 'Completed', 'Exam Link Sent', 'Exam Completed', 'HR Review'];
}

// Log status transition attempt
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$permission_result = in_array($new_status, $allowed_statuses) ? 'Allowed' : 'Denied';
$timestamp = date('Y-m-d H:i:s');
$log_entry = "[$timestamp] User ID: $user_id | Role: $user_role | App ID: $app_id | Current Status: $old_status | Target Status: $new_status | Permission Result: $permission_result\n";
file_put_contents($log_dir . '/workflow_debug.log', $log_entry, FILE_APPEND);

if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

// Enforce status flow constraints
if ($new_status === 'Rejected') {
    $blocked_rejection_statuses = [
        'project_assigned', 'team_assigned', 'started', 'internship_started', 
        'active_intern', 'completed', 'internship_completed', 'certificate_issued'
    ];
    if (in_array($old_status_key, $blocked_rejection_statuses, true) || 
        in_array(strtolower($old_status), ['project assigned', 'team assigned', 'internship started', 'internship completed', 'certificate issued', 'started', 'completed', 'active intern'])) {
        echo json_encode(['success' => false, 'message' => 'Candidate rejection is not allowed after project assignment or completion.']);
        exit();
    }
}
if ($new_status === 'Exam Mail Sent') {
    if (!in_array($old_status_key, ['shortlisted', 'applied', 'exam_sent', 'exam_link_sent', 'exam_mail_sent'], true)) {
        echo json_encode(['success' => false, 'message' => 'Exam email can only be sent after the candidate is shortlisted.']);
        exit();
    }
}
if ($new_status === 'Exam Qualified') {
    if (!in_array($old_status_key, ['exam_sent', 'exam_link_sent', 'exam_mail_sent'], true)) {
        echo json_encode(['success' => false, 'message' => 'This candidate can only be marked as exam qualified after the exam email has been sent.']);
        exit();
    }
}
if ($new_status === 'HOD Pending') {
    if (!in_array($old_status_key, ['exam_sent', 'exam_mail_sent', 'exam_qualified'], true)) {
        echo json_encode(['success' => false, 'message' => 'Cannot request HOD approval before exam details are sent or qualified.']);
        exit();
    }
}
if ($new_status === 'Selected') {
    $is_pursuing_be = is_pursuing_student($education_status, $app['student_type'] ?? null);
    if ($is_pursuing_be) {
        if (!in_array($old_status_key, ['hod_approved', 'selected'], true) && strtolower((string) ($app['hod_approval_status'] ?? '')) !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Cannot select this candidate. Please complete HOD approval first.']);
            exit();
        }
    } else {
        if (!in_array($old_status_key, ['exam_sent', 'exam_mail_sent', 'exam_qualified', 'hod_approved', 'selected'], true)) {
            echo json_encode(['success' => false, 'message' => 'Cannot select this candidate before the exam is sent or qualified.']);
            exit();
        }
    }
}

// ── HOD Approval Email flow ────────────────────────────────────────────────
if ($new_status === 'HOD Pending') {
    // Only applicable for currently pursuing students
    if (is_passed_out_student($education_status, $app['student_type'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'HOD approval is not required for passed-out or graduated students']);
        exit();
    }

    if (empty($hod_email)) {
        echo json_encode(['success' => false, 'message' => 'HOD email not found. Please ensure student has filled HOD details in their application.']);
        exit();
    }

    // Generate a unique secure token
    $hod_token = bin2hex(random_bytes(32));
    $esc_token = mysqli_real_escape_string($conn, $hod_token);

    // Update application with HOD Pending and token
    $update_hod_sql = "UPDATE internship_applications SET
        status = 'HOD Pending',
        hod_approval_status = 'Pending',
        hod_status = 'pending',
        hod_token = '$esc_token',
        hod_approval_sent_at = NOW()
        WHERE id = $app_id";

    if (!mysqli_query($conn, $update_hod_sql)) {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . mysqli_error($conn)]);
        exit();
    }

    // Build stateless approve/reject URLs
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base_dir === '') {
        $base_dir = '/';
    }
    $base_url = $scheme . '://' . $host . $base_dir;
    $approve_url = rtrim($base_url, '/') . '/hod_approval_action.php?application_id=' . urlencode($app_id) . '&action=approve&token=' . urlencode($hod_token);
    $reject_url  = rtrim($base_url, '/') . '/hod_approval_action.php?application_id=' . urlencode($app_id) . '&action=reject&token=' . urlencode($hod_token);

    // Send HOD approval email
    $hod_subject = "Action Required: Internship Approval for $student_name – IMP";
    $hod_message = "Dear " . ($hod_name ?: 'HOD') . ",\n\nYour student **$student_name** has applied for the internship position:\n\n\"$internship_title\"\n\nAs this student is Currently Pursuing their studies, your approval is required before they can be selected for this opportunity.\n\nPlease click one of the buttons in this email to Approve or Reject this request.\n\nApprove Link: $approve_url\nReject Link: $reject_url\n\nThank you,\nIMP Team";

    // Use custom HTML for HOD email with two action buttons
    $hod_html_body = "<!DOCTYPE html><html><head><meta charset='utf-8'><style>
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8fafc;margin:0;padding:0;}
        .container{max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;}
        .header{background:#0f172a;padding:32px 40px;text-align:center;border-bottom:4px solid #2563eb;}
        .header h1{color:#fff;margin:0;font-size:24px;font-weight:800;}
        .header p{color:#94a3b8;margin:4px 0 0;font-size:13px;text-transform:uppercase;letter-spacing:.1em;}
        .content{padding:40px;}
        .greeting{font-size:18px;font-weight:700;color:#0f172a;margin-bottom:16px;}
        .message{font-size:15px;color:#334155;margin-bottom:28px;}
        .card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:28px;}
        .card table{width:100%;border-collapse:collapse;}
        .card td{padding:6px 0;font-size:14px;}
        .card td.label{color:#64748b;font-weight:600;width:40%;}
        .card td.value{color:#0f172a;font-weight:700;}
        .btns{display:flex;gap:12px;justify-content:center;margin-top:20px;}
        .btn-approve{display:inline-block;background:#16a34a;color:#fff!important;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;}
        .btn-reject{display:inline-block;background:#dc2626;color:#fff!important;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;}
        .footer{background:#f1f5f9;padding:24px 40px;text-align:center;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;}
    </style></head><body>
    <div class='container'>
        <div class='header'><h1>IMP</h1><p>Internship Management Platform</p></div>
        <div class='content'>
            <div class='greeting'>Dear " . htmlspecialchars($hod_name ?: 'HOD') . ",</div>
            <div class='message'>Your student <strong>" . htmlspecialchars($student_name) . "</strong> has applied for an internship and requires your HOD approval.</div>
            <div class='card'>
                <table>
                    <tr><td class='label'>Student</td><td class='value'>" . htmlspecialchars($student_name) . "</td></tr>
                    <tr><td class='label'>Internship</td><td class='value'>" . htmlspecialchars($internship_title) . "</td></tr>
                    <tr><td class='label'>Status</td><td class='value'>Awaiting HOD Approval</td></tr>
                </table>
            </div>
            <p style='text-align:center;color:#334155;font-size:14px;'>Please click one of the buttons below to approve or reject this request:</p>
            <div class='btns'>
                <a href='" . htmlspecialchars($approve_url) . "' class='btn-approve'>✓ Approve</a>
                <a href='" . htmlspecialchars($reject_url) . "' class='btn-reject'>✗ Reject</a>
            </div>
            <p style='text-align:center;margin-top:20px;font-size:12px;color:#94a3b8;'>This link is unique and can only be used once. No login required.</p>
        </div>
        <div class='footer'><p>This is an automated notification from IMP.</p></div>
    </div></body></html>";

    // Send directly using PHPMailer via sendEmail if available
    if (function_exists('sendEmail')) {
        sendEmail($hod_email, ($hod_name ?: 'HOD'), $hod_subject, $hod_html_body);
    } else {
        sendEmailNotification($hod_email, $hod_subject, $hod_message, [
            'event'       => 'HOD Approval Request',
            'student'     => $student_name,
            'internship'  => $internship_title,
            'action_url'  => $approve_url,
            'action_label'=> 'Approve Student'
        ]);
    }

    // Log status history
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);
    $notes_escaped = mysqli_real_escape_string($conn, "HOD approval email sent to: $hod_email");
    mysqli_query($conn, "INSERT INTO application_status_history 
        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
        VALUES ($app_id, '$old_status', 'HOD Pending', '$user_role', '$updated_by_name', '$notes_escaped')");

    // Notify the student
    $student_subject = "Your application is pending HOD approval";
    $student_message = "Dear $student_name,\n\nYour application for \"$internship_title\" is awaiting HOD approval. Your HOD has been emailed at $hod_email, and you will be notified once they respond.\n\nBest regards,\nIMP Team";
    sendStudentNotification($student_user_id, $student_name, $student_subject, $student_message, [
        'event' => 'HOD Approval Request',
        'internship' => $internship_title,
        'status' => 'Awaiting HOD approval',
        'hod_name' => $hod_name,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ]);

    $notif_msg = mysqli_real_escape_string($conn, "Your HOD ($hod_name) has been sent an approval request for your internship application.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
        VALUES ($student_user_id, 'HOD Approval Requested', 'info', '$notif_msg')");

    echo json_encode(['success' => true, 'message' => "HOD approval email sent to $hod_email. Status updated to HOD Pending."]);
    exit();
}

// ── Block direct selection of pursuing students without HOD approval ────────────
if ($new_status === 'Selected') {
    $is_pursuing_be = is_pursuing_student($education_status, $app['student_type'] ?? null);

    if ($is_pursuing_be) {
        $current_hod_status = $app['hod_approval_status'] ?? '';
        if (strtolower($current_hod_status) !== 'approved' && $old_status !== 'HOD Approved') {
            echo json_encode(['success' => false, 'message' => 'Cannot select this candidate. Please complete HOD approval first (Send for HOD Approval → HOD Approved → then Select).']);
            exit();
        }
    }
}

// ── Standard status update ────────────────────────────────────────────────
$normalized_status = $new_status;
if (in_array(strtolower(trim($new_status)), ['confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'])) {
    $normalized_status = 'Selected';
}

$new_status_escaped = mysqli_real_escape_string($conn, $normalized_status);
$selected_at_sql  = ($normalized_status === 'Selected') ? ", selected_by = $user_id, selected_at = NOW()" : '';
$final_status_sql = ($normalized_status === 'Selected' || $normalized_status === 'Rejected') ? ", final_selection_status = '$new_status_escaped'" : '';
$update_sql = "UPDATE internship_applications SET status = '$new_status_escaped' $selected_at_sql $final_status_sql WHERE id = $app_id";

if (mysqli_query($conn, $update_sql)) {
    // Check and add to talent pool if eligible (e.g., for 'Selected' status)
    // Talent pool logic: add students with 'Selected' status
    if ($new_status === 'Selected') {
        $tp_check_sql = "SELECT id, user_id FROM internship_applications WHERE id = $app_id LIMIT 1";
        $tp_check = mysqli_query($conn, $tp_check_sql);
        if ($tp_check && $tp_check_row = mysqli_fetch_assoc($tp_check)) {
            $student_user_id_check = intval($tp_check_row['user_id']);
            // Check if this student already has a talent pool entry
            $dup_check = mysqli_query($conn, "SELECT id FROM internship_applications WHERE user_id = $student_user_id_check AND in_talent_pool = 1 AND id != $app_id LIMIT 1");
            if ($dup_check && mysqli_num_rows($dup_check) > 0) {
                // Prevent duplicate talent pool entries
                mysqli_query($conn, "UPDATE internship_applications SET talent_pool_status = 'Yes', in_talent_pool = 0 WHERE id = $app_id");
            } else {
                // Add to talent pool
                mysqli_query($conn, "UPDATE internship_applications SET in_talent_pool = 1, talent_pool_status = 'Yes' WHERE id = $app_id");
            }
        }
    }

    // Get updater's name
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);

    // Insert into status history
    $notes_escaped = mysqli_real_escape_string($conn, $notes ?: "Status updated by $user_role");
    $history_sql = "INSERT INTO application_status_history 
                        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) 
                    VALUES ($app_id, '$old_status', '$new_status_escaped', '$user_role', '$updated_by_name', '$notes_escaped')";
    mysqli_query($conn, $history_sql);

    // Post-update actions
    $student_subject = "Application Update: $normalized_status";
    $student_message = "Dear $student_name,\n\nYour internship application for \"$internship_title\" has been updated to \"$normalized_status\".\n\nPlease log in to the platform for the latest details and next steps.\n\nBest regards,\nIMP Team";
    $notif_title = 'Application Status Updated';
    $notif_type = 'info';
    $notif_msg = "Your application status has changed to \"$normalized_status\".";

    switch ($normalized_status) {
        case 'HR Review':
            $student_subject = "Your application is under HR review";
            $student_message = "Dear $student_name,\n\nYour application for \"$internship_title\" is now under HR review. We will notify you once there is an update.\n\nBest regards,\nIMP Team";
            $notif_title = 'HR Review';
            break;
        case 'Shortlisted':
            $student_subject = "Your application has been shortlisted";
            $student_message = "Dear $student_name,\n\nYour application for \"$internship_title\" has been shortlisted and will be considered for the next phase of the internship process.\n\nWe will update you with the next steps soon.\n\nBest regards,\nIMP Team";
            $notif_title = 'Shortlisted';
            $notif_type = 'success';
            break;
        case 'Exam Mail Sent':
            $student_subject = "Exam Details for " . $internship_title;
            $student_message = "Dear $student_name,\n\nYour application for \"$internship_title\" has progressed and the HR team has sent you the exam details. Please check your dashboard.\n\nBest regards,\nIMP Team";
            $notif_title = 'Exam Mail Sent';
            $notif_type  = 'info';
            $notif_msg   = "Your exam details have been sent. Please check your dashboard.";
            break;
        case 'Selected':
            $student_subject = "Congratulations! You have been selected for the internship";
            $student_message = "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship: \"$internship_title\".\n\nThe HR team will send your confirmation letter separately once it is ready.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator.\n\nBest regards,\nIMP Team";
            $notif_title = 'Internship Selected';
            $notif_type = 'success';
            break;
        case 'Project Assigned':
            $student_subject = "Project Assigned";
            $student_message = "Dear $student_name,\n\nYour project and mentor have been assigned for \"$internship_title\". Please log in to your dashboard to start the internship.\n\nBest regards,\nIMP Team";
            $notif_title = 'Project Assigned';
            $notif_type = 'success';
            break;
        case 'Internship Active':
            $student_subject = "Your internship is now active";
            $student_message = "Dear $student_name,\n\nYour internship for \"$internship_title\" is now active. Please log your daily logs regularly.\n\nBest regards,\nIMP Team";
            $notif_title = 'Internship Active';
            $notif_type = 'success';
            break;
        case 'Rejected':
            $student_subject = "Update: Internship application not selected";
            $student_message = "Dear $student_name,\n\nWe are sorry to inform you that your application for \"$internship_title\" was not selected at this time.\n\nThank you for applying and keep an eye out for future opportunities.\n\nBest regards,\nIMP Team";
            $notif_title = 'Application Rejected';
            break;
        case 'Active Intern':
            $student_subject = "Your internship status has been updated to Active Intern";
            $student_message = "Dear $student_name,\n\nYour internship application for \"$internship_title\" is now active. Please continue with your assigned tasks and log your progress regularly.\n\nBest regards,\nIMP Team";
            $notif_title = 'Active Internship';
            $notif_type = 'success';
            break;
    }

    sendStudentNotification($student_user_id, $student_name, $student_subject, $student_message, [
        'event' => 'Application Status Update',
        'internship' => $internship_title,
        'status' => $new_status,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application'
    ]);

    $notif_msg = mysqli_real_escape_string($conn, $notif_msg);
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message) VALUES ($student_user_id, '$notif_title', '$notif_type', '$notif_msg')");
    $response_message = ($normalized_status === 'Selected') ? 'Candidate selected successfully.' : "Application status updated to $new_status.";
    echo json_encode(['success' => true, 'message' => $response_message]); 
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . mysqli_error($conn)]);
    exit();
}} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_details' => get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine()
    ]);
    exit();
}