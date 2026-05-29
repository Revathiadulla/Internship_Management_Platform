<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('workflows');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);
sync_candidates_from_applications($conn);

$stages = [];
$stage_res = mysqli_query($conn, "SELECT * FROM workflow_stages WHERE is_active = 1 ORDER BY sort_order ASC");
if ($stage_res) while ($row = mysqli_fetch_assoc($stage_res)) $stages[] = $row['stage_name'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = ["a.is_deleted = 0"];
if ($search !== '') {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(sp.full_name LIKE '%$search_escaped%' OR sp.email LIKE '%$search_escaped%' OR jp.title LIKE '%$search_escaped%' OR a.internship_name LIKE '%$search_escaped%')";
}
$where_sql = implode(' AND ', $where_clauses);

$apps = mysqli_query($conn, "SELECT a.id, a.status, a.applied_date, COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown') full_name, COALESCE(jp.title, i.title, a.internship_name, 'Untitled') title
    FROM internship_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $where_sql
    ORDER BY a.applied_date DESC
    LIMIT 50");
$logs = mysqli_query($conn, "SELECT wl.*, COALESCE(c.full_name, 'Candidate') full_name FROM workflow_logs wl LEFT JOIN candidates c ON wl.candidate_id = c.id ORDER BY wl.created_at DESC LIMIT 10");

page_shell_start('workflows', 'Workflows', 'Configure the application pipeline and update candidate statuses.');
?>
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-bold">Status Rules</h2>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <?php foreach ($stages as $index => $stage): ?>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold"><?php echo e($stage); ?></span>
            <?php if ($index < count($stages) - 1): ?><span class="text-slate-400">→</span><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <p class="mt-3 text-sm text-slate-500">Forward pipeline transitions are preferred. Rejected remains available from any stage.</p>
</div>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white xl:col-span-2">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Application</th><th class="px-5 py-3">Current</th><th class="px-5 py-3">Update</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php if ($apps && mysqli_num_rows($apps) > 0): while ($app = mysqli_fetch_assoc($apps)): ?>
                <tr>
                    <td class="px-5 py-4"><div class="font-semibold"><?php echo e($app['full_name']); ?></div><div class="text-xs text-slate-500"><?php echo e($app['title']); ?></div></td>
                    <td class="px-5 py-4"><?php echo status_badge($app['status'] ?: 'Applied'); ?></td>
                    <td class="px-5 py-4">
                        <form method="post" action="update_status.php" class="flex gap-2">
                            <input type="hidden" name="application_id" value="<?php echo (int) $app['id']; ?>">
                            <select name="new_status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <?php foreach ($stages as $stage): ?><option value="<?php echo e($stage); ?>" <?php echo $stage === $app['status'] ? 'selected' : ''; ?>><?php echo e($stage); ?></option><?php endforeach; ?>
                            </select>
                            <button class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">No applications found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Recent Workflow Logs</h2>
        <div class="mt-4 divide-y divide-slate-100">
            <?php if ($logs && mysqli_num_rows($logs) > 0): while ($log = mysqli_fetch_assoc($logs)): ?>
                <div class="py-3 text-sm"><div class="font-semibold"><?php echo e($log['full_name']); ?></div><div class="text-slate-500"><?php echo e($log['old_status'] ?: 'New'); ?> → <?php echo e($log['new_status']); ?></div><div class="text-xs text-slate-400"><?php echo e($log['created_at']); ?></div></div>
            <?php endwhile; else: ?>
                <p class="py-6 text-sm text-slate-500">No workflow changes yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_shell_end(); ?>
