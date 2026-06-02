<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'hr') {
    header("Location: hr_dashboard.php");
    exit();
}
require_module_access('postings');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('Invalid posting URL.');
    header('Location: postings.php');
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM internship_applications WHERE job_posting_id = ? AND is_deleted = 0");
$stmt->bind_param('i', $id);
$stmt->execute();
$app_count = (int) $stmt->get_result()->fetch_assoc()['total'];
if ($app_count > 0) {
    set_flash('Cannot delete posting with linked applications. Close it instead.');
    header('Location: postings.php');
    exit();
}

$stmt = $conn->prepare("DELETE FROM job_postings WHERE id = ?");
$stmt->bind_param('i', $id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    set_flash('Posting deleted.');
} else {
    set_flash('Posting not found or already deleted.');
}
header('Location: postings.php');
exit();
