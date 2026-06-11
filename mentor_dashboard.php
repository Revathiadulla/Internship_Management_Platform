<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
include 'db.php';
require_role('mentor');
include_once __DIR__ . '/includes/hr_module_helpers.php';
require_once __DIR__ . '/includes/progress_helper.php';

$mentor_id = current_user_id();
if ($mentor_id <= 0) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_notification_read') {
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notification_id, $mentor_id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'mark_all_notifications_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param('i', $mentor_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: mentor_dashboard.php');
    exit();
}

$assigned_interns = 0;
$active_projects = 0;
$pending_logs = 0;
$unread_notifications_count = 0;

$assigned_interns_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) AS cnt FROM (
        SELECT ptm.student_id 
        FROM project_teams t 
        JOIN project_team_members ptm ON ptm.project_team_id = t.id 
        WHERE t.mentor_id = ?
        
        UNION
        
        SELECT ia.user_id AS student_id 
        FROM internship_applications ia 
        WHERE ia.mentor_id = ? 
          AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
        
        UNION
        
        SELECT ma.student_id 
        FROM mentor_assignments ma 
        WHERE ma.mentor_id = ? AND ma.status = 'active'
    ) AS assigned
");
$assigned_interns_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$assigned_interns_stmt->execute();
$assigned_interns_row = $assigned_interns_stmt->get_result()->fetch_assoc();
$assigned_interns = intval($assigned_interns_row['cnt'] ?? 0);
$assigned_interns_stmt->close();

$projects_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT internship_id) AS cnt FROM (
        SELECT internship_id 
        FROM project_teams 
        WHERE mentor_id = ? AND internship_id > 0
        
        UNION
        
        SELECT COALESCE(assigned_project_id, internship_id) AS internship_id 
        FROM internship_applications 
        WHERE mentor_id = ? 
          AND status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
          AND COALESCE(assigned_project_id, internship_id) > 0
          
        UNION
        
        SELECT COALESCE(project_id, internship_id) AS internship_id 
        FROM mentor_assignments ma 
        WHERE ma.mentor_id = ? AND ma.status = 'active' AND COALESCE(project_id, internship_id) > 0
    ) AS proj
");
$projects_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$projects_stmt->execute();
$projects_row = $projects_stmt->get_result()->fetch_assoc();
$active_projects = intval($projects_row['cnt'] ?? 0);
$projects_stmt->close();

$logs_stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM daily_logs dl 
    WHERE LOWER(dl.status) IN ('submitted', 'pending_review') 
      AND dl.user_id IN (
          SELECT ptm.student_id 
          FROM project_teams t 
          JOIN project_team_members ptm ON ptm.project_team_id = t.id 
          WHERE t.mentor_id = ?
          
          UNION
          
          SELECT ia.user_id AS student_id 
          FROM internship_applications ia 
          WHERE ia.mentor_id = ? 
            AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
            
          UNION
          
          SELECT ma.student_id 
          FROM mentor_assignments ma 
          WHERE ma.mentor_id = ? AND ma.status = 'active'
      )
");
$logs_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$logs_stmt->execute();
$logs_row = $logs_stmt->get_result()->fetch_assoc();
$pending_logs = intval($logs_row['cnt'] ?? 0);
$logs_stmt->close();

$notif_count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count_stmt->bind_param('i', $mentor_id);
$notif_count_stmt->execute();
$notif_count_row = $notif_count_stmt->get_result()->fetch_assoc();
$unread_notifications_count = intval($notif_count_row['cnt'] ?? 0);
$notif_count_stmt->close();

$team_updates = 0;
$updates_stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM team_discussion_messages tdm 
    WHERE tdm.team_id IN (
        SELECT id FROM project_teams WHERE mentor_id = ?
        UNION
        SELECT DISTINCT COALESCE(team_id, 0) FROM internship_applications WHERE mentor_id = ? AND team_id > 0
    )
");
if ($updates_stmt) {
    $updates_stmt->bind_param('ii', $mentor_id, $mentor_id);
    $updates_stmt->execute();
    $updates_row = $updates_stmt->get_result()->fetch_assoc();
    $team_updates = intval($updates_row['cnt'] ?? 0);
    $updates_stmt->close();
}

$recent_notifications = [];
$notif_stmt = $conn->prepare("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->bind_param('i', $mentor_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($notif_row = $notif_result->fetch_assoc()) {
    $recent_notifications[] = $notif_row;
}
$notif_stmt->close();

// Fetch project details for summary cards
$dashboard_projects = [];
$team_ids_added = [];
$team_names_added = [];

