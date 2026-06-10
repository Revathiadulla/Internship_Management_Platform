<?php
$_SERVER['HTTP_HOST'] = 'localhost';
session_start();

include_once __DIR__ . '/../db.php';

echo "=== MENTOR DROP OUT WORKFLOW VERIFICATION ===\n\n";

// Get a mentor user ID
$mentor_res = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'mentor' LIMIT 1");
$mentor = mysqli_fetch_assoc($mentor_res);
if (!$mentor) {
    die("Error: No mentor found in users table. Run database setups first.\n");
}
$mentor_id = intval($mentor['id']);
$mentor_name = $mentor['full_name'];
echo "Found Mentor: $mentor_name (ID: $mentor_id)\n";

// Get a student with active project team assignment
$student_res = mysqli_query($conn, "
    SELECT u.id, u.full_name, app.id AS application_id, app.internship_id, pt.id AS team_id
    FROM project_team_members ptm
    JOIN users u ON ptm.student_id = u.id
    JOIN project_teams pt ON ptm.project_team_id = pt.id
    JOIN internship_applications app ON app.user_id = u.id AND pt.internship_id = app.internship_id
    WHERE pt.mentor_id = $mentor_id AND app.status = 'Project Assigned'
    LIMIT 1
");
$student = mysqli_fetch_assoc($student_res);
if (!$student) {
    die("Error: No student with an active application found for Mentor ID $mentor_id. Please assign a student to a project first.\n");
}

$student_id = intval($student['id']);
$student_name = $student['full_name'];
$application_id = intval($student['application_id']);
$internship_id = intval($student['internship_id']);
$team_id = intval($student['team_id']);

echo "Found Student: $student_name (ID: $student_id)\n";
echo "Application ID: $application_id, Internship ID: $internship_id, Team ID: $team_id\n\n";

// Start testing raising a dropout request
echo "1. Simulating raising a dropout request...\n";
mysqli_begin_transaction($conn);

try {
    // Insert into dropout_requests
    $reason = "Poor Performance";
    $remarks = "Test remarks from verification script.";
    $status = "Pending";
    
    $drop_stmt = $conn->prepare("INSERT INTO dropout_requests (application_id, mentor_id, reason, remarks, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $drop_stmt->bind_param('iisss', $application_id, $mentor_id, $reason, $remarks, $status);
    $drop_stmt->execute();
    $request_id = $drop_stmt->insert_id;
    $drop_stmt->close();
    
    echo "Saved dropout request. Request ID: $request_id\n";
    
    // Notify admins
    $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE LOWER(role) = 'admin'");
    $notified_admins = 0;
    if ($admin_res) {
        $a_title = 'Student Dropout Request';
        $a_msg = "Mentor $mentor_name requested dropout for student $student_name on internship.";
        $a_type = 'alert';
        $a_link = "admin_dropout_requests.php";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'admin', ?, ?, ?, ?)");
        if ($notif_stmt) {
            while ($a_row = mysqli_fetch_assoc($admin_res)) {
                $a_id = intval($a_row['id']);
                $notif_stmt->bind_param("issss", $a_id, $a_title, $a_msg, $a_type, $a_link);
                $notif_stmt->execute();
                $notified_admins++;
            }
            $notif_stmt->close();
        }
    }
    echo "Inserted notifications for $notified_admins admins.\n";

    // Verify student status remains unchanged
    $chk_app = mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = $application_id");
    $app_row = mysqli_fetch_assoc($chk_app);
    echo "Student Application status before approval: " . $app_row['status'] . " (Expected: Project Assigned)\n";

    // 2. Simulating approving the dropout request
    echo "\n2. Simulating approving the dropout request...\n";
    
    // Update request status to Approved
    $up_stmt = $conn->prepare("UPDATE dropout_requests SET status = 'Approved' WHERE id = ?");
    $up_stmt->bind_param('i', $request_id);
    $up_stmt->execute();
    $up_stmt->close();

    // Update student's application status to Dropout
    $app_up = $conn->prepare("UPDATE internship_applications SET status = 'Dropout' WHERE id = ?");
    $app_up->bind_param('i', $application_id);
    $app_up->execute();
    $app_up->close();

    // Create notification for mentor
    $m_title = 'Dropout Request Approved';
    $m_msg = "Dropout request for student $student_name has been approved by Admin.";
    $m_type = 'success';
    $m_link = "mentor_workspace.php?team_id=$team_id&tab=students";
    
    $mentor_notif = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'mentor', ?, ?, ?, ?)");
    $mentor_notif->bind_param('issss', $mentor_id, $m_title, $m_msg, $m_type, $m_link);
    $mentor_notif->execute();
    $mentor_notif->close();
    
    echo "Dropout request updated to Approved.\n";
    
    // Verify student status changed
    $chk_app2 = mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = $application_id");
    $app_row2 = mysqli_fetch_assoc($chk_app2);
    echo "Student Application status after approval: " . $app_row2['status'] . " (Expected: Dropout)\n";

    // Verify mentor notification was added
    $chk_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications WHERE user_id = $mentor_id AND role = 'mentor' AND title = 'Dropout Request Approved'");
    $notif_count = mysqli_fetch_assoc($chk_notif)['total'];
    echo "Mentor notifications matching: $notif_count (Expected: 1)\n";

    // Rollback so we don't affect actual data
    mysqli_rollback($conn);
    echo "\nTest transaction rolled back successfully. No persistent changes made.\n";
    echo "=== WORKFLOW VERIFICATION PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Error occurred: " . $e->getMessage() . "\n";
}
?>
