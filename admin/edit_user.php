<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_module_access('users');
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
require_once __DIR__ . '/../includes/password_validation.php';
ensure_module_schema($conn);

$id = (int) ($_GET['id'] ?? 0);
$res = mysqli_query($conn, "SELECT * FROM users WHERE id = $id LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: users.php');
    exit();
}
$user = mysqli_fetch_assoc($res);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'hr', 'recruiter', 'coordinator', 'mentor'], true) ? $_POST['role'] : 'hr';
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';
    $permissions = trim($_POST['permissions'] ?? '');
    $password = $_POST['password'] ?? '';
    $reset_required = isset($_POST['reset_required']) ? 1 : 0;
    $password_sql = '';
    if ($password !== '') {
        $password_validation = validate_password_strength($password);
        if (!$password_validation['is_valid']) {
            $error = implode(' ', $password_validation['errors']);
        } else {
            $hash = mysqli_real_escape_string($conn, password_hash($password, PASSWORD_DEFAULT));
            $password_sql = ", password = '$hash', reset_required = 1";
        }
    } else {
        $password_sql = ", reset_required = $reset_required";
    }
    if ($error === '') {
    $q = "UPDATE users SET full_name='" . mysqli_real_escape_string($conn, $full_name) . "', email='" . mysqli_real_escape_string($conn, $email) . "', phone='" . mysqli_real_escape_string($conn, $phone) . "', role='" . mysqli_real_escape_string($conn, $role) . "', status='" . mysqli_real_escape_string($conn, $status) . "', permissions='" . mysqli_real_escape_string($conn, $permissions) . "' $password_sql WHERE id=$id";
    if (mysqli_query($conn, $q)) {
        set_flash('User updated successfully.');
        header('Location: users.php');
        exit();
    }
    $error = mysqli_error($conn);
    }
}

page_shell_start('users', 'Edit User', 'Update role, permissions, access status, or reset password.');
if ($error) echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e($error) . '</div>';
include __DIR__ . '/user_form.php';
page_shell_end();
