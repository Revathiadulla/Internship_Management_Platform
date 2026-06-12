<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$log_id = intval($_POST['log_id'] ?? 0);
$redirect_base = 'student_dashboard.php?section=daily_logs';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $log_id <= 0) {
    header("Location: {$redirect_base}&error=" . urlencode('Unable to process daily log.'));
    exit();
}

$delete_stmt = $conn->prepare("DELETE FROM daily_logs WHERE id = ? AND user_id = ? AND log_date = CURDATE() LIMIT 1");
if (!$delete_stmt) {
    echo 'Prepare failed: ' . htmlspecialchars($conn->error);
    exit();
}

$delete_stmt->bind_param('ii', $log_id, $user_id);
if (!$delete_stmt->execute()) {
    echo 'Delete failed: ' . htmlspecialchars($delete_stmt->error);
    exit();
}

if ($delete_stmt->affected_rows === 0) {
    header("Location: {$redirect_base}&error=" . urlencode('Unable to process daily log.'));
    exit();
}

$delete_stmt->close();
header("Location: {$redirect_base}&deleted=1");
exit();
