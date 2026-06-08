<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";
include_once __DIR__ . "/includes/discontinuation_helpers.php";

// Fetch header user info
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';

// Fetch admin notifications info
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$admin_latest_res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' ORDER BY created_at DESC LIMIT 5");
$admin_latest_notifications = [];
if ($admin_latest_res) {
    while ($row = mysqli_fetch_assoc($admin_latest_res)) {
        $admin_latest_notifications[] = $row;
    }
}

// Calculate metrics
$total_students = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='student'"))['c'] ?? 0);
$total_coordinators = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='coordinator'"))['c'] ?? 0);
$total_mentors = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='mentor'"))['c'] ?? 0);
$total_hr = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='hr'"))['c'] ?? 0);
$total_companies = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='company'"))['c'] ?? 0);
$total_internships = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships"))['c'] ?? 0);
$pending_applications = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internship_applications WHERE status='Applied'"))['c'] ?? 0);
$active_projects = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internship_applications WHERE status IN ('Started','Internship Started','Active Intern','Selected')"))['c'] ?? 0);
$total_daily_logs = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM daily_logs"))['c'] ?? 0);
$total_notifications = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT ((SELECT COUNT(*) FROM student_notifications) + (SELECT COUNT(*) FROM email_notifications_log)) as c"))['c'] ?? 0);

// Calculate active internships count
$status_exists_res = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'status'");
$status_exists = ($status_exists_res && mysqli_num_rows($status_exists_res) > 0);
if ($status_exists) {
    $active_internships = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE LOWER(status) IN ('approved', 'active', 'published') AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0);
} else {
    $active_internships = $total_internships;
}

// Get internship status counts (Active, On Hold, Discontinued, etc.)
$internship_status_counts = get_internship_status_counts($conn);
$pending_reports_count = get_pending_reports_count($conn);

// Fetch recent users for User Management table preview
$user_status_exists_res = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
$user_status_exists = ($user_status_exists_res && mysqli_num_rows($user_status_exists_res) > 0);
$status_select = $user_status_exists ? ", u.status" : "";

$users_res = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.email, u.role" . $status_select . "
    FROM users u
    INNER JOIN (
        SELECT email, MAX(id) AS latest_id
        FROM users
        WHERE role != 'admin'
        GROUP BY email
    ) latest ON u.id = latest.latest_id
    ORDER BY u.id DESC
    LIMIT 5
");
$recent_users = [];
if ($users_res) {
    while ($row = mysqli_fetch_assoc($users_res)) {
        $recent_users[] = $row;
    }
}

// Fetch system activity: recent daily logs & applications
$activities = [];

$activity_res = mysqli_query($conn, "
    SELECT d.tasks_completed, d.created_at, u.full_name, 'log' as type
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.created_at DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($activity_res)) {
    $activities[] = [
        'title' => 'Daily log submitted',
        'desc' => $row['full_name'] . ' logged: "' . mb_strimwidth($row['tasks_completed'], 0, 40, '...') . '"',
        'time' => strtotime($row['created_at']),
        'icon' => 'edit_note',
        'bg' => 'bg-blue-50 text-blue-600 border border-blue-100'
    ];
}

$app_activity_res = mysqli_query($conn, "
    SELECT a.applied_date, u.full_name, COALESCE(i.title, 'Internship') as title, 'app' as type
    FROM internship_applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN internships i ON a.internship_id = i.id
    ORDER BY a.applied_date DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($app_activity_res)) {
    $activities[] = [
        'title' => 'New Application',
        'desc' => $row['full_name'] . ' applied for ' . $row['title'],
        'time' => strtotime($row['applied_date']),
        'icon' => 'assignment',
        'bg' => 'bg-green-50 text-green-600 border border-green-100'
    ];
}

// Sort activities by time DESC
usort($activities, function($a, $b) {
    return $b['time'] - $a['time'];
});
$activities = array_slice($activities, 0, 5);

