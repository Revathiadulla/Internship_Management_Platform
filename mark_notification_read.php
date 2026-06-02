<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Ensure user is logged in and role is set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$role = strtolower($_SESSION['role']);
$action = $_GET['action'] ?? 'read'; // read | delete | read_all | delete_all

// Determine which notification table to use based on role
$table = 'student_notifications';
$owner_column = 'user_id';
if ($role === 'mentor') {
    $table = 'mentor_notifications';
    $owner_column = 'mentor_id';
} elseif ($role === 'hr' || $role === 'admin') {
    $table = 'hr_notifications';
    $owner_column = null; // HR/admin notifications are not tied to a specific owner column
}

// Helper to build WHERE clause when an owner column exists
$ownerCond = $owner_column ? " $owner_column = $user_id" : '';

switch ($action) {
    case 'read':
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit();
        }
        $id = intval($_GET['id']);
        $cond = $owner_column ? " WHERE id = $id AND $owner_column = $user_id" : " WHERE id = $id";
        $sql = "UPDATE $table SET is_read = 1" . $cond;
        mysqli_query($conn, $sql);
        if (isset($_GET['redirect'])) {
            header("Location: " . $_GET['redirect']);
            exit();
        }
        echo json_encode(['success' => true, 'action' => 'read', 'id' => $id]);
        break;

    case 'delete':
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit();
        }
        $id = intval($_GET['id']);
        $cond = $owner_column ? " WHERE id = $id AND $owner_column = $user_id" : " WHERE id = $id";
        $sql = "DELETE FROM $table" . $cond;
        mysqli_query($conn, $sql);
        echo json_encode(['success' => true, 'action' => 'delete', 'id' => $id]);
        break;

    case 'read_all':
        $cond = $owner_column ? " WHERE $owner_column = $user_id" : '';
        $sql = "UPDATE $table SET is_read = 1" . $cond;
        mysqli_query($conn, $sql);
        echo json_encode(['success' => true, 'action' => 'read_all']);
        break;

    case 'delete_all':
        $cond = $owner_column ? " WHERE $owner_column = $user_id" : '';
        $sql = "DELETE FROM $table" . $cond;
        mysqli_query($conn, $sql);
        echo json_encode(['success' => true, 'action' => 'delete_all']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
