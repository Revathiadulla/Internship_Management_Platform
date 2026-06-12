<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';
include_once __DIR__ . '/../includes/mail_helper.php';
require_role('mentor');

$mentor_id = current_user_id();
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$success_msg = trim($_GET['success_msg'] ?? '');
$error_msg = trim($_GET['error_msg'] ?? '');

if ($mentor_id <= 0 || $team_id <= 0) {
    die('Invalid request');
}

header('Location: students.php?team_id=' . $team_id);
exit();

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS team_discussion_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role VARCHAR(20) NOT NULL DEFAULT 'mentor',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(team_id),
    INDEX(sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$team_stmt = $conn->prepare("SELECT t.id, t.team_name, t.internship_id, t.status AS team_status, t.mentor_id, i.title, i.project_type, i.project_subtype, i.description, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name, u.email AS mentor_email FROM project_teams t LEFT JOIN internships i ON t.internship_id = i.id LEFT JOIN users u ON t.mentor_id = u.id WHERE t.id = ? AND t.mentor_id = ? LIMIT 1");
$team_stmt->bind_param('ii', $team_id, $mentor_id);
$team_stmt->execute();
$team = $team_stmt->get_result()->fetch_assoc();
$team_stmt->close();

if (!$team) {
    die('Team not found or access denied');
}

$students = [];
$students_stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, a.id AS app_id, COALESCE(a.status, 'Active Intern') AS app_status FROM project_team_members ptm JOIN users u ON ptm.student_id = u.id LEFT JOIN internship_applications a ON a.id = (SELECT id FROM internship_applications WHERE user_id = u.id ORDER BY (internship_id = ?) DESC, id DESC LIMIT 1) WHERE ptm.project_team_id = ? ORDER BY u.full_name ASC");
$students_stmt->bind_param('ii', $team['internship_id'], $team_id);
$students_stmt->execute();
$students_res = $students_stmt->get_result();
while ($row = $students_res->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_phase') {
        $new_phase = trim($_POST['phase_status'] ?? 'Active');
        $update_stmt = $conn->prepare("UPDATE project_teams SET status = ? WHERE id = ? AND mentor_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param('sii', $new_phase, $team_id, $mentor_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        if ($team['internship_id'] > 0) {
            $app_update_stmt = $conn->prepare("UPDATE internship_applications SET status = ? WHERE internship_id = ? AND user_id IN (SELECT student_id FROM project_team_members WHERE project_team_id = ?)");
            if ($app_update_stmt) {
                $app_update_stmt->bind_param('sii', $new_phase, $team['internship_id'], $team_id);
                $app_update_stmt->execute();
                $app_update_stmt->close();
            }
        }
        header('Location: view_project.php?team_id=' . $team_id . '&success_msg=' . urlencode('Phase updated successfully.'));
        exit();
    } elseif ($action === 'post_update') {
        $message_text = trim($_POST['team_message'] ?? '');
        if ($message_text !== '') {
            $insert_stmt = $conn->prepare("INSERT INTO team_discussion_messages (team_id, sender_id, sender_role, message) VALUES (?, ?, 'mentor', ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param('iis', $team_id, $mentor_id, $message_text);
                $insert_stmt->execute();
                $insert_stmt->close();
            }

            foreach ($students as $student) {
                $student_id = intval($student['id'] ?? 0);
                if ($student_id > 0) {
                    $notif_msg = 'A mentor update was posted for team ' . htmlspecialchars($team['team_name'] ?? 'your team') . ': ' . mb_substr($message_text, 0, 120);
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'student', 'Mentor update', ?, 'info', ?)");
                    $notif_link = 'view_project.php?team_id=' . $team_id;
                    $notif_stmt->bind_param('iss', $student_id, $notif_msg, $notif_link);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            }
            header('Location: view_project.php?team_id=' . $team_id . '&success_msg=' . urlencode('Team update posted successfully.'));
            exit();
        }
        header('Location: view_project.php?team_id=' . $team_id . '&error_msg=' . urlencode('Please enter an announcement before posting.'));
        exit();
    } elseif ($action === 'review_log') {
        $log_id = intval($_POST['log_id'] ?? 0);
        $review_status = trim($_POST['review_status'] ?? 'approved');
        $reviewer_remarks = trim($_POST['reviewer_remarks'] ?? '');
        if ($log_id > 0) {
            $reviewed = ($review_status === 'changes_requested') ? 0 : 1;
            $review_stmt = $conn->prepare("UPDATE daily_logs SET is_reviewed = ?, review_status = ?, reviewer_remarks = ?, reviewed_at = NOW() WHERE id = ? AND user_id IN (SELECT student_id FROM project_team_members WHERE project_team_id = ?)");
            if ($review_stmt) {
                $review_stmt->bind_param('issii', $reviewed, $review_status, $reviewer_remarks, $log_id, $team_id);
                $review_stmt->execute();
                $review_stmt->close();
            }
            header('Location: view_project.php?team_id=' . $team_id . '&success_msg=' . urlencode('Daily log review saved.'));
            exit();
        }
        header('Location: view_project.php?team_id=' . $team_id . '&error_msg=' . urlencode('Please choose a log entry to review.'));
        exit();
    } elseif ($action === 'add_feedback') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');
        $phase = trim($_POST['phase'] ?? '');
        if ($student_id && $internship_id && $rating > 0 && $phase !== '') {
            $mentor_name = $_SESSION['full_name'] ?? 'Mentor';
            $feedback_title = 'Review - ' . $phase;
            $stmt = $conn->prepare("INSERT INTO mentor_feedback (mentor_id, student_id, internship_id, rating, comments, phase, user_id, feedback_title, given_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('iiisssiss', $mentor_id, $student_id, $internship_id, $rating, $comments, $phase, $student_id, $feedback_title, $mentor_name);
            $stmt->execute();
            $stmt->close();
            sendEmailNotification('admin', 'New Mentor Feedback', "Mentor submitted feedback for student $student_id.", ['mentor_id' => $mentor_id]);
            header('Location: view_project.php?team_id=' . $team_id . '&success_msg=' . urlencode('Feedback submitted successfully.'));
            exit();
        }
        header('Location: view_project.php?team_id=' . $team_id . '&error_msg=' . urlencode('Please complete the feedback form.'));
        exit();
    } elseif ($action === 'raise_dropout') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        if ($student_id && $internship_id && $reason !== '') {
            $app_stmt = $conn->prepare("SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1");
            $app_stmt->bind_param('ii', $student_id, $internship_id);
            $app_stmt->execute();
            $app_res = $app_stmt->get_result();
            $app_row = $app_res->fetch_assoc();
            $app_stmt->close();
            if ($app_row) {
                $application_id = intval($app_row['id']);
                $stmt = $conn->prepare("INSERT INTO dropout_requests (application_id, mentor_id, reason, remarks, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                $stmt->bind_param('iiss', $application_id, $mentor_id, $reason, $remarks);
                $stmt->execute();
                $stmt->close();
                sendEmailNotification('admin', 'Dropout Request Raised', "Mentor raised dropout request for application $application_id.", ['mentor_id' => $mentor_id]);
                header('Location: view_project.php?team_id=' . $team_id . '&success_msg=' . urlencode('Dropout request submitted successfully.'));
                exit();
            }
        }
        header('Location: view_project.php?team_id=' . $team_id . '&error_msg=' . urlencode('Please complete the dropout request form.'));
        exit();
    }
}

