<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
include 'db.php';
require_role('mentor');
include_once __DIR__ . '/includes/hr_module_helpers.php';

$mentor_id = current_user_id();
if ($mentor_id <= 0) {
    die('Unauthorized');
}

$team_sql = "SELECT t.id, t.team_name, t.status, t.internship_id, i.title AS project_title, i.project_type, i.project_subtype, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name
             FROM project_teams t
             LEFT JOIN internships i ON t.internship_id = i.id
             LEFT JOIN users u ON t.mentor_id = u.id
             WHERE t.mentor_id = ?
             ORDER BY COALESCE(i.title, t.team_name) ASC, t.team_name ASC";
$team_stmt = $conn->prepare($team_sql);
$team_stmt->bind_param('i', $mentor_id);
$team_stmt->execute();
$team_result = $team_stmt->get_result();
$team_rows = [];
while ($row = $team_result->fetch_assoc()) {
    $team_id = intval($row['id']);
    $member_sql = "SELECT COUNT(*) AS student_count FROM project_team_members WHERE project_team_id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param('i', $team_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    $member_count = 0;
    if ($member_row = $member_result->fetch_assoc()) {
        $member_count = intval($member_row['student_count'] ?? 0);
    }
    $member_stmt->close();

    $phase_map = [
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
    $progress = $phase_map[trim($row['status'] ?? '')] ?? 45;

    $team_rows[] = [
        'team_id' => $team_id,
        'team_name' => $row['team_name'] ?: 'Project Team',
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
}
$team_stmt->close();

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
                        <a href="mentor_workspace.php?team_id=<?= intval($project['team_id']) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">terminal</span>
                            Open Workspace
                        </a>
                        <a href="mentor_workspace.php?team_id=<?= intval($project['team_id']) ?>&tab=students" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100 transition-colors">
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
