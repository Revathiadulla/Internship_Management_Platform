<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_role('mentor');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$mentor_id = current_user_id();
$success_msg = "";
$error_msg = "";

// Generate CSRF token if not set
generate_csrf_token();

// Handle Quick Feedback Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF security check failed.";
    } else {
        $student_id = intval($_POST['student_id']);
        $status = $_POST['status'] ?? 'Reviewed';
        $comments = trim($_POST['comments'] ?? '');
        
        // Find latest daily log for this student
        $log_stmt = $conn->prepare("SELECT id, internship_id, application_id FROM daily_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 1");
        $log_stmt->bind_param('i', $student_id);
        $log_stmt->execute();
        $latest_log = $log_stmt->get_result()->fetch_assoc();
        
        if ($latest_log) {
            $log_id = $latest_log['id'];
            $app_id = $latest_log['application_id'];
            $internship_id = $latest_log['internship_id'];
            
            mysqli_begin_transaction($conn);
            try {
                // Update daily logs
                $up_stmt = $conn->prepare("UPDATE daily_logs SET status = ?, mentor_feedback = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $up_stmt->bind_param('ssii', $status, $comments, $mentor_id, $log_id);
                $up_stmt->execute();
                
                // Insert into mentor_feedback
                $fb_title = "Quick Evaluation (" . date('Y-m-d') . ")";
                $mentor_name = $_SESSION['full_name'] ?? 'Mentor';
                $fb_stmt = $conn->prepare("INSERT INTO mentor_feedback (user_id, log_id, feedback_title, given_by, comments, status) VALUES (?, ?, ?, ?, ?, ?)");
                $fb_stmt->bind_param('iissss', $student_id, $log_id, $fb_title, $mentor_name, $comments, $status);
                $fb_stmt->execute();
                
                // Log action in activity logs
                $act_details = "Submitted quick evaluation for student #" . $student_id . " log ID " . $log_id . " as status: " . $status;
                log_activity($conn, 'Mentor Log Review', $act_details);
                $act_stmt = $conn->prepare("INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details) VALUES (?, 'review', ?, ?, ?)");
                $act_stmt->bind_param('iiis', $mentor_id, $student_id, $log_id, $act_details);
                $act_stmt->execute();

                
                // Notify student
                $notif_msg = "Your mentor reviewed your latest daily log and marked it: " . $status;
                $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message) VALUES (?, 'Log Evaluated', 'mentor', ?)");
                $notif_stmt->bind_param('is', $student_id, $notif_msg);
                $notif_stmt->execute();
                
                mysqli_commit($conn);
                $success_msg = "Feedback submitted successfully!";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = "Failed to submit feedback: " . $e->getMessage();
            }
        } else {
            $error_msg = "No daily logs found for the selected student to evaluate.";
        }
    }
}

