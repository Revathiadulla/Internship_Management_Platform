<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
if ($action === 'get_types') {
    $stmt = $conn->prepare("SELECT id, type_name FROM project_types WHERE status = 'Active' ORDER BY type_name ASC");
    $stmt->execute();
    $res = $stmt->get_result();
    $types = [];
    while ($row = $res->fetch_assoc()) {
        $types[] = $row;
    }
    $stmt->close();
    echo json_encode($types);
    exit();
}

if ($action === 'get_subtypes') {
    $type_id = intval($_GET['type_id'] ?? 0);
    if ($type_id <= 0) {
        echo json_encode([]);
        exit();
    }
    $stmt = $conn->prepare("SELECT id, subtype_name, skills, mode, duration FROM project_subtypes WHERE project_type_id = ? AND status = 'Active' ORDER BY subtype_name ASC");
    $stmt->bind_param('i', $type_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $subtypes = [];
    while ($row = $res->fetch_assoc()) {
        $subtypes[] = $row;
    }
    $stmt->close();
    echo json_encode($subtypes);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit();
