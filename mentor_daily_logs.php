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

// AJAX Endpoint: Fetch log history of an assigned student
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'history') {
    $student_id = intval($_GET['student_id']);
    
    // Ensure student is assigned to this mentor
    $chk_stmt = $conn->prepare("SELECT id FROM mentor_assignments WHERE mentor_id = ? AND student_id = ? AND status = 'active' LIMIT 1");
    $chk_stmt->bind_param('ii', $mentor_id, $student_id);
    $chk_stmt->execute();
    if (!$chk_stmt->get_result()->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized student lookup.']);
        exit();
    }
    
    $history_stmt = $conn->prepare("SELECT id, log_date, tasks_completed, time_spent, focus_level, status, mentor_feedback FROM daily_logs WHERE user_id = ? ORDER BY log_date DESC");
    $history_stmt->bind_param('i', $student_id);
    $history_stmt->execute();
    $hist_result = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'history' => $hist_result]);
    exit();
}

// Handle Form Submissions (Reviews, Approvals, Reminders)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF security check failed.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'submit_review') {
            $log_id = intval($_POST['log_id']);
            $status = $_POST['status'] ?? LOG_STATUS_REVIEWED;
            $comments = trim($_POST['mentor_comment'] ?? '');
            $rating = intval($_POST['mentor_rating'] ?? 5);
            
            // Validate log belongs to an assigned student
            $chk_stmt = $conn->prepare("
                SELECT dl.id, dl.user_id, dl.application_id, u.full_name, dl.log_date 
                FROM daily_logs dl 
                JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
                JOIN users u ON dl.user_id = u.id 
                WHERE dl.id = ? AND ma.mentor_id = ? AND ma.status = 'active'
                LIMIT 1
            ");
            $chk_stmt->bind_param('ii', $log_id, $mentor_id);
            $chk_stmt->execute();
            $log_row = $chk_stmt->get_result()->fetch_assoc();
            
            if ($log_row) {
                $student_id = $log_row['user_id'];
                $log_date = $log_row['log_date'];
                
                mysqli_begin_transaction($conn);
                try {
                    // 1. Update daily_logs
                    $up_stmt = $conn->prepare("UPDATE daily_logs SET status = ?, mentor_feedback = ?, mentor_rating = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $up_stmt->bind_param('ssiii', $status, $comments, $rating, $mentor_id, $log_id);
                    if (!$up_stmt->execute()) {
                        throw new Exception("Failed to update daily log: " . $up_stmt->error);
                    }
                    
                    // 2. Insert into mentor_feedback
                    $fb_title = "Evaluation for Log (" . $log_date . ")";
                    $mentor_name = $_SESSION['full_name'] ?? 'Mentor';
                    $fb_stmt = $conn->prepare("INSERT INTO mentor_feedback (user_id, log_id, feedback_title, given_by, comments, rating, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $fb_stmt->bind_param('iisssis', $student_id, $log_id, $fb_title, $mentor_name, $comments, $rating, $status);
                    if (!$fb_stmt->execute()) {
                        throw new Exception("Failed to insert mentor feedback: " . $fb_stmt->error);
                    }
                    
                    // 3. Log activity
                    $act_details = "Reviewed log dated " . $log_date . " for student " . $log_row['full_name'] . " as status: " . $status;
                    log_activity($conn, 'Mentor Log Review', $act_details);
                    $act_stmt = $conn->prepare("INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details) VALUES (?, 'review', ?, ?, ?)");
                    $act_stmt->bind_param('iiis', $mentor_id, $student_id, $log_id, $act_details);
                    if (!$act_stmt->execute()) {
                        throw new Exception("Failed to insert mentor activity log: " . $act_stmt->error);
                    }
                    
                    // 4. Notify student
                    $notif_msg = "Your log for " . date('M d, Y', strtotime($log_date)) . " has been reviewed by your mentor and marked: " . $status;
                    $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message) VALUES (?, 'Daily Log Reviewed', 'mentor', ?)");
                    $notif_stmt->bind_param('is', $student_id, $notif_msg);
                    if (!$notif_stmt->execute()) {
                        throw new Exception("Failed to notify student: " . $notif_stmt->error);
                    }
                    
                    mysqli_commit($conn);
                    $success_msg = "Daily log review submitted successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Review submission failed: " . $e->getMessage();
                }
            } else {
                $error_msg = "You are not authorized to review this log entry.";
            }
        }
        
        elseif ($action === 'quick_approve') {
            $log_id = intval($_POST['log_id']);
            
            // Validate log belongs to an assigned student
            $chk_stmt = $conn->prepare("
                SELECT dl.id, dl.user_id, dl.application_id, u.full_name, dl.log_date 
                FROM daily_logs dl 
                JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
                JOIN users u ON dl.user_id = u.id 
                WHERE dl.id = ? AND ma.mentor_id = ? AND ma.status = 'active'
                LIMIT 1
            ");
            $chk_stmt->bind_param('ii', $log_id, $mentor_id);
            $chk_stmt->execute();
            $log_row = $chk_stmt->get_result()->fetch_assoc();
            
            if ($log_row) {
                $student_id = $log_row['user_id'];
                $log_date = $log_row['log_date'];
                
                mysqli_begin_transaction($conn);
                try {
                    $status = LOG_STATUS_APPROVED;
                    $comments = 'Approved (Quick Approval)';
                    $rating = 5;
                    
                    // 1. Update daily_logs
                    $up_stmt = $conn->prepare("UPDATE daily_logs SET status = ?, mentor_feedback = ?, mentor_rating = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $up_stmt->bind_param('ssiii', $status, $comments, $rating, $mentor_id, $log_id);
                    if (!$up_stmt->execute()) {
                        throw new Exception("Failed to update daily log: " . $up_stmt->error);
                    }
                    
                    // 2. Insert into mentor_feedback
                    $fb_title = "Quick Approval (" . $log_date . ")";
                    $mentor_name = $_SESSION['full_name'] ?? 'Mentor';
                    $fb_stmt = $conn->prepare("INSERT INTO mentor_feedback (user_id, log_id, feedback_title, given_by, comments, rating, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $fb_stmt->bind_param('iisssis', $student_id, $log_id, $fb_title, $mentor_name, $comments, $rating, $status);
                    if (!$fb_stmt->execute()) {
                        throw new Exception("Failed to insert mentor feedback: " . $fb_stmt->error);
                    }
                    
                    // 3. Log activity
                    $act_details = "Quick-approved log dated " . $log_date . " for student " . $log_row['full_name'];
                    log_activity($conn, 'Mentor Quick Approve', $act_details);
                    $act_stmt = $conn->prepare("INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details) VALUES (?, 'review', ?, ?, ?)");
                    $act_stmt->bind_param('iiis', $mentor_id, $student_id, $log_id, $act_details);
                    if (!$act_stmt->execute()) {
                        throw new Exception("Failed to insert mentor activity log: " . $act_stmt->error);
                    }
                    
                    // 4. Notify student
                    $notif_msg = "Your log for " . date('M d, Y', strtotime($log_date)) . " has been quick-approved by your mentor.";
                    $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message) VALUES (?, 'Daily Log Approved', 'mentor', ?)");
                    $notif_stmt->bind_param('is', $student_id, $notif_msg);
                    if (!$notif_stmt->execute()) {
                        throw new Exception("Failed to notify student: " . $notif_stmt->error);
                    }
                    
                    mysqli_commit($conn);
                    $success_msg = "Log quick-approved successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Quick approval failed: " . $e->getMessage();
                }
            } else {
                $error_msg = "You are not authorized to approve this log entry.";
            }
        }
        
        elseif ($action === 'send_reminder') {
            $student_id = intval($_POST['student_id']);
            
            // Validate student belongs to mentor
            $chk_stmt = $conn->prepare("SELECT id FROM mentor_assignments WHERE mentor_id = ? AND student_id = ? AND status = 'active' LIMIT 1");
            $chk_stmt->bind_param('ii', $mentor_id, $student_id);
            $chk_stmt->execute();
            if ($chk_stmt->get_result()->fetch_assoc()) {
                $notif_msg = "Your mentor has sent you a reminder to log your daily activity.";
                $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message) VALUES (?, 'Log submission reminder', 'mentor', ?)");
                $notif_stmt->bind_param('is', $student_id, $notif_msg);
                if ($notif_stmt->execute()) {
                    $act_details = "Sent log submission reminder to student #" . $student_id;
                    $act_stmt = $conn->prepare("INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details) VALUES (?, 'reminder', ?, NULL, ?)");
                    $act_stmt->bind_param('iis', $mentor_id, $student_id, $act_details);
                    $act_stmt->execute();
                    
                    $success_msg = "Reminder sent successfully to student.";
                } else {
                    $error_msg = "Failed to send notification reminder.";
                }
            } else {
                $error_msg = "You are not authorized to send reminders to this student.";
            }
        }
    }
}

