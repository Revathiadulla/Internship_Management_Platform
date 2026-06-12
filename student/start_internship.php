<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/mail_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$app_id  = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

if ($app_id <= 0) {
    header("Location: student_applications.php?err=" . urlencode("Invalid application."));
    exit();
}

// Fetch application — LEFT JOIN so static-card apps (internship_id = 0) work too
$check_sql = "SELECT a.id as app_id, a.status, a.user_id,
                     COALESCE(i.title, a.internship_name) AS title
              FROM internship_applications a
              LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
              WHERE a.id = '$app_id' AND a.user_id = '$user_id'
              LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    header("Location: student_applications.php?err=" . urlencode("Application not found."));
    exit();
}

$app = mysqli_fetch_assoc($check_result);

// Only allow starting if status is 'Project Assigned'
if ($app['status'] !== 'Project Assigned') {
    header("Location: student_applications.php?err=" . urlencode("You can only start an internship after a Project is Assigned."));
    exit();
}

$old_status = 'Project Assigned';
$new_status = 'Internship Active';
$title_escaped = mysqli_real_escape_string($conn, $app['title']);

// Update status to 'Started'
$update_sql = "UPDATE internship_applications SET status = '$new_status' WHERE id = '$app_id' AND user_id = '$user_id'";
if (mysqli_query($conn, $update_sql)) {

    // Log status change in history
    $student_name_res = mysqli_query($conn, "SELECT full_name FROM student_profiles WHERE user_id = '$user_id' LIMIT 1");
    $student_name_row = mysqli_fetch_assoc($student_name_res);
    $student_name = mysqli_real_escape_string($conn, $student_name_row['full_name'] ?? 'Student');

    mysqli_query($conn, "INSERT INTO application_status_history 
                            (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                         VALUES ('$app_id', '$old_status', '$new_status', 'Student', '$student_name', 
                                 'Student confirmed and started the internship.')");

    // Congratulations notification
    $notif_msg = mysqli_real_escape_string($conn,
        "Congratulations! You have officially started your internship: $title_escaped. Keep logging your daily activities!");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, type, message, is_read)
                         VALUES ('$user_id', 'Internship Started', '$notif_msg', 0)");

    // Send email notification for starting internship
    $start_subject = "Welcome Aboard! Internship Started: " . $app['title'];
    $start_message = "Dear $student_name,\n\nCongratulations! You have officially started your internship: \"" . $app['title'] . "\".\n\nWe are excited to have you on board! To ensure a successful internship experience, please log your daily tasks and hours using the Activity Tracker on your student dashboard regularly.\n\nGood luck, and make the most of this opportunity!";
    sendStudentNotification($user_id, $student_name, $start_subject, $start_message, [
        'event' => 'Internship Confirmed',
        'internship_position' => $app['title'],
        'started_date' => date('Y-m-d H:i:s'),
        'mentor_assigned' => 'Dr. Sarah Jenkins',
        'status' => 'Active Intern',
        'action_url' => 'http://localhost/IMP/student_dashboard.php',
        'action_label' => 'Go to Workspace Dashboard'
    ]);

    header("Location: student_dashboard.php?msg=" . urlencode("Internship Started! Welcome aboard. Start logging your daily activities."));
    exit();
}

header("Location: student_applications.php?err=" . urlencode("Unable to start internship. Please try again."));
exit();
