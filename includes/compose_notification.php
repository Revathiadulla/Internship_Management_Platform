<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php?error=" . urlencode("Session expired. Please login again."));
    exit();
}

$user_role = strtolower($_SESSION['role']);
if ($user_role === 'hr') {
    header('Location: hr_notifications.php?tab=compose');
    exit();
} elseif ($user_role === 'admin') {
    header('Location: admin_notifications.php?tab=compose');
    exit();
} elseif ($user_role === 'coordinator') {
    header('Location: coordinator_notifications.php?tab=compose');
    exit();
} else {
    header('Location: login.php?error=' . urlencode('Unauthorized access.'));
    exit();
}
