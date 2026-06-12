<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    } else {
        header('Location: login.php?error=' . urlencode('Session expired. Please login again.'));
        exit();
    }
}

$sender_role = strtolower($_SESSION['role']);
if (!in_array($sender_role, ['coordinator', 'admin', 'hr', 'mentor'], true)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    } else {
        header('Location: login.php?error=' . urlencode('Unauthorized access.'));
        exit();
    }
}

include __DIR__ . '/db.php';
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/mail_helper.php';
require_once 'includes/notification_attachment_helper.php';

$error_msg = '';
if ($sender_role === 'admin') {
    $redirect_page = 'admin_notifications.php';
} elseif ($sender_role === 'hr') {
    $redirect_page = 'hr_notifications.php';
} elseif ($sender_role === 'coordinator') {
    $redirect_page = 'coordinator_notifications.php';
} else {
    $redirect_page = 'mentor/notifications.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    $recipient_type = trim($_POST['recipient_type'] ?? '');
    $recipient_id   = intval($_POST['recipient_id'] ?? 0);
    $title          = trim($_POST['notification_title'] ?? '');
    $message        = trim($_POST['notification_message'] ?? '');
    $priority       = trim($_POST['priority'] ?? 'medium');
    $send_dashboard = !empty($_POST['send_dashboard']);
    $send_email     = !empty($_POST['send_email']);

    $allowed_recipient_types = ['all_users', 'students', 'coordinators', 'mentors', 'hr', 'specific_user'];
    $allowed_priorities = ['low', 'medium', 'high', 'urgent'];

    if (empty($recipient_type) || !in_array($recipient_type, $allowed_recipient_types, true)) {
        $error_msg = 'Please select a valid recipient type.';
    } elseif (empty($title)) {
        $error_msg = 'Notification title cannot be empty.';
    } elseif (empty($message)) {
        $error_msg = 'Notification message cannot be empty.';
    } elseif (!in_array($priority, $allowed_priorities, true)) {
        $error_msg = 'Please select a valid priority.';
    } elseif ($recipient_type === 'specific_user' && $recipient_id <= 0) {
        $error_msg = 'Please choose a valid recipient from the dropdown.';
    } elseif (!$send_dashboard && !$send_email) {
        $error_msg = 'Please choose at least one delivery option.';
    }

    // Process attachment upload if any
    $attachment_data = null;
    if ($error_msg === '' && isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_err = '';
        $attachment_res = validateAndUploadNotificationAttachment($_FILES['attachment'], $upload_err);
        if ($attachment_res === false) {
            $error_msg = $upload_err;
        } else {
            $attachment_data = $attachment_res;
        }
    }

    if ($error_msg === '') {
        $target_data = resolveNotificationTargetUsers($conn, $recipient_type, $recipient_id);
        $targets = $target_data['users'] ?? [];

        $send_result = ['sent_count' => 0];
        if ($send_dashboard) {
            $send_result = sendRoleBasedNotification(
                $conn,
                intval($_SESSION['user_id']),
                $sender_role,
                $title,
                $message,
                $priority,
                $recipient_type,
                $recipient_id,
                true,
                $attachment_data ? $attachment_data['path'] : null,
                $attachment_data ? $attachment_data['name'] : null,
                $attachment_data ? $attachment_data['size'] : null,
                $attachment_data ? $attachment_data['type'] : null
            );
        }

        $sent_count = intval($send_result['sent_count'] ?? 0);
        $email_sent = 0;
        $email_fails = [];

        if ($send_email && !empty($targets)) {
            // Set global attachments variable for PHPMailer to consume
            if ($attachment_data) {
                $fullPath = __DIR__ . '/' . $attachment_data['path'];
                if (file_exists($fullPath)) {
                    $GLOBALS['mail_options_attachments'] = [[
                        'path' => $fullPath,
                        'name' => $attachment_data['name']
                    ]];
                }
            }

            foreach ($targets as $target) {
                $email = trim($target['email'] ?? '');
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $email_error = '';
                $ok = sendEmailNotification(
                    $email,
                    $title,
                    $message,
                    [
                        'recipient_name' => $target['full_name'] ?? '',
                        'event'          => ucfirst($sender_role) . ' Notification',
                        'action_url'     => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/IMP/' . (($sender_role === 'admin') ? 'admin_dashboard.php' : 'coordinator/dashboard.php'),
                        'action_label'   => 'View Dashboard',
                    ],
                    $email_error
                );

                if ($ok) {
                    $email_sent++;
                } else {
                    $email_fails[] = ($target['full_name'] ?? $email) . ': ' . $email_error;
                }
            }

            // Clear global attachments state
            unset($GLOBALS['mail_options_attachments']);
        }

        $dashboard_label = $send_dashboard ? ('Dashboard notification sent to ' . $sent_count . ' recipient' . ($sent_count === 1 ? '' : 's') . '.') : '';
        $email_label = $send_email ? ('Email sent to ' . $email_sent . ' recipient' . ($email_sent === 1 ? '' : 's') . '.') : '';
        if ($send_dashboard && $send_email) {
            $success_label = trim($dashboard_label . ' ' . $email_label);
        } else {
            $success_label = trim($dashboard_label . ' ' . $email_label);
        }

        if ($send_email && !empty($email_fails)) {
            $success_label .= ' Email failed: ' . implode('; ', $email_fails);
        }

        if ($success_label === '') {
            $success_label = 'Notification request received.';
        }

        $_SESSION['notification_success'] = $success_label;
        header('Location: ' . $redirect_page . '?tab=sent');
        exit();
    }

    $_SESSION['notification_error'] = $error_msg;
    $_SESSION['notification_old'] = [
        'recipient_type'       => $recipient_type,
        'recipient_id'         => $recipient_id,
        'notification_title'   => $title,
        'notification_message' => $message,
        'priority'             => $priority,
        'send_dashboard'       => $send_dashboard,
        'send_email'           => $send_email,
    ];
    header('Location: ' . $redirect_page . '?tab=compose');
    exit();
}

header('Location: ' . $redirect_page);
exit();
