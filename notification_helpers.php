<?php

function fetchAllStudents($conn) {
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function fetchAllAdmins($conn) {
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE role = 'admin' ORDER BY full_name ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function fetchAssignedInternships($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT i.id, i.title FROM internships i JOIN internship_applications a ON a.internship_id = i.id ORDER BY i.title ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function getCoordinatorUnreadCount($conn, $coordinator_id) {
    $sql = "SELECT (
                (SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND receiver_role = 'coordinator' AND is_read = 0)
                +
                (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND role = 'coordinator' AND is_read = 0)
            ) AS total_unread";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $coordinator_id, $coordinator_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    if ($row = $res->fetch_assoc()) {
        $count = intval($row['total_unread']);
    }
    $stmt->close();
    return $count;
}

function getInboxNotificationsForCoordinator($conn, $coordinator_id) {
    $sql = "SELECT * FROM notifications WHERE id IN (
        SELECT id FROM notifications WHERE receiver_id = ? AND receiver_role = 'coordinator'
        UNION
        SELECT id FROM notifications WHERE user_id = ? AND role = 'coordinator'
    ) ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $coordinator_id, $coordinator_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function getCoordinatorLatestNotifications($conn, $coordinator_id, $limit = 5) {
    $sql = "SELECT * FROM notifications WHERE id IN (
        SELECT id FROM notifications WHERE receiver_id = ? AND receiver_role = 'coordinator'
        UNION
        SELECT id FROM notifications WHERE user_id = ? AND role = 'coordinator'
    ) ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iii', $coordinator_id, $coordinator_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function getCoordinatorSentNotificationGroups($conn, $coordinator_id, $limit = 50, $offset = 0) {
    $sql = "SELECT COALESCE(batch_key, CONCAT('legacy_', id)) AS batch_key,
                   title,
                   type,
                   created_at,
                   MAX(attachment_path) AS attachment_path,
                   MAX(attachment_name) AS attachment_name,
                   COUNT(*) AS recipient_count,
                   SUM(is_read) AS read_count
            FROM notifications
            WHERE sender_id = ? AND sender_role = 'coordinator'
            GROUP BY batch_key, title, type, created_at
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iii', $coordinator_id, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function getCoordinatorSentNotificationGroupCount($conn, $coordinator_id) {
    $sql = "SELECT COUNT(DISTINCT COALESCE(batch_key, CONCAT('legacy_', id))) AS c
            FROM notifications
            WHERE sender_id = ? AND sender_role = 'coordinator'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $coordinator_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    if ($row = $res->fetch_assoc()) {
        $count = intval($row['c']);
    }
    $stmt->close();
    return $count;
}

function sendCoordinatorNotification($conn, $sender_id, $title, $message, $type, $recipient_type, $recipient_id = null) {
    $recipient_ids = [];
    $recipient_role = '';

    if ($recipient_type === 'all_students') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'student'");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $recipient_ids[] = intval($row['id']);
            }
            $stmt->close();
        }
        $recipient_role = 'student';
    } elseif ($recipient_type === 'specific_student' && $recipient_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $recipient_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $recipient_ids[] = intval($row['id']);
            }
            $stmt->close();
        }
        $recipient_role = 'student';
    } elseif ($recipient_type === 'students_in_internship' && $recipient_id > 0) {
        $stmt = $conn->prepare("SELECT DISTINCT user_id FROM internship_applications WHERE internship_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $recipient_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $recipient_ids[] = intval($row['user_id']);
            }
            $stmt->close();
        }
        $recipient_role = 'student';
    } elseif ($recipient_type === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $recipient_ids[] = intval($row['id']);
            }
            $stmt->close();
        }
        $recipient_role = 'admin';
    }

    $recipient_ids = array_values(array_unique($recipient_ids));
    if (empty($recipient_ids) || empty($recipient_role)) {
        return 0;
    }

    $batch_key = bin2hex(random_bytes(8));
    $insert_sql = "INSERT INTO notifications (sender_id, sender_role, receiver_id, receiver_role, user_id, role, title, message, type, is_read, batch_key) VALUES (?, 'coordinator', ?, ?, ?, ?, ?, ?, ?, 0, ?)";
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        return 0;
    }

    $sent_count = 0;
    foreach ($recipient_ids as $receiver_id) {
        $receiver_id_int = intval($receiver_id);
        $stmt->bind_param('iisisssss', $sender_id, $receiver_id_int, $recipient_role, $receiver_id_int, $recipient_role, $title, $message, $type, $batch_key);
        if ($stmt->execute()) {
            $sent_count++;
        }
    }
    $stmt->close();
    return $sent_count;
}

