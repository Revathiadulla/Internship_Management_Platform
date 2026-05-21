<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student profile
$sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $sql);
$profile = mysqli_fetch_assoc($result);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}

// Fetch active started internship
$active_sql = "SELECT a.id as app_id, 
                      COALESCE(i.id, 0) as internship_id,
                      COALESCE(i.title, a.internship_name) as title,
                      COALESCE(i.duration, '') as duration,
                      COALESCE(i.mode, '') as mode,
                      a.status, a.applied_date 
               FROM internship_applications a 
               LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
               WHERE a.user_id = '$user_id' AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern') 
               LIMIT 1";
$active_result = mysqli_query($conn, $active_sql);
$has_active = mysqli_num_rows($active_result) > 0;
$active_intern = mysqli_fetch_assoc($active_result);

// Derive company name and domain dynamically if active
if ($has_active) {
    $active_title = strtolower($active_intern['title']);
    $active_domain = "General Aptitude";
    if (strpos($active_title, 'frontend') !== false || strpos($active_title, 'react') !== false || strpos($active_title, 'web') !== false) {
        $active_domain = "Frontend Development";
    } elseif (strpos($active_title, 'data') !== false || strpos($active_title, 'python') !== false || strpos($active_title, 'sql') !== false || strpos($active_title, 'science') !== false) {
        $active_domain = "Data Science";
    } elseif (strpos($active_title, 'ui') !== false || strpos($active_title, 'ux') !== false || strpos($active_title, 'design') !== false) {
        $active_domain = "UI/UX Design";
    } elseif (strpos($active_title, 'backend') !== false || strpos($active_title, 'node') !== false || strpos($active_title, 'php') !== false || strpos($active_title, 'database') !== false) {
        $active_domain = "Backend Development";
    }
    
    $active_intern['domain'] = $active_domain;
    $active_intern['company_name'] = "TechCorp Innovations";
}

if (!$has_active) {
    header("Location: student_dashboard.php?err=" . urlencode("You must start an internship before logging daily activity."));
    exit();
}

// Ensure daily_logs table exists
$log_table_sql = "CREATE TABLE IF NOT EXISTS daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    internship_id INT NOT NULL,
    tasks_completed TEXT NOT NULL,
    time_spent DECIMAL(4,2) NOT NULL,
    focus_level VARCHAR(50) NOT NULL,
    issues_faced TEXT DEFAULT NULL,
    next_plan TEXT DEFAULT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $log_table_sql);

// Handle Log Submission
$success_msg = "";
$error_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_log'])) {
    $tasks = mysqli_real_escape_string($conn, $_POST['tasks_completed']);
    $hours = floatval($_POST['time_spent']);
    $focus = mysqli_real_escape_string($conn, $_POST['focus_level']);
    $issues = mysqli_real_escape_string($conn, $_POST['issues_faced']);
    $next_plan = mysqli_real_escape_string($conn, $_POST['next_plan']);
    $log_date = mysqli_real_escape_string($conn, $_POST['log_date']);
    $intern_id = $active_intern['internship_id'];

    if (empty($tasks) || $hours <= 0) {
        $error_msg = "Please fill in all required fields and enter valid hours.";
    } else {
        $insert_sql = "INSERT INTO daily_logs (user_id, internship_id, tasks_completed, time_spent, focus_level, issues_faced, next_plan, log_date) 
                       VALUES ('$user_id', '$intern_id', '$tasks', '$hours', '$focus', '$issues', '$next_plan', '$log_date')";
        if (mysqli_query($conn, $insert_sql)) {
            // Send email notification for daily progress log
            $log_subject = "IMP Daily Progress Log Submitted - " . date('M d, Y', strtotime($log_date));
            $log_message = "Dear " . ($profile['full_name'] ?? 'Student') . ",\n\nYour daily activity log for **$log_date** has been successfully recorded under the internship \"" . $active_intern['title'] . "\".\n\nLog Details:\n- Time Spent: **$hours hours**\n- Focus Level: **$focus**\n- Milestones Completed:\n$tasks\n\n" . 
                           (!empty($issues) ? "- Reported Blockers/Issues:\n$issues\n\n" : "") . 
                           "Your mentors have been notified to review your logs. Keep up the consistent work!";
            sendEmailNotification($user_id, $log_subject, $log_message, [
                'event' => 'Daily Progress Log',
                'internship_title' => $active_intern['title'],
                'log_date' => $log_date,
                'hours_spent' => "$hours hrs",
                'focus_level' => $focus,
                'action_url' => 'http://localhost/IMP/student_daily_log.php',
                'action_label' => 'View Activity Timeline'
            ]);

            header("Location: student_daily_log.php?msg=" . urlencode("Daily log submitted successfully!"));
            exit();
        } else {
            $error_msg = "Error submitting daily log: " . mysqli_error($conn);
        }
    }
}

