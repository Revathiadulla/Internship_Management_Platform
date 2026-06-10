<?php
session_start();
define('INCLUDE_CHECK', true);
include_once __DIR__ . '/includes/auth.php';
require_hr_or_admin();
include "db.php";
include "includes/mail_helper.php";
include_once __DIR__ . '/includes/workflow_helper.php';

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
if ($app_id <= 0) {
    header("Location: hr_applications.php?error=invalid_id");
    exit();
}

$user_id = current_user_id();

// Fetch application details
$query = "SELECT a.id, a.status, a.user_id, a.aadhaar_status, a.pan_status, a.verification_status, a.education_status,
                 sp.student_type, u.full_name, u.email,
                 sp.hod_name AS sp_hod_name, sp.hod_email AS sp_hod_email, sp.hod_phone AS sp_hod_phone,
                 a.hod_name AS app_hod_name, a.hod_email AS app_hod_email, a.hod_phone AS app_hod_phone,
                 a.internship_name,
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
    header("Location: hr_applications.php?error=not_found");
    exit();
}
$app = $res->fetch_assoc();
$student_id = intval($app['user_id']);
$student_name = $app['full_name'] ?? 'Student';
$student_type = trim((string) ($app['student_type'] ?? ''));
$education_status = trim((string) ($app['education_status'] ?? ''));
$is_pursuing = is_pursuing_student($education_status, $student_type);
$is_passed_out = !$is_pursuing;
$internship_title = $app['internship_title'] ?? 'Internship';

$verification_value = trim((string) ($app['verification_status'] ?? ''));
$aadhaar_verified = strtolower((string) ($app['aadhaar_status'] ?? '')) === 'verified';
$pan_verified = strtolower((string) ($app['pan_status'] ?? '')) === 'verified';
$documents_verified = (strtolower($verification_value) === 'verified') || ($aadhaar_verified && $pan_verified);

if (!$documents_verified) {
    header("Location: hr_applicant_detail.php?app_id=$app_id&error=unverified_docs");
    exit();
}

if (!is_exam_sent_status($app['status'])) {
    header("Location: hr_applicant_detail.php?app_id=$app_id&error=requires_exam_mail");
    exit();
}

if (!$is_pursuing) {
    header("Location: hr_applicant_detail.php?app_id=$app_id&error=not_pursuing");
    exit();
}

$hod_email = !empty($app['app_hod_email']) ? trim($app['app_hod_email']) : (!empty($app['sp_hod_email']) ? trim($app['sp_hod_email']) : '');
$hod_name  = !empty($app['app_hod_name'])  ? trim($app['app_hod_name'])  : (!empty($app['sp_hod_name'])  ? trim($app['sp_hod_name'])  : 'HOD');
$hod_phone = !empty($app['app_hod_phone']) ? trim($app['app_hod_phone']) : (!empty($app['sp_hod_phone']) ? trim($app['sp_hod_phone']) : '');

if (empty($hod_email)) {
    header("Location: hr_applicant_detail.php?app_id=$app_id&error=no_hod_email");
    exit();
}

// Generate token
$hod_token = bin2hex(random_bytes(32));

// Update application status
$update = $conn->prepare("UPDATE internship_applications SET status = 'HOD Pending', hod_status = 'pending', hod_approval_status = 'Pending', final_status = 'pending', hod_token = ?, hod_approval_sent_at = NOW() WHERE id = ?");
$update->bind_param("si", $hod_token, $app_id);
if ($update->execute()) {
    log_status_change('internship_applications', $app_id, $app['status'], 'HOD Pending', 'Application forwarded to HOD for approval');
    
    // Notify student
    $notif_title = "Application Forwarded to HOD";
    $notif_msg = "Your application has been forwarded to your HOD ($hod_name) for approval.";
    add_notification($student_id, 'student', $notif_title, $notif_msg, 'info');

    // Notify HR
    $hr_notif_title = "Application Forwarded to HOD";
    $hr_notif_msg = "Application #$app_id for student $student_name has been forwarded to HOD ($hod_name) for approval.";
    add_notification($user_id, 'hr', $hr_notif_title, $hr_notif_msg, 'success');
    
    // Notify HOD via Email
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base_dir === '') {
        $base_dir = '/';
    }
    $base_url = $scheme . '://' . $host . $base_dir;
    
    $approve_url = rtrim($base_url, '/') . '/hod_approval_action.php?application_id=' . urlencode($app_id) . '&action=approve&token=' . urlencode($hod_token);
    $reject_url  = rtrim($base_url, '/') . '/hod_approval_action.php?application_id=' . urlencode($app_id) . '&action=reject&token=' . urlencode($hod_token);
    
    $subject = "Action Required: Internship Approval for $student_name – IMP";
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
            <div class='greeting'>Dear " . htmlspecialchars($hod_name) . ",</div>
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
        </div>
        <div class='footer'><p>This is an automated notification from IMP.</p></div>
    </div></body></html>";
    
    if (function_exists('sendEmail')) {
        sendEmail($hod_email, $hod_name, $subject, $hod_html_body);
    } else {
        sendEmailNotification($hod_email, $subject, "Dear $hod_name,\n\nYour student $student_name has applied for an internship: $internship_title. Please approve here: $approve_url or reject here: $reject_url\n\nBest regards,\nIMP Team");
    }
}

header("Location: hr_applicant_detail.php?app_id=$app_id&success=forwarded_to_hod");
exit();
