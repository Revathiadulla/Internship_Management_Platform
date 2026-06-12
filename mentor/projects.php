<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('mentor');
include_once __DIR__ . '/../includes/hr_module_helpers.php';

$mentor_id = current_user_id();
if ($mentor_id <= 0) {
    die('Unauthorized');
}

$team_rows = [];
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

        $status_val = trim($row['status'] ?? '');
        $progress = 0;
        if (strtolower($status_val) === 'completed') {
            $progress = 100;
        } elseif (!empty($row['start_date']) && !empty($row['end_date']) && $row['start_date'] !== '—' && $row['end_date'] !== '—' && $row['start_date'] !== '0000-00-00') {
            try {
                $today = new DateTime();
                $start = new DateTime($row['start_date']);
                $end = new DateTime($row['end_date']);
                if ($today < $start) {
                    $progress = 0;
                } else {
                    $totalDays = max(1, $start->diff($end)->days);
                    $elapsedDays = max(0, $start->diff($today)->days);
                    $progress = min(100, round(($elapsedDays / $totalDays) * 100));
                }
            } catch (Exception $e) {
                $progress = 50;
            }
        } else {
            $phase_map = [
                'Planning' => 25, 'Active' => 50, 'In Progress' => 60, 'Mid Review' => 75, 'Final Review' => 85,
                'Completed' => 100, 'Paused' => 35, 'Started' => 55, 'Applied' => 20, 'Shortlisted' => 35,
                'Selected' => 45, 'Internship Started' => 70, 'Internship Active' => 85, 'Active Intern' => 80,
            ];
            $progress = $phase_map[$status_val] ?? 45;
        }

        $team_rows[] = [
            'team_id' => $team_id,
            'team_name' => $team_name ?: 'Project Team',
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'technology_stack' => $row['technology_stack'] ?: 'Not listed',
            'duration' => $row['duration'] ?: '—',
            'start_date' => $row['start_date'] ?: '—',
            'end_date' => $row['end_date'] ?: '—',
            'current_phase' => trim($row['status'] ?: 'Active'),
            'progress_percent' => $progress,
            'student_count' => $member_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
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
                          ia.team_id, u.full_name AS mentor_name
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

        $status_val = trim($row['team_status'] ?? '');
        $progress = 0;
        if (strtolower($status_val) === 'completed') {
            $progress = 100;
        } elseif (!empty($row['start_date']) && !empty($row['end_date']) && $row['start_date'] !== '—' && $row['end_date'] !== '—' && $row['start_date'] !== '0000-00-00') {
            try {
                $today = new DateTime();
                $start = new DateTime($row['start_date']);
                $end = new DateTime($row['end_date']);
                if ($today < $start) {
                    $progress = 0;
                } else {
                    $totalDays = max(1, $start->diff($end)->days);
                    $elapsedDays = max(0, $start->diff($today)->days);
                    $progress = min(100, round(($elapsedDays / $totalDays) * 100));
                }
            } catch (Exception $e) {
                $progress = 50;
            }
        } else {
            $phase_map = [
                'Planning' => 25, 'Active' => 50, 'In Progress' => 60, 'Mid Review' => 75, 'Final Review' => 85,
                'Completed' => 100, 'Paused' => 35, 'Started' => 55, 'Applied' => 20, 'Shortlisted' => 35,
                'Selected' => 45, 'Internship Started' => 70, 'Internship Active' => 85, 'Active Intern' => 80,
            ];
            $progress = $phase_map[$status_val] ?? 50;
        }

        $team_rows[] = [
            'team_id' => $team_id > 0 ? $team_id : -1,
            'team_name' => $team_name,
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'technology_stack' => $row['technology_stack'] ?: 'Not listed',
            'duration' => $row['duration'] ?: '—',
            'start_date' => $row['start_date'] ?: '—',
            'end_date' => $row['end_date'] ?: '—',
            'current_phase' => trim($row['team_status'] ?: 'Active'),
            'progress_percent' => $progress,
            'student_count' => $student_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
        ];

        if ($team_name !== '') {
            $team_names_added[strtolower($team_name)] = true;
        }
    }
    $app_teams_stmt->close();
}

page_shell_start('projects', 'Projects', 'Assigned project workspace');
?>
<div class="space-y-6">
    <?php if (empty($team_rows)): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
            <span class="material-symbols-outlined text-5xl text-slate-305 mb-3 text-slate-350">folder_off</span>
            <p class="text-lg font-semibold text-slate-700">No projects assigned yet.</p>
            <p class="mt-1 text-sm text-slate-500">Once a coordinator assigns a team to you, it will appear here.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
            <?php foreach ($team_rows as $project): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Project</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-800 leading-snug"><?= htmlspecialchars($project['project_title']) ?></h3>
                                <p class="mt-1 text-sm text-slate-500">Team: <span class="font-semibold text-slate-700"><?= htmlspecialchars($project['team_name']) ?></span></p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 uppercase tracking-wide">
                                <?= htmlspecialchars($project['current_phase']) ?>
                            </span>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Subtype</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700 truncate"><?= htmlspecialchars($project['project_subtype']) ?></p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Technology Stack</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700 truncate"><?= htmlspecialchars($project['technology_stack']) ?></p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Duration</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($project['duration']) ?></p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Students</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700"><?= intval($project['student_count']) ?> Assigned</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-500 mb-1">
                                <span>Current Phase Progress</span>
                                <span class="text-slate-700 font-bold"><?= intval($project['progress_percent']) ?>%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-blue-600" style="width: <?= intval($project['progress_percent']) ?>%"></div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <div>
                                <p class="font-semibold text-slate-400 uppercase tracking-wider text-[10px]">Mentor</p>
                                <p class="mt-1 text-slate-700 font-medium"><?= htmlspecialchars($project['mentor_name']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-slate-400 uppercase tracking-wider text-[10px] text-right">Timeline</p>
                                <?php
                                    $s_date = ($project['start_date'] && $project['start_date'] !== '—') ? date('M d, Y', strtotime($project['start_date'])) : '—';
                                    $e_date = ($project['end_date'] && $project['end_date'] !== '—') ? date('M d, Y', strtotime($project['end_date'])) : '—';
                                ?>
                                <p class="mt-1 text-slate-600 font-medium">Start Date: <span class="text-slate-750 font-bold"><?= $s_date ?></span><br>End Date: <span class="text-slate-750 font-bold"><?= $e_date ?></span></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center gap-3">
                        <a href="students.php?team_id=<?= intval($project['team_id']) ?>&team_name=<?= urlencode($project['team_name']) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">terminal</span>
                            Open Workspace
                        </a>
                        <a href="students.php?team_id=<?= intval($project['team_id']) ?>&team_name=<?= urlencode($project['team_name']) ?>&tab=students" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">person_remove</span>
                            Request Drop Student
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php page_shell_end(); ?>
