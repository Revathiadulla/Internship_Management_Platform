<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: login.php?error=' . urlencode('Unauthorized access.'));
    exit();
}

require_once __DIR__ . '/admin_received_notifications.php';
