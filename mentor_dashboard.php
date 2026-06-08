<?php
require_once __DIR__ . '/includes/hr_module_helpers.php';
session_start();
include "db.php"; // Ensure database connection before schema checks
require_once __DIR__ . '/ensure_extended_schema.php';
include_once __DIR__ . '/setup_discontinuation_schema.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/includes/auth.php'; // ensure logged in and role check
require_module_access('mentor_dashboard');

// Get mentor ID from session (assuming mentors are users with role 'mentor')
$mentor_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($mentor_id <= 0) {
    die('Unauthorized');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_log') {
        $log_id = intval($_POST['log_id'] ?? 0);
        if ($log_id > 0) {
            $stmt = $conn->prepare("UPDATE daily_logs SET is_reviewed = 1, review_status = 'approved', reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $log_id);
            $stmt->execute();
            $stmt->close();
            // notify admin
            sendEmailNotification('admin', 'Daily Log Approved', "Mentor approved daily log ID $log_id.", ['mentor_id' => $mentor_id]);
        }
    } elseif ($action === 'request_changes') {
        $log_id = intval($_POST['log_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        if ($log_id > 0) {
            $stmt = $conn->prepare("UPDATE daily_logs SET is_reviewed = 0, review_status = 'changes_requested', reviewer_remarks = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $remarks, $log_id);
            $stmt->execute();
            $stmt->close();
            sendEmailNotification('admin', 'Daily Log Change Requested', "Mentor requested changes for log ID $log_id. Remarks: $remarks", ['mentor_id' => $mentor_id]);
        }
    } elseif ($action === 'add_feedback') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');
        $phase = trim($_POST['phase'] ?? '');
        if ($student_id && $internship_id && $rating > 0) {
            $mentor_name = $_SESSION['full_name'] ?? '';
            if (empty($mentor_name)) {
                $m_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $mentor_id");
                if ($m_res && $m_row = mysqli_fetch_assoc($m_res)) {
                    $mentor_name = $m_row['full_name'];
                }
            }
            if (empty($mentor_name)) {
                $mentor_name = 'Mentor';
            }
            $feedback_title = 'Review - ' . $phase;
            $stmt = $conn->prepare("INSERT INTO mentor_feedback (mentor_id, student_id, internship_id, rating, comments, phase, user_id, feedback_title, given_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('iiisssiss', $mentor_id, $student_id, $internship_id, $rating, $comments, $phase, $student_id, $feedback_title, $mentor_name);
            $stmt->execute();
            $stmt->close();
            sendEmailNotification('admin', 'New Mentor Feedback', "Mentor submitted feedback for student $student_id.", ['mentor_id' => $mentor_id]);

            // Fetch names for notification
            $details_stmt = $conn->prepare("SELECT u.full_name, i.title FROM users u JOIN internships i WHERE u.id = ? AND i.id = ?");
            $details_stmt->bind_param("ii", $student_id, $internship_id);
            $details_stmt->execute();
            $details_stmt->bind_result($student_name, $internship_title);
            $details_stmt->fetch();
            $details_stmt->close();

            // Notify coordinators
            $coord_res = mysqli_query($conn, "SELECT id FROM users WHERE LOWER(role) = 'coordinator'");
            if ($coord_res) {
                $c_title = 'Mentor Feedback Added';
                $c_msg = "Mentor " . ($_SESSION['full_name'] ?? 'Mentor') . " submitted feedback for student " . ($student_name ?? 'Student') . " on '" . ($internship_title ?? 'Internship') . "' (Rating: $rating/5).";
                $c_type = 'info';
                $c_link = "coordinator_internships.php?view=" . intval($internship_id);
                $coord_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'coordinator', ?, ?, ?, ?)");
                if ($coord_stmt) {
                    while ($c_row = mysqli_fetch_assoc($coord_res)) {
                        $c_id = intval($c_row['id']);
                        $coord_stmt->bind_param("issss", $c_id, $c_title, $c_msg, $c_type, $c_link);
                        $coord_stmt->execute();
                    }
                    $coord_stmt->close();
                }
            }
        }
    } elseif ($action === 'raise_dropout') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        if ($student_id && $internship_id && $reason) {
            $stmt = $conn->prepare("INSERT INTO dropout_requests (application_id, mentor_id, reason, status, created_at) SELECT a.id, ?, ?, 'Pending', NOW() FROM internship_applications a WHERE a.user_id = ? AND a.internship_id = ? LIMIT 1");
            // We need application_id; fetch it
            $app_stmt = $conn->prepare("SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1");
            $app_stmt->bind_param('ii', $student_id, $internship_id);
            $app_stmt->execute();
            $app_res = $app_stmt->get_result();
            $app_row = $app_res->fetch_assoc();
            $app_stmt->close();
            if ($app_row) {
                $application_id = $app_row['id'];
                $stmt = $conn->prepare("INSERT INTO dropout_requests (application_id, mentor_id, reason, remarks, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                $stmt->bind_param('iiss', $application_id, $mentor_id, $reason, $remarks);
                $stmt->execute();
                $stmt->close();
                sendEmailNotification('admin', 'Dropout Request Raised', "Mentor raised dropout request for application $application_id.", ['mentor_id' => $mentor_id]);
            }
        }
    }
    // Refresh after POST
    header('Location: mentor_dashboard.php');
    exit();
}

// Check if the mentor has any assigned teams
$team_check_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM project_teams WHERE mentor_id = $mentor_id");
$team_check_row = mysqli_fetch_assoc($team_check_res);
$assigned_teams_count = intval($team_check_row['cnt'] ?? 0);

// Overview counts
$overview = [];
// Assigned interns count (distinct students in teams assigned to this mentor)
$res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT tm.student_id) as cnt 
    FROM project_teams t
    JOIN project_team_members tm ON tm.project_team_id = t.id
    WHERE t.mentor_id = $mentor_id
