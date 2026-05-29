<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('dashboard');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);
sync_candidates_from_applications($conn);

$status_order = ['Applied', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];
$status_counts = array_fill_keys($status_order, 0);
$status_res = mysqli_query($conn, "SELECT COALESCE(status, 'Applied') AS status, COUNT(*) AS cnt FROM internship_applications WHERE is_deleted = 0 GROUP BY status");
if ($status_res) {
    while ($row = mysqli_fetch_assoc($status_res)) {
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = (int) $row['cnt'];
        }
    }
}

function scalar_count(mysqli $conn, string $sql): int {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_assoc($res);
    return (int) ($row['total'] ?? 0);
}

$total_applications = scalar_count($conn, "SELECT COUNT(*) AS total FROM internship_applications WHERE is_deleted = 0");
$total_candidates = scalar_count($conn, "SELECT COUNT(*) AS total FROM candidates");
$pending_logs = scalar_count($conn, "SELECT COUNT(*) AS total FROM daily_logs WHERE hr_review_status = 'Pending'");
$logs_today = scalar_count($conn, "SELECT COUNT(*) AS total FROM daily_logs WHERE DATE(created_at) = CURDATE()");
$new_today = scalar_count($conn, "SELECT COUNT(*) AS total FROM internship_applications WHERE is_deleted = 0 AND DATE(applied_date) = CURDATE()");