$logs = [];
$logs_stmt = $conn->prepare("SELECT dl.id, dl.user_id, dl.log_date, dl.tasks_completed, dl.time_spent, dl.focus_level, dl.issues_faced, dl.next_plan, dl.is_reviewed, dl.review_status, dl.reviewer_remarks, u.full_name FROM daily_logs dl JOIN users u ON dl.user_id = u.id JOIN project_team_members ptm ON ptm.student_id = dl.user_id WHERE ptm.project_team_id = ? ORDER BY dl.log_date DESC, dl.id DESC LIMIT 10");
$logs_stmt->bind_param('i', $team_id);
$logs_stmt->execute();
$logs_res = $logs_stmt->get_result();
while ($log_row = $logs_res->fetch_assoc()) {
    $logs[] = $log_row;
}
$logs_stmt->close();

$feedback = [];
$feedback_stmt = $conn->prepare("SELECT mf.id, mf.rating, mf.comments, mf.phase, mf.feedback_title, mf.given_by, mf.created_at, u.full_name AS student_name FROM mentor_feedback mf LEFT JOIN users u ON mf.student_id = u.id WHERE mf.mentor_id = ? AND mf.internship_id = ? ORDER BY mf.created_at DESC LIMIT 10");
$feedback_stmt->bind_param('ii', $mentor_id, intval($team['internship_id'] ?? 0));
$feedback_stmt->execute();
$feedback_res = $feedback_stmt->get_result();
while ($fb_row = $feedback_res->fetch_assoc()) {
    $feedback[] = $fb_row;
}
$feedback_stmt->close();

$messages = [];
$messages_stmt = $conn->prepare("SELECT tdm.id, tdm.message, tdm.created_at, tdm.sender_role, u.full_name AS sender_name FROM team_discussion_messages tdm LEFT JOIN users u ON tdm.sender_id = u.id WHERE tdm.team_id = ? ORDER BY tdm.created_at ASC");
$messages_stmt->bind_param('i', $team_id);
$messages_stmt->execute();
$messages_res = $messages_stmt->get_result();
while ($msg_row = $messages_res->fetch_assoc()) {
    $messages[] = $msg_row;
}
$messages_stmt->close();

