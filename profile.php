<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_login();
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$user_id = current_user_id();
$user = [];
$stmt = $conn->prepare("SELECT full_name, email, role, phone, status, permissions, profile_image, created_at FROM users WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: [];
}

page_shell_start('dashboard', 'Profile', 'Logged-in user details.');
?>
<div class="max-w-3xl rounded-lg border border-slate-200 bg-white p-6">
    <div class="flex items-center gap-4">
        <?php if (!empty($user['profile_image'])): ?>
            <img src="<?php echo e($user['profile_image']); ?>" class="h-16 w-16 rounded-full object-cover" alt="<?php echo e($user['full_name'] ?? 'User'); ?>">
        <?php else: ?>
            <div class="grid h-16 w-16 place-items-center rounded-full bg-blue-600 text-xl font-bold text-white"><?php echo e(strtoupper(substr($user['full_name'] ?? $_SESSION['full_name'] ?? 'U', 0, 1))); ?></div>
        <?php endif; ?>
        <div>
            <h2 class="text-xl font-extrabold text-slate-900"><?php echo e($user['full_name'] ?? $_SESSION['full_name'] ?? 'User'); ?></h2>
            <p class="text-sm text-slate-500"><?php echo e($user['email'] ?? $_SESSION['email'] ?? ''); ?></p>
        </div>
    </div>
    <dl class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Role</dt><dd class="mt-1 font-semibold capitalize"><?php echo e($user['role'] ?? $_SESSION['role'] ?? ''); ?></dd></div>
        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1 font-semibold"><?php echo e($user['status'] ?? 'Active'); ?></dd></div>
        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Phone</dt><dd class="mt-1 font-semibold"><?php echo e($user['phone'] ?? 'Not added'); ?></dd></div>
        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Permissions</dt><dd class="mt-1 font-semibold"><?php echo e(($user['permissions'] ?? '') ?: 'Default role access'); ?></dd></div>
    </dl>
</div>
<?php page_shell_end(); ?>
