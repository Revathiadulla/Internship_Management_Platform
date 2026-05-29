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

$errors = [];
$posting = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['title', 'department', 'posting_type', 'location', 'openings', 'description', 'requirements', 'status', 'deadline'];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }
    $posting = $data;
    $errors = validate_posting_input($data);
    if (empty($errors)) {
    $title = $data['title'];
    $department = $data['department'];
    $posting_type = $data['posting_type'] ?: 'Internship';
    $location = $data['location'];
    $openings = max(1, (int) $data['openings']);
    $description = $data['description'];
    $requirements = $data['requirements'];
    $status = in_array($data['status'], ['Active', 'Closed'], true) ? $data['status'] : 'Active';
    $deadline = $data['deadline'] !== '' ? $data['deadline'] : null;
    $stmt = $conn->prepare("INSERT INTO job_postings (title, department, posting_type, location, openings, description, requirements, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('ssssissss', $title, $department, $posting_type, $location, $openings, $description, $requirements, $status, $deadline);
        if ($stmt->execute()) {
            set_flash('Posting created successfully.');
            header('Location: postings.php');
            exit();
        }
        $errors[] = 'Unable to create posting: ' . $stmt->error;
    } else {
        $errors[] = 'Unable to prepare posting creation.';
    }
    }
}

page_shell_start('postings', 'Create Posting', 'Add a new job or internship opening.');
if (!empty($errors)) {
    echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e(implode(' ', $errors)) . '</div>';
}
include __DIR__ . '/posting_form.php';
page_shell_end();