// Setup WHERE condition from filters
$where = ["ma.mentor_id = $mentor_id", "ma.status = 'active'"];

$filter_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if ($filter_student_id > 0) {
    $where[] = "dl.user_id = $filter_student_id";
}

$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
if ($filter_status !== '') {
    $where[] = "dl.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

$filter_date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
if ($filter_date_start !== '') {
    $where[] = "dl.log_date >= '" . mysqli_real_escape_string($conn, $filter_date_start) . "'";
}

$filter_date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';
if ($filter_date_end !== '') {
    $where[] = "dl.log_date <= '" . mysqli_real_escape_string($conn, $filter_date_end) . "'";
}

$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($filter_search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $filter_search);
    $where[] = "(dl.tasks_completed LIKE '%$safe_search%' OR dl.issues_faced LIKE '%$safe_search%' OR u.full_name LIKE '%$safe_search%')";
}

$where_sql = implode(' AND ', $where);

// Pagination config
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total matching logs
$count_query = "
    SELECT COUNT(DISTINCT dl.id) as total
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    JOIN internship_applications app ON ma.application_id = app.id
    WHERE $where_sql
";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'] ?? 0;

// Fetch logs
$logs_query = "
    SELECT 
        dl.*, 
        u.full_name as student_name, 
        u.email as student_email,
        app.internship_name,
        app.id as app_id
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    JOIN internship_applications app ON ma.application_id = app.id
    WHERE $where_sql
    ORDER BY dl.log_date DESC, dl.created_at DESC
    LIMIT $limit OFFSET $offset