$team_sql = "SELECT t.id, t.team_name, t.status, t.internship_id, i.title AS project_title, i.project_type, i.project_subtype, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name
             FROM project_teams t
             LEFT JOIN internships i ON t.internship_id = i.id
             LEFT JOIN users u ON t.mentor_id = u.id
             WHERE t.mentor_id = ?
             ORDER BY COALESCE(i.title, t.team_name) ASC, t.team_name ASC";
$team_stmt = $conn->prepare($team_sql);
if ($team_stmt) {
    $team_stmt->bind_param('i', $mentor_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    while ($row = $team_result->fetch_assoc()) {
        $team_id = intval($row['id']);
        $team_name = trim($row['team_name'] ?? '');
        
        // Fetch student count
        $member_sql = "SELECT COUNT(*) AS student_count FROM project_team_members WHERE project_team_id = ?";
        $member_stmt = $conn->prepare($member_sql);
        $member_count = 0;
        if ($member_stmt) {
            $member_stmt->bind_param('i', $team_id);
            $member_stmt->execute();
            $member_result = $member_stmt->get_result();
            if ($member_row = $member_result->fetch_assoc()) {
                $member_count = intval($member_row['student_count'] ?? 0);
            }
            $member_stmt->close();
        }

        $prog_data = calculate_team_progress($conn, $team_id, $team_name);
        $progress = $prog_data['progress_percentage'];

        $dashboard_projects[] = [
            'team_id' => $team_id,
            'team_name' => $team_name ?: 'Project Team',
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_type' => $row['project_type'] ?: 'General',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'current_phase' => trim($row['status'] ?: 'Active'),
            'progress_percent' => $progress,
            'approved_logs' => $prog_data['approved_logs'],
            'expected_logs' => $prog_data['expected_logs'],
            'student_count' => $member_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
            'technology_stack' => $row['technology_stack'] ?: '',
            'duration' => $row['duration'] ?: '—',
            'start_date' => $row['start_date'] ?: '—',
            'end_date' => $row['end_date'] ?: '—',
            'assigned_date' => $row['start_date'] ?: '—',
        ];
        
        if ($team_id > 0) {
            $team_ids_added[$team_id] = true;
        }
        if ($team_name !== '') {
            $team_names_added[strtolower($team_name)] = true;
        }
    }
    $team_stmt->close();
}

// Fallback: Query from internship_applications
$app_teams_sql = "SELECT DISTINCT ia.team_name, ia.team_status, ia.internship_id, ia.internship_name, 
                          COALESCE(i.title, ia.internship_name) as project_title,
                          COALESCE(i.project_type, 'General') as project_type,
                          COALESCE(i.project_subtype, ia.applied_subtype, 'General') as project_subtype,
                          COALESCE(i.technology_stack, ia.tech_stack, '') as technology_stack,
                          COALESCE(i.duration, ia.internship_duration, '—') as duration,
                          i.start_date, i.end_date,
                          ia.team_id, u.full_name AS mentor_name, MIN(ia.applied_date) as assigned_date
                  FROM internship_applications ia
                  LEFT JOIN internships i ON ia.internship_id = i.id
                  LEFT JOIN users u ON ia.mentor_id = u.id
                  WHERE ia.mentor_id = ? 
                    AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
                    AND ia.team_name IS NOT NULL AND ia.team_name != ''
                  GROUP BY ia.team_name, ia.internship_id";
$app_teams_stmt = $conn->prepare($app_teams_sql);
if ($app_teams_stmt) {
    $app_teams_stmt->bind_param('i', $mentor_id);
    $app_teams_stmt->execute();
    $app_teams_result = $app_teams_stmt->get_result();
    while ($row = $app_teams_result->fetch_assoc()) {
        $team_name = trim($row['team_name'] ?? '');
        $team_id = intval($row['team_id'] ?? 0);
        $internship_id = intval($row['internship_id']);

        if ($team_id > 0 && isset($team_ids_added[$team_id])) {
            continue;
        }
        if (isset($team_names_added[strtolower($team_name)])) {
            continue;
        }

        // Count students in internship_applications
        $cnt_stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as cnt FROM internship_applications WHERE mentor_id = ? AND team_name = ? AND status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')");
        $student_count = 0;
        if ($cnt_stmt) {
            $cnt_stmt->bind_param('is', $mentor_id, $team_name);
            $cnt_stmt->execute();
            $student_count = intval($cnt_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $cnt_stmt->close();
        }

        $prog_data = calculate_team_progress($conn, $team_id, $team_name);
        $progress = $prog_data['progress_percentage'];

        $dashboard_projects[] = [
            'team_id' => $team_id > 0 ? $team_id : -1,
            'team_name' => $team_name,
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_type' => $row['project_type'] ?: 'General',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'current_phase' => trim($row['team_status'] ?: 'Active'),
            'progress_percent' => $progress,
            'approved_logs' => $prog_data['approved_logs'],
            'expected_logs' => $prog_data['expected_logs'],
            'student_count' => $student_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
            'technology_stack' => $row['technology_stack'] ?: '',
            'duration' => $row['duration'] ?: '—',
            'start_date' => $row['start_date'] ?: '—',
            'end_date' => $row['end_date'] ?: '—',
            'assigned_date' => $row['assigned_date'] ?: '—',
        ];

        if ($team_name !== '') {
            $team_names_added[strtolower($team_name)] = true;
        }
    }
    $app_teams_stmt->close();
}



$action_html = '<a href="mentor_projects.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700">Open Projects</a>';
page_shell_start('dashboard', 'Mentor Dashboard', 'Track assigned interns, active projects, pending daily logs, and your latest updates.', $action_html);
?>
<style>
.stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
</style>
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- Assigned Interns -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
                <div class="bg-blue-50 p-2.5 rounded-full text-blue-600">
                    <span class="material-symbols-outlined">school</span>
                </div>
                <span class="text-blue-600 text-xs font-bold bg-blue-50 px-2 py-0.5 rounded-full">Interns</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Assigned Interns</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?= intval($assigned_interns) ?></p>
        </div>

        <!-- Active Projects -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
                <div class="bg-emerald-50 p-2.5 rounded-full text-emerald-600">
                    <span class="material-symbols-outlined">trending_up</span>
                </div>
                <span class="text-emerald-600 text-xs font-bold bg-emerald-50 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Active Projects</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?= intval($active_projects) ?></p>
        </div>

        <!-- Pending Daily Logs -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
                <div class="bg-amber-50 p-2.5 rounded-full text-amber-600">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <span class="text-amber-600 text-xs font-bold bg-amber-50 px-2 py-0.5 rounded-full">Review</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Pending Daily Logs</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?= intval($pending_logs) ?></p>
        </div>

        <!-- Unread Notifications -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
                <div class="bg-red-50 p-2.5 rounded-full text-red-600">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <span class="text-red-600 text-xs font-bold bg-red-50 px-2 py-0.5 rounded-full">New</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Unread Notifications</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?= intval($unread_notifications_count) ?></p>
        </div>

        <!-- Team Updates -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
                <div class="bg-purple-50 p-2.5 rounded-full text-purple-600">
                    <span class="material-symbols-outlined">forum</span>
                </div>
                <span class="text-purple-600 text-xs font-bold bg-purple-50 px-2 py-0.5 rounded-full">Updates</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Team Updates</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?= intval($team_updates) ?></p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Projects</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-800">Assigned projects and workspaces</h3>
                </div>
                <a href="mentor_projects.php" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                    <span class="material-symbols-outlined text-[14px]">folder</span>
                    Open Projects
                </a>
            </div>

            <?php if (empty($dashboard_projects)): ?>
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-300">folder_off</span>
                    <p class="mt-2 text-sm font-semibold text-slate-500">No assigned projects found.</p>
                    <p class="mt-1 text-xs text-slate-400">Once a coordinator assigns a team to you, it will appear here.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4<?= count($dashboard_projects) > 1 ? ' md:grid-cols-2' : '' ?>">
                    <?php foreach ($dashboard_projects as $project): ?>
                        <div class="rounded-2xl border-l-4 border-l-blue-600 border-t border-r border-b border-slate-200 bg-white shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden min-h-[440px] transform hover:-translate-y-1">
                            <!-- Card Header with Subtle Gradient/Background -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-200 shrink-0">
                                        <span class="material-symbols-outlined text-2xl">terminal</span>
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-lg leading-tight tracking-tight"><?= htmlspecialchars($project['project_title']) ?></h4>
                                        <p class="text-xs font-semibold text-slate-500 mt-1 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400">groups</span>
                                            Team: <span class="font-bold text-slate-700"><?= htmlspecialchars($project['team_name']) ?></span>
                                        </p>
                                    </div>
                                </div>
                                <!-- Status/Phase Badge -->
                                <?php
                                    $phase_status = $project['current_phase'];
                                    $phase_class = 'bg-blue-50 text-blue-700 border-blue-200';
                                    if ($phase_status === 'Completed') {
                                        $phase_class = 'bg-emerald-50 text-emerald-700 border-emerald-250';
                                    } elseif ($phase_status === 'Paused') {
                                        $phase_class = 'bg-amber-50 text-amber-700 border-amber-250';
                                    } elseif ($phase_status === 'Active' || $phase_status === 'In Progress') {
                                        $phase_class = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                    }
                                ?>
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold <?= $phase_class ?> uppercase tracking-wider whitespace-nowrap shadow-sm">
                                    <?= htmlspecialchars($phase_status) ?>
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="p-6 space-y-6 flex-1 flex flex-col justify-between">
                                <div class="space-y-5">
                                    <!-- Info chips section -->
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Project Info</p>
                                        <div class="flex flex-wrap gap-2.5">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">domain</span>
                                                <?= htmlspecialchars($project['project_type']) ?>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">category</span>
                                                <?= htmlspecialchars($project['project_subtype']) ?>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">school</span>
                                                <?= intval($project['student_count']) ?> Student<?= intval($project['student_count']) === 1 ? '' : 's' ?>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                                <?= htmlspecialchars($project['duration']) ?>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">person</span>
                                                <?= htmlspecialchars($project['mentor_name']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Tech Stack Chips -->
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Technologies Used</p>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                                $tech_stack_str = !empty($project['technology_stack']) ? $project['technology_stack'] : 'HTML, CSS, JavaScript, PHP, MySQL';
                                                $techs = array_map('trim', explode(',', $tech_stack_str));
                                                foreach ($techs as $tech): 
                                                    if (empty($tech)) continue;
                                            ?>
                                                <span class="inline-flex items-center rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-100 transition-colors">
                                                    <?= htmlspecialchars($tech) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Progress section -->
                                    <div class="pt-2">
                                        <div class="flex items-center justify-between text-xs font-bold text-slate-500 mb-2">
                                            <span class="flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-base text-slate-400">track_changes</span>
                                                Project Progress <span class="text-[10px] text-slate-400 font-normal ml-1">(<?= intval($project['approved_logs']) ?> / <?= intval($project['expected_logs']) ?> Logs)</span>
                                            </span>
                                            <span class="text-slate-800 font-extrabold text-sm"><?= intval($project['progress_percent']) ?>%</span>
                                        </div>
                                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden border border-slate-200">
                                            <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500" style="width: <?= intval($project['progress_percent']) ?>%"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <?php
                                            $s_date = ($project['start_date'] && $project['start_date'] !== '—') ? date('M d, Y', strtotime($project['start_date'])) : '—';
                                            $e_date = ($project['end_date'] && $project['end_date'] !== '—') ? date('M d, Y', strtotime($project['end_date'])) : '—';
                                            $assigned_date_display = ($project['assigned_date'] && $project['assigned_date'] !== '—') ? date('M d, Y', strtotime($project['assigned_date'])) : '—';
                                        ?>
                                        <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Assigned Date &amp; Timeline</p>
                                        <p class="text-xs text-slate-700 font-bold mt-1 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-slate-450">assignment_turned_in</span>
                                            Assigned: <span class="text-blue-600"><?= $assigned_date_display ?></span>
                                        </p>
                                        <p class="text-xs text-slate-500 font-semibold mt-0.5 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400">date_range</span>
                                            <?= $s_date ?> - <?= $e_date ?>
                                        </p>
                                    </div>
                                    <a href="mentor_workspace.php?team_id=<?= intval($project['team_id']) ?>&team_name=<?= urlencode($project['team_name']) ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-650 px-5 py-2.5 text-xs font-bold text-white hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-md shadow-blue-100 hover:shadow-lg whitespace-nowrap cursor-pointer transform hover:-translate-y-0.5">
                                        <span class="material-symbols-outlined text-[16px]">terminal</span>
                                        Open Workspace
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Recent notifications</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-800">Latest updates</h3>
                </div>
                <a href="mentor_notifications.php" class="text-sm font-semibold text-blue-600">View all</a>
            </div>
            <div class="mt-5 space-y-3">
                <?php if (empty($recent_notifications)): ?>
                    <p class="text-sm text-slate-500">No recent notifications.</p>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $notification): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($notification['title'] ?: 'Notification') ?></p>
                                    <p class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($notification['message']) ?></p>
                                </div>
                                <?php if (empty($notification['is_read'])): ?>
                                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-blue-700">New</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-xs text-slate-500"><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php page_shell_end(); ?>
