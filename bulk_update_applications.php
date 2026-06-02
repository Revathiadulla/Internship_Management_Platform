<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_ajax_module_access('applications');
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
header('Content-Type: application/json');

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$application_ids = isset($_POST['application_ids']) ? $_POST['application_ids'] : [];
$allowed_actions = [
    'move_to_test_completed',
    'move_to_interview_scheduled',
    'move_to_hr_round',
    'move_to_hod_approved',
    'select_candidate',
    'move_to_offer_sent',
    'move_to_onboarding_completed',
    'reject',
    'verification_pending',
    'verify',
    'verification_rejected',
    'delete'
];


if (!in_array($action, $allowed_actions, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid bulk action.']);
    exit();
}

if (!is_array($application_ids) || count($application_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No applications selected.']);
    exit();
}

$clean_ids = array_values(array_filter(array_unique(array_map('intval', $application_ids)), fn($id) => $id > 0));
if (count($clean_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid application IDs were provided.']);
    exit();
}

$ids_csv = implode(',', $clean_ids);
$user_role = current_user_role();
$user_id = current_user_id();
$updated_by_name = 'HR';

$name_stmt = $conn->prepare("SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1");
$name_stmt->bind_param("i", $user_id);
$name_stmt->execute();
$name_res = $name_stmt->get_result();
if ($name_res && $name_row = $name_res->fetch_assoc()) {
    $updated_by_name = $name_row['full_name'] ?: $updated_by_name;
}

$app_query = "SELECT a.id, a.user_id, a.status, a.verification_status,
                     COALESCE(i.title, a.internship_name) AS internship_title,
                     COALESCE(u.full_name, sp.full_name) AS student_name
              FROM internship_applications a
              LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
              LEFT JOIN users u ON a.user_id = u.id
              LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
              WHERE a.id IN ($ids_csv) AND a.is_deleted = 0";
$app_result = mysqli_query($conn, $app_query);

$apps = [];
while ($row = mysqli_fetch_assoc($app_result)) {
    $apps[$row['id']] = $row;
}

if (count($apps) === 0) {
    echo json_encode(['success' => false, 'message' => 'Selected applications were not found.']);
    exit();
}

$notif_type_map = [
    'Test Completed' => 'test',
    'Interview Scheduled' => 'info',
    'HR Round' => 'info',
    'HOD Approved' => 'approved',
    'Selected' => 'approved',
    'Offer Sent' => 'approved',
    'Onboarding Completed' => 'approved',
    'Rejected' => 'rejected',
    'Pending' => 'verification',
    'Verified' => 'verification',
    'Verification Rejected' => 'verification',
    'Deleted' => 'deleted',
];

function sendBulkEmailNotification($recipient, $subject, $messageText, $metadata = []) {
    if (function_exists('sendEmailNotification')) {
        return sendEmailNotification($recipient, $subject, $messageText, $metadata);
    }
    return false;
}

$changed_count = 0;
$deleted_count = 0;

// Start transaction
mysqli_begin_transaction($conn);

try {
    if ($action !== 'delete') {
        if (in_array($action, ['verification_pending', 'verify', 'verification_rejected'], true)) {
            $verification_map = [
                'verification_pending' => 'Pending',
                'verify' => 'Verified',
                'verification_rejected' => 'Rejected',
            ];
            $new_verification = $verification_map[$action];

            $update_stmt = $conn->prepare("UPDATE internship_applications SET verification_status = ? WHERE id = ? AND is_deleted = 0");
            $history_stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");

            foreach ($apps as $app) {
                $old_verification = $app['verification_status'] ?: 'Pending';
                if ($old_verification === $new_verification) {
                    continue;
                }

                $application_id = intval($app['id']);
                $title = $app['internship_title'] ?: 'Internship application';
                $student_name = $app['student_name'] ?: 'Student';
                $notes = 'Bulk verification status update applied by HR.';

                $update_stmt->bind_param("si", $new_verification, $application_id);
                $update_stmt->execute();

                $old_verification_label = "Verification: $old_verification";
                $new_verification_label = "Verification: $new_verification";
                $history_stmt->bind_param("isssss", $application_id, $old_verification_label, $new_verification_label, $user_role, $updated_by_name, $notes);
                $history_stmt->execute();

                $notif_title = "Document Verification: $new_verification";
                $notif_msg = "Your document verification status is now $new_verification for \"$title\".";
                $notif_type = $notif_type_map[$new_verification] ?? 'verification';
                $notif_stmt->bind_param("isss", $app['user_id'], $notif_type, $notif_title, $notif_msg);
                $notif_stmt->execute();

                sendBulkEmailNotification(intval($app['user_id']), "IMP Verification Update: $title", "Dear $student_name,\n\nYour document verification status has been updated to $new_verification for \"$title\".\n\nPlease log in to review your application.", [
                    'event' => 'Document Verification',
                    'action_url' => 'http://localhost/IMP/student_applications.php',
                    'action_label' => 'View Application Status'
                ]);

                $changed_count++;
            }
        } else {
            $status_map = [
                'move_to_test_completed' => 'Test Completed',
                'move_to_interview_scheduled' => 'Interview Scheduled',
                'move_to_hr_round' => 'HR Round',
                'move_to_hod_approved' => 'HOD Approved',
                'select_candidate' => 'Selected',
                'move_to_offer_sent' => 'Offer Sent',
                'move_to_onboarding_completed' => 'Onboarding Completed',
                'reject' => 'Rejected',
            ];
            $new_status = $status_map[$action] ?? null;
            if ($new_status === null) {
                throw new Exception('Unsupported bulk action.');
            }

            $update_stmt = $conn->prepare("UPDATE internship_applications SET status = ? WHERE id = ? AND is_deleted = 0");
            $history_stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");

            foreach ($apps as $app) {
                if ($app['status'] === $new_status) {
                    continue;
                }

                $application_id = intval($app['id']);
                $old_status = $app['status'] ?: 'Applied';
                $title = $app['internship_title'] ?: 'Internship application';
                $student_name = $app['student_name'] ?: 'Student';
                $notes = 'Bulk status update applied by HR.';

                $update_stmt->bind_param("si", $new_status, $application_id);
                $update_stmt->execute();

                $history_stmt->bind_param("isssss", $application_id, $old_status, $new_status, $user_role, $updated_by_name, $notes);
                $history_stmt->execute();

                $notif_type = $notif_type_map[$new_status] ?? 'info';
                $notif_title = "Application Status: $new_status";
                $notif_msg = "Your application status has been updated to \"$new_status\" for \"$title\".";
                $notif_stmt->bind_param("isss", $app['user_id'], $notif_type, $notif_title, $notif_msg);
                $notif_stmt->execute();

                sendBulkEmailNotification(intval($app['user_id']), "IMP Application Status Update: $new_status for $title", "Dear $student_name,\n\nYour application status for \"$title\" has changed from $old_status to $new_status.\n\nPlease log in to review the next steps.", [
                    'event' => 'Application Status Update',
                    'action_url' => 'http://localhost/IMP/student_applications.php',
                    'action_label' => 'View Application Status'
                ]);

                $changed_count++;
            }
        }
    } else {
        // Delete action
        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
        $history_stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($apps as $app) {
            $application_id = intval($app['id']);
            $old_status = $app['status'] ?: 'Applied';
            $title = $app['internship_title'] ?: 'Internship application';
            $student_name = $app['student_name'] ?: 'Student';
            $notif_title = "Application Deleted";
            $notif_msg = "Your application for \"$title\" has been deleted by HR.";
            
            $notif_stmt->bind_param("isss", $app['user_id'], $notif_type_map['Deleted'], $notif_title, $notif_msg);
            $notif_stmt->execute();

            $notes = 'Bulk soft-delete applied by HR.';
            $deleted_label = 'Deleted';
            $history_stmt->bind_param("isssss", $application_id, $old_status, $deleted_label, $user_role, $updated_by_name, $notes);
            $history_stmt->execute();

            sendBulkEmailNotification(intval($app['user_id']), "IMP Application Deleted: $title", "Dear $student_name,\n\nYour application for \"$title\" has been removed by HR. If you have questions, contact support.", [
                'event' => 'Application Deleted',
                'action_url' => 'http://localhost/IMP/student_applications.php',
                'action_label' => 'View Notifications'
            ]);
        }

        $delete_extra = '';
        $deleted_at_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_at'");
        if ($deleted_at_check && mysqli_num_rows($deleted_at_check) > 0) {
            $delete_extra .= ', deleted_at = NOW()';
        }
        $deleted_by_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_by'");
        if ($deleted_by_check && mysqli_num_rows($deleted_by_check) > 0) {
            $delete_extra .= ", deleted_by = '" . mysqli_real_escape_string($conn, $updated_by_name) . "'";
        }

        $delete_sql = "UPDATE internship_applications SET is_deleted = 1$delete_extra WHERE id IN ($ids_csv)";
        if (mysqli_query($conn, $delete_sql)) {
            $deleted_count = mysqli_affected_rows($conn);
        }
    }

    // Commit transaction
    mysqli_commit($conn);

    $result_msg = '';
    if ($action === 'delete') {
        $result_msg = $deleted_count > 0 ? "$deleted_count application(s) deleted successfully." : 'No applications were deleted.';
    } else {
        $result_msg = $changed_count > 0 ? "$changed_count application(s) updated successfully." : 'No status changes were needed.';
    }

    echo json_encode(['success' => true, 'message' => $result_msg]);
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Bulk operation failed: ' . $e->getMessage()]);
    exit();
}
?>
