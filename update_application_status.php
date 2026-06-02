
<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_role(['hr', 'admin', 'mentor']);
header('Content-Type: application/json');
include "db.php";
include_once __DIR__ . "/includes/mail_helper.php";

$user_id = current_user_id();
$user_role = current_user_role();

// Get POST data
$app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($app_id <= 0 || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID or status']);
    exit();
}

// Fetch current application details using prepared statements
$stmt = $conn->prepare("SELECT id, status, education_status FROM internship_applications WHERE id = ? AND is_deleted = 0 LIMIT 1");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app_result = $stmt->get_result();

if ($app_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$app = $app_result->fetch_assoc();
$old_status = $app['status'];
$education_status = $app['education_status'];

// Validate status transitions for the expanded placement workflow
$allowed_statuses = ['Applied', 'Test Completed', 'Interview Scheduled', 'HR Round', 'HOD Approved', 'Selected', 'Offer Sent', 'Onboarding Completed', 'Rejected'];

if (!in_array($new_status, $allowed_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status option']);
    exit();
}

// Check HOD/Mentor-specific constraints
if ($user_role === 'mentor') {
    // 1. Fetch hod_email of this application to verify ownership
    $check_hod_stmt = $conn->prepare("SELECT hod_email FROM internship_applications WHERE id = ? LIMIT 1");
    $check_hod_stmt->bind_param("i", $app_id);
    $check_hod_stmt->execute();
    $hod_res = $check_hod_stmt->get_result()->fetch_assoc();
    $hod_email_db = $hod_res ? trim($hod_res['hod_email']) : '';
    
    $mentor_email = $_SESSION['email'] ?? '';
    
    if (empty($hod_email_db) || strcasecmp($hod_email_db, $mentor_email) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You are not the HOD designated for this application.']);
        exit();
    }
    
    // 2. Allow only HOD Approved or Rejected
    if ($new_status !== 'HOD Approved' && $new_status !== 'Rejected') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: HODs can only approve or reject applications.']);
        exit();
    }
}

if ($new_status === 'HOD Approved' && $education_status !== 'Pursuing') {
    echo json_encode(['success' => false, 'message' => 'HOD Approved status is only applicable for Pursuing students.']);
    exit();
}

// Define the valid forward-only pipeline order based on education status
if ($education_status === 'Pursuing') {
    $pipeline = ['Applied', 'Test Completed', 'Interview Scheduled', 'HR Round', 'HOD Approved', 'Selected', 'Offer Sent', 'Onboarding Completed'];
} else {
    $pipeline = ['Applied', 'Test Completed', 'Interview Scheduled', 'HR Round', 'Selected', 'Offer Sent', 'Onboarding Completed'];
}

// Block transitions out of Rejected (terminal)
if ($old_status === 'Rejected' && $new_status !== 'Rejected') {
    echo json_encode(['success' => false, 'message' => 'Cannot move application out of Rejected status.']);
    exit();
}

// Block backward moves in the pipeline (when not moving to Rejected)
if ($new_status !== 'Rejected') {
    $old_idx = array_search($old_status, $pipeline, true);
    $new_idx = array_search($new_status, $pipeline, true);
    if ($old_idx !== false && $new_idx !== false && $new_idx < $old_idx) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot move backwards from \"$old_status\" to \"$new_status\"."
        ]);
        exit();
    }
}


// Start database transaction
mysqli_begin_transaction($conn);

