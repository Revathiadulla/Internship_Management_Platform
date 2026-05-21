<?php
/**
 * generate_notifications.php
 * Called internally to auto-create notifications from application/internship events.
 * Also exposes a JSON endpoint for AJAX polling.
 */
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);

// ── Helper: insert notification if not already present ───────────────────────
function insertNotif($conn, $user_id, $type, $message) {
    $type    = mysqli_real_escape_string($conn, $type);
    $message = mysqli_real_escape_string($conn, $message);
    // Avoid duplicates: same user + type + message within last 7 days
    $check = mysqli_query($conn,
        "SELECT id FROM student_notifications
         WHERE user_id = $user_id AND type = '$type' AND message = '$message'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         LIMIT 1");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($conn,
            "INSERT INTO student_notifications (user_id, type, message, is_read)
             VALUES ($user_id, '$type', '$message', 0)");
    }
}

// ── 1. Application status-based notifications ────────────────────────────────
$apps_sql = "SELECT a.id, a.status, a.education_status, a.test_status, a.test_score,
                    COALESCE(i.title, a.internship_name) as title
             FROM internship_applications a
             LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
             WHERE a.user_id = $user_id
             ORDER BY a.applied_date DESC";
$apps_res = mysqli_query($conn, $apps_sql);

while ($app = mysqli_fetch_assoc($apps_res)) {
    $title  = $app['title'];
    $status = $app['status'];

    switch ($status) {
        case 'Applied':
            insertNotif($conn, $user_id, 'info',
                "Your application for \"$title\" has been submitted successfully.");
            break;
        case 'Test Completed':
            insertNotif($conn, $user_id, 'success',
                "Assessment completed for \"$title\". Score: " . ($app['test_score'] ?? 'Pending') . ".");
            break;
        case 'HR Round':
            insertNotif($conn, $user_id, 'info',
                "Your application for \"$title\" has moved to the HR Round. Stay prepared!");
            break;
        case 'HOD Approved':
            insertNotif($conn, $user_id, 'success',
                "HOD has approved your application for \"$title\". Awaiting final selection.");
            break;
        case 'Selected':
            insertNotif($conn, $user_id, 'success',
                "Congratulations! You have been selected for \"$title\". Check your dashboard.");
            break;
        case 'Rejected':
            insertNotif($conn, $user_id, 'error',
                "Your application for \"$title\" was not selected this time. Keep applying!");
            break;
        case 'Started':
        case 'Internship Started':
        case 'Active Intern':
            insertNotif($conn, $user_id, 'internship',
                "Your internship \"$title\" has officially started. Welcome aboard!");
            break;
    }

    // Test pending reminder
    if (($app['test_status'] ?? '') !== 'Completed' &&
        in_array($status, ['Applied', 'HR Round'])) {
        insertNotif($conn, $user_id, 'test',
            "Reminder: Complete your assessment test for \"$title\" within 48 hours.");
    }
}

// ── 2. Mentor feedback notifications ─────────────────────────────────────────
$fb_check = mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'mentor_feedback'");
$fb_row = mysqli_fetch_assoc($fb_check);
if ($fb_row['cnt'] > 0) {
    $fb_res = mysqli_query($conn,
        "SELECT feedback_title, given_by FROM mentor_feedback
         WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
    while ($fb = mysqli_fetch_assoc($fb_res)) {
        $by    = $fb['given_by'] ?? 'your mentor';
        $ftitle = $fb['feedback_title'] ?? 'Performance Review';
        insertNotif($conn, $user_id, 'feedback',
            "New feedback received: \"$ftitle\" from $by.");
    }
}

// ── 3. Certificate availability ───────────────────────────────────────────────
$cert_check = mysqli_query($conn,
    "SELECT a.applied_date, COALESCE(i.title, a.internship_name) as title
     FROM internship_applications a
     LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
     WHERE a.user_id = $user_id
       AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern')
     LIMIT 1");
if ($cert_row = mysqli_fetch_assoc($cert_check)) {
    $start     = new DateTime($cert_row['applied_date']);
    $today     = new DateTime();
    $days_done = $start->diff($today)->days;
    if ($days_done >= 90) {
        insertNotif($conn, $user_id, 'certificate',
            "Your internship certificate for \"{$cert_row['title']}\" is now available. Download it from the Certificate section.");
    }
}

// ── 4. Mentor assignment pending ─────────────────────────────────────────────
$mentor_check = mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'mentor_assignments'");
$mc_row = mysqli_fetch_assoc($mentor_check);
if ($mc_row['cnt'] > 0) {
    $ma_res = mysqli_query($conn,
        "SELECT COUNT(*) as cnt FROM mentor_assignments WHERE student_id = $user_id");
    $ma_row = mysqli_fetch_assoc($ma_res);
    if (intval($ma_row['cnt']) === 0) {
        insertNotif($conn, $user_id, 'mentor',
            "Mentor assignment is pending. Your mentor will be assigned shortly.");
    }
}

// ── Return fresh notifications as JSON ───────────────────────────────────────
$notifs_res = mysqli_query($conn,
    "SELECT * FROM student_notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 50");
$notifs = [];
while ($row = mysqli_fetch_assoc($notifs_res)) {
    $notifs[] = $row;
}

$unread_count = count(array_filter($notifs, fn($n) => !$n['is_read']));

echo json_encode([
    'success'      => true,
    'notifications' => $notifs,
    'unread_count' => $unread_count,
]);
