<?php
session_start();
define('INCLUDE_CHECK', true);
include_once __DIR__ . '/includes/auth.php';
require_hr_or_admin();
include "db.php";
include "includes/mail_helper.php";
require_once __DIR__ . '/includes/workflow_helper.php';

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($app_id <= 0) {
    header("Location: hr_applications.php?error=invalid_id");
    exit();
}

$user_id = current_user_id();

// Fetch application and user/student info
$query = "SELECT a.id, a.status, a.user_id, a.aadhaar_status, a.pan_status, a.education_status,
                 sp.student_type, u.full_name, u.email
          FROM internship_applications a
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
$student_type = $app['student_type'] ?? 'pursuing';
$education_status = $app['education_status'] ?? '';
$is_pursuing = ($student_type === 'pursuing' || $education_status === 'Currently Pursuing' || $education_status === 'Pursuing');

// Check Document Verification Type
if ($type === 'aadhaar') {
    $new_status = ($action === 'reject') ? 'rejected' : 'verified';
    $update = $conn->prepare("UPDATE internship_applications SET aadhaar_status = ?, aadhaar_verified_by = ?, aadhaar_verified_at = NOW() WHERE id = ?");
    $update->bind_param("sii", $new_status, $user_id, $app_id);
    if ($update->execute()) {
        log_status_change('internship_applications_aadhaar', $app_id, $app['aadhaar_status'], $new_status, 'Aadhaar ' . $new_status . ' by HR');
        
        // Notify student
        $notif_title = "Aadhaar " . ucfirst($new_status);
        $notif_msg = "Your Aadhaar document has been " . $new_status . " by HR.";
        add_notification($student_id, 'student', $notif_title, $notif_msg, ($new_status === 'verified') ? 'success' : 'error');
        
        // Send email
        $subject = "Aadhaar Document " . ucfirst($new_status) . " - IMP";
        $email_body = "<p>Dear $student_name,</p><p>Your Aadhaar document has been " . $new_status . " by HR.</p><p>Best regards,<br>IMP Team</p>";
        if (function_exists('sendEmail')) {
            sendEmail($app['email'], $student_name, $subject, $email_body);
        } else {
            sendEmailNotification($app['email'], $subject, "Dear $student_name,\n\nYour Aadhaar document has been " . $new_status . " by HR.\n\nBest regards,\nIMP Team");
        }
    }
    header("Location: hr_applicant_detail.php?app_id=$app_id&success=aadhaar_$new_status");
    exit();
} elseif ($type === 'pan') {
    $new_status = ($action === 'reject') ? 'rejected' : 'verified';
    $update = $conn->prepare("UPDATE internship_applications SET pan_status = ?, pan_verified_by = ?, pan_verified_at = NOW() WHERE id = ?");
    $update->bind_param("sii", $new_status, $user_id, $app_id);
    if ($update->execute()) {
        log_status_change('internship_applications_pan', $app_id, $app['pan_status'], $new_status, 'PAN ' . $new_status . ' by HR');
        
        // Notify student
        $notif_title = "PAN " . ucfirst($new_status);
        $notif_msg = "Your PAN document has been " . $new_status . " by HR.";
        add_notification($student_id, 'student', $notif_title, $notif_msg, ($new_status === 'verified') ? 'success' : 'error');
        
        // Send email
        $subject = "PAN Document " . ucfirst($new_status) . " - IMP";
        $email_body = "<p>Dear $student_name,</p><p>Your PAN document has been " . $new_status . " by HR.</p><p>Best regards,<br>IMP Team</p>";
        if (function_exists('sendEmail')) {
            sendEmail($app['email'], $student_name, $subject, $email_body);
        } else {
            sendEmailNotification($app['email'], $subject, "Dear $student_name,\n\nYour PAN document has been " . $new_status . " by HR.\n\nBest regards,\nIMP Team");
        }
    }
    header("Location: hr_applicant_detail.php?app_id=$app_id&success=pan_$new_status");
    exit();
}

// Check Selection Actions (for passed_out candidates)
if ($action === 'approve') {
    // Both documents must be verified
    if ($app['aadhaar_status'] !== 'verified' || $app['pan_status'] !== 'verified') {
        header("Location: hr_applicant_detail.php?app_id=$app_id&error=unverified_docs");
        exit();
    }
    
    // For pursuing candidates, this page shouldn't allow direct selection, they must be HOD Approved first
    if ($is_pursuing && $app['status'] !== 'HOD Approved') {
        header("Location: hr_applicant_detail.php?app_id=$app_id&error=requires_hod");
        exit();
    }
    
    // Update status to Selected
    $update = $conn->prepare("UPDATE internship_applications SET status = 'Selected', final_status = 'selected', hr_status = 'Selected', selected_by = ?, selected_at = NOW() WHERE id = ?");
    $update->bind_param("ii", $user_id, $app_id);
    if ($update->execute()) {
        log_status_change('internship_applications', $app_id, $app['status'], 'Selected', 'Passed-out student directly approved by HR');
        
        // Notify student
        $notif_title = "Internship Selected";
        $notif_msg = "Congratulations! You have been selected for the internship.";
        add_notification($student_id, 'student', $notif_title, $notif_msg, 'success');
        
        // Send email
        $subject = "Congratulations! You are selected - IMP";
        $email_body = "<p>Dear $student_name,</p><p>We are pleased to inform you that you have been selected for the internship.</p><p>Best regards,<br>IMP Team</p>";
        if (function_exists('sendEmail')) {
            sendEmail($app['email'], $student_name, $subject, $email_body);
        } else {
            sendEmailNotification($app['email'], $subject, "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship.\n\nBest regards,\nIMP Team");
        }
    }
    header("Location: hr_applicant_detail.php?app_id=$app_id&success=approved");
    exit();
} elseif ($action === 'reject') {
    // Update status to Rejected
    $update = $conn->prepare("UPDATE internship_applications SET status = 'Rejected', final_status = 'rejected' WHERE id = ?");
    $update->bind_param("i", $app_id);
    if ($update->execute()) {
        log_status_change('internship_applications', $app_id, $app['status'], 'Rejected', 'Candidate rejected by HR');
        
        // Notify student
        $notif_title = "Application Rejected";
        $notif_msg = "Your internship application has been rejected.";
        add_notification($student_id, 'student', $notif_title, $notif_msg, 'error');
    }
    header("Location: hr_applicant_detail.php?app_id=$app_id&success=rejected");
    exit();
}

header("Location: hr_applicant_detail.php?app_id=$app_id");
exit();
