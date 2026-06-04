<?php
session_start();
include "db.php";

// 1. Verify role = mentor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header("Location: login.php");
    exit();
}

$mentor_id = intval($_SESSION['user_id']);
$team_id = intval($_POST['team_id'] ?? 0);
$student_id_raw = $_POST['student_id'] ?? '';
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($team_id <= 0 || empty($subject) || empty($message) || empty($student_id_raw)) {
    header("Location: mentor_notifications.php?error_msg=" . urlencode("All fields are required."));
    exit();
}

// Fetch mentor name
$mentor_name = "Mentor";
$m_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $mentor_id LIMIT 1");
if ($m_row = mysqli_fetch_assoc($m_res)) {
    $mentor_name = $m_row['full_name'];
}

// 2. Fetch target student(s) with validation
$students = [];
if ($student_id_raw === 'all') {
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, t.team_name
        FROM users u
        JOIN project_team_members tm ON tm.student_id = u.id
        JOIN project_teams t ON t.id = tm.project_team_id
        WHERE t.mentor_id = ? AND t.id = ?
    ");
    $stmt->bind_param("ii", $mentor_id, $team_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
} else {
    $target_student_id = intval($student_id_raw);
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, t.team_name
        FROM users u
        JOIN project_team_members tm ON tm.student_id = u.id
        JOIN project_teams t ON t.id = tm.project_team_id
        WHERE t.mentor_id = ? AND t.id = ? AND u.id = ?
    ");
    $stmt->bind_param("iii", $mentor_id, $team_id, $target_student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

if (empty($students)) {
    header("Location: mentor_notifications.php?error_msg=" . urlencode("No valid students found in the selected team assigned to you."));
    exit();
}

// 3. Insert notifications and try emails
$email_failed = false;
foreach ($students as $student) {
    $dest_student_id = intval($student['id']);
    $student_name = $student['full_name'];
    $student_email = $student['email'];
    $team_name = $student['team_name'];

    // Insert dashboard notification
    $s_link = "student_notifications.php";
    $notif_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, sender_id, role, title, message, type, is_read, created_at, link)
        VALUES (?, ?, 'student', ?, ?, 'mentor_message', 0, NOW(), ?)
    ");
    if ($notif_stmt) {
        $notif_stmt->bind_param("iisss", $dest_student_id, $mentor_id, $subject, $message, $s_link);
        $notif_stmt->execute();
        $notif_stmt->close();
    }

    // Attempt email sending
    $email_subject = "[IMP Mentor Notification] " . $subject;
    $email_body = "Hello " . $student_name . ",\n\n" . $message . "\n\nRegards,\n" . $mentor_name . "\nMentor - Internship Management Platform";
    $headers = "From: no-reply@imp.com\r\n" .
               "Reply-To: no-reply@imp.com\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Check if mail server is set up, or suppress warning and capture success
    if (!empty($student_email)) {
        @$mail_sent = mail($student_email, $email_subject, $email_body, $headers);
        if (!$mail_sent) {
            $email_failed = true;
        }
    } else {
        $email_failed = true;
    }
}

if ($email_failed) {
    header("Location: mentor_notifications.php?success_msg=" . urlencode("Dashboard notification sent. Email could not be sent because mail server is not configured."));
} else {
    header("Location: mentor_notifications.php?success_msg=" . urlencode("Notification sent successfully."));
}
exit();