// Fetch unread notifications count
$unread_sql = "SELECT COUNT(*) as count FROM student_notifications WHERE user_id = '$user_id' AND is_read = 0";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? $unread_row['count'] : 0;

// Fetch all logs submitted for active internship
$logs_sql = "SELECT * FROM daily_logs 
             WHERE user_id = '$user_id' AND internship_id = '{$active_intern['internship_id']}' 
             ORDER BY log_date DESC";
$logs_result = mysqli_query($conn, $logs_sql);
$all_logs = [];
$total_hours = 0.0;
while($row = mysqli_fetch_assoc($logs_result)) {
    $all_logs[] = $row;
    $total_hours += floatval($row['time_spent']);
}

// Format duration or current progress metrics
$date_start = new DateTime($active_intern['applied_date']);
$date_today = new DateTime();
$days_active = $date_start->diff($date_today)->days + 1;
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#191c1d] font-sans antialiased">

  <?php if (isset($_GET['msg'])): ?>
      <!-- Success Toast Notification -->
      <div id="success-toast" class="fixed top-6 right-6 z-50 bg-green-600 text-white rounded-2xl shadow-xl px-5 py-4 border border-green-500/30 flex items-center gap-3.5 transform translate-x-[400px] transition-transform duration-500 ease-out select-none">
          <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px] font-bold">check_circle</span>
          </div>
          <div>
              <p class="text-xs text-green-100 font-bold uppercase tracking-wider">Success</p>
              <p class="text-sm font-bold tracking-tight mt-0.5"><?php echo htmlspecialchars($_GET['msg']); ?></p>
          </div>
          <button onclick="document.getElementById('success-toast').remove()" class="p-1 hover:bg-white/10 rounded transition-colors ml-4">
              <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
      </div>
      <script>
          document.addEventListener('DOMContentLoaded', () => {
              const toast = document.getElementById('success-toast');
              setTimeout(() => {
                  toast.classList.remove('translate-x-[400px]');
              }, 100);
              setTimeout(() => {
                  if (toast) {
                      toast.classList.add('translate-x-[400px]');
                      setTimeout(() => {
                          toast.remove();
                      }, 500);
                  }
              }, 4000);
          });
      </script>
  <?php endif; ?>

  <!-- SideNavBar -->
  <aside class="fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col py-6 shadow-sm">
    <div class="px-6 mb-8">
      <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
        <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="32" height="32" rx="8" fill="currentColor"/>
          <circle cx="16" cy="16" r="3" fill="white"/>
          <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
          <circle cx="16" cy="8" r="1.5" fill="white"/>
          <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
          <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
          <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
          <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
        </svg>
        <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
      </a>
      <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Student Portal</p>
    </div>
    
    <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
      <?php if ($has_active): ?>
      <a class="flex items-center gap-3 text-gray-600 rounded-lg px-4 py-3 font-medium transition-all hover:bg-gray-50 hover:text-blue-600" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#active-internship-card">
        <span class="material-symbols-outlined">badge</span>
        <span class="text-sm font-medium">My Internship</span>
      </a>
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_daily_log.php">
        <span class="material-symbols-outlined">edit_note</span>
        <span class="text-sm font-medium">Daily Logs</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#assigned-project-card">
        <span class="material-symbols-outlined">terminal</span>
        <span class="text-sm font-medium">Project</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#mentor-feedback-card">
        <span class="material-symbols-outlined">reviews</span>
        <span class="text-sm font-medium">Feedback</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php else: ?>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_browse_internships.php">
        <span class="material-symbols-outlined">work</span>
        <span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_applications.php">
        <span class="material-symbols-outlined">assignment</span>
        <span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
    </nav>
    
    <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">help</span>
        <span class="text-sm font-medium">Help Center</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="login.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Canvas -->
  <div class="pl-64 flex flex-col min-h-screen relative">
    
    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
      <div class="flex items-center gap-4 flex-1">
        <span class="text-xs font-semibold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg shrink-0">Student Workspace</span>
        <div class="relative w-full max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
            <input id="log-search-input" class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-600 focus:bg-white transition-colors shadow-inner" placeholder="Search my daily logs..." type="text">
        </div>
      </div>
      
      <div class="flex items-center gap-6">
        <a href="student_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative block">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($unread_count > 0): ?>
              <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          <?php endif; ?>
        </a>
        <div class="h-8 w-[1px] bg-gray-200"></div>
        
        <!-- Profile Info -->
        <div class="flex items-center gap-3 group select-none p-1 rounded-lg">
          <div class="text-right hidden md:block">
            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($profile['full_name']); ?></p>
            <p class="text-xs text-gray-500">Active Intern</p>
          </div>
          <img alt="User profile" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-8 max-w-7xl w-full mx-auto space-y-8">
      
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Daily Activity Tracker</h1>
        <p class="text-slate-500 mt-2">Log your daily tasks, track working hours, and communicate blockers with your mentors.</p>
      </div>

      <?php if (!empty($error_msg)): ?>
          <div class="bg-red-50 text-red-700 px-4 py-3 rounded-xl border border-red-200 font-semibold text-xs flex items-center gap-2">
              <span class="material-symbols-outlined text-[18px]">error</span>
              <?php echo $error_msg; ?>
          </div>
      <?php endif; ?>

      <!-- Bento Grid Content -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Submit Log Form -->
        <div class="lg:col-span-5 flex flex-col gap-6">
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-6">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                <span class="material-symbols-outlined text-[22px]">edit_note</span>
              </div>
              <h2 class="text-lg font-bold text-slate-800">Submit Daily Activity</h2>
            </div>

            <!-- Internship Reference Details (Read-Only) -->
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 grid grid-cols-2 gap-y-3.5 gap-x-2 text-xs">
                <div>
                  <span class="text-slate-400 block mb-0.5 font-semibold">Active Internship</span>
                  <span class="font-bold text-slate-800 truncate block"><?php echo htmlspecialchars($active_intern['title']); ?></span>
                </div>
                <div>
                  <span class="text-slate-400 block mb-0.5 font-semibold">Company</span>
                  <span class="font-bold text-slate-800 block"><?php echo htmlspecialchars($active_intern['company_name']); ?></span>
                </div>
                <div>
                  <span class="text-slate-400 block mb-0.5 font-semibold">Mentor Assigned</span>
                  <span class="font-bold text-slate-800 block">Dr. Sarah Jenkins</span>
                </div>
                <div>
                  <span class="text-slate-400 block mb-0.5 font-semibold">Active Phase</span>
                  <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px] font-extrabold uppercase">Evaluation</span>
                </div>
            </div>

            <!-- Activity Submission Form -->
            <form action="student_daily_log.php" method="POST" class="space-y-4">
              <div>
                <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Log Date</label>
                <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10">
              </div>

              <div>
                <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Tasks Completed <span class="text-red-500">*</span></label>
                <textarea name="tasks_completed" required class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Describe the milestones or specific tasks you completed today..." rows="3"></textarea>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Time Spent (hrs)</label>
                  <input type="number" name="time_spent" required step="0.5" min="0.5" max="24" value="8.0" class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10">
                </div>
                <div>
                  <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Focus Level</label>
                  <select name="focus_level" class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10">
                    <option value="High Productivity">🔥 High Productivity</option>
                    <option value="Steady Progress" selected>📈 Steady Progress</option>
                    <option value="Learning Phase">🧠 Learning Phase</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Blockers or Issues Faced</label>
                <textarea name="issues_faced" class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="List any technical blocks or issues you hit (optional)..." rows="2"></textarea>
              </div>

              <div>
                <label class="block font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">Next Day's Milestones</label>
                <textarea name="next_plan" class="w-full rounded-xl border-slate-200 text-xs py-2.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Briefly list your roadmap goals for tomorrow..." rows="2"></textarea>
              </div>

              <button type="submit" name="submit_log" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[16px]">send</span>
                Submit Daily Log
              </button>
            </form>
          </div>

          <!-- Quick Stats Cards -->
          <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 rounded-2xl shadow-xl shadow-blue-600/10 relative overflow-hidden">
            <div class="relative z-10 space-y-4">
              <div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-blue-200">Total Internship Hours</span>
                <p class="text-4xl font-black mt-1"><?php echo number_format($total_hours, 1); ?> <span class="text-sm font-medium text-blue-200">Hours</span></p>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-xs bg-white/20 px-3 py-1 rounded-full font-bold">Active for <?php echo $days_active; ?> day(s)</span>
                <span class="text-xs font-semibold text-blue-100">Avg <?php echo $total_hours > 0 && $days_active > 0 ? number_format($total_hours / count($all_logs), 1) : 0; ?> hrs/entry</span>
              </div>
            </div>
            <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-white/[0.08] pointer-events-none select-none">
              <span class="material-symbols-outlined text-[140px]">trending_up</span>
            </div>
          </div>
        </div>

        <!-- Right: Activity Logs Timeline -->
        <div class="lg:col-span-7">
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-full flex flex-col">
            <div class="flex justify-between items-center mb-6">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-600">
                  <span class="material-symbols-outlined text-[22px]">history</span>
                </div>
                <h2 class="text-lg font-bold text-slate-800">Activity Log Timeline</h2>
              </div>
              <span class="text-xs text-slate-400 font-semibold"><?php echo count($all_logs); ?> logs submitted</span>
            </div>

            <!-- Scrollable Timeline -->
            <div class="flex-grow space-y-6 max-h-[80vh] overflow-y-auto pr-2 relative" id="timeline-container">
              
              <?php if (empty($all_logs)): ?>
                  <div class="flex flex-col items-center justify-center p-12 text-center border-2 border-dashed border-slate-100 rounded-2xl space-y-4">
                      <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300">
                        <span class="material-symbols-outlined text-4xl">assignment_late</span>
                      </div>
                      <div>
                        <h3 class="font-bold text-slate-700 text-sm">No Logs Logged Yet</h3>
                        <p class="text-xs text-slate-400 max-w-[240px] mt-1 mx-auto">Fill out the left form to submit your very first activity record today!</p>
                      </div>
                  </div>
              <?php else: ?>
                  
                  <!-- Timeline indicator bar -->
                  <div class="absolute left-5 top-2 bottom-2 w-[1px] bg-slate-100"></div>

                  <?php foreach ($all_logs as $index => $log): ?>
                      <div class="relative pl-12 group timeline-item" data-tasks="<?php echo htmlspecialchars(strtolower($log['tasks_completed'])); ?>">
                        
                        <!-- Timeline Node Dot -->
                        <div class="absolute left-3.5 top-1 w-3.5 h-3.5 rounded-full border-2 border-blue-600 bg-white group-hover:bg-blue-600 transition-colors z-10 shadow-sm"></div>

                        <!-- Card content -->
                        <div class="bg-slate-50/50 hover:bg-slate-50 border border-slate-100 rounded-xl p-4 transition-colors space-y-3">
                          <div class="flex items-start justify-between gap-4">
                            <div>
                              <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></span>
                              <div class="flex items-center gap-2 mt-1">
                                <h4 class="font-bold text-slate-800 text-xs">Day Log Submitted</h4>
                                <span class="px-2 py-0.5 bg-slate-200/50 text-slate-600 font-bold rounded text-[9px]"><?php echo $log['focus_level']; ?></span>
                              </div>
                            </div>
                            <div class="text-right">
                              <span class="text-xs font-extrabold text-slate-700 bg-white px-2 py-1 rounded border border-slate-100 shadow-sm"><?php echo number_format($log['time_spent'], 1); ?> hrs</span>
                            </div>
                          </div>

                          <div class="text-xs space-y-2">
                            <div>
                              <span class="font-bold text-[10px] text-slate-400 uppercase block tracking-wider mb-0.5">Tasks Done:</span>
                              <p class="text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($log['tasks_completed'])); ?></p>
                            </div>

                            <?php if (!empty($log['issues_faced'])): ?>
                                <div class="bg-red-50/50 p-2.5 rounded-lg border border-red-100/50 mt-2">
                                  <span class="font-bold text-[10px] text-red-600 uppercase block tracking-wider mb-0.5">Issues Faced:</span>
                                  <p class="text-red-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($log['issues_faced'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($log['next_plan'])): ?>
                                <div class="bg-blue-50/30 p-2.5 rounded-lg border border-blue-100/30 mt-2">
                                  <span class="font-bold text-[10px] text-blue-600 uppercase block tracking-wider mb-0.5">Next Action Plan:</span>
                                  <p class="text-blue-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($log['next_plan'])); ?></p>
                                </div>
                            <?php endif; ?>
                          </div>
                        </div>

                      </div>
                  <?php endforeach; ?>

              <?php endif; ?>

            </div>

          </div>
        </div>

      </div>

    </main>

  </div>

  <script>
    // Live Search Filter for Logs
    document.getElementById("log-search-input").addEventListener("input", function(e) {
        const query = e.target.value.toLowerCase().trim();
        const items = document.querySelectorAll(".timeline-item");
        
        items.forEach(item => {
            const tasks = item.getAttribute("data-tasks") || "";
            if (query === "" || tasks.includes(query)) {
                item.classList.remove("hidden");
            } else {
                item.classList.add("hidden");
            }
        });
    });
  </script>
</body>
</html>
