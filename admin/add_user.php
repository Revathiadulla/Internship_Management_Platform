<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_module_access('users');
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
require_once __DIR__ . '/../includes/password_validation.php';
ensure_module_schema($conn);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'hr', 'recruiter', 'coordinator', 'mentor'], true) ? $_POST['role'] : 'hr';
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';
    $permissions = trim($_POST['permissions'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($full_name === '' || $email === '' || $password === '') {
        $error = 'Name, email, and password are required.';
    } else {
        $password_validation = validate_password_strength($password);
        if (!$password_validation['is_valid']) {
            $error = implode(' ', $password_validation['errors']);
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
        $q = "INSERT INTO users (full_name, email, phone, role, password, status, permissions, reset_required)
              VALUES ('" . mysqli_real_escape_string($conn, $full_name) . "', '" . mysqli_real_escape_string($conn, $email) . "', '" . mysqli_real_escape_string($conn, $phone) . "', '" . mysqli_real_escape_string($conn, $role) . "', '" . mysqli_real_escape_string($conn, $hash) . "', '" . mysqli_real_escape_string($conn, $status) . "', '" . mysqli_real_escape_string($conn, $permissions) . "', 1)";
        if (mysqli_query($conn, $q)) {
            set_flash('User created successfully.');
            header('Location: users.php');
            exit();
        }
        $error = mysqli_error($conn);
        }
    }
}

page_shell_start('users', 'Add User', 'Create HR/Admin login access.');
if ($error) echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e($error) . '</div>';
include __DIR__ . '/user_form.php';
page_shell_end();
