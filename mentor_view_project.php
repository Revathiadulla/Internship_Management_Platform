<?php
session_start();
include "db.php";
include_once __DIR__ . '/includes/auth.php';
require_module_access('mentor_dashboard');

$mentor_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
if ($mentor_id <= 0 || $team_id <= 0) {
    die('Invalid request');
}

// Verify team belongs to this mentor
$stmt = $conn->prepare("SELECT t.id, t.team_name, t.internship_id, t.status AS team_status, t.mentor_id, i.title, i.project_type, i.project_subtype, i.description, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name, u.email AS mentor_email
    FROM project_teams t
    LEFT JOIN internships i ON t.internship_id = i.id
    LEFT JOIN users u ON t.mentor_id = u.id
    WHERE t.id = ? AND t.mentor_id = ? LIMIT 1");
$stmt->bind_param('ii', $team_id, $mentor_id);
$stmt->execute();
$res = $stmt->get_result();
$team = $res->fetch_assoc();
$stmt->close();

if (!$team) {
    die('Team not found or access denied');
}

// Fetch assigned students
$students = [];
$s_stmt = $conn->prepare("SELECT u.id, u.full_name, u.email FROM project_team_members ptm JOIN users u ON ptm.student_id = u.id WHERE ptm.project_team_id = ? ORDER BY u.full_name ASC");
$s_stmt->bind_param('i', $team_id);
$s_stmt->execute();
$s_res = $s_stmt->get_result();
while ($r = $s_res->fetch_assoc()) $students[] = $r;
$s_stmt->close();

// Determine current phase — derive from team status; also attempt to show dominant app status if available
$current_phase = $team['team_status'] ?? 'Active';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Project Details - <?= htmlspecialchars($team['team_name'] ?? 'Project') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial}</style>
</head>
<body class="bg-gray-50 text-gray-900">
  <main class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-bold"><?= htmlspecialchars($team['title'] ?: 'Project') ?></h1>
          <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($team['team_name']) ?></p>
        </div>
        <div class="text-right">
          <a href="mentor_dashboard.php" class="text-sm text-blue-600">Back to dashboard</a>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <div class="space-y-2">
          <div><span class="text-xs text-gray-500">Project Type</span><div class="font-semibold"><?= htmlspecialchars($team['project_type'] ?? '-') ?></div></div>
          <div><span class="text-xs text-gray-500">Project Subtype</span><div class="font-semibold"><?= htmlspecialchars($team['project_subtype'] ?? '-') ?></div></div>
          <div><span class="text-xs text-gray-500">Duration</span><div class="font-semibold"><?= htmlspecialchars($team['duration'] ?? '-') ?></div></div>
          <div><span class="text-xs text-gray-500">Technology Stack</span><div class="font-semibold"><?= htmlspecialchars($team['technology_stack'] ?? '-') ?></div></div>
        </div>
        <div class="space-y-2">
          <div><span class="text-xs text-gray-500">Start Date</span><div class="font-semibold"><?= !empty($team['start_date']) ? date('M d, Y', strtotime($team['start_date'])) : '-' ?></div></div>
          <div><span class="text-xs text-gray-500">End Date</span><div class="font-semibold"><?= !empty($team['end_date']) ? date('M d, Y', strtotime($team['end_date'])) : '-' ?></div></div>
          <div><span class="text-xs text-gray-500">Current Phase</span><div class="font-semibold"><?= htmlspecialchars($current_phase) ?></div></div>
          <div><span class="text-xs text-gray-500">Mentor</span><div class="font-semibold"><?= htmlspecialchars($team['mentor_name'] ?? '-') ?></div></div>
        </div>
      </div>

      <div class="mt-6">
        <h3 class="text-sm font-bold text-gray-800">Project Description</h3>
        <div class="mt-2 text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($team['description'] ?? 'No description available.')) ?></div>
      </div>

      <div class="mt-6">
        <h3 class="text-sm font-bold text-gray-800">Assigned Students</h3>
        <?php if (empty($students)): ?>
          <p class="text-sm text-gray-500 mt-2">No students assigned.</p>
        <?php else: ?>
          <ul class="mt-2 divide-y divide-gray-100 bg-gray-50 rounded-lg overflow-hidden">
            <?php foreach ($students as $st): ?>
              <li class="px-4 py-3 flex items-center justify-between text-sm">
                <div>
                  <div class="font-semibold"><?= htmlspecialchars($st['full_name']) ?></div>
                  <div class="text-xs text-gray-500"><?= htmlspecialchars($st['email']) ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="mt-6 text-right">
        <a href="mentor_dashboard.php" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Close</a>
      </div>
    </div>
  </main>
</body>
</html>
