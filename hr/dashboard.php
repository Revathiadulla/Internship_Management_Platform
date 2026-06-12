<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_module_access('dashboard');
require __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/status_utils.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
ensure_module_schema($conn);
sync_candidates_from_applications($conn);

$status_order = ['Applied', 'HR Review', 'Shortlisted', 'HOD Pending', 'HOD Approved', 'Selected', 'Rejected'];
$status_counts = array_fill_keys($status_order, 0);
$status_res = mysqli_query($conn, "SELECT COALESCE(status, 'Applied') AS status, COUNT(*) AS cnt FROM internship_applications WHERE is_deleted = 0 AND status NOT IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Internship Completed', 'Certificate Issued', 'Archived') GROUP BY status");
if ($status_res) {
    while ($row = mysqli_fetch_assoc($status_res)) {
        $st = $row['status'];
        if ($st === 'HR Selected') { $st = 'Selected'; }
        
        // Map legacy HOD statuses
        if (in_array($st, ['HOD Approval Pending', 'Forwarded to HOD'], true)) {
            $st = 'HOD Pending';
        }
        if (isset($status_counts[$st])) {
            $status_counts[$st] += (int) $row['cnt'];
        }
    }
}

function scalar_count(mysqli $conn, string $sql): int {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_assoc($res);
    return (int) ($row['total'] ?? 0);
}

$total_applications = scalar_count($conn, "SELECT COUNT(*) AS total FROM internship_applications WHERE is_deleted = 0 AND status NOT IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Internship Completed', 'Certificate Issued', 'Archived')");
$total_candidates = scalar_count($conn, "SELECT COUNT(*) AS total FROM candidates");
$pending_logs = scalar_count($conn, "SELECT COUNT(*) AS total FROM daily_logs WHERE hr_review_status = 'Pending'");
$logs_today = scalar_count($conn, "SELECT COUNT(*) AS total FROM daily_logs WHERE DATE(created_at) = CURDATE()");
$new_today = scalar_count($conn, "SELECT COUNT(*) AS total FROM internship_applications WHERE is_deleted = 0 AND status NOT IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Internship Completed', 'Certificate Issued', 'Archived') AND DATE(applied_date) = CURDATE()");

