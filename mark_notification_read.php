<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$action  = $_GET['action'] ?? 'read';   // read | delete | read_all | delete_all

// ── Mark single as read ──────────────────────────────────────────────────────
if ($action === 'read' && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
    $sql = "UPDATE student_notifications SET is_read = 1 WHERE id = $id AND user_id = $user_id";
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'read', 'id' => $id]);
    exit();
}

// ── Delete single notification ───────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
    $sql = "DELETE FROM student_notifications WHERE id = $id AND user_id = $user_id";
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'delete', 'id' => $id]);
    exit();
}

// ── Mark all as read ─────────────────────────────────────────────────────────
if ($action === 'read_all' || isset($_GET['all'])) {
    $sql = "UPDATE student_notifications SET is_read = 1 WHERE user_id = $user_id";
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'read_all']);
    exit();
}

// ── Delete all notifications ─────────────────────────────────────────────────
if ($action === 'delete_all') {
    $sql = "DELETE FROM student_notifications WHERE user_id = $user_id";
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'delete_all']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
?>