$recent_apps = mysqli_query($conn, "SELECT a.id, a.status, a.verification_status, a.applied_date,
        COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown Applicant') AS full_name,
        COALESCE(sp.college_name, a.college_name, 'Not added') AS college,
        COALESCE(sp.skills, a.skills, a.relevant_skills, '') AS skills,
        COALESCE(jp.title, i.title, a.internship_name, 'Untitled Posting') AS posting_title
    FROM internship_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.is_deleted = 0
    ORDER BY a.applied_date DESC
    LIMIT 8");

$recent_logs = mysqli_query($conn, "SELECT dl.*, u.full_name, COALESCE(i.title, CONCAT('Internship #', dl.internship_id)) as posting_title 
    FROM daily_logs dl 
    JOIN users u ON dl.user_id = u.id 
    LEFT JOIN internships i ON dl.internship_id = i.id 
    ORDER BY dl.log_date DESC, dl.created_at DESC 
    LIMIT 6");

$activity_rows = [];
$activity_sql = "SELECT * FROM (
    SELECT h.created_at,
           COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown Applicant') AS actor_name,
           CONCAT('Status changed from ', COALESCE(h.old_status, 'New'), ' to ', h.new_status) AS action_text,
           h.updated_by_name AS source_name,
           h.new_status AS badge_status
    FROM application_status_history h
    LEFT JOIN internship_applications a ON a.id = h.application_id
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    LEFT JOIN users u ON a.user_id = u.id
    UNION ALL
    SELECT wl.created_at,
           COALESCE(c.full_name, 'Candidate') AS actor_name,
           CONCAT('Workflow changed from ', COALESCE(wl.old_status, 'New'), ' to ', wl.new_status) AS action_text,
           'Workflow' AS source_name,
           wl.new_status AS badge_status
    FROM workflow_logs wl
    LEFT JOIN candidates c ON wl.candidate_id = c.id
) activity
ORDER BY created_at DESC
LIMIT 10";
$activity_res = mysqli_query($conn, $activity_sql);
if ($activity_res) {
    while ($row = mysqli_fetch_assoc($activity_res)) {
        $activity_rows[] = $row;
    }
}

page_shell_start('dashboard', 'Dashboard', 'Live HR overview powered by current applications, postings, candidates, and users.');
?>
<div class="grid gap-5 lg:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Applications</p>
        <p class="mt-3 text-4xl font-extrabold text-slate-900"><?php echo number_format($total_applications); ?></p>
        <p class="mt-2 text-sm text-slate-500"><?php echo number_format($new_today); ?> new today</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Candidates</p>
        <p class="mt-3 text-4xl font-extrabold text-slate-900"><?php echo number_format($total_candidates); ?></p>
        <p class="mt-2 text-sm text-slate-500">Synced from applications</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500 text-amber-600">Pending Log Reviews</p>
        <p class="mt-3 text-4xl font-extrabold text-slate-900"><?php echo number_format($pending_logs); ?></p>
        <p class="mt-2 text-sm text-slate-500">Awaiting HR evaluation</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500 text-blue-600">Logs Submitted Today</p>
        <p class="mt-3 text-4xl font-extrabold text-slate-900"><?php echo number_format($logs_today); ?></p>
        <p class="mt-2 text-sm text-slate-500">Daily progress submissions</p>
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-6">
    <?php foreach ($status_counts as $status => $count): ?>
        <a href="hr_applications.php?status=<?php echo urlencode($status); ?>" class="rounded-lg border border-slate-200 bg-white p-5 transition hover:border-blue-200 hover:shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500"><?php echo e($status); ?></p>
            <p class="mt-3 text-3xl font-extrabold"><?php echo number_format($count); ?></p>
        </a>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Recent Applicants</h2>
                <p class="text-sm text-slate-500">Latest applications submitted into the system.</p>
            </div>
            <a href="hr_applications.php" class="text-sm font-semibold text-blue-700 hover:underline">View all</a>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-3">Applicant</th><th class="px-5 py-3">Posting</th><th class="px-5 py-3">Verification</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Applied</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if ($recent_apps && mysqli_num_rows($recent_apps) > 0): while ($app = mysqli_fetch_assoc($recent_apps)): ?>
                <tr>
                    <td class="px-5 py-4">
                        <div class="font-semibold text-slate-900"><?php echo e($app['full_name']); ?></div>
                        <div class="text-xs text-slate-500"><?php echo e($app['college']); ?><?php echo $app['skills'] ? ' · ' . e(substr($app['skills'], 0, 45)) : ''; ?></div>
                    </td>
                    <td class="px-5 py-4 text-slate-600"><?php echo e($app['posting_title']); ?></td>
                    <td class="px-5 py-4"><?php echo status_badge($app['verification_status'] ?: 'Pending'); ?></td>
                    <td class="px-5 py-4"><?php echo status_badge($app['status'] ?: 'Applied'); ?></td>
                    <td class="px-5 py-4 text-right text-slate-500"><?php echo $app['applied_date'] ? e(date('M d, Y', strtotime($app['applied_date']))) : 'NA'; ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No applications found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h2 class="text-lg font-bold text-slate-900">Recent Student Logs</h2>
            <a href="student_logs.php" class="text-sm font-semibold text-blue-700 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if ($recent_logs && mysqli_num_rows($recent_logs) > 0): while ($log = mysqli_fetch_assoc($recent_logs)): ?>
                <div class="py-3 text-sm flex justify-between items-start gap-4">
                    <div>
                        <div class="font-semibold text-slate-900"><?php echo e($log['full_name']); ?></div>
                        <div class="text-xs text-slate-500 truncate max-w-[200px]" title="<?php echo e($log['tasks_completed']); ?>"><?php echo e($log['tasks_completed']); ?></div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($log['hr_review_status'] ?: 'Pending'); ?></span>
                        <p class="text-[10px] text-slate-400 mt-1 font-semibold"><?php echo date('M d', strtotime($log['log_date'])); ?></p>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <p class="py-6 text-sm text-slate-500">No logs available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-lg font-bold text-slate-900">Recent Activity</h2>
        <p class="text-sm text-slate-500">Status and workflow changes recorded from live data.</p>
    </div>
    <div class="divide-y divide-slate-100">
        <?php if (!empty($activity_rows)): foreach ($activity_rows as $activity): ?>
            <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-slate-900"><?php echo e($activity['actor_name']); ?></p>
                    <p class="mt-1 text-sm text-slate-600"><?php echo e($activity['action_text']); ?></p>
                    <p class="mt-1 text-xs text-slate-400">By <?php echo e($activity['source_name'] ?: 'System'); ?></p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <?php echo status_badge($activity['badge_status'] ?: 'Updated'); ?>
                    <span class="text-xs text-slate-400"><?php echo e(date('M d, Y H:i', strtotime($activity['created_at']))); ?></span>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="px-6 py-10 text-center text-sm text-slate-500">No recent activity available.</div>
        <?php endif; ?>
    </div>
</div>
<?php page_shell_end(); ?>
