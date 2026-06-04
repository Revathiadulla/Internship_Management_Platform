<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header('Location: login.php');
    exit();
}

include 'db.php';
require_once 'notification_helpers.php';

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    $recipient_type = trim($_POST['recipient_type'] ?? '');
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $title = trim($_POST['notification_title'] ?? '');
    $message = trim($_POST['notification_message'] ?? '');
    $type = trim($_POST['notification_type'] ?? 'info');

    $allowed_types = ['info', 'success', 'reminder', 'alert'];
    $allowed_recipient_types = ['all_students', 'specific_student', 'students_in_internship', 'admin'];

    if (empty($recipient_type) || !in_array($recipient_type, $allowed_recipient_types, true)) {
        $error_msg = 'Please select a valid recipient type.';
    } elseif (empty($title)) {
        $error_msg = 'Notification title cannot be empty.';
    } elseif (empty($message)) {
        $error_msg = 'Notification message cannot be empty.';
    } elseif (empty($type) || !in_array($type, $allowed_types, true)) {
        $error_msg = 'Please choose a valid notification type.';
    } elseif (in_array($recipient_type, ['specific_student', 'students_in_internship'], true) && $recipient_id <= 0) {
        $error_msg = 'Please choose a valid recipient from the dropdown.';
    }

    if ($error_msg === '') {
        $sent_count = sendCoordinatorNotification(
            $conn,
            intval($_SESSION['user_id']),
            $title,
            $message,
            $type,
            $recipient_type,
            $recipient_id
        );

        if ($sent_count > 0) {
            $_SESSION['notification_success'] = 'Notification sent successfully to ' . $sent_count . ' recipient' . ($sent_count > 1 ? 's' : '') . '.';
            header('Location: coordinator_notifications.php?tab=sent');
            exit();
        }

        $error_msg = 'Unable to send notification. No recipients were found for the selected target.';
    }

    $_SESSION['notification_error'] = $error_msg;
    $_SESSION['notification_old'] = [
        'recipient_type' => $recipient_type,
        'recipient_id' => $recipient_id,
        'notification_title' => $title,
        'notification_message' => $message,
        'notification_type' => $type
    ];
    header('Location: coordinator_notifications.php?tab=compose');
    exit();
}

header('Location: coordinator_notifications.php');
exit();