";
$logs_result = mysqli_query($conn, $logs_query);

// Fetch assigned students list for dropdowns
$dropdown_students_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.full_name
    FROM mentor_assignments ma
    JOIN users u ON ma.student_id = u.id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    ORDER BY u.full_name ASC
");
$dropdown_students_stmt->bind_param('i', $mentor_id);
$dropdown_students_stmt->execute();
$dropdown_students = $dropdown_students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count pending reviews
$pending_count_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM daily_logs dl 
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    WHERE ma.mentor_id = ? AND ma.status = 'active' AND dl.status = 'Submitted'
");
$pending_count_stmt->bind_param('i', $mentor_id);
$pending_count_stmt->execute();
$total_pending = $pending_count_stmt->get_result()->fetch_assoc()['count'] ?? 0;
?>
<?php
$action_html = '
<div class="flex items-center gap-3">
    <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm text-center flex items-center gap-2">
        <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Pending</p>
        <p class="text-base font-black text-blue-600">' . str_pad((string)$total_pending, 2, '0', STR_PAD_LEFT) . '</p>
    </div>
    <a href="export_logs.php" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
        <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
    </a>
</div>';

page_shell_start('review_logs', 'Review Daily Logs', 'Full activity overview for your assigned interns.', $action_html);
?>
<style>
    .status-pill { padding: 4px 12px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; border: 1px solid; }
    .status-submitted { background-color: #fffbeb; color: #d97706; border-color: #fef3c7; }
    .status-approved { background-color: #f0fdf4; color: #16a34a; border-color: #dcfce7; }
    .status-reviewed { background-color: #eff6ff; color: #2563eb; border-color: #dbeafe; }
    .status-needs-update { background-color: #fef2f2; color: #dc2626; border-color: #fee2e2; }
    .detail-section { border-left: 3px solid #e5e7eb; padding-left: 1rem; margin-bottom: 1.5rem; }
    .detail-label { font-size: 10px; font-weight: 850; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem; }
</style>
<div class="max-w-5xl mx-auto space-y-6">
    
    <!-- Toast status alerts -->
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

                <!-- Filters panel -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                    <form method="GET" action="mentor_daily_logs.php" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <label class="block font-bold text-[10px] text-gray-400 uppercase tracking-wider mb-2">Intern</label>
                            <select name="student_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:border-blue-600 focus:ring-blue-600/10">
                                <option value="">All Interns</option>
                                <?php foreach ($dropdown_students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $filter_student_id === (int)$student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-[10px] text-gray-400 uppercase tracking-wider mb-2">Status</label>
                            <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:border-blue-600 focus:ring-blue-600/10">
                                <option value="">All Statuses</option>
                                <option value="Submitted" <?php echo $filter_status === 'Submitted' ? 'selected' : ''; ?>>Awaiting Review</option>
                                <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Reviewed" <?php echo $filter_status === 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="Needs Update" <?php echo $filter_status === 'Needs Update' ? 'selected' : ''; ?>>Needs Revision</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-[10px] text-gray-400 uppercase tracking-wider mb-2">From Date</label>
                            <input type="date" name="date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:border-blue-600 focus:ring-blue-600/10">
                        </div>

                        <div>
                            <label class="block font-bold text-[10px] text-gray-400 uppercase tracking-wider mb-2">To Date</label>
                            <input type="date" name="date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:border-blue-600 focus:ring-blue-600/10">
                        </div>

                        <div>
                            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-sm">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                        <form method="GET" action="mentor_daily_logs.php" class="relative w-full max-w-sm">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Search keyword in logs..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-600 focus:bg-white transition-colors">
                        </form>
                        <a href="mentor_daily_logs.php" class="text-xs text-blue-600 font-bold hover:underline">Clear Filters</a>
                    </div>
                </div>

                <!-- Submissions List -->
                <div class="space-y-6">
                    <?php if ($logs_result && mysqli_num_rows($logs_result) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($logs_result)): 
                            $initials = strtoupper(substr($log['student_name'], 0, 2));
                            $pill_class = 'status-submitted';
                            $pill_label = 'Awaiting Review';
                            if ($log['status'] === 'Approved') { $pill_class = 'status-approved'; $pill_label = 'Approved'; }
                            if ($log['status'] === 'Reviewed') { $pill_class = 'status-reviewed'; $pill_label = 'Reviewed'; }
                            if ($log['status'] === 'Needs Update') { $pill_class = 'status-needs-update'; $pill_label = 'Needs Update'; }
                        ?>
                            <!-- Submission Card -->
                            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                <!-- Card Header -->
                                <div class="bg-gray-50/50 px-8 py-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-11 h-11 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-base shadow-sm">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <h2 class="text-base font-bold text-gray-900"><?php echo htmlspecialchars($log['student_name']); ?></h2>
                                            <div class="flex items-center gap-3 mt-1">
                                                <p class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm text-slate-400">hub</span>
                                                    <?php echo htmlspecialchars($log['internship_name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-300">•</p>
                                                <p class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm text-slate-400">calendar_today</span>
                                                    Log Date: <?php echo date('M d, Y', strtotime($log['log_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="status-pill <?php echo $pill_class; ?>"><?php echo $pill_label; ?></span>
                                </div>

                                <!-- Card Body -->
                                <div class="p-8">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                                        <!-- Left Column: Core Activity -->
                                        <div class="md:col-span-8 space-y-5">
                                            <div class="detail-section border-blue-200">
                                                <p class="detail-label">Tasks Completed</p>
                                                <p class="text-xs text-gray-700 leading-relaxed font-semibold break-words whitespace-pre-wrap">
                                                    <?php echo nl2br(htmlspecialchars($log['tasks_completed'])); ?>
                                                </p>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-6">
                                                <?php if (!empty($log['issues_faced'])): ?>
                                                    <div class="detail-section border-red-200">
                                                        <p class="detail-label">Blockers / Issues Faced</p>
                                                        <p class="text-xs text-red-800 font-medium italic break-words whitespace-pre-wrap">
                                                            <?php echo nl2br(htmlspecialchars($log['issues_faced'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($log['next_plan'])): ?>
                                                    <div class="detail-section border-green-200">
                                                        <p class="detail-label">Next Action Plan</p>
                                                        <p class="text-xs text-slate-700 font-semibold break-words whitespace-pre-wrap">
                                                            <?php echo nl2br(htmlspecialchars($log['next_plan'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($log['mentor_feedback'])): ?>
                                                <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 mt-4 text-xs flex gap-2">
                                                    <span class="material-symbols-outlined text-blue-600 text-sm mt-0.5">comment</span>
                                                    <div>
                                                        <span class="font-bold text-blue-800">Your Feedback Comment:</span>
                                                        <p class="text-slate-600 mt-1 italic break-words whitespace-pre-wrap">"<?php echo htmlspecialchars($log['mentor_feedback']); ?>"</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Right Column: Metadata & Stats -->
                                        <div class="md:col-span-4 bg-gray-50/50 p-6 rounded-xl border border-gray-150 flex flex-col justify-center gap-4">
                                            <div>
                                                <p class="detail-label">Time Logged</p>
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-blue-600 text-lg">timer</span>
                                                    <p class="text-lg font-bold text-gray-900"><?php echo number_format($log['time_spent'], 1); ?> <span class="text-xs font-medium text-gray-500">Hours</span></p>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="detail-label">Focus Indicator</p>
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2.5 h-2.5 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                                    <p class="text-xs font-bold text-gray-700"><?php echo htmlspecialchars($log['focus_level']); ?></p>
                                                </div>
                                            </div>
                                            <?php if (!empty($log['attachment_path'])): 
                                                $ext = strtolower(pathinfo($log['attachment_path'], PATHINFO_EXTENSION));
                                                $icon = 'insert_drive_file';
                                                if ($ext === 'pdf') {
                                                    $icon = 'picture_as_pdf';
                                                } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg'])) {
                                                    $icon = 'image';
                                                } elseif (in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z'])) {
                                                    $icon = 'folder_zip';
                                                }
                                            ?>
                                                <div class="pt-2 border-t border-gray-200">
                                                    <p class="detail-label">Log Attachment</p>
                                                    <div class="flex items-center gap-3 mt-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                                        <span class="material-symbols-outlined text-slate-500 text-2xl"><?php echo $icon; ?></span>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-xs font-semibold text-slate-700 truncate"><?php echo htmlspecialchars(basename($log['attachment_path'])); ?></p>
                                                            <p class="text-[10px] text-slate-400 uppercase tracking-wider"><?php echo strtoupper($ext); ?> File</p>
                                                        </div>
                                                        <div class="flex items-center gap-1.5">
                                                            <?php if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'svg'])): ?>
                                                                <a href="<?php echo htmlspecialchars($log['attachment_path']); ?>" target="_blank" class="text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-1.5 rounded-lg transition-colors flex items-center justify-center" title="Preview File">
                                                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="<?php echo htmlspecialchars($log['attachment_path']); ?>" download class="text-slate-600 hover:text-slate-750 bg-slate-100 hover:bg-slate-200 p-1.5 rounded-lg transition-colors flex items-center justify-center" title="Download File">
                                                                <span class="material-symbols-outlined text-sm">download</span>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Card Footer: Actions -->
                                    <div class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap gap-3 items-center">
                                        <button onclick="openReviewModal(<?php echo $log['id']; ?>, '<?php echo htmlspecialchars(addslashes($log['student_name'])); ?>', '<?php echo htmlspecialchars(addslashes($log['mentor_feedback'] ?? '')); ?>', '<?php echo $log['status']; ?>', <?php echo $log['mentor_rating'] ?: '5'; ?>)" class="bg-blue-600 text-white px-5 py-2 rounded-xl text-xs font-bold hover:bg-blue-700 transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                                            <span class="material-symbols-outlined text-sm">rate_review</span>
                                            <span>Add Feedback / Grade</span>
                                        </button>
                                        
                                        <?php if ($log['status'] === 'Submitted'): ?>
                                            <form action="mentor_daily_logs.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to quick-approve this log submission? Status will be set to Approved and rating will be 5 stars.');">
                                                <?php echo csrf_token_field(); ?>
                                                <input type="hidden" name="action" value="quick_approve">
                                                <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                                <button type="submit" class="bg-white border border-blue-600 text-blue-600 px-5 py-2 rounded-xl text-xs font-bold hover:bg-blue-50 transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                                    <span>Quick Approve</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="mentor_daily_logs.php" method="POST" class="inline" onsubmit="return confirm('Send a log submission reminder notification to this student?');">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="action" value="send_reminder">
                                            <input type="hidden" name="student_id" value="<?php echo $log['user_id']; ?>">
                                            <button type="submit" class="bg-white border border-gray-200 text-gray-600 px-5 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all flex items-center gap-1.5 cursor-pointer">
                                                <span class="material-symbols-outlined text-sm">mail</span>
                                                <span>Send Reminder</span>
                                            </button>
                                        </form>

                                        <button onclick="viewHistory(<?php echo $log['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($log['student_name'])); ?>')" class="ml-auto text-slate-400 hover:text-blue-600 text-xs font-bold uppercase tracking-wider flex items-center gap-1 cursor-pointer">
                                            <span>View Full Log History</span>
                                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="bg-white p-12 rounded-2xl border border-gray-200 shadow-sm text-center">
                            <span class="material-symbols-outlined text-5xl text-gray-300 mb-2">assignment_late</span>
                            <h3 class="text-lg font-bold text-gray-800">No logs found</h3>
                            <p class="text-sm text-gray-500 mt-1">Try adjusting the filter options or keywords above.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Pagination Controls -->
                    <?php
                    $params = $_GET;
                    echo module_pagination($total_rows, $limit, $page, 'mentor_daily_logs.php', $params);
                    ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Review Log Modal -->
    <div id="review-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-slate-800">Evaluate Log Submission</h3>
                <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form action="mentor_daily_logs.php" method="POST" class="p-6 space-y-4" onsubmit="return confirm('Submit this evaluation and notify the intern?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" id="modal-log-id" name="log_id">

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Intern</label>
                    <input type="text" id="modal-student-name" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs text-slate-700 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Update Status</label>
                    <select id="modal-status" name="status" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:border-blue-600 focus:ring-blue-600/10">
                        <option value="Reviewed">Reviewed</option>
                        <option value="Approved">Approved</option>
                        <option value="Needs Update">Needs Revision / Update</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Score/Rating (1 to 5 Stars)</label>
                    <select id="modal-rating" name="mentor_rating" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:border-blue-600 focus:ring-blue-600/10">
                        <option value="5">⭐⭐⭐⭐⭐ Excellent (5)</option>
                        <option value="4">⭐⭐⭐⭐ Good (4)</option>
                        <option value="3">⭐⭐⭐ Satisfactory (3)</option>
                        <option value="2">⭐⭐ Needs Improvement (2)</option>
                        <option value="1">⭐ Poor (1)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Feedback Comment</label>
                    <textarea id="modal-feedback" name="mentor_comment" required rows="4" class="w-full border border-slate-200 rounded-xl p-4 text-xs focus:ring-4 focus:ring-blue-100 focus:border-blue-600 outline-none placeholder:text-gray-400 transition-all" placeholder="Provide constructive feedback instructions..."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeReviewModal()" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-md">Submit Evaluation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- History Timeline Modal -->
    <div id="history-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150 flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-slate-800" id="history-modal-title">Student Log History</h3>
                <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <!-- Timeline Content -->
            <div class="p-6 overflow-y-auto space-y-4 flex-grow" id="history-timeline-content">
                <div class="text-center py-6 text-slate-400">Loading timeline history...</div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 text-right">
                <button type="button" onclick="closeHistoryModal()" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-700 transition-all">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openReviewModal(logId, studentName, feedback, status, rating) {
            document.getElementById('modal-log-id').value = logId;
            document.getElementById('modal-student-name').value = studentName;
            document.getElementById('modal-feedback').value = feedback;
            document.getElementById('modal-status').value = status;
            document.getElementById('modal-rating').value = rating;
            document.getElementById('review-modal').classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('review-modal').classList.add('hidden');
        }

        function closeHistoryModal() {
            document.getElementById('history-modal').classList.add('hidden');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;")
                      .replace(/'/g, "&#039;");
        }

        async function viewHistory(studentId, studentName) {
            document.getElementById('history-modal-title').textContent = `${studentName} - Full Log History`;
            const contentDiv = document.getElementById('history-timeline-content');
            contentDiv.innerHTML = '<div class="text-center py-6 text-slate-400">Loading timeline history...</div>';
            document.getElementById('history-modal').classList.remove('hidden');
            
            try {
                const response = await fetch(`mentor_daily_logs.php?ajax_action=history&student_id=${studentId}`);
                const data = await response.json();
                if (data.success) {
                    if (data.history.length === 0) {
                        contentDiv.innerHTML = '<div class="text-center py-6 text-slate-400">No daily logs submitted by this student.</div>';
                    } else {
                        let html = '<div class="space-y-4 relative border-l-2 border-slate-100 pl-4 ml-2">';
                        data.history.forEach(log => {
                            let pill = 'bg-amber-100 text-amber-700';
                            if (log.status === 'Approved') pill = 'bg-green-100 text-green-700';
                            if (log.status === 'Reviewed') pill = 'bg-blue-100 text-blue-700';
                            if (log.status === 'Needs Update') pill = 'bg-red-100 text-red-700';
                            
                            html += `
                                <div class="relative space-y-1">
                                    <div class="absolute -left-[23px] top-1.5 w-3 h-3 rounded-full bg-blue-600 border-2 border-white"></div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-800">${log.log_date} (${log.time_spent} hrs)</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${pill}">${log.status}</span>
                                    </div>
                                    <p class="text-xs text-slate-600">${escapeHtml(log.tasks_completed).replace(/\n/g, '<br>')}</p>
                                    ${log.mentor_feedback ? `<p class="text-[11px] text-blue-700 italic bg-blue-50/50 p-2 rounded">Comment: "${escapeHtml(log.mentor_feedback)}"</p>` : ''}
                                </div>
                                <hr class="border-slate-100 my-2">
                            `;
                        });
                        html += '</div>';
                        contentDiv.innerHTML = html;
                    }
                } else {
                    contentDiv.innerHTML = `<div class="text-center py-6 text-red-500">Error: ${data.message}</div>`;
                }
            } catch (err) {
                contentDiv.innerHTML = '<div class="text-center py-6 text-red-500">Network error fetching timeline.</div>';
            }
        }
    </script>
<?php page_shell_end(); ?>
