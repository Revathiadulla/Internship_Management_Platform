<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$redirect_base = 'student_dashboard.php?section=daily_logs';

// ── Handle POST (update) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_id          = intval($_POST['log_id'] ?? 0);
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $time_spent      = floatval($_POST['time_spent'] ?? 0);
    $focus_level     = trim($_POST['focus_level'] ?? '');
    $issues_faced    = trim($_POST['issues_faced'] ?? '');
    $next_plan       = trim($_POST['next_plan'] ?? '');

    if ($log_id <= 0 || $tasks_completed === '' || $focus_level === '' || $time_spent <= 0) {
        header("Location: {$redirect_base}&error=" . urlencode('All required fields must be filled.'));
        exit();
    }

    // Update only if log_date = today AND belongs to this student
    $update_stmt = $conn->prepare(
        "UPDATE daily_logs
            SET tasks_completed = ?, time_spent = ?, focus_level = ?, issues_faced = ?, next_plan = ?
          WHERE id = ? AND user_id = ? AND log_date = CURDATE()
          LIMIT 1"
    );
    if (!$update_stmt) {
        header("Location: {$redirect_base}&error=" . urlencode('Unable to process daily log.'));
        exit();
    }

    $update_stmt->bind_param('sdsssis', $tasks_completed, $time_spent, $focus_level, $issues_faced, $next_plan, $log_id, $user_id);

    if (!$update_stmt->execute()) {
        header("Location: {$redirect_base}&error=" . urlencode('Unable to process daily log.'));
        exit();
    }

    if ($update_stmt->affected_rows === 0) {
        header("Location: {$redirect_base}&error=" . urlencode('Log not found or cannot be edited (only today\'s log is editable).'));
        exit();
    }

    $update_stmt->close();
    header("Location: {$redirect_base}&edited=1");
    exit();
}

// ── Handle GET (show edit form) ──────────────────────────────────────────────
$log_id = intval($_GET['id'] ?? 0);
if ($log_id <= 0) {
    header("Location: {$redirect_base}&error=" . urlencode('Invalid log ID.'));
    exit();
}

// Fetch the log – only if it belongs to the student AND is today's log
$fetch_stmt = $conn->prepare(
    "SELECT * FROM daily_logs WHERE id = ? AND user_id = ? AND log_date = CURDATE() LIMIT 1"
);
if (!$fetch_stmt) {
    header("Location: {$redirect_base}&error=" . urlencode('Unable to process daily log.'));
    exit();
}

$fetch_stmt->bind_param('ii', $log_id, $user_id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();
$log = $result->fetch_assoc();
$fetch_stmt->close();

if (!$log) {
    header("Location: {$redirect_base}&error=" . urlencode('Log not found or cannot be edited (only today\'s log is editable).'));
    exit();
}

// Fetch student profile for header display
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile_res = mysqli_query($conn, $profile_sql);
$profile     = mysqli_fetch_assoc($profile_res);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Daily Log — IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">

  <div class="w-full max-w-lg">

    <!-- Back link -->
    <a href="student_dashboard.php?section=daily_logs"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 font-medium mb-4 transition-colors">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Dashboard
    </a>

    <!-- Card -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">

      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">edit_note</span>
        </div>
        <div>
          <h1 class="text-lg font-bold text-slate-800">Edit Daily Log</h1>
          <p class="text-xs text-slate-400"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></p>
        </div>
      </div>

      <!-- Form -->
      <form method="POST" action="edit_daily_log.php" class="space-y-4">
        <input type="hidden" name="log_id" value="<?php echo intval($log['id']); ?>">

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tasks Completed <span class="text-red-500">*</span></label>
          <textarea name="tasks_completed" rows="4" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
            placeholder="Describe what you worked on today…"><?php echo htmlspecialchars($log['tasks_completed']); ?></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time Spent (hours) <span class="text-red-500">*</span></label>
          <input type="number" name="time_spent" min="0.5" max="12" step="0.5" required
            value="<?php echo htmlspecialchars($log['time_spent']); ?>"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-colors"
            placeholder="e.g. 4">
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Focus Level <span class="text-red-500">*</span></label>
          <select name="focus_level" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-colors bg-white">
            <option value="">Select focus level</option>
            <option value="High"   <?php echo ($log['focus_level'] === 'High')   ? 'selected' : ''; ?>>High</option>
            <option value="Medium" <?php echo ($log['focus_level'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
            <option value="Low"    <?php echo ($log['focus_level'] === 'Low')    ? 'selected' : ''; ?>>Low</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Issues Faced <span class="text-slate-400 font-normal">(optional)</span></label>
          <textarea name="issues_faced" rows="2"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
            placeholder="Any blockers or challenges?"><?php echo htmlspecialchars($log['issues_faced'] ?? ''); ?></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Next Day Plan <span class="text-slate-400 font-normal">(optional)</span></label>
          <textarea name="next_plan" rows="2"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
            placeholder="What will you work on tomorrow?"><?php echo htmlspecialchars($log['next_plan'] ?? ''); ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit"
            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm">
            Update Log
          </button>
          <a href="student_dashboard.php?section=daily_logs"
            class="flex-1 text-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
