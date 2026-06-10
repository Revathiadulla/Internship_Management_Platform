<?php
session_start();
include "db.php";
require_once "includes/auth.php";
require_once "includes/mail_helper.php";
require_once "includes/notification_attachment_helper.php";

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
    $err = "All fields are required.";
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'message' => $err]);
    } else {
        header("Location: mentor_notifications.php?error_msg=" . urlencode($err));
    }
    exit();
}

// Process file upload if any
$attachment_data = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload_err = '';
    $attachment_res = validateAndUploadNotificationAttachment($_FILES['attachment'], $upload_err);
    if ($attachment_res === false) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'message' => $upload_err]);
        } else {
            header("Location: mentor_notifications.php?error_msg=" . urlencode($upload_err));
        }
        exit();
    }
    $attachment_data = $attachment_res;
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
    $err = "No valid students found in the selected team assigned to you.";
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'message' => $err]);
    } else {
        header("Location: mentor_notifications.php?error_msg=" . urlencode($err));
    }
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
        INSERT INTO notifications (user_id, sender_id, role, title, message, type, is_read, created_at, link, attachment_path, attachment_name, attachment_size, attachment_type)
        VALUES (?, ?, 'student', ?, ?, 'mentor_message', 0, NOW(), ?, ?, ?, ?, ?)
    ");
    if ($notif_stmt) {
        $a_path = $attachment_data ? $attachment_data['path'] : null;
        $a_name = $attachment_data ? $attachment_data['name'] : null;
        $a_size = $attachment_data ? intval($attachment_data['size']) : null;
        $a_type = $attachment_data ? $attachment_data['type'] : null;
        $notif_stmt->bind_param("iisssssis", $dest_student_id, $mentor_id, $subject, $message, $s_link, $a_path, $a_name, $a_size, $a_type);
        $notif_stmt->execute();
        $notif_stmt->close();
    }

    // Set email attachment if provided
    if ($attachment_data) {
        $fullPath = __DIR__ . '/' . $attachment_data['path'];
        if (file_exists($fullPath)) {
            $GLOBALS['mail_options_attachments'] = [[
                'path' => $fullPath,
                'name' => $attachment_data['name']
            ]];
        }
    }

    // Attempt email sending
    if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = '';
        $mail_sent = sendEmailNotification($student_email, "[IMP Mentor Notification] " . $subject, $message, [
            'recipient_name' => $student_name,
            'event'          => 'Mentor Notification',
            'action_url'     => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/IMP/student_notifications.php',
            'action_label'   => 'View Notifications',
        ], $email_err);

        if (!$mail_sent) {
            $email_failed = true;
        }
    } else {
        $email_failed = true;
    }

    // Clear email attachments global state
    if ($attachment_data) {
        unset($GLOBALS['mail_options_attachments']);
    }
}

if (isset($_POST['ajax'])) {
    if ($email_failed) {
        echo json_encode(['success' => true, 'message' => "Dashboard notification sent. Email could not be sent (check mail server setup)."]);
    } else {
        echo json_encode(['success' => true, 'message' => "Notification sent successfully."]);
    }
} else {
    if ($email_failed) {
        header("Location: mentor_notifications.php?success_msg=" . urlencode("Dashboard notification sent. Email could not be sent (check mail server setup)."));
    } else {
        header("Location: mentor_notifications.php?success_msg=" . urlencode("Notification sent successfully."));
    }
}
exit();
