<?php
session_start();
include __DIR__ . '/db.php';
header('Content-Type: application/json');

// Params: source=global (default), receiver_role, filter=all|unread|info|success|alert, page, per_page
$source = isset($_GET['source']) ? $_GET['source'] : 'global';
$receiver_role = isset($_GET['receiver_role']) ? mysqli_real_escape_string($conn, $_GET['receiver_role']) : null;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(200, intval($_GET['per_page']))) : 25;
$offset = ($page - 1) * $per_page;

// Only allow global notifications for admin/HR pages
$table = 'notifications';
$where = [];
$params = [];

if (!empty($receiver_role)) {
    $where[] = "receiver_role = '" . mysqli_real_escape_string($conn, $receiver_role) . "'";
}

switch ($filter) {
    case 'unread':
        $where[] = 'is_read = 0';
        break;
    case 'info':
        $where[] = "LOWER(notification_type) = 'info'";
        break;
    case 'success':
        $where[] = "LOWER(notification_type) = 'success'";
        break;
    case 'alert':
        $where[] = "LOWER(notification_type) IN ('alert','warning','error')";
        break;
    case 'all':
    default:
        // no additional where
        break;
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where);
}

// total count
$count_sql = "SELECT COUNT(*) as cnt FROM $table" . $where_sql;
$count_res = mysqli_query($conn, $count_sql);
$total = 0;
if ($count_res) {
    $r = mysqli_fetch_assoc($count_res);
    $total = intval($r['cnt'] ?? 0);
}

// fetch rows
$sort_col = 'created_at';
$sql = "SELECT id, title, message, notification_type, is_read, link, created_at FROM $table" . $where_sql . " ORDER BY $sort_col DESC LIMIT $per_page OFFSET $offset";
$res = mysqli_query($conn, $sql);
$rows = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'data' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page
]);
exit();
?>
