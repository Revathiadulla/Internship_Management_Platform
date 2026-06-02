<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('workflows');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$application_id = (int) ($_POST['application_id'] ?? 0);
$new_status = trim($_POST['new_status'] ?? '');

if ($application_id <= 0 || $new_status === '') {
    set_flash('Invalid status update request.');
    header('Location: workflows.php');
    exit();
}

// Verify dynamic status option against the 6 core stages
$stage_stmt = $conn->prepare("SELECT id FROM workflow_stages WHERE stage_name = ? AND is_active = 1 LIMIT 1");
$stage_stmt->bind_param("s", $new_status);
$stage_stmt->execute();
$stage_res = $stage_stmt->get_result();
if ($stage_res->num_rows === 0) {
    set_flash('Status not defined in workflow stages.');
    header('Location: workflows.php');
    exit();
}

$app_stmt = $conn->prepare("SELECT id, user_id, status FROM internship_applications WHERE id = ? LIMIT 1");
$app_stmt->bind_param("i", $application_id);
$app_stmt->execute();
$app_res = $app_stmt->get_result();
if ($app_res->num_rows === 0) {
    set_flash('Application not found.');
    header('Location: workflows.php');
    exit();
}
$app = $app_res->fetch_assoc();
$old_status = $app['status'] ?: 'Applied';
$user_id = (int) $app['user_id'];

mysqli_begin_transaction($conn);
try {
    $changed_by = current_user_id();
    $changed_by_name = 'Workflow';
    if ($changed_by > 0) {
        $u_stmt = $conn->prepare("SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1");
        $u_stmt->bind_param("i", $changed_by);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $changed_by_name = $u_row['full_name'] ?: 'Workflow';
        }
        $u_stmt->close();
    }

    if ($new_status === 'Rejected') {
        $update_stmt = $conn->prepare("UPDATE internship_applications SET status = ?, is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $new_status, $changed_by_name, $application_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE internship_applications SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $application_id);
    }
    $update_stmt->execute();

    sync_candidates_from_applications($conn);

    $candidate_id = 0;
    $cand_stmt = $conn->prepare("SELECT id FROM candidates WHERE user_id = ? LIMIT 1");
    $cand_stmt->bind_param("i", $user_id);
    $cand_stmt->execute();
    $cand_res = $cand_stmt->get_result();
    if ($cand_row = $cand_res->fetch_assoc()) {
        $candidate_id = (int) $cand_row['id'];
    }

    $changed_by = current_user_id() ?: 'NULL';
    $log_stmt = $conn->prepare("INSERT INTO workflow_logs (application_id, candidate_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?, ?)");
    $log_stmt->bind_param("iissi", $application_id, $candidate_id, $old_status, $new_status, $changed_by);
    $log_stmt->execute();

    $role_name = current_user_role() ?: 'hr';
    $history_stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name) VALUES (?, ?, ?, ?, 'Workflow')");
    $history_stmt->bind_param("isss", $application_id, $old_status, $new_status, $role_name);
    $history_stmt->execute();

    mysqli_commit($conn);
    set_flash('Workflow status updated.');
} catch (Exception $e) {
    mysqli_rollback($conn);
    set_flash('Failed to update workflow status: ' . $e->getMessage());
}

header('Location: workflows.php');
exit();
