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
$app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($app_id <= 0 || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID or status']);
    exit();
}

// Fetch current application details
$app_sql = "SELECT id, status, education_status FROM internship_applications WHERE id = $app_id LIMIT 1";
$app_result = mysqli_query($conn, $app_sql);

if (mysqli_num_rows($app_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$app = mysqli_fetch_assoc($app_result);
$old_status = $app['status'];
$education_status = $app['education_status'];

// Validate status transition based on role
$allowed_statuses = [];
if ($user_role === 'hr') {
    $allowed_statuses = ['Applied', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];
} elseif ($user_role === 'coordinator') {
    $allowed_statuses = ['Applied', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];
}

if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

// Conditional workflow logic: Skip HOD Approved for Passed Out students
if ($education_status === 'Passed Out' && $new_status === 'HOD Approved') {
    echo json_encode(['success' => false, 'message' => 'HOD approval is not required for Passed Out students']);
    exit();
}

// Update application status
$update_sql = "UPDATE internship_applications SET status = '$new_status' WHERE id = $app_id";
if (mysqli_query($conn, $update_sql)) {
    checkAndAddToTalentPool($conn, $app_id);
    // Get updater's name
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : 'HR';

    // Insert into status history
    $notes_escaped = mysqli_real_escape_string($conn, $notes ?: "Status updated by $user_role");
    $history_sql = "INSERT INTO application_status_history 
                        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) 
                    VALUES ($app_id, '$old_status', '$new_status', '$user_role', '$updated_by_name', '$notes_escaped')";
    mysqli_query($conn, $history_sql);

    // Notify the student
    $notif_msg = mysqli_real_escape_string($conn, "Your application status has been updated to: $new_status.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, message) 
                         SELECT user_id, 'Application Update', '$notif_msg' 
                         FROM internship_applications WHERE id = $app_id");

    // Fetch details to send email
    $app_details_sql = "SELECT a.user_id, COALESCE(i.title, a.internship_name) as title, u.full_name 
                        FROM internship_applications a 
                        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                        LEFT JOIN users u ON a.user_id = u.id
                        WHERE a.id = $app_id LIMIT 1";
    $app_details_res = mysqli_query($conn, $app_details_sql);
    if ($app_details_res && $app_details = mysqli_fetch_assoc($app_details_res)) {
        $student_uid = $app_details['user_id'];
        $internship_title = $app_details['title'];
        $student_name = $app_details['full_name'] ?: 'Student';
        
        $status_subject = "IMP Application Status Update: $new_status for $internship_title";
        $status_message = "Dear $student_name,\n\nYour application status for the \"$internship_title\" internship has been updated.\n\n- Previous Status: **$old_status**\n- New Status: **$new_status**\n" . 
                          (!empty($notes) ? "- Coordinator/HR Notes: *$notes*\n" : "") . "\n" .
                          ($new_status === 'Selected' ? "Congratulations! Please log in to your student dashboard to confirm and start your internship immediately." : "Please log in to your dashboard to review your status and check any further actions.");
        
        sendEmailNotification($student_uid, $status_subject, $status_message, [
            'event' => 'Application Status Update',
            'internship_position' => $internship_title,
            'previous_status' => $old_status,
            'new_status' => $new_status,
            'notes' => $notes ?: 'Status updated by ' . $user_role,
            'action_url' => 'http://localhost/IMP/student_applications.php',
            'action_label' => 'View Application Status'
        ]);
    }

    echo json_encode(['success' => true, 'message' => "Status updated to $new_status"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . mysqli_error($conn)]);
}
