<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('users');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$role = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');
$where = ["role IN ('hr', 'recruiter', 'admin', 'coordinator', 'mentor')"];
if ($role !== '') {
    $where[] = "role = '" . mysqli_real_escape_string($conn, $role) . "'";
}
if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $where[] = "(full_name LIKE '%$safe%' OR email LIKE '%$safe%')";
}
$result = mysqli_query($conn, "SELECT id, full_name, email, role, phone, status, permissions, reset_required, created_at FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");

page_shell_start('users', 'Users', 'Create HR users, assign roles, manage permissions, and reset passwords.', '<a href="add_user.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><span class="material-symbols-outlined text-lg">person_add</span> Add user</a>');
?>
<form method="get" class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-4">
    <input name="search" value="<?php echo e($search); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" placeholder="Search name or email">
    <select name="role" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">All roles</option>
        <?php foreach (['admin', 'hr', 'recruiter', 'coordinator', 'mentor'] as $r): ?>
            <option value="<?php echo e($r); ?>" <?php echo $role === $r ? 'selected' : ''; ?>><?php echo e(ucfirst($r)); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
</form>
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">User</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Permissions</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        <?php if ($result && mysqli_num_rows($result) > 0): while ($user = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td class="px-5 py-4"><div class="font-semibold"><?php echo e($user['full_name']); ?></div><div class="text-xs text-slate-500"><?php echo e($user['email']); ?> <?php echo $user['phone'] ? '· ' . e($user['phone']) : ''; ?></div></td>
                <td class="px-5 py-4 font-semibold capitalize text-slate-700"><?php echo e($user['role']); ?></td>
                <td class="px-5 py-4 text-slate-600"><?php echo e($user['permissions'] ?: 'Default access'); ?></td>
                <td class="px-5 py-4"><?php echo status_badge($user['status'] ?: 'Active'); ?><?php echo (int) $user['reset_required'] === 1 ? '<span class="ml-2 text-xs font-semibold text-amber-700">Reset required</span>' : ''; ?></td>
                <td class="px-5 py-4 text-right"><a class="font-semibold text-blue-700 hover:underline" href="edit_user.php?id=<?php echo (int) $user['id']; ?>">Edit</a></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No HR/Admin users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php page_shell_end(); ?>