// Fetch active student count vs total student count
$assigned_pct = $total_students > 0 ? round(($active_internships / $total_students) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Admin Dashboard – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#003ea8",
            "primary-hover": "#002a75",
            surface: "#f8f9fa",
            "surface-container": "#ffffff",
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          }
        }
      }
    };
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
      .stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
      .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200">
  <!-- Top Nav -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-8">
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
      <div class="hidden md:flex gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest border-l border-gray-200 pl-6">
        Platform Administration
      </div>
    </div>
    
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-semibold text-slate-700">System Online</span>
      </div>

      <!-- Theme Switcher -->
      <button id="theme-toggle" class="p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors rounded-full flex items-center justify-center cursor-pointer">
          <span class="material-symbols-outlined text-[20px]" id="theme-toggle-icon">dark_mode</span>
      </button>
      <!-- Notifications Bell -->
      <div class="relative mr-1" id="notifications-container-menu">
          <button id="notifications-menu-button" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative focus:outline-none cursor-pointer flex items-center justify-center">
              <span class="material-symbols-outlined">notifications</span>
              <?php if ($admin_unread_count > 0): ?>
                  <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $admin_unread_count; ?></span>
              <?php endif; ?>
          </button>
          <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
              <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                  <span class="font-bold text-xs text-gray-800">Notifications</span>
                  <?php if ($admin_unread_count > 0): ?>
                      <a href="mark_notification_read.php?action=read_all&redirect=admin_dashboard.php" class="text-[10px] font-bold text-blue-600 hover:text-blue-800">Mark all read</a>
                  <?php endif; ?>
              </div>
              <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                  <?php if (empty($admin_latest_notifications)): ?>
                      <div class="px-4 py-3 text-center text-xs text-gray-400">No notifications.</div>
                  <?php else: ?>
                      <?php foreach ($admin_latest_notifications as $notif): ?>
                          <a href="admin_received_notifications.php" class="block px-4 py-2.5 hover:bg-gray-50 transition-colors">
                              <div class="flex justify-between items-start gap-1">
                                  <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400"><?php echo htmlspecialchars($notif['title']); ?></span>
                                  <?php if (!$notif['is_read']): ?>
                                      <span class="w-1.5 h-1.5 bg-blue-600 rounded-full shrink-0 mt-1"></span>
                                  <?php endif; ?>
                              </div>
                              <p class="text-xs text-gray-700 font-medium truncate mt-0.5" title="<?php echo htmlspecialchars($notif['message']); ?>"><?php echo htmlspecialchars($notif['message']); ?></p>
                              <span class="text-[9px] text-gray-400 mt-1 block"><?php echo date('h:i A, d M', strtotime($notif['created_at'])); ?></span>
                          </a>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
              <div class="border-t border-gray-100 pt-1 text-center">
                  <a href="admin_received_notifications.php" class="block py-2 text-xs font-bold text-blue-600 hover:text-blue-800">View all notifications</a>
              </div>
          </div>
      </div>
      
      <!-- Profile Button -->
      <div class="relative">
        <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
          <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
            <?php echo htmlspecialchars($header_name); ?> (Admin)
          </span>
          <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
            <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
              <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
            <?php else: ?>
              <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
        </button>
        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
          <a href="admin_users.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">manage_accounts</span> Manage Users
          </a>
          <hr class="my-1 border-gray-100">
          <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-500 text-sm mt-1">Complete system control and platform monitoring</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <button onclick="window.location.href='admin_users.php'" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:bg-gray-50 hover:shadow-md transition-all shadow-sm cursor-pointer">
              <span class="material-symbols-outlined text-lg">person_add</span> Manage Users
            </button>
            <button onclick="window.location.href='manual_message.php'" class="bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:bg-blue-700 hover:shadow-md transition-all shadow-sm cursor-pointer">
              <span class="material-symbols-outlined text-lg">chat</span> Send Message
            </button>
          </div>
        </div>

        <!-- Metrics Grid (10 metrics) -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <!-- Students -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-blue-50 p-2.5 rounded-full text-blue-600">
                <span class="material-symbols-outlined">school</span>
              </div>
              <span class="text-blue-600 text-xs font-bold bg-blue-50 px-2 py-0.5 rounded-full">Students</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Total Students</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_students; ?></p>
          </div>
          
          <!-- Coordinators -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-indigo-50 p-2.5 rounded-full text-indigo-600">
                <span class="material-symbols-outlined">manage_accounts</span>
              </div>
              <span class="text-indigo-600 text-xs font-bold bg-indigo-50 px-2 py-0.5 rounded-full">Staff</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Coordinators</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_coordinators; ?></p>
          </div>

          <!-- Mentors -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-purple-50 p-2.5 rounded-full text-purple-600">
                <span class="material-symbols-outlined">co_present</span>
              </div>
              <span class="text-purple-600 text-xs font-bold bg-purple-50 px-2 py-0.5 rounded-full">Guides</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Total Mentors</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_mentors; ?></p>
          </div>

          <!-- HR Users -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-teal-50 p-2.5 rounded-full text-teal-600">
                <span class="material-symbols-outlined">forum</span>
              </div>
              <span class="text-teal-600 text-xs font-bold bg-teal-50 px-2 py-0.5 rounded-full">Recruiters</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">HR Users</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_hr; ?></p>
          </div>

          <!-- Companies -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-orange-50 p-2.5 rounded-full text-orange-600">
                <span class="material-symbols-outlined">business</span>
              </div>
              <span class="text-orange-600 text-xs font-bold bg-orange-50 px-2 py-0.5 rounded-full">Partners</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Total Companies</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_companies; ?></p>
          </div>

          <!-- Internships -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-green-50 p-2.5 rounded-full text-green-600">
                <span class="material-symbols-outlined">work</span>
              </div>
              <span class="text-green-600 text-xs font-bold bg-green-50 px-2 py-0.5 rounded-full">Open</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Internships</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_internships; ?></p>
          </div>

          <!-- Pending Applications -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-amber-50 p-2.5 rounded-full text-amber-600">
                <span class="material-symbols-outlined">pending_actions</span>
              </div>
              <span class="text-amber-600 text-xs font-bold bg-amber-50 px-2 py-0.5 rounded-full">Review</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Pending Apps</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $pending_applications; ?></p>
          </div>

          <!-- Active Projects -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-pink-50 p-2.5 rounded-full text-pink-600">
                <span class="material-symbols-outlined">trending_up</span>
              </div>
              <span class="text-pink-600 text-xs font-bold bg-pink-50 px-2 py-0.5 rounded-full">Live</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Active Projects</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $active_projects; ?></p>
          </div>

          <!-- Daily Logs -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-teal-50 p-2.5 rounded-full text-teal-600">
                <span class="material-symbols-outlined">history_edu</span>
              </div>
              <span class="text-teal-600 text-xs font-bold bg-teal-50 px-2 py-0.5 rounded-full">Entries</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Daily Logs</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_daily_logs; ?></p>
          </div>

          <!-- Notifications -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-slate-50 p-2.5 rounded-full text-slate-600">
                <span class="material-symbols-outlined">campaign</span>
              </div>
              <span class="text-slate-600 text-xs font-bold bg-slate-50 px-2 py-0.5 rounded-full">Messages Sent</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Messages Sent</h3>
            <p class="text-3xl font-black text-gray-900 mt-1"><?php echo $total_notifications; ?></p>
          </div>

          <!-- Pending Reports (New) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm stat-card">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-red-50 p-2.5 rounded-full text-red-600">
                <span class="material-symbols-outlined">warning</span>
              </div>
              <span class="text-red-600 text-xs font-bold bg-red-50 px-2 py-0.5 rounded-full">Action</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Pending Reports</h3>
            <p class="text-3xl font-black text-gray-900 mt-1">
              <a href="admin_student_reports.php" class="text-red-600 hover:text-red-700"><?php echo $pending_reports_count; ?></a>
            </p>
          </div>
        </div>

        <!-- Internship Status Distribution -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm mt-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900">Internship Status Distribution</h2>
            <a href="admin_student_reports.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700">View All Reports →</a>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Active -->
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
              <p class="text-xs font-bold text-green-700 uppercase tracking-wider">Active</p>
              <p class="text-3xl font-black text-green-900 mt-2"><?php echo $internship_status_counts['active']; ?></p>
            </div>

            <!-- On Hold -->
            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
              <p class="text-xs font-bold text-orange-700 uppercase tracking-wider">On Hold</p>
              <p class="text-3xl font-black text-orange-900 mt-2"><?php echo $internship_status_counts['on_hold']; ?></p>
            </div>

            <!-- Discontinued -->
            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
              <p class="text-xs font-bold text-red-700 uppercase tracking-wider">Discontinued</p>
              <p class="text-3xl font-black text-red-900 mt-2"><?php echo $internship_status_counts['discontinued']; ?></p>
            </div>

            <!-- Removed -->
            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
              <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Removed</p>
              <p class="text-3xl font-black text-slate-900 mt-2"><?php echo $internship_status_counts['removed']; ?></p>
            </div>

            <!-- Completed -->
            <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
              <p class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Completed</p>
              <p class="text-3xl font-black text-emerald-900 mt-2"><?php echo $internship_status_counts['completed']; ?></p>
            </div>
          </div>
        </div>

        <!-- Grid Layout for Tables & Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
          
          <!-- Recently Registered Users (Col span 2) -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2 hover:shadow-md transition-shadow">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center">
              <h2 class="text-lg font-bold text-gray-900">Recently Registered Users</h2>
              <a href="admin_users.php" class="text-blue-600 text-xs font-bold hover:underline">View All Users</a>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                  <tr>
                    <th class="px-6 py-4">Name</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Email</th>
                    <th class="px-6 py-4">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php if (empty($recent_users)): ?>
                    <tr>
                      <td colspan="4" class="px-6 py-8 text-center text-gray-400 text-xs">No users registered yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recent_users as $ru): 
                      $role_colors = [
                        'student' => 'bg-blue-50 text-blue-700 border-blue-100',
                        'coordinator' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                        'mentor' => 'bg-purple-50 text-purple-700 border-purple-100',
                        'admin' => 'bg-red-50 text-red-700 border-red-100',
                        'hr' => 'bg-teal-50 text-teal-700 border-teal-100',
                        'company' => 'bg-orange-50 text-orange-700 border-orange-100'
                      ];
                      $role_cls = $role_colors[strtolower($ru['role'])] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                    ?>
                      <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-4 flex items-center gap-3">
                          <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs border border-slate-200">
                            <?php echo strtoupper(substr($ru['full_name'], 0, 2)); ?>
                          </div>
                          <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($ru['full_name']); ?></p>
                        </td>
                        <td class="px-6 py-4">
                          <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border <?php echo $role_cls; ?>">
                            <?php echo htmlspecialchars($ru['role']); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($ru['email']); ?></td>
                        <td class="px-6 py-4">
                          <?php 
                          $user_status = isset($ru['status']) ? htmlspecialchars($ru['status']) : 'Active';
                          $is_active = (strtolower($user_status) === 'active');
                          $status_color = $is_active ? 'text-green-600' : 'text-red-600';
                          $dot_color = $is_active ? 'bg-green-500' : 'bg-red-500';
                          $pulse_class = $is_active ? 'animate-pulse' : '';
                          ?>
                          <span class="flex items-center gap-1.5 <?php echo $status_color; ?> font-bold text-xs">
                            <div class="w-1.5 h-1.5 rounded-full <?php echo $dot_color; ?> <?php echo $pulse_class; ?>"></div> <?php echo $user_status; ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- System Activity Feed -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col hover:shadow-md transition-shadow">
            <div class="p-5 border-b border-gray-100">
              <h2 class="text-lg font-bold text-gray-900">System Activity</h2>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-6">
              <?php if (empty($activities)): ?>
                <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                  <span class="material-symbols-outlined text-4xl mb-2">info</span>
                  <p class="text-xs">No recent platform activity.</p>
                </div>
              <?php else: ?>
                <?php foreach ($activities as $act): ?>
                  <div class="flex gap-4">
                    <div class="p-2 rounded-full h-fit flex items-center justify-center shrink-0 <?php echo $act['bg']; ?>">
                      <span class="material-symbols-outlined text-base"><?php echo $act['icon']; ?></span>
                    </div>
                    <div>
                      <p class="text-sm text-gray-900 font-bold leading-tight"><?php echo htmlspecialchars($act['title']); ?></p>
                      <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($act['desc']); ?></p>
                      <p class="text-[10px] text-gray-400 mt-0.5"><?php echo date('h:i A - d M Y', $act['time']); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="p-4 border-t border-gray-100 flex flex-col gap-2 mt-auto">
              <button onclick="window.location.href='admin_daily_logs.php'" class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer">
                View Daily Logs Monitoring
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <footer class="max-w-6xl mx-auto mt-12 pt-6 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400 gap-4 mb-8">
        <p>© 2026 InternshipHub Enterprise Portal. All rights reserved.</p>
        <div class="flex gap-6 font-medium">
          <span class="text-gray-300">Internal Management System v3.0.0</span>
        </div>
      </footer>
    </main>
  </div>
  <script>
    // Profile Dropdown
    const profileBtn = document.getElementById('profile-menu-button');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Notifications Dropdown
    const notifBtn = document.getElementById('notifications-menu-button');
    const notifDropdown = document.getElementById('notifications-dropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            if (notifDropdown) notifDropdown.classList.add('hidden');
        });
    }

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden');
        });
    }

    document.addEventListener('click', () => {
        if (profileDropdown) profileDropdown.classList.add('hidden');
        if (notifDropdown) notifDropdown.classList.add('hidden');
    });
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
      const themeToggle = document.getElementById('theme-toggle');
      const themeToggleIcon = document.getElementById('theme-toggle-icon');
      
      if (themeToggle && themeToggleIcon) {
          // Set initial icon
          if (document.documentElement.classList.contains('dark')) {
              themeToggleIcon.textContent = 'light_mode';
          } else {
              themeToggleIcon.textContent = 'dark_mode';
          }
          
          themeToggle.addEventListener('click', () => {
              if (document.documentElement.classList.contains('dark')) {
                  document.documentElement.classList.remove('dark');
                  localStorage.setItem('theme', 'light');
                  themeToggleIcon.textContent = 'dark_mode';
              } else {
                  document.documentElement.classList.add('dark');
                  localStorage.setItem('theme', 'dark');
                  themeToggleIcon.textContent = 'light_mode';
              }
          });
      }
  });
  </script>
</body>
</html>