$recent_apps = mysqli_query($conn, "SELECT a.id, a.status, a.verification_status, a.applied_date,
        COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown Applicant') AS full_name,
        COALESCE(sp.college_name, a.college_name, 'Not added') AS college,
        COALESCE(sp.skills, a.skills, '') AS skills,
        COALESCE(jp.title, i.title, a.internship_name, 'Untitled Posting') AS assigned_project_title,
        COALESCE(NULLIF(a.applied_subtype, ''), NULLIF(i.project_subtype, ''), '') AS applied_subtype,
        a.internship_name AS application_internship_name
    FROM internship_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.is_deleted = 0
    ORDER BY a.applied_date DESC
    LIMIT 8");

function get_recent_app_posting_title(array $app): string {
    $selected_statuses = ['Selected', 'Active Intern', 'Internship Started'];
    if (in_array($app['status'] ?? '', $selected_statuses, true)) {
        return trim($app['assigned_project_title'] ?? '') ?: 'Untitled Posting';
    }

    $subtype = trim($app['applied_subtype'] ?? '');
    if ($subtype === '') {
        $subtype = trim(preg_replace('/(\s+Internship|\s+Intern)$/i', '', $app['application_internship_name'] ?? ''));
    }

    if ($subtype === '' || in_array(strtolower($subtype), ['internship management platform', 'imp'], true)) {
        return 'Not Available';
    }

    return $subtype;
}

$recent_logs = mysqli_query($conn, "SELECT dl.*, u.full_name, COALESCE(i.title, CONCAT('Internship #', dl.internship_id)) as posting_title 
    FROM daily_logs dl 
    JOIN users u ON dl.user_id = u.id 
    LEFT JOIN internships i ON dl.internship_id = i.id 
    ORDER BY dl.log_date DESC, dl.created_at DESC 
    LIMIT 6");

$activity_rows = [];
$activity_sql = "SELECT h.created_at,
       COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown Applicant') AS actor_name,
       CONCAT('Status changed from ', COALESCE(h.old_status, 'New'), ' to ', h.new_status) AS action_text,
       h.updated_by_name AS source_name,
       h.new_status AS badge_status
    FROM application_status_history h
    LEFT JOIN internship_applications a ON a.id = h.application_id
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY h.created_at DESC
    LIMIT 10";
$activity_res = mysqli_query($conn, $activity_sql);
if ($activity_res) {
    while ($row = mysqli_fetch_assoc($activity_res)) {
        $activity_rows[] = $row;
    }
}

$applied_count     = $status_counts['Applied'] ?? 0;
$shortlisted_count = $status_counts['Shortlisted'] ?? 0;

$hod_pending_count = $status_counts['HOD Pending'] ?? 0;
$hr_review_count   = $status_counts['HR Review'] ?? 0;
$hod_approved_count= $status_counts['HOD Approved'] ?? 0;
$selected_count    = $status_counts['Selected'] ?? 0;
$rejected_count    = $status_counts['Rejected'] ?? 0;

page_shell_start('dashboard', 'Dashboard', 'Live HR overview powered by current applications, postings, candidates, and users.');
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Welcome Header Card -->
<div class="mb-8 rounded-2xl bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 p-6 md:p-8 text-white shadow-md relative overflow-hidden">
    <!-- decorative background circles -->
    <div class="absolute right-0 top-0 -mt-4 -mr-4 w-48 h-48 rounded-full bg-white opacity-10 blur-xl"></div>
    <div class="absolute bottom-0 right-1/4 -mb-10 w-64 h-64 rounded-full bg-indigo-500 opacity-20 blur-2xl"></div>
    
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">Welcome back, HR Manager!</h2>
            <p class="mt-2 text-indigo-100 text-sm md:text-base font-medium max-w-xl">
                Here's the latest overview of the Internship Management Platform. You can manage student applications, review daily progress logs, and coordinate with HODs.
            </p>
            <div class="mt-4 text-xs font-bold bg-white/10 hover:bg-white/20 transition px-3 py-1.5 rounded-full inline-flex items-center gap-2">
                <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                <span>Today is <?php echo date('F j, Y'); ?></span>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 shrink-0">
            <a href="applications.php" class="bg-white text-indigo-900 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:shadow-md hover:bg-indigo-50 transition flex items-center gap-2">
                <i class="fas fa-file-invoice"></i> Applications
            </a>
            <a href="applications.php?status=Shortlisted" class="bg-indigo-600 border border-indigo-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:shadow-md hover:bg-indigo-500/85 transition flex items-center gap-2">
                <i class="fas fa-star"></i> Shortlisted
            </a>
            <a href="student_logs.php" class="bg-purple-900 border border-purple-800 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:shadow-md hover:bg-purple-850 transition flex items-center gap-2">
                <i class="fas fa-history"></i> Student Logs
            </a>
        </div>
    </div>
</div>

<!-- Statistics Metrics Grid -->
<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <!-- Applications Card -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition duration-200">
        <div class="flex items-center justify-between">
            <div class="bg-blue-50 text-blue-600 p-3.5 rounded-2xl">
                <i class="fas fa-file-alt text-xl"></i>
            </div>
            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full">Overview</span>
        </div>
        <div class="mt-4">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Applications</h3>
            <p class="mt-1.5 text-3xl font-black text-slate-800"><?php echo number_format($total_applications); ?></p>
            <p class="mt-2 text-xs text-slate-400 font-semibold flex items-center gap-1">
                <i class="fas fa-plus-circle text-green-500"></i> <?php echo number_format($new_today); ?> new today
            </p>
        </div>
    </div>

    <!-- Candidates Card -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition duration-200">
        <div class="flex items-center justify-between">
            <div class="bg-violet-50 text-violet-600 p-3.5 rounded-2xl">
                <i class="fas fa-users text-xl"></i>
            </div>
            <span class="text-xs font-bold text-violet-600 bg-violet-50 px-2.5 py-1 rounded-full">Talent</span>
        </div>
        <div class="mt-4">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Candidates</h3>
            <p class="mt-1.5 text-3xl font-black text-slate-800"><?php echo number_format($total_candidates); ?></p>
            <p class="mt-2 text-xs text-slate-400 font-semibold">Synced from applications</p>
        </div>
    </div>

    <!-- Pending Log Reviews Card -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition duration-200">
        <div class="flex items-center justify-between">
            <div class="bg-amber-50 text-amber-600 p-3.5 rounded-2xl">
                <i class="fas fa-clipboard-list text-xl"></i>
            </div>
            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full">Action Required</span>
        </div>
        <div class="mt-4">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider">Pending Log Reviews</h3>
            <p class="mt-1.5 text-3xl font-black text-slate-800"><?php echo number_format($pending_logs); ?></p>
            <p class="mt-2 text-xs text-slate-400 font-semibold">Awaiting HR evaluation</p>
        </div>
    </div>

    <!-- Logs Submitted Today Card -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition duration-200">
        <div class="flex items-center justify-between">
            <div class="bg-indigo-50 text-indigo-600 p-3.5 rounded-2xl">
                <i class="fas fa-calendar-check text-xl"></i>
            </div>
            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full">Submissions</span>
        </div>
        <div class="mt-4">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider">Logs Submitted Today</h3>
            <p class="mt-1.5 text-3xl font-black text-slate-800"><?php echo number_format($logs_today); ?></p>
            <p class="mt-2 text-xs text-slate-400 font-semibold">Daily progress entries</p>
        </div>
    </div>
</div>

<!-- Workflow status grid (8 cards) -->
<div class="mt-8">
    <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mb-4">Workflow Application Pipeline</h2>
    <div class="grid gap-5 grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
        <!-- Applied -->
        <a href="applications.php?status=Applied" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-blue-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-blue-50 text-blue-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-file-signature"></i>
                </div>
                <span class="text-[10px] font-bold text-blue-600 tracking-wider">Applied</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($applied_count); ?></p>
            </div>
        </a>
        
        <!-- Shortlisted -->
        <a href="applications.php?status=Shortlisted" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-orange-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-orange-50 text-orange-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-star"></i>
                </div>
                <span class="text-[10px] font-bold text-orange-600 tracking-wider">Shortlisted</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($shortlisted_count); ?></p>
            </div>
        </a>

        

        <!-- HOD Pending -->
        <a href="applications.php?status=HOD+Pending" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-orange-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-orange-50 text-orange-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <span class="text-[10px] font-bold text-orange-600 tracking-wider">HOD Pending</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($hod_pending_count); ?></p>
            </div>
        </a>

        <!-- HR Review -->
        <a href="applications.php?status=HR+Review" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-indigo-50 text-indigo-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-user-pen"></i>
                </div>
                <span class="text-[10px] font-bold text-indigo-600 tracking-wider">HR Review</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($hr_review_count); ?></p>
            </div>
        </a>

        <!-- HOD Approved -->
        <a href="applications.php?status=HOD+Approved" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-teal-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-teal-50 text-teal-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-user-check"></i>
                </div>
                <span class="text-[10px] font-bold text-teal-600 tracking-wider">HOD Appr</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($hod_approved_count); ?></p>
            </div>
        </a>

        <!-- Selected -->
        <a href="applications.php?status=Selected" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-green-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-green-50 text-green-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span class="text-[10px] font-bold text-green-600 tracking-wider">Selected</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($selected_count); ?></p>
            </div>
        </a>

        <!-- Rejected -->
        <a href="applications.php?status=Rejected" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:border-red-200 transition duration-150 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
                <div class="bg-red-50 text-red-600 p-2 rounded-xl text-sm shrink-0">
                    <i class="fas fa-times-circle"></i>
                </div>
                <span class="text-[10px] font-bold text-red-600 tracking-wider">Rejected</span>
            </div>
            <div class="mt-4">
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($rejected_count); ?></p>
            </div>
        </a>
    </div>
</div>

<!-- Recent Submissions Grid -->
<div class="mt-8 grid gap-6 xl:grid-cols-3">
    <!-- Recent Applicants Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm xl:col-span-2 overflow-hidden hover:shadow-md transition">
        <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-800">Recent Applicants</h3>
                <p class="text-xs text-slate-400 font-semibold mt-1">Latest application submissions</p>
            </div>
            <a href="applications.php" class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline">View all</a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-6 py-4">Applicant</th>
                        <th class="px-6 py-4">Internship Domain</th>
                        <th class="px-6 py-4">Verification</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Applied</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600">
                    <?php if ($recent_apps && mysqli_num_rows($recent_apps) > 0): while ($app = mysqli_fetch_assoc($recent_apps)): ?>
                        <tr class="hover:bg-slate-50/30 transition">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <img class="w-9 h-9 rounded-full border border-slate-200 shadow-sm shrink-0" src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['full_name']); ?>&background=random&size=100" alt="">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-800 leading-tight truncate"><?php echo htmlspecialchars($app['full_name']); ?></p>
                                    <p class="text-[11px] text-slate-400 mt-1 max-w-[180px] truncate"><?php echo htmlspecialchars($app['college']); ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-medium"><?php echo htmlspecialchars(get_recent_app_posting_title($app)); ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                  $v = $app['verification_status'] ?: 'Pending';
                                  $v_badge = 'bg-gray-50 text-gray-600 border-gray-200';
                                  if ($v === 'Verified') $v_badge = 'bg-green-50 text-green-700 border-green-200';
                                  if ($v === 'Rejected') $v_badge = 'bg-red-50 text-red-700 border-red-200';
                                ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?php echo $v_badge; ?>">
                                    <?php echo $v; ?>
                                </span>
                              </td>
                              <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border <?php echo getStatusBadgeClass($app['status'] ?: 'Applied'); ?>">
                                    <?php echo htmlspecialchars(formatStatusLabel($app['status'] ?: 'Applied')); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-400 font-semibold text-xs"><?php echo $app['applied_date'] ? date('M d, Y', strtotime($app['applied_date'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-400 text-xs italic">No applications found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Logs Card -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between border-b border-slate-50 pb-4 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Recent Student Logs</h3>
                    <p class="text-xs text-slate-400 font-semibold mt-1">Submitted progress reports</p>
                </div>
                <a href="student_logs.php" class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline">View all</a>
            </div>
            
            <div class="divide-y divide-slate-100">
                <?php if ($recent_logs && mysqli_num_rows($recent_logs) > 0): while ($log = mysqli_fetch_assoc($recent_logs)): ?>
                    <div class="py-3.5 flex justify-between items-start gap-4">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-800 leading-tight truncate"><?php echo htmlspecialchars($log['full_name']); ?></p>
                            <p class="text-xs text-slate-400 mt-1 truncate" title="<?php echo htmlspecialchars($log['tasks_completed']); ?>"><?php echo htmlspecialchars($log['tasks_completed']); ?></p>
                        </div>
                        <div class="text-right shrink-0 flex flex-col items-end gap-1">
                            <?php
                              $s = $log['hr_review_status'] ?: 'Pending';
                              $s_badge = 'bg-gray-100 text-gray-700';
                              if ($s === 'Approved') $s_badge = 'bg-green-100 text-green-700';
                              if ($s === 'Rejected') $s_badge = 'bg-red-100 text-red-700';
                            ?>
                            <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?php echo $s_badge; ?>"><?php echo $s; ?></span>
                            <p class="text-[10px] text-slate-400 mt-0.5 font-bold uppercase"><?php echo date('M d', strtotime($log['log_date'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p class="py-6 text-sm text-slate-400 italic text-center">No student logs registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4 pt-4 border-t border-slate-50">
            <a href="student_logs.php" class="w-full text-center inline-block bg-slate-50 hover:bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-bold transition">
                Monitor Student Logs
            </a>
        </div>
    </div>
</div>

<!-- Timeline Activity Section -->
<div class="mt-8 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition">
    <div class="border-b border-slate-50 pb-4 mb-6">
        <h3 class="text-base font-bold text-slate-800">Platform Activity Timeline</h3>
        <p class="text-xs text-slate-400 font-semibold mt-1">Live updates from system status logs</p>
    </div>
    
    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
        <?php if (!empty($activity_rows)): foreach ($activity_rows as $activity): 
            $act_status = $activity['badge_status'] ?? '';
            
            // Resolve custom timeline icons and backgrounds
            $act_icon = '<i class="fas fa-edit text-xs"></i>';
            $act_bg = 'bg-slate-50 text-slate-500 border border-slate-200';
            
            if (strpos(strtolower($act_status), 'applied') !== false) {
                $act_icon = '<i class="fas fa-file-signature text-xs"></i>';
                $act_bg = 'bg-blue-50 text-blue-600 border border-blue-100';
            } elseif (strpos(strtolower($act_status), 'shortlisted') !== false) {
                $act_icon = '<i class="fas fa-star text-xs"></i>';
                $act_bg = 'bg-orange-50 text-orange-600 border border-orange-100';
            } elseif (strpos(strtolower($act_status), 'exam') !== false) {
                $act_icon = '<i class="fas fa-laptop-code text-xs"></i>';
                $act_bg = 'bg-purple-50 text-purple-600 border border-purple-100';
            } elseif (strpos(strtolower($act_status), 'selected') !== false) {
                $act_icon = '<i class="fas fa-user-check text-xs"></i>';
                $act_bg = 'bg-green-50 text-green-600 border border-green-100';
            } elseif (strpos(strtolower($act_status), 'rejected') !== false) {
                $act_icon = '<i class="fas fa-user-slash text-xs"></i>';
                $act_bg = 'bg-red-50 text-red-600 border border-red-100';
            } elseif (strpos(strtolower($act_status), 'hod') !== false) {
                $act_icon = '<i class="fas fa-university text-xs"></i>';
                $act_bg = 'bg-teal-50 text-teal-600 border border-teal-100';
            }
        ?>
            <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Icon positioned on the timeline line -->
                <div class="absolute -left-7 top-1 w-5 h-5 rounded-full flex items-center justify-center shadow-sm <?php echo $act_bg; ?>">
                    <?php echo $act_icon; ?>
                </div>
                
                <div>
                    <span class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($activity['actor_name']); ?></span>
                    <span class="text-xs text-slate-500 ml-1"><?php echo htmlspecialchars($activity['action_text']); ?></span>
                    <p class="text-[10px] text-slate-400 mt-1 font-semibold">Initiated by <?php echo htmlspecialchars($activity['source_name'] ?: 'System'); ?></p>
                </div>
                
                <div class="text-right shrink-0 flex flex-col items-start sm:items-end gap-1">
                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?php echo getStatusBadgeClass($act_status ?: 'Updated'); ?>">
                        <?php echo htmlspecialchars(formatStatusLabel($act_status ?: 'Updated')); ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="py-6 text-center text-slate-400 text-xs italic">No activity registered yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php page_shell_end(); ?>
