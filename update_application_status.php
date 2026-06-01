<?php
session_start();
include "db.php";
include_once __DIR__ . "/includes/mail_helper.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Only HR and Coordinator can update status
if ($user_role !== 'hr' && $user_role !== 'coordinator') {
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
                   sp.hod_name AS sp_hod_name, sp.hod_email AS sp_hod_email, sp.hod_phone AS sp_hod_phone
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
$education_status = $app['education_status'] ?? '';
$student_user_id  = intval($app['user_id']);
$internship_title = $app['internship_title'] ?? 'Internship';
$student_name     = $app['student_name'] ?? 'Student';

// Resolve HOD details (prefer application data, fallback to student_profiles)
$hod_name  = !empty($app['hod_name'])  ? $app['hod_name']  : ($app['sp_hod_name']  ?? '');
$hod_email = !empty($app['hod_email']) ? $app['hod_email'] : ($app['sp_hod_email'] ?? '');
$hod_phone = !empty($app['hod_phone']) ? $app['hod_phone'] : ($app['sp_hod_phone'] ?? '');

// Validate status transition based on role
$allowed_statuses = [];
if ($user_role === 'hr') {
    $allowed_statuses = ['HR Review', 'HOD Approval Pending', 'Selected', 'Rejected', 'Test Completed', 'HR Round'];
} elseif ($user_role === 'coordinator') {
    $allowed_statuses = ['HR Review', 'HOD Approval Pending', 'Selected', 'Rejected', 'Test Completed', 'HR Round', 'Active Intern'];
}

if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

// ── HOD Approval Email flow ────────────────────────────────────────────────
if ($new_status === 'HOD Approval Pending') {
    // Only applicable for Currently Pursuing students
    if ($education_status !== 'Currently Pursuing') {
        echo json_encode(['success' => false, 'message' => 'HOD approval is only required for Currently Pursuing students']);
        exit();
    }

    if (empty($hod_email)) {
        echo json_encode(['success' => false, 'message' => 'HOD email not found. Please ensure student has filled HOD details in their application.']);
        exit();
    }

    // Generate a unique secure token
    $hod_token = bin2hex(random_bytes(32));
    $esc_token = mysqli_real_escape_string($conn, $hod_token);

    // Update application with HOD Approval Pending and token
    $update_hod_sql = "UPDATE internship_applications SET
        status = 'HOD Approval Pending',
        hod_approval_status = 'Pending',
        hod_token = '$esc_token',
        hod_approval_sent_at = NOW()
        WHERE id = $app_id";

    if (!mysqli_query($conn, $update_hod_sql)) {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . mysqli_error($conn)]);
        exit();
    }

    // Build stateless approve/reject URLs
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $approve_url = $base_url . '/IMP/hod_approval.php?token=' . urlencode($hod_token) . '&decision=approve';
    $reject_url  = $base_url . '/IMP/hod_approval.php?token=' . urlencode($hod_token) . '&decision=reject';

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
        VALUES ($app_id, '$old_status', 'HOD Approval Pending', '$user_role', '$updated_by_name', '$notes_escaped')");

    // Notify the student
    $notif_msg = mysqli_real_escape_string($conn, "Your HOD ($hod_name) has been sent an approval request for your internship application.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
        VALUES ($student_user_id, 'HOD Approval Requested', 'info', '$notif_msg')");

    echo json_encode(['success' => true, 'message' => "HOD approval email sent to $hod_email. Status updated to HOD Approval Pending."]);
    exit();
}

// ── Block direct selection of Pursuing students without HOD approval ────────
if ($new_status === 'Selected' && $education_status === 'Currently Pursuing') {
    $current_hod_status = $app['hod_approval_status'] ?? 'Pending';
    if ($current_hod_status !== 'Approved' && $app['status'] !== 'HOD Approved') {
        echo json_encode(['success' => false, 'message' => 'Cannot select this student. HOD approval is required for Currently Pursuing students. Please send HOD approval email first.']);
        exit();
    }
}

// ── Standard status update ────────────────────────────────────────────────
$selected_at_sql = ($new_status === 'Selected') ? ", selected_by = $user_id, selected_at = NOW()" : '';
$update_sql = "UPDATE internship_applications SET status = '$new_status' $selected_at_sql WHERE id = $app_id";

if (mysqli_query($conn, $update_sql)) {
    checkAndAddToTalentPool($conn, $app_id);

    // Get updater's name
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);

    // Insert into status history
    $notes_escaped = mysqli_real_escape_string($conn, $notes ?: "Status updated by $user_role");
    $history_sql = "INSERT INTO application_status_history 
                        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) 
                    VALUES ($app_id, '$old_status', '$new_status', '$user_role', '$updated_by_name', '$notes_escaped')";
    mysqli_query($conn, $history_sql);

    // Notify the student
    $notif_msg = mysqli_real_escape_string($conn, "Your application status has been updated to: $new_status.");
    $notif_title = mysqli_real_escape_string($conn, "Application Status: $new_status");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message) 
                         SELECT user_id, '$notif_title', 'Application Update', '$notif_msg'
                         FROM internship_applications WHERE id = $app_id");

    // Send email to student
    $status_subject = "IMP Application Status Update: $new_status for $internship_title";
    $status_message = "Dear $student_name,\n\nYour application status for the \"$internship_title\" internship has been updated.\n\n- Previous Status: **$old_status**\n- New Status: **$new_status**\n" . 
                      (!empty($notes) ? "- Coordinator/HR Notes: *$notes*\n" : "") . "\n" .
                      ($new_status === 'Selected' ? "Congratulations! Please log in to your student dashboard to confirm and start your internship immediately." : "Please log in to your dashboard to review your status and check any further actions.");
    
    sendEmailNotification($student_user_id, $status_subject, $status_message, [
        'event' => 'Application Status Update',
        'internship_position' => $internship_title,
        'previous_status' => $old_status,
        'new_status' => $new_status,
        'notes' => $notes ?: 'Status updated by ' . $user_role,
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application Status'
    ]);

    echo json_encode(['success' => true, 'message' => "Status updated to $new_status"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . mysqli_error($conn)]);
}
