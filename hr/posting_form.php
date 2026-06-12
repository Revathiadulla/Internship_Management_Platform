<?php $is_edit = isset($posting); ?>
<form method="post" class="max-w-4xl rounded-lg border border-slate-200 bg-white p-6">
    <div class="grid gap-5 md:grid-cols-2">
        <label class="block text-sm font-semibold text-slate-700">Title
            <input required name="title" value="<?php echo e($posting['title'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Department
            <input name="department" value="<?php echo e($posting['department'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Type
            <select name="posting_type" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach (['Internship', 'Job'] as $type): ?>
                    <option value="<?php echo e($type); ?>" <?php echo ($posting['posting_type'] ?? 'Internship') === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm font-semibold text-slate-700">Status
            <select name="status" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach (['Active', 'Closed'] as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo ($posting['status'] ?? 'Active') === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm font-semibold text-slate-700">Location
            <input name="location" value="<?php echo e($posting['location'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Openings
            <input required type="number" min="1" name="openings" value="<?php echo e($posting['openings'] ?? 1); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">Deadline
            <input type="date" name="deadline" value="<?php echo e($posting['deadline'] ?? ''); ?>" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
    </div>
    <label class="mt-5 block text-sm font-semibold text-slate-700">Description
        <textarea name="description" rows="5" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?php echo e($posting['description'] ?? ''); ?></textarea>
    </label>
    <label class="mt-5 block text-sm font-semibold text-slate-700">Requirements
        <textarea name="requirements" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?php echo e($posting['requirements'] ?? ''); ?></textarea>
    </label>
    <div class="mt-6 flex items-center gap-3">
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"><?php echo $is_edit ? 'Save changes' : 'Create posting'; ?></button>
        <a href="postings.php" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
