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
$result = mysqli_query($conn, "SELECT * FROM job_postings WHERE id = $id LIMIT 1");
if (!$result || mysqli_num_rows($result) === 0) {
    set_flash('Posting not found.');
    header('Location: postings.php');
    exit();
}
$posting = mysqli_fetch_assoc($result);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'posting_type' => trim($_POST['posting_type'] ?? 'Internship'),
        'location' => trim($_POST['location'] ?? ''),
        'openings' => trim($_POST['openings'] ?? '1'),
        'description' => trim($_POST['description'] ?? ''),
        'requirements' => trim($_POST['requirements'] ?? ''),
        'status' => trim($_POST['status'] ?? 'Active'),
        'deadline' => trim($_POST['deadline'] ?? ''),
    ];
    $posting = array_merge($posting, $data);
    $errors = validate_posting_input($data);
    if (empty($errors)) {
    $title = $data['title'];
    $department = $data['department'];
    $posting_type = $data['posting_type'] ?: 'Internship';
    $location = $data['location'];
    $openings = max(1, (int) ($_POST['openings'] ?? 1));
    $description = $data['description'];
    $requirements = $data['requirements'];
    $status = in_array($data['status'], ['Active', 'Closed'], true) ? $data['status'] : 'Active';
    $deadline = $data['deadline'] !== '' ? $data['deadline'] : null;
    $stmt = $conn->prepare("UPDATE job_postings SET title = ?, department = ?, posting_type = ?, location = ?, openings = ?, description = ?, requirements = ?, status = ?, deadline = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ssssissssi', $title, $department, $posting_type, $location, $openings, $description, $requirements, $status, $deadline, $id);
        if ($stmt->execute()) {
            set_flash('Posting updated successfully.');
            header('Location: postings.php');
            exit();
        }
        $errors[] = 'Unable to update posting: ' . $stmt->error;
    } else {
        $errors[] = 'Unable to prepare posting update.';
    }
    }
}

page_shell_start('postings', 'Edit Posting', 'Update opening details and active/closed status.');
if (!empty($errors)) {
    echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e(implode(' ', $errors)) . '</div>';
}
include __DIR__ . '/posting_form.php';
page_shell_end();
