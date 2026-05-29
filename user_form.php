<?php $is_edit = isset($user); ?>
<form method="post" class="max-w-4xl rounded-lg border border-slate-200 bg-white p-6">
    <div class="grid gap-5 md:grid-cols-2">
        <label class="block text-sm font-semibold text-slate-700">Full name
            <input required name="full_name" value="<?php echo e($user['full_name'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Email
            <input required type="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Phone
            <input name="phone" value="<?php echo e($user['phone'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Role
            <select name="role" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach (['hr', 'recruiter', 'admin', 'coordinator', 'mentor'] as $role): ?>
                    <option value="<?php echo e($role); ?>" <?php echo ($user['role'] ?? 'hr') === $role ? 'selected' : ''; ?>><?php echo e(ucfirst($role)); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm font-semibold text-slate-700">Status
            <select name="status" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach (['Active', 'Inactive'] as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo ($user['status'] ?? 'Active') === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm font-semibold text-slate-700"><?php echo $is_edit ? 'New password' : 'Temporary password'; ?>
            <input <?php echo $is_edit ? '' : 'required'; ?> type="password" name="password" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="<?php echo $is_edit ? 'Leave blank to keep existing password' : ''; ?>">
        </label>
    </div>
    <label class="mt-5 block text-sm font-semibold text-slate-700">Permissions
        <textarea name="permissions" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Example: postings,candidates,reports"><?php echo e($user['permissions'] ?? ''); ?></textarea>
    </label>
    <?php if ($is_edit): ?>
        <label class="mt-5 flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="reset_required" <?php echo !empty($user['reset_required']) ? 'checked' : ''; ?>> Require password reset on next login</label>
    <?php endif; ?>
    <div class="mt-6 flex gap-3">
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"><?php echo $is_edit ? 'Save changes' : 'Create user'; ?></button>
        <a href="users.php" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
