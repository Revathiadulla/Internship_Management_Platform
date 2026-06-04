<?php
session_start();
include "db.php";
include "status_utils.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile_result = mysqli_query($conn, $profile_sql);
$profile = mysqli_fetch_assoc($profile_result);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}

$app_sql = "SELECT a.id AS app_id,
                   COALESCE(i.title, a.internship_name) AS title,
                   a.status,
                   a.verification_status,
                   a.applied_date,
                   a.education_status,
                   i.project_type,
                   i.project_subtype,
                   (SELECT CONCAT(new_status, ' — ', DATE_FORMAT(created_at, '%b %d, %Y'))
                    FROM application_status_history
                    WHERE application_id = a.id
                    ORDER BY created_at DESC
                    LIMIT 1) AS latest_pipeline_update
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.user_id = '$user_id'
            ORDER BY a.applied_date DESC";
$app_result = mysqli_query($conn, $app_sql);
$applications = [];
while ($row = mysqli_fetch_assoc($app_result)) {
    $applications[] = $row;
}

function getProgressSteps() {
    return [
        ['status' => 'Applied', 'label' => 'Applied', 'icon' => 'send'],
        ['status' => 'Assessment', 'label' => 'Assessment', 'icon' => 'quiz'],
        ['status' => 'HR Review', 'label' => 'HR Review', 'icon' => 'manage_search'],
        ['status' => 'Interview', 'label' => 'Interview', 'icon' => 'event'],
        ['status' => 'Approved', 'label' => 'Approved', 'icon' => 'check_circle'],
        ['status' => 'Internship Started', 'label' => 'Started', 'icon' => 'play_circle'],
        ['status' => 'Rejected', 'label' => 'Rejected', 'icon' => 'cancel'],
    ];
}

function getStepState($current_status, $step_status) {
    $current = strtolower($current_status);
    $step = strtolower($step_status);

    if ($current === 'rejected') {
        if ($step === 'rejected') {
            return 'rejected';
        }
        return 'pending';
    }

    $statuses = array_map('strtolower', array_column(getProgressSteps(), 'status'));
    $current_index = array_search($current, $statuses, true);
    $step_index = array_search($step, $statuses, true);

    if ($step === $current) {
        return 'current';
    }
    if ($current_index !== false && $step_index !== false && $step_index < $current_index) {
        return 'completed';
    }
    return 'pending';
}

function formatDate($value) {
    if (empty($value)) {
        return 'N/A';
    }
    return date('M d, Y', strtotime($value));
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>My Applications - IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    .step-line { height: 2px; background: #e2e8f0; flex: 1; }
    .step-dot { width: 1.5rem; height: 1.5rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; border: 2px solid #cbd5e1; background: white; }
    .step-dot.completed { background: #0ea5e9; border-color: #0ea5e9; color: white; }
    .step-dot.current { background: #2563eb; border-color: #2563eb; color: white; }
    .step-dot.rejected { background: #ef4444; border-color: #ef4444; color: white; }
    .step-label { font-size: 0.78rem; color: #475569; }
    .step-label.completed, .step-label.current { color: #0f172a; font-weight: 700; }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#111827] antialiased">
  <main class="min-h-screen px-6 py-8">
    <div class="max-w-7xl mx-auto space-y-8">
      <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Student Application Tracking</p>
            <h1 class="mt-2 text-3xl font-extrabold text-slate-900">My Applications</h1>
            <p class="mt-2 text-sm text-slate-600">View your internship applications, current status, verification state, and latest pipeline progress.</p>
          </div>
          <div class="flex items-center gap-3 text-sm text-slate-500">
            <span class="material-symbols-outlined">account_circle</span>
            <div>
              <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($profile['full_name']); ?></p>
              <p>Student account</p>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($applications)): ?>
        <div class="rounded-[2rem] bg-white border border-dashed border-slate-300 p-10 text-center text-slate-600 shadow-sm">
          <p class="text-lg font-semibold text-slate-900">No applications found.</p>
          <p class="mt-2">Submit an application from the internship list to start tracking your progress.</p>
        </div>
      <?php else: ?>
        <div class="grid gap-6">
          <?php foreach ($applications as $app): ?>
            <?php
              $badgeClass = getStatusBadgeClass($app['status']);
              $verificationClass = getVerificationBadgeClass($app['verification_status']);
              $steps = getProgressSteps();
            ?>
            <article class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                  <?php
                    $is_selected_or_approved = in_array($app['status'], ['Selected', 'Started', 'Internship Started', 'Active Intern']);
                    $display_title = $is_selected_or_approved 
                        ? $app['title'] 
                        : (!empty($app['project_type']) && !empty($app['project_subtype']) 
                            ? $app['project_type'] . ' - ' . $app['project_subtype'] 
                            : (!empty($app['project_subtype']) 
                                ? $app['project_subtype'] 
                                : (!empty($app['project_type']) 
                                    ? $app['project_type'] 
                                    : $app['title'])));
                  ?>
                  <h2 class="text-2xl font-semibold text-slate-900"><?php echo htmlspecialchars($display_title ?: 'Untitled Internship'); ?></h2>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-sm font-semibold <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                    <span class="rounded-full border px-3 py-1 text-sm font-semibold <?php echo $verificationClass; ?>"><?php echo htmlspecialchars($app['verification_status']); ?></span>
                  </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 text-sm text-slate-600">
                  <div class="rounded-3xl bg-slate-50 border border-slate-200 px-4 py-3">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Applied Date</p>
                    <p class="mt-2 font-semibold text-slate-900"><?php echo formatDate($app['applied_date']); ?></p>
                  </div>
                  <div class="rounded-3xl bg-slate-50 border border-slate-200 px-4 py-3">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Latest Pipeline Update</p>
                    <p class="mt-2 font-semibold text-slate-900"><?php echo htmlspecialchars($app['latest_pipeline_update'] ?: 'No updates yet'); ?></p>
                  </div>
                </div>
              </div>

              <div class="mt-6 space-y-4">
                <div class="flex items-center gap-2 text-sm text-slate-500">
                  <span class="material-symbols-outlined">timeline</span>
                  <span>Application progress</span>
                </div>
                <div class="space-y-4">
                  <?php foreach ($steps as $step): ?>
                    <?php $state = getStepState($app['status'], $step['status']); ?>
                    <div class="flex items-start gap-4">
                      <div class="flex flex-col items-center">
                        <div class="step-dot <?php echo $state; ?>">
                          <span class="material-symbols-outlined text-[14px]"><?php echo $step['icon']; ?></span>
                        </div>
                        <?php if ($step !== end($steps)): ?>
                          <div class="mt-1 h-8 w-px bg-slate-200"></div>
                        <?php endif; ?>
                      </div>
                      <div class="flex-1">
                        <div class="flex items-center justify-between gap-3">
                          <span class="step-label <?php echo $state === 'completed' || $state === 'current' ? 'text-slate-900' : ''; ?>"><?php echo htmlspecialchars($step['label']); ?></span>
                          <?php if ($state === 'completed'): ?>
                            <span class="text-[11px] uppercase tracking-[0.24em] text-emerald-600 font-semibold">Done</span>
                          <?php elseif ($state === 'current'): ?>
                            <span class="text-[11px] uppercase tracking-[0.24em] text-sky-600 font-semibold">Current</span>
                          <?php elseif ($state === 'rejected'): ?>
                            <span class="text-[11px] uppercase tracking-[0.24em] text-red-600 font-semibold">Rejected</span>
                          <?php else: ?>
                            <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400 font-semibold">Pending</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
