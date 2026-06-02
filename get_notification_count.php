<?php
session_start();
include_once __DIR__ . '/includes/auth.php';

// Check auth
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

include 'db.php';

$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
$notification_count = 0;

if ($role === 'hr' || $role === 'admin') {
    $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM hr_notifications WHERE is_read = 0");
    if ($notif_res) {
        $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
    }
} elseif ($role === 'mentor') {
    $uid = current_user_id();
    $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM mentor_notifications WHERE mentor_id = $uid AND is_read = 0");
    if ($notif_res) {
        $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
    }

} elseif ($role === 'coordinator') {
    $uid = current_user_id();
    $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM coordinator_notifications WHERE coordinator_id = $uid AND is_read = 0");
    if ($notif_res) {
        $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
    }
} elseif ($role === 'company') {
    $uid = current_user_id();
    $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM company_notifications WHERE company_id = $uid AND is_read = 0");
    if ($notif_res) {
        $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
    }
} else {

    // Default fallback (e.g. students or general fallback)
    $uid = current_user_id();
    $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM student_notifications WHERE user_id = $uid AND is_read = 0");
    if ($notif_res) {
        $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'count' => $notification_count
]);
