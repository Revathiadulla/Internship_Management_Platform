<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_login();
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$user_id = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($new_password !== '' || $confirm_password !== '' || $current_password !== '') {
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || !password_verify($current_password, $row['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile image upload failed.';
        } elseif (!in_array($ext, $allowed_ext, true)) {
            $errors[] = 'Profile image must be JPG, PNG, or WEBP.';
        } elseif ((int) $file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Profile image must be 2MB or smaller.';
        } else {
            $dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                $profile_image = 'uploads/profiles/' . $filename;
            } else {
                $errors[] = 'Unable to save profile image.';
            }
        }
    }

    if (empty($errors)) {
        if ($new_password !== '') {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($profile_image !== null) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, password = ?, profile_image = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $full_name, $phone, $hash, $profile_image, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->bind_param('sssi', $full_name, $phone, $hash, $user_id);
            }
        } else {
            if ($profile_image !== null) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, profile_image = ? WHERE id = ?");
                $stmt->bind_param('sssi', $full_name, $phone, $profile_image, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $stmt->bind_param('ssi', $full_name, $phone, $user_id);
            }
        }
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            set_flash('Settings updated successfully.');
            header('Location: settings.php');
            exit();
        }
        $errors[] = 'Unable to update settings.';
    }
}

$stmt = $conn->prepare("SELECT full_name, email, role, phone, profile_image FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

page_shell_start('dashboard', 'Settings', 'Update your live account details and password.');
if (!empty($errors)) {
    echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e(implode(' ', $errors)) . '</div>';
}
?>
<form method="post" enctype="multipart/form-data" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-6">
    <div class="flex items-center gap-4">
        <?php if (!empty($user['profile_image'])): ?>
            <img src="<?php echo e($user['profile_image']); ?>" class="h-16 w-16 rounded-full object-cover" alt="<?php echo e($user['full_name']); ?>">
        <?php else: ?>
            <div class="grid h-16 w-16 place-items-center rounded-full bg-blue-600 text-xl font-bold text-white"><?php echo e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))); ?></div>
        <?php endif; ?>
        <div>
            <h2 class="text-lg font-bold text-slate-900"><?php echo e($user['full_name'] ?? 'User'); ?></h2>
            <p class="text-sm text-slate-500"><?php echo e($user['email'] ?? ''); ?> · <?php echo e(ucfirst($user['role'] ?? '')); ?></p>
        </div>
    </div>
    <div class="mt-6 grid gap-5 md:grid-cols-2">
        <label class="block text-sm font-semibold text-slate-700">Full name
            <input required name="full_name" value="<?php echo e($user['full_name'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Phone
            <input name="phone" value="<?php echo e($user['phone'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Profile image
            <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
    </div>
    <div class="mt-6 border-t border-slate-100 pt-6">
        <h3 class="font-bold text-slate-900">Change password</h3>
        <div class="mt-4 grid gap-5 md:grid-cols-3">
            <input type="password" name="current_password" placeholder="Current password" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="password" name="new_password" placeholder="New password" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="password" name="confirm_password" placeholder="Confirm password" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
    </div>
    <button class="mt-6 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Save settings</button>
</form>
<?php page_shell_end(); ?>