$current_phase = trim($team['team_status'] ?? 'Active');
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
$phase_progress = $phase_progress_map[$current_phase] ?? 45;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Mentor Workspace - <?= htmlspecialchars($team['team_name'] ?? 'Project') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial}</style>
</head>
<body class="bg-slate-50 text-slate-900">
  <main class="max-w-6xl mx-auto p-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-600">Mentor Workspace</p>
          <h1 class="text-2xl font-bold mt-1"><?= htmlspecialchars($team['title'] ?: 'Project') ?></h1>
          <p class="text-sm text-slate-500 mt-1">Team: <?= htmlspecialchars($team['team_name'] ?: 'Project Team') ?></p>
        </div>
        <div class="text-sm text-slate-500">
          <a href="dashboard.php" class="text-blue-600 font-semibold">← Back to dashboard</a>
        </div>
      </div>

      <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        Coordinator-managed assignments remain the source of truth for project title, team members, and project allocation. Mentors can review progress, logs, feedback, and share updates.
      </div>

      <?php if ($success_msg !== ''): ?>
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700"><?= htmlspecialchars($success_msg) ?></div>
      <?php endif; ?>
      <?php if ($error_msg !== ''): ?>
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?= htmlspecialchars($error_msg) ?></div>
      <?php endif; ?>

      <div class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <div class="space-y-6">
          <div class="rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-800">Project Details</h2>
              <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700"><?= htmlspecialchars($current_phase) ?></span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Project Subtype</p>
                  <p class="font-semibold text-slate-700"><?= htmlspecialchars($team['project_subtype'] ?: 'General') ?></p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Technology Stack</p>
                  <p class="font-semibold text-slate-700"><?= htmlspecialchars($team['technology_stack'] ?: 'Not listed') ?></p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Duration</p>
                  <p class="font-semibold text-slate-700"><?= htmlspecialchars($team['duration'] ?: '-') ?></p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Current Phase</p>
                  <p class="font-semibold text-slate-700"><?= htmlspecialchars($current_phase) ?></p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Phase Progress</p>
                  <div class="mt-1 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-600" style="width: <?= intval($phase_progress) ?>%"></div>
                  </div>
                  <p class="text-xs font-semibold text-slate-500 mt-1"><?= intval($phase_progress) ?>%</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-400">Assigned Mentor</p>
                  <p class="font-semibold text-slate-700"><?= htmlspecialchars($team['mentor_name'] ?? '-') ?></p>
                </div>
              </div>
            </div>
            <div class="mt-5">
              <p class="text-xs uppercase tracking-wide text-slate-400">Project Description</p>
              <p class="mt-2 text-sm text-slate-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($team['description'] ?? 'No description available.')) ?></p>
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-800">Team Members</h2>
              <span class="text-xs font-semibold text-slate-500"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (empty($students)): ?>
              <p class="mt-3 text-sm text-slate-500">No students have been assigned to this team yet.</p>
            <?php else: ?>
              <div class="mt-4 space-y-3">
                <?php foreach ($students as $student): ?>
                  <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-3">
                    <div>
                      <p class="font-semibold text-slate-800"><?= htmlspecialchars($student['full_name']) ?></p>
                      <p class="text-xs text-slate-500"><?= htmlspecialchars($student['email']) ?></p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"><?= htmlspecialchars($student['app_status'] ?? 'Active Intern') ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-800">Daily Logs</h2>
              <a href="daily_logs.php" class="text-sm font-semibold text-blue-600">Open review center</a>
            </div>
            <?php if (empty($logs)): ?>
              <p class="mt-3 text-sm text-slate-500">No logs submitted for this team yet.</p>
            <?php else: ?>
              <div class="mt-4 space-y-3">
                <?php foreach ($logs as $log): ?>
                  <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($log['full_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= date('M d, Y', strtotime($log['log_date'])) ?></p>
                      </div>
                      <span class="rounded-full <?= ($log['review_status'] === 'changes_requested') ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?> px-2.5 py-1 text-xs font-semibold">
                        <?= htmlspecialchars($log['review_status'] ?: 'Pending') ?>
                      </span>
                    </div>
                    <div class="mt-2 text-sm text-slate-600">
                      <p><span class="font-semibold">Tasks:</span> <?= htmlspecialchars($log['tasks_completed'] ?: '—') ?></p>
                      <p class="mt-1"><span class="font-semibold">Focus:</span> <?= htmlspecialchars($log['focus_level'] ?: '—') ?></p>
                    </div>
                    <form method="post" class="mt-3 space-y-2">
                      <input type="hidden" name="action" value="review_log">
                      <input type="hidden" name="log_id" value="<?= intval($log['id']) ?>">
                      <div class="grid gap-2 md:grid-cols-[auto_1fr]">
                        <select name="review_status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                          <option value="approved" <?= ($log['review_status'] === 'approved') ? 'selected' : '' ?>>Approve</option>
                          <option value="changes_requested" <?= ($log['review_status'] === 'changes_requested') ? 'selected' : '' ?>>Request changes</option>
                        </select>
                        <textarea name="reviewer_remarks" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Add review feedback"></textarea>
                      </div>
                      <div class="text-right">
                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Save Review</button>
                      </div>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-xl border border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-800">Update Phase / Status</h2>
            <form method="post" class="mt-3 space-y-3">
              <input type="hidden" name="action" value="update_phase">
              <label class="block text-sm font-medium text-slate-700">Current phase</label>
              <select name="phase_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="Planning" <?= $current_phase === 'Planning' ? 'selected' : '' ?>>Planning</option>
                <option value="Active" <?= $current_phase === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="In Progress" <?= $current_phase === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Mid Review" <?= $current_phase === 'Mid Review' ? 'selected' : '' ?>>Mid Review</option>
                <option value="Final Review" <?= $current_phase === 'Final Review' ? 'selected' : '' ?>>Final Review</option>
                <option value="Completed" <?= $current_phase === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Paused" <?= $current_phase === 'Paused' ? 'selected' : '' ?>>Paused</option>
              </select>
              <p class="text-xs text-slate-500">This updates the team review status for the workspace and the linked student applications.</p>
              <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Save Status</button>
            </form>
          </div>

          <div class="rounded-xl border border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-800">Mentor Feedback</h2>
            <form method="post" class="mt-3 space-y-3">
              <input type="hidden" name="action" value="add_feedback">
              <input type="hidden" name="internship_id" value="<?= intval($team['internship_id'] ?? 0) ?>">
              <select name="student_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Select student</option>
                <?php foreach ($students as $student): ?>
                  <option value="<?= intval($student['id']) ?>"><?= htmlspecialchars($student['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="grid grid-cols-2 gap-3">
                <input type="number" name="rating" min="1" max="5" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Rating /5">
                <input type="text" name="phase" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Phase">
              </div>
              <textarea name="comments" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Add review comments"></textarea>
              <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Submit Feedback</button>
            </form>
            <?php if (empty($feedback)): ?>
              <p class="mt-4 text-sm text-slate-500">No feedback submitted for this team yet.</p>
            <?php else: ?>
              <div class="mt-4 space-y-3">
                <?php foreach ($feedback as $item): ?>
                  <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2">
                      <p class="font-semibold text-slate-800"><?= htmlspecialchars($item['student_name'] ?: 'Student') ?></p>
                      <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⭐ <?= intval($item['rating']) ?>/5</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($item['phase'] ?: 'Review') ?> • <?= date('M d, Y', strtotime($item['created_at'])) ?></p>
                    <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($item['comments'] ?: 'No comments provided.') ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="rounded-xl border border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-800">Request Dropout</h2>
            <form method="post" class="mt-3 space-y-3">
              <input type="hidden" name="action" value="raise_dropout">
              <input type="hidden" name="internship_id" value="<?= intval($team['internship_id'] ?? 0) ?>">
              <select name="student_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Select student</option>
                <?php foreach ($students as $student): ?>
                  <option value="<?= intval($student['id']) ?>"><?= htmlspecialchars($student['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <textarea name="reason" rows="2" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Reason for dropout request"></textarea>
              <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Optional notes"></textarea>
              <button type="submit" class="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white">Submit Request</button>
            </form>
          </div>
        </div>
      </div>

      <div class="mt-6 rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-800">Team Announcements / Discussion</h2>
          <span class="text-xs font-semibold text-slate-500"><?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?></span>
        </div>
        <form method="post" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="post_update">
          <textarea name="team_message" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Share a team update, reminder, or announcement"></textarea>
          <div class="text-right">
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Post Update</button>
          </div>
        </form>
        <?php if (empty($messages)): ?>
          <p class="mt-4 text-sm text-slate-500">No team updates yet. Share the first update with the group.</p>
        <?php else: ?>
          <div class="mt-4 space-y-3">
            <?php foreach ($messages as $message): ?>
              <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold text-slate-800"><?= htmlspecialchars($message['sender_name'] ?: 'Mentor') ?></p>
                  <p class="text-xs text-slate-500"><?= date('M d, Y H:i', strtotime($message['created_at'])) ?></p>
                </div>
                <p class="mt-2 text-sm text-slate-600 whitespace-pre-wrap"><?= htmlspecialchars($message['message']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
