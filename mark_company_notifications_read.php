<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = current_user_id();
    $stmt = $conn->prepare("UPDATE company_notifications SET is_read = 1 WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
}
header('Content-Type: application/json');
echo json_encode(['success' => false]);
exit();
?>