function notification_column_exists($conn, $table, $column) {
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `" . $table_safe . "` LIKE '" . $column_safe . "'");
    return $res && mysqli_num_rows($res) > 0;
}

function resolveNotificationTargetUsers($conn, $recipient_type, $recipient_id = 0) {
    $recipient_type = strtolower(trim($recipient_type));
    $users = [];
    $role = '';

    if ($recipient_type === 'all_users') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users ORDER BY full_name ASC");
        $role = 'all';
    } elseif ($recipient_type === 'students' || $recipient_type === 'student') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE LOWER(role) = 'student' ORDER BY full_name ASC");
        $role = 'student';
    } elseif ($recipient_type === 'coordinators' || $recipient_type === 'coordinator') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE LOWER(role) = 'coordinator' ORDER BY full_name ASC");
        $role = 'coordinator';
    } elseif ($recipient_type === 'mentors' || $recipient_type === 'mentor') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE LOWER(role) = 'mentor' ORDER BY full_name ASC");
        $role = 'mentor';
    } elseif ($recipient_type === 'hr') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE LOWER(role) = 'hr' ORDER BY full_name ASC");
        $role = 'hr';
    } elseif ($recipient_type === 'specific_user' || $recipient_type === 'specific') {
        if ($recipient_id > 0) {
            $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $recipient_id);
            }
        } else {
            $stmt = null;
        }
    } else {
        $stmt = null;
    }

    if ($stmt) {
        if ($recipient_type === 'specific_user' || $recipient_type === 'specific') {
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $users[] = $res->fetch_assoc();
                }
            }
        } else {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $users[] = $row;
            }
        }
        $stmt->close();
    }

    return ['users' => $users, 'role' => $role];
}

function sendRoleBasedNotification($conn, $sender_id, $sender_role, $title, $message, $priority = 'medium', $recipient_type = 'all_users', $recipient_id = 0, $send_dashboard = true, $attachment_path = null, $attachment_name = null, $attachment_size = null, $attachment_type = null) {
    $recipient_data = resolveNotificationTargetUsers($conn, $recipient_type, $recipient_id);
    $users = $recipient_data['users'] ?? [];
    $recipient_role = $recipient_data['role'] ?? '';

    if ($send_dashboard !== true || empty($users)) {
        return ['sent_count' => 0, 'recipient_role' => $recipient_role, 'target_count' => count($users)];
    }

    $priority_value = 'info';
    if (in_array(strtolower($priority), ['high', 'urgent'], true)) {
        $priority_value = 'alert';
    } elseif (in_array(strtolower($priority), ['medium', 'normal'], true)) {
        $priority_value = 'reminder';
    }

    $prefix = '';
    if (strtolower($priority) === 'high') {
        $prefix = '[High Priority] ';
    } elseif (strtolower($priority) === 'urgent') {
        $prefix = '[Urgent] ';
    } elseif (strtolower($priority) === 'medium') {
        $prefix = '[Medium Priority] ';
    }

    $effective_title = trim($prefix . $title);
    $batch_key = bin2hex(random_bytes(8));

    $insert_sql = "INSERT INTO notifications (sender_id, sender_role, receiver_id, receiver_role, user_id, role, title, message, type, is_read, batch_key, attachment_path, attachment_name, attachment_size, attachment_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        return ['sent_count' => 0, 'recipient_role' => $recipient_role, 'target_count' => count($users)];
    }

    $sent_count = 0;
    foreach ($users as $user) {
        $user_id = intval($user['id'] ?? 0);
        if ($user_id <= 0) {
            continue;
        }

        $receiver_role_value = strtolower(trim($user['role'] ?? $recipient_role ?? ''));
        $stmt->bind_param('isisisssssssis', $sender_id, $sender_role, $user_id, $receiver_role_value, $user_id, $receiver_role_value, $effective_title, $message, $priority_value, $batch_key, $attachment_path, $attachment_name, $attachment_size, $attachment_type);
        if ($stmt->execute()) {
            $sent_count++;
        }
    }
    $stmt->close();

    return ['sent_count' => $sent_count, 'recipient_role' => $recipient_role, 'target_count' => count($users)];
}

function getNotificationSendTargetLabel($recipient_type) {
    switch ($recipient_type) {
        case 'all_users':
            return 'All Users';
        case 'students':
            return 'Students';
        case 'coordinators':
            return 'Coordinators';
        case 'mentors':
            return 'Mentors';
        case 'hr':
            return 'HR';
        case 'specific_user':
            return 'Specific User';
        case 'all_students':
            return 'All Students';
        case 'specific_student':
            return 'Specific Student';
        case 'students_in_internship':
            return 'Students in Selected Internship';
        case 'admin':
            return 'Admin';
        default:
            return 'Undefined';
    }
}
