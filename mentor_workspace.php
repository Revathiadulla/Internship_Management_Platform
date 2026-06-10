<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
include 'db.php';
require_role('mentor');
include_once __DIR__ . '/includes/hr_module_helpers.php';

$mentor_id = current_user_id();
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$success_msg = trim($_GET['success_msg'] ?? '');
$error_msg = trim($_GET['error_msg'] ?? '');
$active_tab = trim($_GET['tab'] ?? 'overview');
if (!in_array($active_tab, ['overview', 'students', 'logs', 'discussion'], true)) {
    $active_tab = 'overview';
}

if ($mentor_id <= 0 || $team_id <= 0) {
    die('Invalid request');
}

// ── POST Handlers ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'post_update') {
        $message_text = trim($_POST['team_message'] ?? '');
        if ($message_text === '') {
            header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=discussion&error_msg=' . urlencode('Please enter a message before posting.'));
            exit();
        }

        $insert_stmt = $conn->prepare("INSERT INTO team_discussion_messages (team_id, sender_id, sender_role, message) VALUES (?, ?, 'mentor', ?)");
        $insert_stmt->bind_param('iis', $team_id, $mentor_id, $message_text);
        $insert_stmt->execute();
        $insert_stmt->close();

        $student_ids = [];
        $student_stmt = $conn->prepare("SELECT student_id FROM project_team_members WHERE project_team_id = ?");
        $student_stmt->bind_param('i', $team_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        while ($student_row = $student_result->fetch_assoc()) {
            $student_id = intval($student_row['student_id'] ?? 0);
            if ($student_id > 0 && !in_array($student_id, $student_ids, true)) {
                $student_ids[] = $student_id;
            }
        }
        $student_stmt->close();

        if (!empty($student_ids)) {
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'student', 'Mentor update', ?, 'info', ?)");
            $notify_link = 'mentor_workspace.php?team_id=' . $team_id;
            $notify_msg = 'A mentor update was shared for your team: ' . mb_substr($message_text, 0, 120);
            foreach ($student_ids as $student_id) {
                $notify_stmt->bind_param('iss', $student_id, $notify_msg, $notify_link);
                $notify_stmt->execute();
            }
            $notify_stmt->close();
        }

        header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=discussion&success_msg=' . urlencode('Team update posted successfully.'));
        exit();
    }

    if ($action === 'send_notification') {
        $subject = trim($_POST['notification_subject'] ?? '');
        $message = trim($_POST['notification_message'] ?? '');
        if ($subject === '' || $message === '') {
            header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=discussion&error_msg=' . urlencode('Please complete the notification details.'));
            exit();
        }

        $student_stmt = $conn->prepare("SELECT student_id FROM project_team_members WHERE project_team_id = ?");
        $student_stmt->bind_param('i', $team_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $student_ids = [];
        while ($student_row = $student_result->fetch_assoc()) {
            $student_id = intval($student_row['student_id'] ?? 0);
            if ($student_id > 0 && !in_array($student_id, $student_ids, true)) {
                $student_ids[] = $student_id;
            }
        }
        $student_stmt->close();

        $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'student', ?, ?, 'info', ?)");
        $notify_link = 'mentor_workspace.php?team_id=' . $team_id;
        foreach ($student_ids as $student_id) {
            $notify_stmt->bind_param('isss', $student_id, $subject, $message, $notify_link);
            $notify_stmt->execute();
        }
        $notify_stmt->close();

        header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=discussion&success_msg=' . urlencode('Notification sent to the team.'));
        exit();
    }

    if ($action === 'raise_dropout') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        if ($student_id <= 0 || $internship_id <= 0 || $reason === '') {
            header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=students&error_msg=' . urlencode('Please complete the dropout request form.'));
            exit();
        }

        $app_stmt = $conn->prepare("SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1");
        $app_stmt->bind_param('ii', $student_id, $internship_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        $app_row = $app_result->fetch_assoc();
        $app_stmt->close();

        if ($app_row) {
            $application_id = intval($app_row['id']);
            $drop_stmt = $conn->prepare("INSERT INTO dropout_requests (application_id, mentor_id, reason, remarks, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $drop_stmt->bind_param('iiss', $application_id, $mentor_id, $reason, $remarks);
            $drop_stmt->execute();
            $drop_stmt->close();

            // Fetch student name
            $stud_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
            $stud_stmt->bind_param('i', $student_id);
            $stud_stmt->execute();
            $stud_row = $stud_stmt->get_result()->fetch_assoc();
            $student_name = $stud_row['full_name'] ?? 'Student';
            $stud_stmt->close();

            // Fetch internship title
            $intern_stmt = $conn->prepare("SELECT title FROM internships WHERE id = ? LIMIT 1");
            $intern_stmt->bind_param('i', $internship_id);
            $intern_stmt->execute();
            $intern_row = $intern_stmt->get_result()->fetch_assoc();
            $internship_title = $intern_row['title'] ?? 'Internship';
            $intern_stmt->close();

            // Create notification for admins
            $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE LOWER(role) = 'admin'");
            if ($admin_res) {
                $a_title = 'Student Dropout Request';
                $a_msg = "Mentor " . ($_SESSION['full_name'] ?? 'Mentor') . " requested dropout for student " . $student_name . " on '" . $internship_title . "'.";
                $a_type = 'alert';
                $a_link = "admin_dropout_requests.php";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'admin', ?, ?, ?, ?)");
                if ($notif_stmt) {
                    while ($a_row = mysqli_fetch_assoc($admin_res)) {
                        $a_id = intval($a_row['id']);
                        $notif_stmt->bind_param("issss", $a_id, $a_title, $a_msg, $a_type, $a_link);
                        $notif_stmt->execute();
                    }
                    $notif_stmt->close();
                }
            }
        }

        header('Location: mentor_workspace.php?team_id=' . $team_id . '&tab=students&success_msg=' . urlencode('Dropout request submitted successfully.'));
        exit();
    }
}