// Compute Statistics
// 1. Assigned Interns
$assigned_stmt = $conn->prepare("SELECT COUNT(*) as count FROM mentor_assignments WHERE mentor_id = ? AND status = 'active'");
$assigned_stmt->bind_param('i', $mentor_id);
$assigned_stmt->execute();
$assigned_count = $assigned_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// 2. Active Interns (Submitted log in the last 7 days)
$active_stmt = $conn->prepare("SELECT COUNT(DISTINCT ma.student_id) as count FROM mentor_assignments ma JOIN daily_logs dl ON ma.student_id = dl.user_id WHERE ma.mentor_id = ? AND ma.status = 'active' AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$active_stmt->bind_param('i', $mentor_id);
$active_stmt->execute();
$active_count = $active_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Consolidated counts from daily_logs grouped by status
$status_counts_stmt = $conn->prepare("
    SELECT dl.status, COUNT(*) as count
    FROM daily_logs dl
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    GROUP BY dl.status
");
$status_counts_stmt->bind_param('i', $mentor_id);
$status_counts_stmt->execute();
$status_rows = $status_counts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$log_counts = [
    LOG_STATUS_SUBMITTED => 0,
    LOG_STATUS_APPROVED => 0,
    LOG_STATUS_REVIEWED => 0,
    LOG_STATUS_NEEDS_UPDATE => 0
];
$total_logs = 0;
foreach ($status_rows as $row) {
    if (array_key_exists($row['status'], $log_counts)) {
        $log_counts[$row['status']] = intval($row['count']);
    }
    $total_logs += intval($row['count']);
}

// 3. Pending Daily Logs (Submitted status)
$pending_count = $log_counts[LOG_STATUS_SUBMITTED];

// 4. Reviewed Logs (Reviewed + Approved statuses)
$reviewed_count = $log_counts[LOG_STATUS_REVIEWED] + $log_counts[LOG_STATUS_APPROVED];

// 5. Needs Update Count (Revisions status)
$needs_update_count = $log_counts[LOG_STATUS_NEEDS_UPDATE];

// 6. Completion Percentage (Approved / Total logs)
$completion_pct = 0.0;
if ($total_logs > 0) {
    $completion_pct = round(($log_counts[LOG_STATUS_APPROVED] / $total_logs) * 100, 1);
}
// 7. Overdue Submissions
$yesterday = date('Y-m-d', strtotime('-1 day'));
$today = date('Y-m-d');
$day_of_week = date('N');
if ($day_of_week == 1) { // Monday
    $yesterday = date('Y-m-d', strtotime('-3 days')); // Friday
}
$overdue_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ma.student_id) as count 
    FROM mentor_assignments ma 
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    AND ma.student_id NOT IN (
        SELECT DISTINCT user_id 
        FROM daily_logs 
        WHERE log_date IN (?, ?)
    )
");
$overdue_stmt->bind_param('iss', $mentor_id, $yesterday, $today);
$overdue_stmt->execute();
$overdue_count = $overdue_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Alerts - Inactive Interns (no logs in last 3 days)
$inactive_interns_stmt = $conn->prepare("
    SELECT u.id, u.full_name, MAX(dl.log_date) as last_log_date
    FROM mentor_assignments ma
    JOIN users u ON ma.student_id = u.id
    LEFT JOIN daily_logs dl ON u.id = dl.user_id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    GROUP BY u.id, u.full_name
    HAVING last_log_date IS NULL OR last_log_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)
");
$inactive_interns_stmt->bind_param('i', $mentor_id);
$inactive_interns_stmt->execute();
$inactive_interns = $inactive_interns_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Alerts - Pending reviews older than 24 hours
$old_pending_stmt = $conn->prepare("
    SELECT dl.id, u.full_name, dl.log_date, dl.created_at
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    WHERE ma.mentor_id = ? AND ma.status = 'active' AND dl.status = 'Submitted'
    AND dl.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$old_pending_stmt->bind_param('i', $mentor_id);
$old_pending_stmt->execute();
$old_pendings = $old_pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch assigned interns list with average ratings and last log dates
$interns_stmt = $conn->prepare("
    SELECT 
        u.id, 
        u.full_name, 
        u.email, 
        app.internship_name, 
        app.id as application_id,
        COUNT(dl.id) as total_logs,
        COUNT(CASE WHEN dl.status = 'Approved' THEN 1 END) as approved_logs,
        AVG(CASE WHEN dl.mentor_rating > 0 THEN dl.mentor_rating ELSE NULL END) as avg_rating,
        MAX(dl.log_date) as last_log_date
    FROM mentor_assignments ma
    JOIN users u ON ma.student_id = u.id
    JOIN internship_applications app ON ma.application_id = app.id
    LEFT JOIN daily_logs dl ON u.id = dl.user_id AND app.id = dl.application_id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    GROUP BY u.id, u.full_name, u.email, app.internship_name, app.id
");
$interns_stmt->bind_param('i', $mentor_id);
$interns_stmt->execute();
$interns_list = $interns_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent log submissions
$recent_logs_stmt = $conn->prepare("
    SELECT 
        dl.id, 
        dl.log_date, 
        dl.status, 
        u.full_name as student_name,
        u.id as student_id
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    ORDER BY dl.log_date DESC, dl.created_at DESC
    LIMIT 5
");
$recent_logs_stmt->bind_param('i', $mentor_id);
$recent_logs_stmt->execute();
$recent_logs = $recent_logs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent activity
$activity_stmt = $conn->prepare("
    SELECT action_type, details, created_at
    FROM mentor_activity_logs
    WHERE mentor_id = ?
    ORDER BY created_at DESC
    LIMIT 4
");
$activity_stmt->bind_param('i', $mentor_id);
$activity_stmt->execute();
$activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch HOD pending applications where candidate's status is 'HR Round' and education_status is 'Pursuing' and hod_email matches mentor's email
$mentor_email = $_SESSION['email'] ?? '';
$hod_pending_list = [];
if (!empty($mentor_email)) {
    $hod_pending_stmt = $conn->prepare("
        SELECT 
            a.id, 
            a.user_id, 
            a.internship_name, 
            a.education_status,
            a.hod_name,
            a.hod_email,
            u.full_name as student_name, 
            u.email as student_email,
            sp.resume_file,
            sp.resume_url
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        WHERE a.status = 'HR Round' 
          AND a.education_status = 'Pursuing'
          AND LOWER(TRIM(a.hod_email)) = LOWER(TRIM(?))
          AND a.is_deleted = 0
    ");
    if ($hod_pending_stmt) {
        $hod_pending_stmt->bind_param('s', $mentor_email);
        $hod_pending_stmt->execute();
        $hod_pending_list = $hod_pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<?php
$action_html = '<a href="export_logs.php" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
    <span class="material-symbols-outlined text-[16px]">download</span> Export Logs CSV
</a>
<a href="mentor_daily_logs.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
    <span class="material-symbols-outlined text-[16px]">rate_review</span> Review Submissions
</a>';

page_shell_start('dashboard', 'Mentor Dashboard', 'Welcome back, ' . htmlspecialchars($_SESSION['full_name'] ?? 'Mentor') . '. You have ' . $pending_count . ' pending log reviews.', $action_html);
?>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">
    <!-- Toast alerts -->
    <?php if ($success_msg !== ''): ?>
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_msg !== ''): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-red-600">error</span>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Assigned Interns -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Assigned Interns</p>
                <p class="text-2xl font-black text-slate-800 mt-1.5"><?php echo $assigned_count; ?></p>
                <span class="text-[10px] text-blue-600 font-bold block mt-1">Active assignments</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">group</span>
            </div>
        </div>
        <!-- Active Interns -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Active (7d)</p>
                <p class="text-2xl font-black text-emerald-600 mt-1.5"><?php echo $active_count; ?></p>
                <span class="text-[10px] text-emerald-600 font-bold block mt-1">Logs sent recently</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">bolt</span>
            </div>
        </div>
        <!-- Pending Logs -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Pending Logs</p>
                <p class="text-2xl font-black text-amber-500 mt-1.5"><?php echo $pending_count; ?></p>
                <span class="text-[10px] text-amber-650 font-bold block mt-1">Awaiting evaluation</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">hourglass_empty</span>
            </div>
        </div>
        <!-- Reviewed Logs -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Reviewed Logs</p>
                <p class="text-2xl font-black text-blue-600 mt-1.5"><?php echo $reviewed_count; ?></p>
                <span class="text-[10px] text-slate-400 font-bold block mt-1">Approved & reviewed</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">done_all</span>
            </div>
        </div>
        <!-- Overdue Interns -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Overdue Interns</p>
                <p class="text-2xl font-black text-red-600 mt-1.5"><?php echo $overdue_count; ?></p>
                <span class="text-[10px] text-red-600 font-bold block mt-1">No logs > 3 days</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">warning</span>
            </div>
        </div>
        <!-- Completion -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(241,245,249,0.5)] flex items-start justify-between">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Completion</p>
                <p class="text-2xl font-black text-indigo-600 mt-1.5"><?php echo $completion_pct; ?>%</p>
                <span class="text-[10px] text-slate-400 font-bold block mt-1">Avg progress rate</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">stars</span>
            </div>
        </div>
    </div>

            <!-- Main Grid -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Academic Approvals (HOD Role) -->
                <div class="col-span-12 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-black text-slate-800 tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600">verified_user</span>
                            Academic Approvals (HOD Role)
                        </h3>
                        <span class="text-xs font-bold text-slate-400 bg-slate-50 px-2.5 py-0.5 rounded-lg border border-slate-100"><?php echo count($hod_pending_list); ?> Pending</span>
                    </div>
                    
                    <?php if (empty($hod_pending_list)): ?>
                        <p class="text-xs text-slate-400 font-bold py-2">No pending applications requiring academic approval.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4 text-slate-500 font-bold text-xs">Student</th>
                                        <th class="py-3 px-4 text-slate-500 font-bold text-xs">Email</th>
                                        <th class="py-3 px-4 text-slate-500 font-bold text-xs">Internship</th>
                                        <th class="py-3 px-4 text-slate-500 font-bold text-xs">HOD Info</th>
                                        <th class="py-3 px-4 text-slate-500 font-bold text-xs text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($hod_pending_list as $app): 
                                        $profile_mock = [
                                            'resume_file' => $app['resume_file'] ?? '',
                                            'resume_url' => $app['resume_url'] ?? ''
                                        ];
                                        $view_link = get_resume_view_link($profile_mock);
                                        $exists = check_resume_exists($profile_mock);
                                        $has_res = (!empty($app['resume_file']) || !empty($app['resume_url']));
                                    ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors" id="hod-row-<?php echo $app['id']; ?>">
                                            <td class="py-4 px-4 font-semibold text-slate-800"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                            <td class="py-4 px-4 text-slate-500"><?php echo htmlspecialchars($app['student_email']); ?></td>
                                            <td class="py-4 px-4 text-slate-550"><?php echo htmlspecialchars($app['internship_name']); ?></td>
                                            <td class="py-4 px-4 text-xs text-slate-400">
                                                <strong>Name:</strong> <?php echo htmlspecialchars($app['hod_name'] ?? ''); ?><br>
                                                <strong>Email:</strong> <?php echo htmlspecialchars($app['hod_email'] ?? ''); ?>
                                            </td>
                                            <td class="py-4 px-4 text-right space-x-2 whitespace-nowrap">
                                                <?php if ($has_res): ?>
                                                    <a href="<?php echo $view_link; ?>" target="_blank" data-resume-exists="<?php echo $exists ? 'true' : 'false'; ?>" class="inline-flex items-center justify-center bg-blue-50 border border-blue-200 text-blue-600 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm hover:bg-blue-100 cursor-pointer">
                                                        View Resume
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="handleHodApproval(<?php echo $app['id']; ?>, 'HOD Approved')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm cursor-pointer">
                                                    Approve
                                                </button>
                                                <button onclick="handleHodApproval(<?php echo $app['id']; ?>, 'Rejected')" class="bg-red-650 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm cursor-pointer">
                                                    Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Interns -->
                <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-black text-slate-800 tracking-tight">Assigned Interns</h3>
                        <span class="text-xs font-bold text-slate-400 bg-slate-50 px-2.5 py-0.5 rounded-lg border border-slate-100"><?php echo count($interns_list); ?> Active</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($interns_list as $intern): 
                            $initial = strtoupper(substr($intern['full_name'], 0, 2));
                            // Assume target 40 logs for program completion
                            $progress_pct = 0;
                            if ($intern['total_logs'] > 0) {
                                $progress_pct = min(100, round(($intern['approved_logs'] / 40) * 100));
                            }
                            
                            // Last seen indicator
                            $last_active_str = 'No logs';
                            $is_inactive_warning = false;
                            if ($intern['last_log_date']) {
                                $days_diff = (time() - strtotime($intern['last_log_date'])) / (60 * 60 * 24);
                                if ($days_diff < 1) {
                                    $last_active_str = 'Active Today';
                                } else {
                                    $days_int = floor($days_diff);
                                    $last_active_str = "Active $days_int day" . ($days_int > 1 ? 's' : '') . ' ago';
                                    if ($days_int >= 4) {
                                        $is_inactive_warning = true;
                                    }
                                }
                            }
                            
                            // Rating stars display
                            $stars_html = '';
                            if ($intern['avg_rating'] > 0) {
                                $rating = round($intern['avg_rating'], 1);
                                $full_stars = floor($rating);
                                for ($i = 0; $i < 5; $i++) {
                                    if ($i < $full_stars) {
                                        $stars_html .= '★';
                                    } else {
                                        $stars_html .= '☆';
                                    }
                                }
                                $rating_display = '<span class="text-amber-500 text-xs font-bold" title="Avg Rating: ' . $rating . '">' . $stars_html . ' <span class="text-[10px] text-slate-500 font-semibold">(' . $rating . ')</span></span>';
                            } else {
                                $rating_display = '<span class="text-slate-300 text-[10px] font-bold">No rating yet</span>';
                            }
                        ?>
                            <div class="border border-slate-100 bg-slate-50/20 rounded-2xl p-4 flex flex-col justify-between hover:shadow-md transition-all duration-200">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-750 flex items-center justify-center font-bold text-sm shrink-0"><?php echo $initial; ?></div>
                                        <div>
                                            <h4 class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($intern['full_name']); ?></h4>
                                            <p class="text-xs text-slate-400 font-semibold mt-0.5"><?php echo htmlspecialchars($intern['internship_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($is_inactive_warning): ?>
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-red-600 bg-red-50 border border-red-100 px-1.5 py-0.5 rounded-md animate-pulse">
                                                <span class="material-symbols-outlined text-[11px]">warning</span> Overdue
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[9px] font-bold text-slate-400 bg-slate-50 border border-slate-100 px-1.5 py-0.5 rounded-md">
                                                <?php echo $last_active_str; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Average Rating</div>
                                    <?php echo $rating_display; ?>
                                </div>
                                <div class="mt-3 space-y-1">
                                    <div class="flex justify-between text-[10px] font-bold text-slate-500">
                                        <span>Logs: <?php echo $intern['total_logs']; ?></span>
                                        <span>Approved: <?php echo $intern['approved_logs']; ?> (<?php echo $progress_pct; ?>%)</span>
                                    </div>
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-full transition-all" style="width: <?php echo $progress_pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($interns_list)): ?>
                            <div class="col-span-2 text-center py-6 text-slate-400 text-sm">No interns assigned to you yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alerts & Warnings -->
                <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-black text-slate-850 flex items-center gap-1.5"><span class="material-symbols-outlined text-orange-500">notifications_active</span> Overdue Alerts</h3>
                            <?php 
                                $total_alerts = count($inactive_interns) + count($old_pendings);
                            ?>
                            <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full"><?php echo $total_alerts; ?> Alert(s)</span>
                        </div>
                        <div class="space-y-3">
                            <!-- Inactive Interns -->
                            <?php foreach ($inactive_interns as $alert): ?>
                                <div class="flex items-start gap-3 p-3 bg-red-50 rounded-xl border border-red-100">
                                    <span class="material-symbols-outlined text-red-500 text-sm mt-0.5">warning</span>
                                    <div>
                                        <p class="text-xs font-bold text-red-800">Student Inactive (3+ Days)</p>
                                        <p class="text-[11px] text-red-600 mt-0.5"><?php echo htmlspecialchars($alert['full_name']); ?> has not submitted a log since <?php echo $alert['last_log_date'] ? date('M d, Y', strtotime($alert['last_log_date'])) : 'beginning'; ?>.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Pending Reviews > 24h -->
                            <?php foreach ($old_pendings as $alert): ?>
                                <div class="flex items-start gap-3 p-3 bg-amber-50 rounded-xl border border-amber-100">
                                    <span class="material-symbols-outlined text-amber-500 text-sm mt-0.5">hourglass_empty</span>
                                    <div>
                                        <p class="text-xs font-bold text-amber-800">Pending Review > 24 Hours</p>
                                        <p class="text-[11px] text-amber-600 mt-0.5"><?php echo htmlspecialchars($alert['full_name']); ?> log for <?php echo date('M d, Y', strtotime($alert['log_date'])); ?> is awaiting evaluation.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($total_alerts === 0): ?>
                                <div class="flex flex-col items-center justify-center py-6 text-center text-slate-400">
                                    <span class="material-symbols-outlined text-3xl text-slate-200">verified</span>
                                    <p class="text-xs mt-1">All clear! No overdue logs or inactive interns.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Logs Table -->
                <div class="col-span-12 lg:col-span-7 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="text-lg font-black text-slate-800 tracking-tight">Recent Logs</h3>
                        <a href="mentor_daily_logs.php" class="text-blue-600 text-xs font-bold hover:underline">View All</a>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="py-3 px-6 text-slate-500 font-bold text-xs">Intern</th>
                                <th class="py-3 px-6 text-slate-500 font-bold text-xs">Date</th>
                                <th class="py-3 px-6 text-slate-500 font-bold text-xs">Status</th>
                                <th class="py-3 px-6 text-slate-500 font-bold text-xs text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($recent_logs as $log): 
                                $initial = strtoupper(substr($log['student_name'], 0, 2));
                                $status_color = 'bg-amber-50 text-amber-700 border-amber-200';
                                if ($log['status'] === 'Approved') $status_color = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                if ($log['status'] === 'Needs Update') $status_color = 'bg-red-50 text-red-700 border-red-200';
                                if ($log['status'] === 'Reviewed') $status_color = 'bg-blue-50 text-blue-700 border-blue-200';
                            ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-[10px]"><?php echo $initial; ?></span>
                                            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($log['student_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-slate-500"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($log['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <a href="mentor_daily_logs.php?search=<?php echo urlencode($log['student_name']); ?>" class="text-blue-600 font-bold text-xs hover:underline">Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-6 text-slate-400">No logs submitted yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick Feedback Submission Form -->
                <div class="col-span-12 lg:col-span-5 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-blue-600">rate_review</span>
                            <h3 class="text-lg font-black text-slate-800 tracking-tight">Quick Feedback</h3>
                        </div>
                        <form action="mentor_dashboard.php" method="POST" class="space-y-4" onsubmit="return confirm('Are you sure you want to submit this quick feedback?');">
                            <?php echo csrf_token_field(); ?>
                            <div>
                                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Select Intern</label>
                                <select name="student_id" required class="w-full rounded-xl border border-slate-200 text-sm py-2.5 focus:border-blue-600 focus:ring-blue-600/10">
                                    <option value="">-- Choose Intern Student --</option>
                                    <?php foreach ($interns_list as $intern): ?>
                                        <option value="<?php echo $intern['id']; ?>"><?php echo htmlspecialchars($intern['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Log Status</label>
                                <select name="status" required class="w-full rounded-xl border border-slate-200 text-sm py-2.5 focus:border-blue-600 focus:ring-blue-600/10">
                                    <option value="Approved">Approved (Progress Approved)</option>
                                    <option value="Reviewed">Reviewed (No further actions)</option>
                                    <option value="Needs Update">Needs Update (Requests revisions)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Comments / Feedback</label>
                                <textarea name="comments" required class="w-full rounded-xl border border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Share specific insights or instructions..." rows="3"></textarea>
                            </div>

                            <button type="submit" name="submit_feedback" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 mt-2 rounded-xl font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">send</span>
                                Submit Evaluation
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Audit/Activity Logs and Charts -->
                <div class="col-span-12 lg:col-span-6 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Submission & Evaluation Trends</h3>
                    <div class="relative h-[250px] w-full">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Recent Supervision Activity</h3>
                    <div class="relative pl-6 border-l border-slate-100 space-y-6 mt-4">
                        <?php foreach ($activities as $act): 
                            $icon = 'rate_review';
                            $icon_color = 'bg-blue-50 text-blue-700 ring-4 ring-white';
                            if ($act['action_type'] === 'assignment') {
                                $icon = 'person_add';
                                $icon_color = 'bg-green-50 text-green-700 ring-4 ring-white';
                            }
                            $time_ago = date('M d, Y · g:i A', strtotime($act['created_at']));
                        ?>
                            <div class="relative">
                                <!-- Dot indicator on the line -->
                                <div class="absolute -left-[37px] top-0.5 flex h-7 w-7 items-center justify-center rounded-full <?php echo $icon_color; ?> shadow-sm">
                                    <span class="material-symbols-outlined text-[13px] font-bold"><?php echo $icon; ?></span>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-slate-700 leading-snug"><?php echo htmlspecialchars($act['details']); ?></p>
                                    <span class="text-[10px] text-slate-400 font-medium mt-1 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span> <?php echo $time_ago; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-6 text-slate-400 text-xs font-bold">No supervision logs recorded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <script>
        function handleHodApproval(appId, newStatus) {
            const actionWord = newStatus === 'HOD Approved' ? 'approve' : 'reject';
            const confirmMsg = `Are you sure you want to ${actionWord} this candidate application?`;
            if (!confirm(confirmMsg)) return;

            const notes = prompt(`Add any optional comments for the ${actionWord} action:`) || "";

            const formData = new FormData();
            formData.append('application_id', appId);
            formData.append('new_status', newStatus);
            formData.append('notes', notes);

            fetch('update_application_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    const row = document.getElementById(`hod-row-${appId}`);
                    if (row) {
                        row.remove();
                    } else {
                        location.reload();
                    }
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred during submission.");
            });
        }

        // Setup Chart.js trendsChart
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending Review', 'Approved', 'Needs Revision'],
                    datasets: [{
                        data: [
                            <?php echo $pending_count; ?>, 
                            <?php echo $log_counts[LOG_STATUS_APPROVED] ?? 0; ?>, 
                            <?php echo $needs_update_count; ?>
                        ],
                        backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11, weight: 'bold' }
                            }
                        }
                    }
                }
            });
        });
    </script>
<?php print_resume_not_found_js(); ?>
<?php page_shell_end(); ?>