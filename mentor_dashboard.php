<?php
session_start();
include_once __DIR__ . '/includes/auth.php'; // ensure logged in and role check
require_module_access('mentor_dashboard');
include "db.php";
include_once __DIR__ . '/ensure_extended_schema.php';

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
            $stmt = $conn->prepare("UPDATE daily_logs SET is_reviewed = 1, review_status = 'approved', reviewed_at = NOW() WHERE id = ? AND mentor_id = ?");
            $stmt->bind_param('ii', $log_id, $mentor_id);
            $stmt->execute();
            $stmt->close();
            // notify admin
            sendEmailNotification('admin', 'Daily Log Approved', "Mentor approved daily log ID $log_id.", ['mentor_id' => $mentor_id]);
        }
    } elseif ($action === 'request_changes') {
        $log_id = intval($_POST['log_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        if ($log_id > 0) {
            $stmt = $conn->prepare("UPDATE daily_logs SET is_reviewed = 0, review_status = 'changes_requested', reviewer_remarks = ?, reviewed_at = NOW() WHERE id = ? AND mentor_id = ?");
            $stmt->bind_param('sii', $remarks, $log_id, $mentor_id);
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
            $stmt = $conn->prepare("INSERT INTO mentor_feedback (mentor_id, student_id, internship_id, rating, comments, phase, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('iiisss', $mentor_id, $student_id, $internship_id, $rating, $comments, $phase);
            $stmt->execute();
            $stmt->close();
            sendEmailNotification('admin', 'New Mentor Feedback', "Mentor submitted feedback for student $student_id.", ['mentor_id' => $mentor_id]);
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

// Overview counts
$overview = [];
// Assigned interns count
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM mentor_assignments WHERE mentor_id = $mentor_id");
$overview['assigned_interns'] = (int) mysqli_fetch_assoc($res)['cnt'];
// Active projects (internships linked to assigned interns that are in Active Intern status)
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT i.id) as cnt FROM mentor_assignments ma JOIN internship_applications a ON ma.application_id = a.id JOIN internships i ON a.internship_id = i.id WHERE ma.mentor_id = $mentor_id AND a.status = 'Active Intern'");
$overview['active_projects'] = (int) mysqli_fetch_assoc($res)['cnt'];
// Pending daily logs (logs not reviewed)
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM daily_logs dl JOIN mentor_assignments ma ON dl.student_id = ma.student_id WHERE ma.mentor_id = $mentor_id AND dl.is_reviewed = 0");
$overview['pending_logs'] = (int) mysqli_fetch_assoc($res)['cnt'];
// Completed reviews (feedback entries by this mentor)
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM mentor_feedback WHERE mentor_id = $mentor_id");
$overview['completed_reviews'] = (int) mysqli_fetch_assoc($res)['cnt'];
// Dropout requests raised
$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM dropout_requests WHERE mentor_id = $mentor_id");
$overview['dropout_requests'] = (int) mysqli_fetch_assoc($res)['cnt'];

// Assigned interns list
$assigned_sql = "SELECT ma.id as assign_id, u.full_name, u.email, i.title as internship_title, jp.title as project_title, a.status as current_phase, a.progress_percent, a.status as app_status, a.test_status, dl.last_log_date
FROM mentor_assignments ma
JOIN internship_applications a ON ma.application_id = a.id
JOIN users u ON a.user_id = u.id
LEFT JOIN internships i ON a.internship_id = i.id
LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
LEFT JOIN (SELECT student_id, MAX(log_date) as last_log_date FROM daily_logs GROUP BY student_id) dl ON a.user_id = dl.student_id
WHERE ma.mentor_id = $mentor_id";
$assigned_res = mysqli_query($conn, $assigned_sql);

// Pending daily logs details
$logs_sql = "SELECT dl.id, dl.student_id, u.full_name, u.email, i.title as internship_title, dl.log_date, dl.tasks_completed, dl.time_spent, dl.focus_level, dl.issues_faced, dl.next_plan
FROM daily_logs dl
JOIN mentor_assignments ma ON dl.student_id = ma.student_id
JOIN users u ON dl.student_id = u.id
LEFT JOIN internship_applications a ON dl.student_id = a.user_id
LEFT JOIN internships i ON a.internship_id = i.id
WHERE ma.mentor_id = $mentor_id AND dl.is_reviewed = 0";
$logs_res = mysqli_query($conn, $logs_sql);

// Mentor notifications (unread)
$notif_sql = "SELECT * FROM student_notifications WHERE user_id = $mentor_id AND is_read = 0 ORDER BY created_at DESC LIMIT 10";
$notif_res = mysqli_query($conn, $notif_sql);

page_shell_start('mentor_dashboard', 'Mentor Dashboard', 'Review assigned interns, daily logs, feedback, and dropout requests', '');
?>
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
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Daily Log Review Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Pending Daily Logs</h2>
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
                        <button type="button" class="px-2 py-1 bg-slate-200 text-slate-800 rounded" data-log-id="<?= $log['id'] ?>" onclick="viewLog(<?= $log['id'] ?>)">View</button>
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
                $stu_res = mysqli_query($conn, "SELECT ma.student_id, u.full_name FROM mentor_assignments ma JOIN users u ON ma.student_id = u.id WHERE ma.mentor_id = $mentor_id");
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
                $int_res = mysqli_query($conn, "SELECT DISTINCT i.id, i.title FROM mentor_assignments ma JOIN internship_applications a ON ma.application_id = a.id JOIN internships i ON a.internship_id = i.id WHERE ma.mentor_id = $mentor_id");
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
                $stu_res2 = mysqli_query($conn, "SELECT ma.student_id, u.full_name FROM mentor_assignments ma JOIN users u ON ma.student_id = u.id WHERE ma.mentor_id = $mentor_id");
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
                $int_res2 = mysqli_query($conn, "SELECT DISTINCT i.id, i.title FROM mentor_assignments ma JOIN internship_applications a ON ma.application_id = a.id JOIN internships i ON a.internship_id = i.id WHERE ma.mentor_id = $mentor_id");
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

<!-- Mentor Notifications -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-4">Notifications</h2>
    <?php if (mysqli_num_rows($notif_res) > 0): ?>
        <ul class="space-y-2">
            <?php while ($n = mysqli_fetch_assoc($notif_res)): ?>
                <li class="p-3 border border-slate-200 rounded bg-slate-50">
                    <p class="text-sm text-slate-800"><?= htmlspecialchars($n['message']) ?></p>
                    <p class="text-xs text-slate-500"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></p>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No new notifications.</p>
    <?php endif; ?>
</div>

<!-- Modal Scripts (simple JS placeholders) -->
<script>
function viewLog(id) {
    // Implement modal fetch if needed; placeholder alert
    alert('View Log ID: ' + id);
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
</script>
<?php
page_shell_end();
?>