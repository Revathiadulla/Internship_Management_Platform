<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Debug logging for requests to help diagnose client/server issues
try {
    $debug_dir = __DIR__ . '/uploads';
    if (!is_dir($debug_dir)) @mkdir($debug_dir, 0777, true);
    $debug_file = $debug_dir . '/notification_debug.log';
    $dbg = [];
    $dbg[] = "----- " . date('Y-m-d H:i:s') . " -----";
    $dbg[] = 'REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? '');
    $dbg[] = 'REMOTE_ADDR: ' . ($_SERVER['REMOTE_ADDR'] ?? '');
    $dbg[] = 'HTTP_USER_AGENT: ' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $dbg[] = 'SESSION_USER_ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL');
    $dbg[] = 'SESSION_ROLE: ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NULL');
    $dbg[] = 'GET: ' . json_encode($_GET);
    $dbg[] = 'POST: ' . json_encode($_POST);
    $dbg[] = 'HEADERS: ' . json_encode(function_exists('getallheaders') ? getallheaders() : []);
    @file_put_contents($debug_file, implode("\n", $dbg) . "\n", FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    // ignore logging errors
}

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$role = strtolower($_SESSION['role']);

// Read JSON input if Content-Type is application/json or request method is POST
$json_raw = file_get_contents('php://input');
$data = json_decode($json_raw, true) ?? [];

