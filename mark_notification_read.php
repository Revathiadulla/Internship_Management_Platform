<?php
session_start();
include "db.php";

header('Content-Type: application/json');

<<<<<<< HEAD
if (!isset($_SESSION['user_id'])) {
=======
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
>>>>>>> dd7d2df (Add HR Mentor Company modules)
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
<<<<<<< HEAD
$action  = $_GET['action'] ?? 'read';   // read | delete | read_all | delete_all

// ── Mark single as read ──────────────────────────────────────────────────────
if ($action === 'read' && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
    $sql = "UPDATE student_notifications SET is_read = 1 WHERE id = $id AND user_id = $user_id";
    mysqli_query($conn, $sql);
=======
$role = strtolower($_SESSION['role']);
$action  = $_GET['action'] ?? 'read';   // read | delete | read_all | delete_all

// Determine table and owner column based on role
$table = 'student_notifications';
$owner_column = 'user_id';
if ($role === 'mentor') {
    $table = 'mentor_notifications';
    $owner_column = 'mentor_id';
} elseif ($role === 'hr' || $role === 'admin') {
    $table = 'hr_notifications';
    $owner_column = null;
}

// ── Mark single as read ──────────────────────────────────────────────────────
if ($action === 'read' && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
    $cond = $owner_column ? " WHERE id = $id AND $owner_column = $user_id" : " WHERE id = $id";
    $sql = "UPDATE $table SET is_read = 1" . $cond;
    mysqli_query($conn, $sql);
    if (isset($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    }
>>>>>>> dd7d2df (Add HR Mentor Company modules)
    echo json_encode(['success' => true, 'action' => 'read', 'id' => $id]);
    exit();
}

// ── Delete single notification ───────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
<<<<<<< HEAD
    $sql = "DELETE FROM student_notifications WHERE id = $id AND user_id = $user_id";
=======
    $cond = $owner_column ? " WHERE id = $id AND $owner_column = $user_id" : " WHERE id = $id";
    $sql = "DELETE FROM $table" . $cond;
>>>>>>> dd7d2df (Add HR Mentor Company modules)
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'delete', 'id' => $id]);
    exit();
}

// ── Mark all as read ─────────────────────────────────────────────────────────
if ($action === 'read_all' || isset($_GET['all'])) {
<<<<<<< HEAD
    $sql = "UPDATE student_notifications SET is_read = 1 WHERE user_id = $user_id";
=======
    $cond = $owner_column ? " WHERE $owner_column = $user_id" : "";
    $sql = "UPDATE $table SET is_read = 1" . $cond;
>>>>>>> dd7d2df (Add HR Mentor Company modules)
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'read_all']);
    exit();
}

// ── Delete all notifications ─────────────────────────────────────────────────
if ($action === 'delete_all') {
<<<<<<< HEAD
    $sql = "DELETE FROM student_notifications WHERE user_id = $user_id";
=======
    $cond = $owner_column ? " WHERE $owner_column = $user_id" : "";
    $sql = "DELETE FROM $table" . $cond;
>>>>>>> dd7d2df (Add HR Mentor Company modules)
    mysqli_query($conn, $sql);
    echo json_encode(['success' => true, 'action' => 'delete_all']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