// ── Data Queries ────────────────────────────────────────────────────────────

$team_stmt = $conn->prepare("SELECT t.id, t.team_name, t.status AS team_status, t.internship_id, t.mentor_id, i.title, i.project_type, i.project_subtype, i.description, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name, u.email AS mentor_email FROM project_teams t LEFT JOIN internships i ON t.internship_id = i.id LEFT JOIN users u ON t.mentor_id = u.id WHERE t.id = ? AND t.mentor_id = ? LIMIT 1");
$team_stmt->bind_param('ii', $team_id, $mentor_id);
$team_stmt->execute();
$team = $team_stmt->get_result()->fetch_assoc();
$team_stmt->close();

if (!$team) {
    die('Team not found or access denied');
}

// Students — extended with college_name from student_profiles
$students = [];
$internship_id_for_students = intval($team['internship_id'] ?? 0);
$students_stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, COALESCE(sp.college_name, '') AS college_name, COALESCE(a.status, 'Active Intern') AS app_status, a.id AS application_id, a.internship_id, dr.status AS dropout_status FROM project_team_members ptm JOIN users u ON ptm.student_id = u.id LEFT JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN internship_applications a ON a.id = (SELECT id FROM internship_applications WHERE user_id = u.id ORDER BY (internship_id = ?) DESC, id DESC LIMIT 1) LEFT JOIN dropout_requests dr ON dr.application_id = a.id AND dr.status IN ('Requested', 'Pending') WHERE ptm.project_team_id = ? ORDER BY u.full_name ASC");
$students_stmt->bind_param('ii', $internship_id_for_students, $team_id);
$students_stmt->execute();
$students_res = $students_stmt->get_result();
while ($row = $students_res->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();

// Daily Logs
$logs = [];
$logs_stmt = $conn->prepare("SELECT dl.id, dl.log_date, dl.tasks_completed, dl.time_spent, dl.focus_level, dl.issues_faced, dl.next_plan, dl.is_reviewed, dl.review_status, dl.reviewer_remarks, u.full_name FROM daily_logs dl JOIN users u ON dl.user_id = u.id JOIN project_team_members ptm ON ptm.student_id = dl.user_id WHERE ptm.project_team_id = ? ORDER BY dl.log_date DESC, dl.id DESC LIMIT 10");
$logs_stmt->bind_param('i', $team_id);
$logs_stmt->execute();
$logs_res = $logs_stmt->get_result();
while ($log_row = $logs_res->fetch_assoc()) {
    $logs[] = $log_row;
}
$logs_stmt->close();

// Team Discussion Messages
$messages = [];
$messages_stmt = $conn->prepare("SELECT tdm.message, tdm.created_at, tdm.sender_role, u.full_name AS sender_name FROM team_discussion_messages tdm LEFT JOIN users u ON tdm.sender_id = u.id WHERE tdm.team_id = ? ORDER BY tdm.created_at DESC");
$messages_stmt->bind_param('i', $team_id);
$messages_stmt->execute();
$messages_res = $messages_stmt->get_result();
while ($message_row = $messages_res->fetch_assoc()) {
    $messages[] = $message_row;
}
$messages_stmt->close();

// Phase progress mapping
$phase_progress_map = [
    'Planning' => 25,
    'Active' => 50,
    'In Progress' => 60,
    'Mid Review' => 75,
    'Final Review' => 85,
    'Completed' => 100,
    'Paused' => 35,
    'Started' => 55,
    'Applied' => 20,
    'Shortlisted' => 35,
    'Selected' => 45,
    'Internship Started' => 70,
    'Internship Active' => 85,
    'Active Intern' => 80,
];
$current_phase = trim($team['team_status'] ?? 'Active');
$phase_progress = $phase_progress_map[$current_phase] ?? 45;

// ── Render Page ─────────────────────────────────────────────────────────────

$action_html = '<a href="mentor_projects.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-all"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Back to Projects</a>';
page_shell_start('projects', 'Mentor Workspace', e($team['title'] ?: 'Assigned Project') . '  ·  Team: ' . e($team['team_name'] ?: 'Project Team'), $action_html);
?>

<style>
/* ── Workspace Tab Styles ─────────────────────────────────────────────────── */
.ws-tabs {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
    overflow-x: auto;
}
.ws-tab {
    position: relative;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    transition: color 0.2s, border-color 0.2s;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ws-tab:hover {
    color: #334155;
}
.ws-tab.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.ws-tab .tab-icon {
    font-size: 20px;
}
.ws-tab .tab-count {
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    line-height: 1.4;
}
.ws-tab.active .tab-count {
    background: #dbeafe;
    color: #2563eb;
}
.ws-panel { display: none; }
.ws-panel.active { display: block; }

/* ── Summary Row Items ────────────────────────────────────────────────────── */
.summary-item {
    padding: 16px;
    border-right: 1px solid #e2e8f0;
}
.summary-item:last-child {
    border-right: none;
}
@media (max-width: 1023px) {
    .summary-item {
        border-right: none;
        border-bottom: 1px solid #f1f5f9;
    }
    .summary-item:last-child {
        border-bottom: none;
    }
}

/* ── Discussion Messages ──────────────────────────────────────────────────── */
.msg-thread {
    max-height: 480px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.msg-thread::-webkit-scrollbar { width: 6px; }
.msg-thread::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

/* ── Responsive Table Rows ────────────────────────────────────────────────── */
@media (max-width: 767px) {
    .data-grid-header { display: none !important; }
    .data-grid-row {
        grid-template-columns: 1fr !important;
        gap: 6px !important;
    }
    .data-grid-row [data-label]::before {
        content: attr(data-label) ": ";
        font-weight: 700;
        color: #94a3b8;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
}
</style>

<?php if ($success_msg !== ''): ?>
  <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 flex items-center gap-2">
      <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
      <?= e($success_msg) ?>
  </div>
<?php endif; ?>
<?php if ($error_msg !== ''): ?>
  <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 flex items-center gap-2">
      <span class="material-symbols-outlined text-[18px] text-red-600">error</span>
      <?= e($error_msg) ?>
  </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB NAVIGATION
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="ws-tabs" id="workspace-tabs" role="tablist">
    <button type="button" class="ws-tab<?= $active_tab === 'overview' ? ' active' : '' ?>" data-tab="overview" onclick="switchTab('overview')" role="tab">
        <span class="material-symbols-outlined tab-icon">dashboard</span>
        Overview
    </button>
    <button type="button" class="ws-tab<?= $active_tab === 'students' ? ' active' : '' ?>" data-tab="students" onclick="switchTab('students')" role="tab">
        <span class="material-symbols-outlined tab-icon">group</span>
        Students
        <span class="tab-count"><?= count($students) ?></span>
    </button>
    <button type="button" class="ws-tab<?= $active_tab === 'logs' ? ' active' : '' ?>" data-tab="logs" onclick="switchTab('logs')" role="tab">
        <span class="material-symbols-outlined tab-icon">description</span>
        Daily Logs
        <span class="tab-count"><?= count($logs) ?></span>
    </button>
    <button type="button" class="ws-tab<?= $active_tab === 'discussion' ? ' active' : '' ?>" data-tab="discussion" onclick="switchTab('discussion')" role="tab">
        <span class="material-symbols-outlined tab-icon">forum</span>
        Discussion
        <span class="tab-count"><?= count($messages) ?></span>
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     OVERVIEW TAB
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="panel-overview" class="ws-panel<?= $active_tab === 'overview' ? ' active' : '' ?>" role="tabpanel">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 pt-6 pb-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Project Details</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-800">Project Summary</h3>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-100 px-3.5 py-1.5 text-xs font-bold text-blue-700">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                <?= e($current_phase) ?>
            </span>
        </div>

        <!-- Horizontal Summary Row -->
        <div class="grid grid-cols-2 lg:grid-cols-6 border-t border-slate-100">
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Project Subtype</p>
                <p class="mt-1.5 text-sm font-semibold text-slate-700"><?= e($team['project_subtype'] ?: 'General') ?></p>
            </div>
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Technology Stack</p>
                <p class="mt-1.5 text-sm font-semibold text-slate-700"><?= e($team['technology_stack'] ?: 'Not listed') ?></p>
            </div>
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Duration</p>
                <p class="mt-1.5 text-sm font-semibold text-slate-700"><?= e($team['duration'] ?: '—') ?></p>
            </div>
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Mentor</p>
                <p class="mt-1.5 text-sm font-semibold text-slate-700"><?= e($team['mentor_name'] ?: 'Mentor') ?></p>
            </div>
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Current Phase</p>
                <p class="mt-1.5 text-sm font-semibold text-slate-700"><?= e($current_phase) ?></p>
            </div>
            <div class="summary-item">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Current Phase Progress</p>
                <div class="mt-2 flex items-center gap-2.5">
                    <div class="flex-1 h-2 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-600 transition-all duration-500" style="width: <?= intval($phase_progress) ?>%"></div>
                    </div>
                    <span class="text-xs font-bold text-slate-700 whitespace-nowrap"><?= intval($phase_progress) ?>%</span>
                </div>
            </div>
        </div>

        <!-- Project Description -->
        <?php $desc = trim($team['description'] ?? ''); ?>
        <?php if ($desc !== ''): ?>
        <div class="border-t border-slate-100 px-6 py-5">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Project Description</p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 whitespace-pre-wrap"><?= nl2br(e($desc)) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Team Members</p>
            <p class="mt-2 text-2xl font-bold text-slate-900"><?= count($students) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Recent Logs</p>
            <p class="mt-2 text-2xl font-bold text-slate-900"><?= count($logs) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Team Messages</p>
            <p class="mt-2 text-2xl font-bold text-slate-900"><?= count($messages) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Phase</p>
            <p class="mt-2 text-2xl font-bold text-blue-600"><?= e($current_phase) ?></p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     STUDENTS TAB
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="panel-students" class="ws-panel<?= $active_tab === 'students' ? ' active' : '' ?>" role="tabpanel">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Assigned Students</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-800">Team Members</h3>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
                <?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if (empty($students)): ?>
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">person_off</span>
                <p class="mt-2 text-sm font-semibold text-slate-500">No students assigned to this team yet.</p>
                <p class="mt-1 text-xs text-slate-400">Students will appear here once assigned by a coordinator.</p>
            </div>
        <?php else: ?>
            <!-- Table Header -->
            <div class="data-grid-header hidden md:grid grid-cols-[1.2fr_1.4fr_1fr_100px_250px] gap-4 items-center px-4 py-2.5 rounded-lg bg-slate-50 border border-slate-100 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-2">
                <span>Name</span>
                <span>Email</span>
                <span>College</span>
                <span class="text-center" style="min-width:100px">Status</span>
                <span class="text-center" style="min-width:250px">Action</span>
            </div>
            <!-- Table Rows -->
            <div class="space-y-2">
                <?php foreach ($students as $student): ?>
                <div class="data-grid-row grid grid-cols-[1.2fr_1.4fr_1fr_100px_250px] gap-4 items-center rounded-xl border border-slate-100 bg-white hover:bg-slate-50 transition-colors px-4 py-3.5">
                    <div data-label="Name">
                        <p class="text-sm font-semibold text-slate-800"><?= e($student['full_name']) ?></p>
                    </div>
                    <div data-label="Email">
                        <p class="text-sm text-slate-500 truncate"><?= e($student['email']) ?></p>
                    </div>
                    <div data-label="College">
                        <p class="text-sm text-slate-600"><?= e($student['college_name'] ?: '—') ?></p>
                    </div>
                    <div data-label="Status" class="text-center" style="min-width:100px">
                        <?php
                            $st_status = $student['app_status'] ?? 'Active Intern';
                            $st_class = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                            if (stripos($st_status, 'reject') !== false || stripos($st_status, 'dropout') !== false) {
                                $st_class = 'bg-red-50 text-red-700 border-red-200';
                            } elseif (stripos($st_status, 'pause') !== false || stripos($st_status, 'pending') !== false) {
                                $st_class = 'bg-amber-50 text-amber-700 border-amber-200';
                            }
                        ?>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold <?= $st_class ?>"><?= e($st_status) ?></span>
                    </div>
                    <div class="text-center flex items-center justify-center gap-2" style="min-width:250px">
                        <a href="mentor_daily_logs.php?student_id=<?= $student['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                            View Details
                        </a>
                        <?php if (!empty($student['dropout_status'])): ?>
                            <button type="button" disabled class="inline-flex items-center gap-1 rounded-lg bg-slate-100 border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-400 cursor-not-allowed">
                                <span class="material-symbols-outlined text-[14px]">hourglass_empty</span>
                                Drop Pending
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="openDropModal(<?= $student['id'] ?>, <?= intval($student['internship_id']) ?>, '<?= e(addslashes($student['full_name'])) ?>')" class="inline-flex items-center gap-1 rounded-lg bg-red-50 border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[14px]">person_remove</span>
                                Request Drop
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     DAILY LOGS TAB
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="panel-logs" class="ws-panel<?= $active_tab === 'logs' ? ' active' : '' ?>" role="tabpanel">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Daily Logs</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-800">Recent Submissions</h3>
            </div>
            <a href="mentor_daily_logs.php" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                Open Review Center
            </a>
        </div>

        <?php if (empty($logs)): ?>
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">assignment</span>
                <p class="mt-2 text-sm font-semibold text-slate-500">No daily logs submitted for this team yet.</p>
                <p class="mt-1 text-xs text-slate-400">Logs will appear here once students start submitting.</p>
            </div>
        <?php else: ?>
            <!-- Table Header -->
            <div class="data-grid-header hidden md:grid grid-cols-[1.1fr_0.8fr_1.8fr_0.6fr_auto_auto] gap-4 items-center px-4 py-2.5 rounded-lg bg-slate-50 border border-slate-100 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-2">
                <span>Student Name</span>
                <span>Date</span>
                <span>Task Summary</span>
                <span class="text-center">Hours</span>
                <span class="text-center" style="min-width:90px">Status</span>
                <span class="text-center" style="min-width:80px">Action</span>
            </div>
            <!-- Table Rows -->
            <div class="space-y-2">
                <?php foreach ($logs as $log): ?>
                <?php
                    $log_status = $log['review_status'] ?: 'Pending';
                    $log_status_display = ucfirst(str_replace('_', ' ', $log_status));
                    if ($log_status === 'approved') {
                        $log_class = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    } elseif ($log_status === 'changes_requested') {
                        $log_class = 'bg-amber-50 text-amber-700 border-amber-200';
                    } else {
                        $log_class = 'bg-slate-50 text-slate-600 border-slate-200';
                    }
                ?>
                <div class="data-grid-row grid grid-cols-[1.1fr_0.8fr_1.8fr_0.6fr_auto_auto] gap-4 items-center rounded-xl border border-slate-100 bg-white hover:bg-slate-50 transition-colors px-4 py-3.5">
                    <div data-label="Student">
                        <p class="text-sm font-semibold text-slate-800"><?= e($log['full_name']) ?></p>
                    </div>
                    <div data-label="Date">
                        <p class="text-sm text-slate-500"><?= date('M d, Y', strtotime($log['log_date'])) ?></p>
                    </div>
                    <div data-label="Tasks">
                        <p class="text-sm text-slate-600 line-clamp-2"><?= e($log['tasks_completed'] ?: '—') ?></p>
                    </div>
                    <div data-label="Hours" class="text-center">
                        <p class="text-sm font-semibold text-slate-700"><?= e($log['time_spent'] ?: '—') ?></p>
                    </div>
                    <div data-label="Status" class="text-center" style="min-width:90px">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold <?= $log_class ?>"><?= e($log_status_display) ?></span>
                    </div>
                    <div class="text-center" style="min-width:80px">
                        <a href="mentor_daily_logs.php" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">rate_review</span>
                            Review
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     DISCUSSION TAB
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="panel-discussion" class="ws-panel<?= $active_tab === 'discussion' ? ' active' : '' ?>" role="tabpanel">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Team Announcements &amp; Discussion</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-800">Team Communication</h3>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
                <?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?>
            </span>
        </div>

        <!-- Post Announcement Form -->
        <form method="post" class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-6">
            <input type="hidden" name="action" value="post_update">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Post an Announcement</label>
            <textarea name="team_message" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 transition-all resize-none" placeholder="Share a team update, guidance note, or reminder..."></textarea>
            <div class="flex items-center justify-between mt-3">
                <p class="text-[11px] text-slate-400">All team members will be notified</p>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    Post &amp; Notify Team
                </button>
            </div>
        </form>

        <!-- Messages Thread -->
        <div class="msg-thread">
            <?php if (empty($messages)): ?>
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-300">chat_bubble_outline</span>
                    <p class="mt-2 text-sm font-semibold text-slate-500">No team messages yet.</p>
                    <p class="mt-1 text-xs text-slate-400">Post the first announcement to start the conversation.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($messages as $message): ?>
                    <?php
                        $is_mentor_msg = ($message['sender_role'] === 'mentor');
                        $role_badge_class = $is_mentor_msg
                            ? 'bg-blue-50 text-blue-700 border-blue-100'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                        $role_label = $is_mentor_msg ? 'Mentor' : 'Student';
                    ?>
                    <div class="rounded-xl border border-slate-100 bg-white p-4 hover:border-slate-200 transition-colors">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-8 w-8 place-items-center rounded-full <?= $is_mentor_msg ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' ?> text-xs font-bold">
                                    <?= strtoupper(substr(e($message['sender_name'] ?: 'U'), 0, 1)) ?>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800"><?= e($message['sender_name'] ?: 'Team Member') ?></p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold <?= $role_badge_class ?>"><?= $role_label ?></span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 font-medium whitespace-nowrap"><?= date('M d, Y · h:i A', strtotime($message['created_at'])) ?></p>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed whitespace-pre-wrap"><?= e($message['message']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB SWITCHING JAVASCRIPT & MODALS
     ═══════════════════════════════════════════════════════════════════════════ -->
<!-- Dropout Request Modal -->
<div id="dropout-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-slate-800">Request Student Dropout</h3>
            <button onclick="closeDropModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="mentor_workspace.php?team_id=<?= $team_id ?>" method="POST" class="p-6 space-y-4" onsubmit="return confirm('Submit this dropout request for administration review?');">
            <input type="hidden" name="action" value="raise_dropout">
            <input type="hidden" id="modal-student-id" name="student_id">
            <input type="hidden" id="modal-internship-id" name="internship_id">

            <div>
                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Student</label>
                <input type="text" id="modal-student-name" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs text-slate-700 focus:outline-none">
            </div>

            <div>
                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Reason</label>
                <select name="reason" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:border-blue-600 focus:ring-blue-600/10 bg-white">
                    <option value="">Select a reason</option>
                    <option value="No Daily Log Submission">No Daily Log Submission</option>
                    <option value="Poor Performance">Poor Performance</option>
                    <option value="Inactive for Long Period">Inactive for Long Period</option>
                    <option value="Not Attending Meetings">Not Attending Meetings</option>
                    <option value="Requested Withdrawal">Requested Withdrawal</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Remarks / Notes</label>
                <textarea name="remarks" rows="4" class="w-full border border-slate-200 rounded-xl p-4 text-xs focus:ring-4 focus:ring-blue-100 focus:border-blue-600 outline-none placeholder:text-gray-400 transition-all" placeholder="Provide additional details or context for the dropout request..."></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeDropModal()" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all cursor-pointer">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 transition-all shadow-md cursor-pointer">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Deactivate all tabs
    document.querySelectorAll('.ws-tab').forEach(function(t) {
        t.classList.remove('active');
    });
    // Hide all panels
    document.querySelectorAll('.ws-panel').forEach(function(p) {
        p.classList.remove('active');
    });
    // Activate selected tab
    var tabBtn = document.querySelector('[data-tab="' + tabId + '"]');
    if (tabBtn) tabBtn.classList.add('active');
    // Show selected panel
    var panel = document.getElementById('panel-' + tabId);
    if (panel) panel.classList.add('active');
    // Update URL without reload
    var url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    history.replaceState({}, '', url);
}

function openDropModal(studentId, internshipId, studentName) {
    document.getElementById('modal-student-id').value = studentId;
    document.getElementById('modal-internship-id').value = internshipId;
    document.getElementById('modal-student-name').value = studentName;
    document.getElementById('dropout-modal').classList.remove('hidden');
}

function closeDropModal() {
    document.getElementById('dropout-modal').classList.add('hidden');
}
</script>

<?php page_shell_end(); ?>