");
$overview['assigned_interns'] = (int) mysqli_fetch_assoc($res)['cnt'];

// Active projects (internships/projects linked to teams assigned to this mentor)
$res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT t.internship_id) as cnt 
    FROM project_teams t 
    WHERE t.mentor_id = $mentor_id
");
$overview['active_projects'] = (int) mysqli_fetch_assoc($res)['cnt'];

// Pending daily logs (logs not reviewed of students assigned to this mentor)
$res = mysqli_query($conn, "
    SELECT COUNT(*) AS cnt 
    FROM daily_logs dl 
    JOIN project_team_members tm ON tm.student_id = dl.user_id 
    JOIN project_teams t ON tm.project_team_id = t.id
    WHERE t.mentor_id = $mentor_id AND dl.is_reviewed = 0
");
$overview['pending_logs'] = (int) mysqli_fetch_assoc($res)['cnt'];

// Completed reviews (feedback entries by this mentor / logs reviewed)
$completed_sql = "
    SELECT COUNT(*) AS cnt 
    FROM daily_logs dl 
    JOIN project_team_members tm ON tm.student_id = dl.user_id 
    JOIN project_teams t ON tm.project_team_id = t.id
    WHERE t.mentor_id = $mentor_id AND dl.is_reviewed = 1
";
$res = mysqli_query($conn, $completed_sql);
if ($res) {
    $overview['completed_reviews'] = (int) mysqli_fetch_assoc($res)['cnt'];
} else {
    error_log('Mentor Dashboard query error (completed_reviews): ' . mysqli_error($conn));
    $overview['completed_reviews'] = 0;
}

// Dropout requests raised
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM dropout_requests dr WHERE dr.mentor_id = $mentor_id");
$overview['dropout_requests'] = (int) mysqli_fetch_assoc($res)['cnt'];

// Assigned interns list
$assigned_sql = "
    SELECT 
        tm.id as assign_id,
        t.id as team_id,
        t.internship_id,
        u.id as user_id,
        u.id as student_id,
        u.full_name,
        u.email,
        i.title as internship_title,
        t.team_name as project_title,
        COALESCE(a.status, 'Active Intern') as current_phase,
        0 AS progress_percent,
        COALESCE(a.status, 'Active Intern') as app_status,
        COALESCE(a.test_status, '') as test_status,
        dl.last_log_date,
        a.id as app_id
    FROM project_teams t
    JOIN project_team_members tm ON tm.project_team_id = t.id
    JOIN users u ON tm.student_id = u.id
    LEFT JOIN internships i ON t.internship_id = i.id
    LEFT JOIN internship_applications a ON a.id = (
        SELECT id FROM internship_applications 
        WHERE user_id = u.id 
        ORDER BY (internship_id = t.internship_id) DESC, id DESC 
        LIMIT 1
    )
    LEFT JOIN (
        SELECT user_id, MAX(log_date) as last_log_date 
        FROM daily_logs 
        GROUP BY user_id
    ) dl ON u.id = dl.user_id
    WHERE t.mentor_id = $mentor_id
";
$assigned_res = mysqli_query($conn, $assigned_sql);

// Pending daily logs details
$logs_sql = "
    SELECT 
        dl.id,
        dl.user_id as student_id,
        u.full_name,
        u.email,
        i.title as internship_title,
        dl.log_date,
        dl.tasks_completed,
        dl.time_spent,
        dl.focus_level,
        dl.issues_faced,
        dl.next_plan
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN project_team_members tm ON u.id = tm.student_id
    JOIN project_teams t ON tm.project_team_id = t.id
    LEFT JOIN internships i ON t.internship_id = i.id
    WHERE t.mentor_id = $mentor_id AND dl.is_reviewed = 0
";
$logs_res = mysqli_query($conn, $logs_sql);

// Mentor notifications (unread)
$notif_sql = "SELECT * FROM notifications WHERE user_id = $mentor_id AND is_read = 0 ORDER BY created_at DESC LIMIT 10";
$notif_res = mysqli_query($conn, $notif_sql);

page_shell_start('mentor_dashboard', 'Mentor Dashboard', 'Review assigned interns, daily logs, feedback, and dropout requests', '');
?>
<?php if (isset($_GET['success_msg'])): ?>
<div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded-lg text-sm font-semibold mb-4">
    <?= htmlspecialchars($_GET['success_msg']) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error_msg'])): ?>
<div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg text-sm font-semibold mb-4">
    <?= htmlspecialchars($_GET['error_msg']) ?>
</div>
<?php endif; ?>



<?php if ($assigned_teams_count === 0): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center text-slate-500 mb-6">
    No teams assigned to you yet.
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-6">
    <!-- Overview cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg text-center">
            <p class="text-sm font-medium text-blue-700">Assigned Interns</p>
            <p class="text-2xl font-bold text-blue-900"><?= $overview['assigned_interns'] ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg text-center">
            <p class="text-sm font-medium text-green-700">Active Projects</p>
            <p class="text-2xl font-bold text-green-900"><?= $overview['active_projects'] ?></p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg text-center">
            <p class="text-sm font-medium text-yellow-700">Pending Daily Logs</p>
            <p class="text-2xl font-bold text-yellow-900"><?= $overview['pending_logs'] ?></p>
        </div>
        <div class="bg-indigo-50 p-4 rounded-lg text-center">
            <p class="text-sm font-medium text-indigo-700">Completed Reviews</p>
            <p class="text-2xl font-bold text-indigo-900"><?= $overview['completed_reviews'] ?></p>
        </div>
        <div class="bg-red-50 p-4 rounded-lg text-center">
            <p class="text-sm font-medium text-red-700">Dropout Requests</p>
            <p class="text-2xl font-bold text-red-900"><?= $overview['dropout_requests'] ?></p>
        </div>
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Mentor Actions</h2>
        <p class="text-sm text-slate-500">Send a quick manual message to your assigned students or platform staff.</p>
    </div>
    <a href="manual_message.php" class="inline-flex items-center justify-center rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Send Message</a>
</div>

<!-- Assigned Interns Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Assigned Interns</h2>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <th class="py-2 px-4">Student</th>
                <th class="py-2 px-4">Email</th>
                <th class="py-2 px-4">Internship</th>
                <th class="py-2 px-4">Project</th>
                <th class="py-2 px-4">Current Phase</th>
                <th class="py-2 px-4">Progress %</th>
                <th class="py-2 px-4">Status</th>
                <th class="py-2 px-4">Last Log</th>
                <th class="py-2 px-4">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
            <?php while ($row = mysqli_fetch_assoc($assigned_res)): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-4"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['email']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['internship_title'] ?? '-') ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['project_title'] ?? '-') ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['current_phase']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['progress_percent'] ?? '0') ?>%</td>
                    <td class="py-2 px-4"><?= htmlspecialchars($row['app_status']) ?></td>
                    <td class="py-2 px-4"><?= $row['last_log_date'] ? date('M d, Y', strtotime($row['last_log_date'])) : '—' ?></td>
                    <td class="py-2 px-4">
                        <a href="mentor_view_project.php?team_id=<?= intval($row['team_id'] ?? 0) ?>" class="px-3 py-1 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700 transition inline-block">View Project</a>
                        <button type="button" onclick="openReportModal(<?= $row['user_id'] ?>, <?= $row['app_id'] ?>, '<?= htmlspecialchars(addslashes($row['full_name'])) ?>')" 
                                class="px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition ml-2">
                            Report
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Daily Log Review Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Pending Daily Logs</h2>
    <?php if (mysqli_num_rows($logs_res) === 0): ?>
        <p class="text-slate-500 text-sm">No daily logs submitted yet.</p>
    <?php else: ?>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <th class="py-2 px-4">Student</th>
                <th class="py-2 px-4">Internship</th>
                <th class="py-2 px-4">Date</th>
                <th class="py-2 px-4">Tasks</th>
                <th class="py-2 px-4">Hours</th>
                <th class="py-2 px-4">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
            <?php while ($log = mysqli_fetch_assoc($logs_res)): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-4"><?= htmlspecialchars($log['full_name']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($log['internship_title'] ?? '-') ?></td>
                    <td class="py-2 px-4"><?= date('M d, Y', strtotime($log['log_date'])) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($log['tasks_completed']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($log['time_spent']) ?></td>
                    <td class="py-2 px-4 space-x-1">
                        <!-- View Log Modal Trigger -->
                        <button type="button" class="px-2 py-1 bg-slate-200 text-slate-800 rounded view-log-btn" 
                                data-log-id="<?= $log['id'] ?>"
                                data-student="<?= htmlspecialchars($log['full_name']) ?>"
                                data-date="<?= date('M d, Y', strtotime($log['log_date'])) ?>"
                                data-tasks="<?= htmlspecialchars($log['tasks_completed']) ?>"
                                data-hours="<?= htmlspecialchars($log['time_spent']) ?>"
                                data-focus="<?= htmlspecialchars($log['focus_level'] ?? 'Medium') ?>"
                                data-issues="<?= htmlspecialchars($log['issues_faced'] ?? 'None') ?>"
                                data-next="<?= htmlspecialchars($log['next_plan'] ?? 'None') ?>"
                                onclick="viewLog(this)">View</button>
                        <!-- Approve -->
                        <form method="post" class="inline" style="display:inline;">
                            <input type="hidden" name="action" value="approve_log">
                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                            <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded" onclick="return confirm('Approve this log?')">Approve</button>
                        </form>
                        <!-- Request Changes -->
                        <button type="button" class="px-2 py-1 bg-yellow-600 text-white rounded" onclick="openChangeModal(<?= $log['id'] ?>)">Request Changes</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Feedback Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Submit Feedback</h2>
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="action" value="add_feedback">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Student</label>
            <select name="student_id" required class="w-full border border-slate-200 rounded p-2">
                <option value="">Select Student</option>
                <?php
                $stu_res = mysqli_query($conn, "SELECT DISTINCT tm.student_id, u.full_name FROM project_teams t JOIN project_team_members tm ON t.id = tm.project_team_id JOIN users u ON tm.student_id = u.id WHERE t.mentor_id = $mentor_id");
                while ($s = mysqli_fetch_assoc($stu_res)) {
                    echo "<option value='{$s['student_id']}'>" . htmlspecialchars($s['full_name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Internship</label>
            <select name="internship_id" required class="w-full border border-slate-200 rounded p-2">
                <option value="">Select Internship</option>
                <?php
                $int_res = mysqli_query($conn, "SELECT DISTINCT i.id, i.title FROM project_teams t JOIN internships i ON t.internship_id = i.id WHERE t.mentor_id = $mentor_id");
                while ($i = mysqli_fetch_assoc($int_res)) {
                    echo "<option value='{$i['id']}'>" . htmlspecialchars($i['title']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Rating (1‑5)</label>
            <input type="number" name="rating" min="1" max="5" required class="w-full border border-slate-200 rounded p-2" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Phase</label>
            <input type="text" name="phase" placeholder="e.g., HR Round" required class="w-full border border-slate-200 rounded p-2" />
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Comments</label>
            <textarea name="comments" rows="4" class="w-full border border-slate-200 rounded p-2"></textarea>
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Submit Feedback</button>
        </div>
    </form>
</div>

<!-- Dropout Request Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Raise Dropout Request</h2>
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="action" value="raise_dropout">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Student</label>
            <select name="student_id" required class="w-full border border-slate-200 rounded p-2">
                <option value="">Select Student</option>
                <?php
                $stu_res2 = mysqli_query($conn, "SELECT DISTINCT tm.student_id, u.full_name FROM project_teams t JOIN project_team_members tm ON t.id = tm.project_team_id JOIN users u ON tm.student_id = u.id WHERE t.mentor_id = $mentor_id");
                while ($s = mysqli_fetch_assoc($stu_res2)) {
                    echo "<option value='{$s['student_id']}'>" . htmlspecialchars($s['full_name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Internship</label>
            <select name="internship_id" required class="w-full border border-slate-200 rounded p-2">
                <option value="">Select Internship</option>
                <?php
                $int_res2 = mysqli_query($conn, "SELECT DISTINCT i.id, i.title FROM project_teams t JOIN internships i ON t.internship_id = i.id WHERE t.mentor_id = $mentor_id");
                while ($i = mysqli_fetch_assoc($int_res2)) {
                    echo "<option value='{$i['id']}'>" . htmlspecialchars($i['title']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
            <textarea name="reason" rows="3" required class="w-full border border-slate-200 rounded p-2"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks (optional)</label>
            <textarea name="remarks" rows="3" class="w-full border border-slate-200 rounded p-2"></textarea>
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Submit Request</button>
        </div>
    </form>
</div>

<!-- Mentor Notifications Summary -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
            Notifications
            <?php
            $unread_count_dashboard = 0;
            $cnt_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $mentor_id AND is_read = 0");
            if ($cnt_res) {
                $unread_count_dashboard = intval(mysqli_fetch_assoc($cnt_res)['cnt']);
            }
            if ($unread_count_dashboard > 0): ?>
                <span class="bg-red-500 text-white text-xs font-extrabold px-2 py-0.5 rounded-full"><?= $unread_count_dashboard ?> New</span>
            <?php endif; ?>
        </h2>
        <a href="mentor_notifications.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition shadow-sm">
            Go to Notifications
        </a>
    </div>
    <?php if (mysqli_num_rows($notif_res) > 0): ?>
        <ul class="space-y-2">
            <?php 
            $shown = 0;
            while ($n = mysqli_fetch_assoc($notif_res)): 
                if ($shown++ >= 3) break;
            ?>
                <li class="p-3 border border-slate-200 rounded bg-slate-50 <?= !$n['is_read'] ? 'border-blue-200 bg-blue-50/20' : '' ?>">
                    <p class="text-sm text-slate-800"><?= htmlspecialchars($n['message']) ?></p>
                    <p class="text-xs text-slate-500 mt-1"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></p>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500 text-sm">No new notifications.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal Scripts (simple JS placeholders) -->
<script>
function viewLog(btn) {
    const student = btn.dataset.student;
    const date = btn.dataset.date;
    const tasks = btn.dataset.tasks;
    const hours = btn.dataset.hours;
    const focus = btn.dataset.focus;
    const issues = btn.dataset.issues;
    const next = btn.dataset.next;
    
    document.getElementById('modalLogStudent').textContent = student;
    document.getElementById('modalLogDate').textContent = date;
    document.getElementById('modalLogTasks').textContent = tasks;
    document.getElementById('modalLogHours').textContent = hours + ' hours';
    document.getElementById('modalLogFocus').textContent = focus;
    document.getElementById('modalLogIssues').textContent = issues || 'None';
    document.getElementById('modalLogNext').textContent = next || 'None';
    
    document.getElementById('logDetailsModal').style.display = 'flex';
}
function closeLogModal() {
    document.getElementById('logDetailsModal').style.display = 'none';
}
function openChangeModal(id) {
    const reason = prompt('Enter change request remarks:');
    if (reason !== null) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `<input type='hidden' name='action' value='request_changes'>
                          <input type='hidden' name='log_id' value='${id}'>
                          <input type='hidden' name='remarks' value='${reason}'>`;
        document.body.appendChild(form);
        form.submit();
    }
}

// Report Student Modal Functions
function openReportModal(studentId, appId, studentName) {
    document.getElementById('reportStudentId').value = studentId;
    document.getElementById('reportAppId').value = appId;
    document.getElementById('reportStudentNameDisplay').textContent = studentName;
    document.getElementById('reportModal').style.display = 'flex';
}

// Report Student Modal Functions
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}

function submitReport() {
    const form = document.getElementById('reportForm');
    const formData = new FormData(form);

    fetch('mentor_report_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeReportModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the report.');
    });
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeReportModal();
            }
        });
    }
    const logModal = document.getElementById('logDetailsModal');
    if (logModal) {
        logModal.addEventListener('click', function(e) {
            if (e.target === logModal) {
                closeLogModal();
            }
        });
    }
});
</script>

<!-- Report Student Modal -->
<div id="reportModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="bg-red-600 text-white p-6 flex items-center justify-between rounded-t-lg">
            <h2 class="text-xl font-bold">Report Student</h2>
            <button type="button" onclick="closeReportModal()" class="text-white hover:text-red-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="reportForm" class="p-6 space-y-4">
            <input type="hidden" name="student_id" id="reportStudentId">
            <input type="hidden" name="application_id" id="reportAppId">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Student Name</label>
                <p class="text-sm font-bold text-slate-900" id="reportStudentNameDisplay"></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Report Reason *</label>
                <select name="reason" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none">
                    <option value="">-- Select Reason --</option>
                    <option value="No Daily Log Submission">No Daily Log Submission</option>
                    <option value="Poor Performance">Poor Performance</option>
                    <option value="Inactive for Long Period">Inactive for Long Period</option>
                    <option value="Not Attending Meetings">Not Attending Meetings</option>
                    <option value="Requested Withdrawal">Requested Withdrawal</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Detailed Remarks</label>
                <textarea name="remarks" rows="4" placeholder="Enter detailed remarks about this student..." required
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="submitReport()" class="flex-1 px-4 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition">
                    Submit Report
                </button>
                <button type="button" onclick="closeReportModal()" class="flex-1 px-4 py-2 bg-slate-200 text-slate-800 font-semibold rounded-lg hover:bg-slate-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Daily Log Details Modal -->
<div id="logDetailsModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden border border-slate-100 animate-fade-in">
        <div class="bg-slate-900 text-white p-5 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold">Daily Activity Details</h2>
                <p class="text-xs text-slate-400 mt-1" id="modalLogDate"></p>
            </div>
            <button type="button" onclick="closeLogModal()" class="text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Student</label>
                <p class="text-sm font-bold text-slate-800" id="modalLogStudent"></p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Hours Spent</label>
                    <p class="text-sm font-bold text-slate-800" id="modalLogHours"></p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Focus Level</label>
                    <p class="text-sm font-bold text-slate-800" id="modalLogFocus"></p>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Tasks Completed</label>
                <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-3 text-sm text-slate-700 leading-relaxed whitespace-pre-wrap mt-1" id="modalLogTasks"></div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Issues Faced</label>
                <div class="bg-red-50/50 border border-red-100 rounded-xl p-3 text-sm text-red-800 leading-relaxed whitespace-pre-wrap mt-1" id="modalLogIssues"></div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Next Plan</label>
                <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-3 text-sm text-slate-700 leading-relaxed whitespace-pre-wrap mt-1" id="modalLogNext"></div>
            </div>
        </div>
        
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button type="button" onclick="closeLogModal()" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-sm font-semibold transition-all">
                Close
            </button>
        </div>
    </div>
</div>

<?php
page_shell_end();
?>