$action = $data['action'] ?? $_GET['action'] ?? 'read';
$id = isset($data['id']) ? intval($data['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
$source = $data['source'] ?? $_GET['source'] ?? null;
$receiver_role = $data['receiver_role'] ?? $_GET['receiver_role'] ?? null;
$receiver_role_safe = $receiver_role ? mysqli_real_escape_string($conn, $receiver_role) : null;

// Map user-friendly actions to standard internal ones
if ($action === 'mark_all_read') {
    $action = 'read_all';
} elseif ($action === 'clear_all') {
    $action = 'delete_all';
} elseif ($action === 'mark_read') {
    $action = 'read';
}

if (isset($_GET['all']) && $_GET['all'] == 1) {
    $action = 'read_all';
}

if ($id !== null && $source === null) {
    if ($role === 'student') {
        $check_stud = mysqli_query($conn, "SELECT id FROM student_notifications WHERE id = $id AND user_id = $user_id LIMIT 1");
        if ($check_stud && mysqli_num_rows($check_stud) > 0) {
            $source = 'student';
        } else {
            $check_glob = mysqli_query($conn, "SELECT id FROM notifications WHERE id = $id AND user_id = $user_id LIMIT 1");
            if ($check_glob && mysqli_num_rows($check_glob) > 0) {
                $source = 'global';
            }
        }
    } elseif ($role === 'mentor') {
        $check_ment = mysqli_query($conn, "SELECT id FROM mentor_notifications WHERE id = $id AND mentor_id = $user_id LIMIT 1");
        if ($check_ment && mysqli_num_rows($check_ment) > 0) {
            $source = 'mentor';
        }
    }
}

if ($source === 'student') {
    $table = 'student_notifications';
    $owner_column = 'user_id';
} elseif ($source === 'global') {
    $table = 'notifications';
    $owner_column = 'user_id';
} elseif ($source === 'mentor') {
    $table = 'mentor_notifications';
    $owner_column = 'mentor_id';
} elseif ($source === 'hr') {
    $table = 'hr_notifications';
    $owner_column = null;
} else {
    // Determine which notification table to use based on role (default behavior)
    $table = 'student_notifications';
    $owner_column = 'user_id';
    if ($role === 'mentor') {
        $table = 'mentor_notifications';
        $owner_column = 'mentor_id';
    } elseif ($role === 'coordinator' || $role === 'admin') {
        $table = 'notifications';
        $owner_column = 'user_id';
    } elseif ($role === 'hr') {
        $table = 'hr_notifications';
        $owner_column = null; // HR notifications are not tied to a specific owner column
    }
}

try {
    switch ($action) {
        case 'read':
            if ($id === null) {
                throw new Exception('Missing ID');
            }
            $has_role_column = ($table === 'notifications');
            $cond = " WHERE id = $id";
            if ($owner_column) {
                $cond .= " AND $owner_column = $user_id";
            }
            if ($has_role_column) {
                $cond .= " AND role = '$role'";
            }
            $sql = "UPDATE $table SET is_read = 1" . $cond;
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['redirect'])) {
                header("Location: " . $_GET['redirect']);
                exit();
            }
            echo json_encode(['success' => true, 'action' => 'read', 'id' => $id, 'message' => 'Updated successfully']);
            break;

        case 'read_redirect':
            if ($id === null) {
                throw new Exception('Missing ID');
            }
            $has_role_column = ($table === 'notifications');
            $cond = " WHERE id = $id";
            if ($owner_column) {
                $cond .= " AND $owner_column = $user_id";
            }
            if ($has_role_column) {
                $cond .= " AND role = '$role'";
            }
            $sql = "UPDATE $table SET is_read = 1" . $cond;
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }

            // Fetch the link if it exists
            $link_res = mysqli_query($conn, "SELECT link FROM $table" . $cond);
            if ($link_res && $link_row = mysqli_fetch_assoc($link_res)) {
                if (!empty($link_row['link'])) {
                    header("Location: " . $link_row['link']);
                    exit();
                }
            }
            
            // Fallback if no link exists
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['fallback'])) {
                header("Location: " . $_GET['fallback']);
                exit();
            }
            echo json_encode(['success' => true, 'action' => 'read_redirect', 'id' => $id, 'message' => 'Updated successfully']);
            break;

        case 'delete':
            if ($id === null) {
                throw new Exception('Missing ID');
            }
            $has_role_column = ($table === 'notifications');
            $cond = " WHERE id = $id";
            if ($owner_column) {
                $cond .= " AND $owner_column = $user_id";
            }
            if ($has_role_column) {
                $cond .= " AND role = '$role'";
            }
            $sql = "DELETE FROM $table" . $cond;
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }
            echo json_encode(['success' => true, 'action' => 'delete', 'id' => $id, 'message' => 'Updated successfully']);
            break;

        case 'read_all':
            if ($role === 'student') {
                // For student, update both student_notifications and notifications (where role = 'student')
                $sql1 = "UPDATE student_notifications SET is_read = 1 WHERE user_id = $user_id";
                $sql2 = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND role = 'student'";
                if (!mysqli_query($conn, $sql1) || !mysqli_query($conn, $sql2)) {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                // If a receiver_role is explicitly provided (admin actions), scope strictly to that role
                if (!empty($receiver_role_safe)) {
                    $cond = " WHERE receiver_role = '" . $receiver_role_safe . "'";
                } else {
                    $cond = "";
                    if ($owner_column) {
                        $cond .= " WHERE $owner_column = $user_id";
                        if ($table === 'notifications') {
                            $cond .= " AND role = '$role'";
                        }
                    }
                }
                $sql = "UPDATE $table SET is_read = 1" . $cond;
                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['redirect'])) {
                header("Location: " . $_GET['redirect']);
                exit();
            }
            echo json_encode(['success' => true, 'action' => 'read_all', 'message' => 'Updated successfully']);
            break;

        case 'delete_all':
            if ($role === 'student') {
                $sql1 = "DELETE FROM student_notifications WHERE user_id = $user_id";
                $sql2 = "DELETE FROM notifications WHERE user_id = $user_id AND role = 'student'";
                if (!mysqli_query($conn, $sql1) || !mysqli_query($conn, $sql2)) {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                $cond = "";
                if ($owner_column) {
                    $cond .= " WHERE $owner_column = $user_id";
                    if ($table === 'notifications') {
                        $cond .= " AND role = '$role'";
                    }
                }
                $sql = "DELETE FROM $table" . $cond;
                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
            echo json_encode(['success' => true, 'action' => 'delete_all', 'message' => 'Updated successfully']);
            break;

        case 'delete_read':
            if ($role === 'student') {
                $sql1 = "DELETE FROM student_notifications WHERE user_id = $user_id AND is_read = 1";
                $sql2 = "DELETE FROM notifications WHERE user_id = $user_id AND role = 'student' AND is_read = 1";
                if (!mysqli_query($conn, $sql1) || !mysqli_query($conn, $sql2)) {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                if (!empty($receiver_role_safe)) {
                    $cond = " WHERE receiver_role = '" . $receiver_role_safe . "' AND is_read = 1";
                } else {
                    $cond = " WHERE is_read = 1";
                    if ($owner_column) {
                        $cond .= " AND $owner_column = $user_id";
                        if ($table === 'notifications') {
                            $cond .= " AND role = '$role'";
                        }
                    }
                }
                $sql = "DELETE FROM $table" . $cond;
                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }
            echo json_encode(['success' => true, 'action' => 'delete_read', 'message' => 'Updated successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>
