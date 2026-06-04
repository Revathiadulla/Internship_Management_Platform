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

function getNotificationSendTargetLabel($recipient_type) {
    switch ($recipient_type) {
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