try {
    // Get updater's name
    $name_stmt = $conn->prepare("SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1");
    $name_stmt->bind_param("i", $user_id);
    $name_stmt->execute();
    $name_res = $name_stmt->get_result();
    $name_row = $name_res->fetch_assoc();
    $updated_by_name = $_SESSION['full_name'] ?? ($name_row ? $name_row['full_name'] : 'HR');
    $name_stmt->close();

    // Update application status
    if ($new_status === 'Rejected') {
        $update_stmt = $conn->prepare("UPDATE internship_applications SET status = ?, is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND is_deleted = 0");
        $update_stmt->bind_param("ssi", $new_status, $updated_by_name, $app_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE internship_applications SET status = ? WHERE id = ? AND is_deleted = 0");
        $update_stmt->bind_param("si", $new_status, $app_id);
    }
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update status column");
    }

    // Insert into status history — only on a real transition, skip same→same
    if ($old_status !== $new_status) {
        // Fetch student name & internship title for detailed activity logging
        $info_stmt = $conn->prepare("SELECT a.user_id, COALESCE(i.title, a.internship_name) as title, u.full_name FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0 LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
        $info_stmt->bind_param("i", $app_id);
        $info_stmt->execute();
        $info_res = $info_stmt->get_result()->fetch_assoc();
        $student_fullname = $info_res ? $info_res['full_name'] : 'Unknown';
        $internship_title = $info_res ? $info_res['title'] : 'Internship';
        
        log_activity($conn, 'Status Transition', "Updated status of candidate $student_fullname for \"$internship_title\" from $old_status to $new_status.");

        $notes_val = !empty($notes) ? $notes : null;
        $history_stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $history_stmt->bind_param("isssss", $app_id, $old_status, $new_status, $user_role, $updated_by_name, $notes_val);
        if (!$history_stmt->execute()) {
            throw new Exception("Failed to insert status history");
        }

        // Notify the student
        $notif_type_map = [
            'Selected'             => 'approved',
            'Rejected'             => 'rejected',
            'Test Completed'       => 'test',
            'Interview Scheduled'  => 'info',
            'HR Round'             => 'info',
            'HOD Approved'         => 'approved',
            'Offer Sent'           => 'approved',
            'Onboarding Completed' => 'approved',
            'Applied'              => 'info',
        ];

        $notif_type  = $notif_type_map[$new_status] ?? 'info';
        $notif_title = "Application Status: $new_status";
        $notif_msg   = "Your application status has been updated to \"$new_status\"." . (!empty($notes) ? " Note: $notes" : '');
        
        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) SELECT user_id, ?, ?, ? FROM internship_applications WHERE id = ?");
        $notif_stmt->bind_param("sssi", $notif_type, $notif_title, $notif_msg, $app_id);
        if (!$notif_stmt->execute()) {
            throw new Exception("Failed to insert student notification");
        }
    }

    // Commit transaction
    mysqli_commit($conn);

    // Send email notification (outside the transaction, in case mail server lags/fails)
    if ($old_status !== $new_status) {
        $app_details_stmt = $conn->prepare("SELECT a.user_id, COALESCE(i.title, a.internship_name) as title, u.full_name FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0 LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ? LIMIT 1");
        $app_details_stmt->bind_param("i", $app_id);
        $app_details_stmt->execute();
        $app_details_res = $app_details_stmt->get_result();
        
        if ($app_details_res && $app_details = $app_details_res->fetch_assoc()) {
            $student_uid      = intval($app_details['user_id']);
            $internship_title = $app_details['title'];
            $student_name     = $app_details['full_name'] ?: 'Student';

            $status_subject = "IMP Application Status Update: $new_status for $internship_title";
            $status_message = "Dear $student_name,\n\nYour application status for the \"$internship_title\" internship has been updated.\n\n- Previous Status: **$old_status**\n- New Status: **$new_status**\n" .
                              (!empty($notes) ? "- Coordinator/HR Notes: *$notes*\n" : "") . "\n" .
                              ($new_status === 'Selected' ? "Congratulations! Please log in to your student dashboard to confirm and start your internship immediately." : "Please log in to your dashboard to review your status and check any further actions.");

            sendEmailNotification($student_uid, $status_subject, $status_message, [
                'event'               => 'Application Status Update',
                'internship_position' => $internship_title,
                'previous_status'     => $old_status,
                'new_status'          => $new_status,
                'notes'               => $notes ?: 'Status updated by ' . $user_role,
                'action_url'          => 'http://localhost/IMP/student_applications.php',
                'action_label'        => 'View Application Status'
            ]);
        }
    }

    echo json_encode(['success' => true, 'message' => "Status updated to $new_status"]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
}
