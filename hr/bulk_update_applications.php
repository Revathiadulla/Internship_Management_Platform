<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_ajax_role(['hr', 'admin']);
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/mail_helper.php';

header('Content-Type: application/json');

function json_response($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if (!isset($_POST['application_ids']) || !is_array($_POST['application_ids']) || empty($_POST['application_ids'])) {
    json_response(false, 'No application IDs provided');
}

$app_ids = array_map('intval', $_POST['application_ids']);

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Backward compatibility with older verification status bulk call
if (empty($action) && isset($_POST['verification_status'])) {
    $v_status = trim($_POST['verification_status']);
    if ($v_status === 'Pending') $action = 'verification_pending';
    elseif ($v_status === 'Verified') $action = 'verify';
    elseif ($v_status === 'Rejected') $action = 'verification_rejected';
}

if (empty($action)) {
    json_response(false, 'No action provided');
}

// Begin transaction
mysqli_autocommit($conn, false);
$all_success = true;
$failed_ids = [];

foreach ($app_ids as $app_id) {
    // Fetch current details
    $app_res = mysqli_query($conn, "SELECT a.status, a.user_id, a.education_status, u.full_name, u.email, sp.student_type 
                                    FROM internship_applications a 
                                    LEFT JOIN users u ON a.user_id = u.id 
                                    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                                    WHERE a.id = $app_id");
    if (!$app_res || mysqli_num_rows($app_res) === 0) {
        $all_success = false;
        $failed_ids[] = $app_id;
        continue;
    }
    $app_data = mysqli_fetch_assoc($app_res);
    $student_user_id = intval($app_data['user_id']);
    $student_name = $app_data['full_name'] ?? 'Student';
    $student_email = $app_data['email'] ?? '';
    $student_type = $app_data['student_type'] ?? 'pursuing';
    $education_status = $app_data['education_status'] ?? '';
    $is_pursuing = ($student_type === 'pursuing' || $education_status === 'Currently Pursuing' || $education_status === 'Pursuing');
    $old_status = $app_data['status'];

    $update_query = "";
    $history_notes = "";
    $notif_title = "";
    $notif_msg = "";
    $notif_type = "info";

    switch ($action) {
        // mark_exam_completed is removed — exam tracking is external

        case 'move_to_hod_approved':
            if ($is_pursuing) {
                $update_query = "UPDATE internship_applications SET status = 'HOD Approved', hod_status = 'approved', hod_approval_status = 'Approved', hod_action_at = NOW() WHERE id = $app_id";
                $history_notes = "Marked HOD approval in bulk.";
                $notif_title = "HOD Approved";
                $notif_msg = "Your application has been approved by your HOD.";
                $notif_type = "success";
            } else {
                // Not applicable for passed-out
                continue 2;
            }
            break;

        case 'select_candidate':
            // Verify documents are verified
            $doc_check = mysqli_query($conn, "SELECT aadhaar_status, pan_status FROM internship_applications WHERE id = $app_id");
            $doc_row = mysqli_fetch_assoc($doc_check);
            if (($doc_row['aadhaar_status'] ?? '') !== 'verified' || ($doc_row['pan_status'] ?? '') !== 'verified') {
                $all_success = false;
                $failed_ids[] = $app_id;
                continue 2;
            }
            // Verify HOD status / Qualification status
            if ($is_pursuing) {
                if ($old_status !== 'HOD Approved' && $old_status !== 'Selected') {
                    $all_success = false;
                    $failed_ids[] = $app_id;
                    continue 2;
                }
            } else {
                // For passed-out students, require Exam Link Sent, Exam Mail Sent or Shortlisted status
                if (!in_array($old_status, ['Exam Link Sent', 'Exam Mail Sent', 'Shortlisted', 'HR Review'])) {
                    $all_success = false;
                    $failed_ids[] = $app_id;
                    continue 2;
                }
            }
            $update_query = "UPDATE internship_applications SET status = 'Selected', final_status = 'selected', selected_by = " . current_user_id() . ", selected_at = NOW() WHERE id = $app_id";
            $history_notes = "Selected candidate in bulk.";
            $notif_title = "Selected";
            $notif_msg = "Congratulations! You have been selected for the internship.";
            $notif_type = "success";
            break;

        case 'reject':
            $update_query = "UPDATE internship_applications SET status = 'Rejected', final_status = 'rejected' WHERE id = $app_id";
            $history_notes = "Rejected candidate in bulk.";
            $notif_title = "Application Rejected";
            $notif_msg = "Your internship application has been rejected.";
            $notif_type = "error";
            break;

        case 'verification_pending':
            $update_query = "UPDATE internship_applications SET verification_status = 'Pending' WHERE id = $app_id";
            $history_notes = "Document verification pending updated in bulk.";
            $notif_title = "Verification Pending";
            $notif_msg = "Your document verification status is now Pending.";
            break;

        case 'verify':
            $update_query = "UPDATE internship_applications SET verification_status = 'Verified' WHERE id = $app_id";
            $history_notes = "Documents verified in bulk.";
            $notif_title = "Verification Approved";
            $notif_msg = "Your document verification status is now Verified.";
            $notif_type = "success";
            break;

        case 'verification_rejected':
            $update_query = "UPDATE internship_applications SET verification_status = 'Rejected' WHERE id = $app_id";
            $history_notes = "Documents rejected in bulk.";
            $notif_title = "Verification Rejected";
            $notif_msg = "Your document verification status is now Rejected.";
            $notif_type = "error";
            break;

        case 'archive':
            $blocked_statuses = ['Applied', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approved', 'Selected', 'Project Assigned', 'Active Intern'];
            if (in_array($old_status, $blocked_statuses, true)) {
                $all_success = false;
                $failed_ids[] = $app_id;
                continue 2;
            }
            $update_query = "UPDATE internship_applications SET is_deleted = 1 WHERE id = $app_id";
            $history_notes = "Archived application by HR.";
            break;

        case 'delete':
            $update_query = "UPDATE internship_applications SET is_deleted = 1 WHERE id = $app_id";
            break;

        // Legacy compatibility: move_to_hr_round → HR Review
        case 'move_to_hr_round':
            $update_query = "UPDATE internship_applications SET status = 'HR Review' WHERE id = $app_id";
            $history_notes = "Status moved to HR Review in bulk.";
            break;
    }

    if (!empty($update_query)) {
        if (!mysqli_query($conn, $update_query)) {
            $all_success = false;
            $failed_ids[] = $app_id;
            continue;
        }

        // Auto transition status to HR Review if both documents verified
        if ($action === 'verify') {
            $get_curr_status_sql = mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = $app_id");
            $curr_status_row = mysqli_fetch_assoc($get_curr_status_sql);
            if ($curr_status_row['status'] === 'Applied') {
                mysqli_query($conn, "UPDATE internship_applications SET status = 'HR Review' WHERE id = $app_id");
                mysqli_query($conn, "INSERT INTO application_status_history 
                    (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                    VALUES ($app_id, 'Applied', 'HR Review', 'HR', 'System', 'Automatically transitioned to HR Review upon document verification.')");
            }
        }

        // Insert history
        if (!empty($history_notes) && $action !== 'delete') {
            $new_st_sql = mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = $app_id");
            $new_st_row = mysqli_fetch_assoc($new_st_sql);
            $new_st = $new_st_row['status'];
            mysqli_query($conn, "INSERT INTO application_status_history 
                (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                VALUES ($app_id, '$old_status', '$new_st', 'HR', 'HR Manager', '" . mysqli_real_escape_string($conn, $history_notes) . "')");
        }

        // Insert notification and send email
        if (!empty($notif_msg) && $student_user_id > 0) {
            mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, title, message) 
                                 VALUES ($student_user_id, '$notif_type', '" . mysqli_real_escape_string($conn, $notif_title) . "', '" . mysqli_real_escape_string($conn, $notif_msg) . "')");
            sendStudentNotification($student_user_id, $student_name, $notif_title, $notif_msg, [
                'event' => 'Bulk Update Status',
                'action_url' => 'http://localhost/IMP/student_dashboard.php',
                'action_label' => 'View Dashboard'
            ]);
        }
    }
}

if ($all_success) {
    mysqli_commit($conn);
    json_response(true, 'All selected applications updated successfully');
} else {
    mysqli_rollback($conn);
    $failed_list = implode(', ', $failed_ids);
    json_response(false, "Failed to update some applications: $failed_list. Please check prerequisites (e.g. verified documents or exam qualification).");
}
?>
