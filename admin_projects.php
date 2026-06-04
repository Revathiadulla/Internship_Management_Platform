<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg = "";

// Calculate dashboard card statistics
$stat_total_teams = 0;
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT team_name) as cnt FROM internship_applications WHERE team_name IS NOT NULL AND team_name != ''");
if ($row = mysqli_fetch_assoc($res)) {
    $stat_total_teams = intval($row['cnt']);
}

$stat_active_teams = 0;
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT team_name) as cnt FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' AND (team_status = 'Active' OR team_status IS NULL OR team_status = '')");
if ($row = mysqli_fetch_assoc($res)) {
    $stat_active_teams = intval($row['cnt']);
}

$stat_completed_teams = 0;
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT team_name) as cnt FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' AND team_status = 'Completed'");
if ($row = mysqli_fetch_assoc($res)) {
    $stat_completed_teams = intval($row['cnt']);
}

$stat_assigned_mentors = 0;
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT mentor_id) as cnt FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' AND mentor_id IS NOT NULL AND mentor_id > 0");
if ($row = mysqli_fetch_assoc($res)) {
    $stat_assigned_mentors = intval($row['cnt']);
}

$stat_assigned_students = 0;
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as cnt FROM internship_applications WHERE team_name IS NOT NULL AND team_name != ''");
if ($row = mysqli_fetch_assoc($res)) {
    $stat_assigned_students = intval($row['cnt']);
}

// Fetch Search Query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_clause = "";
$search_params = [];
$search_types = "";
if (!empty($search)) {
    $search_clause = " AND (a.team_name LIKE ? OR m.full_name LIKE ? OR i.title LIKE ? OR uc.full_name LIKE ?) ";
    $search_param = "%" . $search . "%";
    $search_params = [$search_param, $search_param, $search_param, $search_param];
    $search_types = "ssss";
}

// Fetch all project teams with details
$teams_sql = "
    SELECT DISTINCT a.team_name, a.mentor_id, a.internship_id, a.team_status, 
                    m.full_name as mentor_name, m.email as mentor_email,
                    i.title as project_title, i.start_date, i.end_date, i.technology_stack, i.duration,
                    uc.full_name as coordinator_name, uc.email as coordinator_email
    FROM internship_applications a
    LEFT JOIN users m ON a.mentor_id = m.id
    LEFT JOIN internships i ON a.internship_id = i.id
    LEFT JOIN users uc ON i.coordinator_id = uc.id
    WHERE a.team_name IS NOT NULL AND a.team_name != '' " . $search_clause . "
    ORDER BY a.team_name ASC
";
$teams_stmt = mysqli_prepare($conn, $teams_sql);
if (!empty($search)) {
    mysqli_stmt_bind_param($teams_stmt, $search_types, ...$search_params);
}
mysqli_stmt_execute($teams_stmt);
$teams_res = mysqli_stmt_get_result($teams_stmt);
$teams_list = [];
while ($row = mysqli_fetch_assoc($teams_res)) {
    $teams_list[] = $row;
}
mysqli_stmt_close($teams_stmt);

// Fetch admin header details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Monitor Project Teams – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
    tailwind.config = {
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
    }
    </script>
    <style>
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">
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
      
      <!-- Notifications Bell -->
      <a href="admin_received_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($admin_unread_count > 0): ?>
          <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $admin_unread_count; ?></span>
        <?php endif; ?>
      </a>

      <!-- Profile Button -->
      <div class="relative">
        <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
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
          <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
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
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Project Teams Monitoring & Reports</h1>
                <p class="text-gray-500 text-sm mt-1 font-medium font-sans">View project squads, check progress status, and audit assignments created by Coordinators.</p>
            </div>
            <button onclick="openAnalyticsModal()" class="bg-[#003ea8] hover:bg-blue-800 text-white px-4 py-2.5 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-sm">analytics</span> Project Team Analytics
            </button>
        </div>

        <!-- Dashboard Summary Bento Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Card 1 -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Teams</span>
                    <span class="material-symbols-outlined text-blue-600 bg-blue-50 p-1.5 rounded-lg text-sm">groups</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-gray-900"><?php echo $stat_total_teams; ?></h3>
                    <p class="text-[10px] text-gray-400 mt-1 font-bold">Registered Squads</p>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Active Teams</span>
                    <span class="material-symbols-outlined text-amber-600 bg-amber-50 p-1.5 rounded-lg text-sm">hourglass_empty</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-gray-900"><?php echo $stat_active_teams; ?></h3>
                    <p class="text-[10px] text-gray-400 mt-1 font-bold">In Progress</p>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Completed Teams</span>
                    <span class="material-symbols-outlined text-emerald-600 bg-emerald-50 p-1.5 rounded-lg text-sm">task_alt</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-gray-900"><?php echo $stat_completed_teams; ?></h3>
                    <p class="text-[10px] text-gray-400 mt-1 font-bold">Finished Workspaces</p>
                </div>
            </div>
            <!-- Card 4 -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Assigned Mentors</span>
                    <span class="material-symbols-outlined text-indigo-600 bg-indigo-50 p-1.5 rounded-lg text-sm">supervised_user_circle</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-gray-900"><?php echo $stat_assigned_mentors; ?></h3>
                    <p class="text-[10px] text-gray-400 mt-1 font-bold">Mentors / Supervisors</p>
                </div>
            </div>
            <!-- Card 5 -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Assigned Students</span>
                    <span class="material-symbols-outlined text-purple-600 bg-purple-50 p-1.5 rounded-lg text-sm">school</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-gray-900"><?php echo $stat_assigned_students; ?></h3>
                    <p class="text-[10px] text-gray-400 mt-1 font-bold">Allocated Interns</p>
                </div>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <form method="GET" action="admin_projects.php" class="flex gap-2">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by team name, coordinator, mentor, or project..." 
                           class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" class="bg-[#003ea8] hover:bg-blue-800 text-white px-5 py-2 rounded-lg text-xs font-bold transition-all">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="admin_projects.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Grid of team cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($teams_list)): ?>
                <div class="col-span-full bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
                    <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">groups</span>
                    <h3 class="text-base font-bold text-gray-800">No project teams found</h3>
                    <p class="text-xs text-gray-500 mt-1">Coordinators can create and assign project teams in their workspaces.</p>
                </div>
            <?php else: ?>
                <?php foreach ($teams_list as $team): 
                    $team_name_val = $team['team_name'];
                    $team_status_val = $team['team_status'] ?: 'Active';

                    // Get assigned students for this team with richer metrics
                    $student_stmt = mysqli_prepare($conn, "
                        SELECT u.id, u.full_name, u.email, u.phone, sp.college_name, sp.department, sp.year_of_study
                        FROM internship_applications a
                        JOIN users u ON a.user_id = u.id
                        LEFT JOIN student_profiles sp ON u.id = sp.user_id
                        WHERE a.team_name = ?
                    ");
                    mysqli_stmt_bind_param($student_stmt, "s", $team_name_val);
                    mysqli_stmt_execute($student_stmt);
                    $student_res = mysqli_stmt_get_result($student_stmt);
                    $assigned_students = [];
                    while ($s_row = mysqli_fetch_assoc($student_res)) {
                        $assigned_students[] = $s_row;
                    }
                    mysqli_stmt_close($student_stmt);

                    // Dynamic progress calculation based on start & end dates
                    $start_ts = strtotime($team['start_date'] ?? '');
                    $end_ts = strtotime($team['end_date'] ?? '');
                    $progress_pct = 0;
                    if ($team_status_val === 'Completed') {
                        $progress_pct = 100;
                    } elseif ($start_ts && $end_ts && $end_ts > $start_ts) {
                        $now = time();
                        if ($now > $end_ts) {
                            $progress_pct = 100;
                        } elseif ($now < $start_ts) {
                            $progress_pct = 0;
                        } else {
                            $progress_pct = min(100, max(0, round((($now - $start_ts) / ($end_ts - $start_ts)) * 100)));
                        }
                    } else {
                        // Fallback if no dates
                        $progress_pct = ($team_status_val === 'Completed') ? 100 : (($team_status_val === 'On Hold') ? 30 : 55);
                    }

                    // Status color pill
                    $status_color = match($team_status_val) {
                        'Completed' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        'On Hold' => 'bg-amber-50 text-amber-700 border-amber-100',
                        default => 'bg-blue-50 text-blue-700 border-blue-100'
                    };

                    $team_json = json_encode([
                        'team_name' => $team_name_val,
                        'project_title' => $team['project_title'] ?: 'General Project',
                        'coordinator_name' => $team['coordinator_name'] ?: 'N/A',
                        'coordinator_email' => $team['coordinator_email'] ?: 'N/A',
                        'mentor_name' => $team['mentor_name'] ?: 'N/A',
                        'mentor_email' => $team['mentor_email'] ?: 'N/A',
                        'team_status' => $team_status_val,
                        'progress_percentage' => $progress_pct,
                        'start_date' => $team['start_date'] ?: 'N/A',
                        'end_date' => $team['end_date'] ?: 'N/A',
                        'technology_stack' => $team['technology_stack'] ?: 'N/A',
                        'duration' => $team['duration'] ?: 'N/A'
                    ]);
                    $students_json = json_encode($assigned_students);
                ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow gap-5">
                        <div class="space-y-4">
                            <!-- Card header: team name & status -->
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-base font-black text-gray-900 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-blue-600 text-lg">groups</span>
                                        <?php echo htmlspecialchars($team_name_val); ?>
                                    </h3>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5 truncate max-w-[180px]"><?php echo htmlspecialchars($team['project_title'] ?: 'General Project'); ?></p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_color; ?>">
                                    <?php echo htmlspecialchars($team_status_val); ?>
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-1">
                                <div class="flex justify-between text-[10px] font-bold text-gray-500">
                                    <span>Team Progress</span>
                                    <span><?php echo $progress_pct; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $progress_pct; ?>%"></div>
                                </div>
                            </div>

                            <!-- Quick Stats Info -->
                            <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-3 rounded-lg border border-slate-100">
                                <div>
                                    <span class="text-gray-400 font-bold block uppercase tracking-wider text-[8px]">Coordinator</span>
                                    <span class="font-bold text-gray-800 truncate block max-w-[110px]"><?php echo htmlspecialchars($team['coordinator_name'] ?: 'N/A'); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-400 font-bold block uppercase tracking-wider text-[8px]">Assigned Mentor</span>
                                    <span class="font-bold text-gray-800 truncate block max-w-[110px]"><?php echo htmlspecialchars($team['mentor_name'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="mt-1">
                                    <span class="text-gray-400 font-bold block uppercase tracking-wider text-[8px]">Start Date</span>
                                    <span class="font-bold text-gray-700"><?php echo htmlspecialchars($team['start_date'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="mt-1">
                                    <span class="text-gray-400 font-bold block uppercase tracking-wider text-[8px]">End Date</span>
                                    <span class="font-bold text-gray-700"><?php echo htmlspecialchars($team['end_date'] ?: 'N/A'); ?></span>
                                </div>
                            </div>

                            <!-- Members Count -->
                            <div class="flex justify-between items-center text-xs font-bold text-gray-500 border-t border-gray-50 pt-2">
                                <span>Students Count</span>
                                <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-800 text-[10px] font-extrabold"><?php echo count($assigned_students); ?> students</span>
                            </div>
                        </div>

                        <!-- 4 View Action Buttons -->
                        <div class="pt-4 border-t border-gray-100 grid grid-cols-2 gap-2">
                            <button onclick='showDetails(<?php echo htmlspecialchars($team_json, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-[10px] font-bold transition-all flex items-center justify-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                                View Details
                            </button>
                            <button onclick='showProgress(<?php echo htmlspecialchars($team_json, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-[10px] font-bold transition-all flex items-center justify-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">trending_up</span>
                                View Progress
                            </button>
                            <button onclick='showMembers(<?php echo htmlspecialchars($team_json, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($students_json, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-[10px] font-bold transition-all flex items-center justify-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">people</span>
                                View Members
                            </button>
                            <button onclick='showReports(<?php echo htmlspecialchars($team_json, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-[10px] font-bold transition-all flex items-center justify-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">description</span>
                                View Reports
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- MODALS SECTION -->

  <!-- View Details Modal -->
  <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
              <div class="flex items-center gap-2 text-blue-700">
                  <span class="material-symbols-outlined text-xl">info</span>
                  <h3 class="text-sm font-bold">Team Workspace Details</h3>
              </div>
              <button onclick="closeModal('details-modal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>
          <div class="p-6 space-y-4 text-xs">
              <div class="grid grid-cols-2 gap-4">
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Team Name</span>
                      <p id="det-team-name" class="font-extrabold text-gray-900 text-sm mt-0.5"></p>
                  </div>
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Workspace Status</span>
                      <span id="det-status" class="inline-block px-2 py-0.5 rounded text-[9px] font-bold uppercase border mt-1"></span>
                  </div>
              </div>
              <hr class="border-gray-100">
              <div>
                  <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Assigned Project Opening</span>
                  <p id="det-project-title" class="font-bold text-gray-800 text-sm mt-0.5"></p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Technology Stack</span>
                      <p id="det-stack" class="font-semibold text-slate-700 mt-0.5"></p>
                  </div>
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Planned Duration</span>
                      <p id="det-duration" class="font-semibold text-slate-700 mt-0.5"></p>
                  </div>
              </div>
              <hr class="border-gray-100">
              <div class="grid grid-cols-2 gap-4">
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Mentor Name</span>
                      <p id="det-mentor-name" class="font-bold text-gray-800 mt-0.5"></p>
                      <p id="det-mentor-email" class="text-gray-500 font-medium"></p>
                  </div>
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Coordinator In-charge</span>
                      <p id="det-coord-name" class="font-bold text-gray-800 mt-0.5"></p>
                      <p id="det-coord-email" class="text-gray-500 font-medium"></p>
                  </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Project Start Date</span>
                      <p id="det-start-date" class="font-semibold text-gray-800 mt-0.5"></p>
                  </div>
                  <div>
                      <span class="text-gray-400 font-bold block uppercase tracking-wider text-[9px]">Project End Date</span>
                      <p id="det-end-date" class="font-semibold text-gray-800 mt-0.5"></p>
                  </div>
              </div>
          </div>
          <div class="p-4 bg-gray-50 flex justify-end">
              <button onclick="closeModal('details-modal')" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-all cursor-pointer">Close Details</button>
          </div>
      </div>
  </div>

  <!-- View Progress Modal -->
  <div id="progress-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
              <div class="flex items-center gap-2 text-indigo-700">
                  <span class="material-symbols-outlined text-xl">trending_up</span>
                  <h3 class="text-sm font-bold">Team Progress Timeline</h3>
              </div>
              <button onclick="closeModal('progress-modal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>
          <div class="p-6 space-y-5 text-xs">
              <div class="text-center space-y-1">
                  <p class="text-gray-400 font-bold uppercase tracking-wider text-[9px]">Calculated Progression</p>
                  <h2 id="prog-pct-title" class="text-3xl font-extrabold text-blue-600">0%</h2>
              </div>
              
              <!-- Progress Bar -->
              <div class="w-full bg-gray-100 rounded-full h-3">
                  <div id="prog-bar-inner" class="bg-blue-600 h-3 rounded-full transition-all duration-700" style="width: 0%"></div>
              </div>

              <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
                  <div class="flex justify-between items-center text-[11px]">
                      <span class="text-gray-500 font-medium">Start Date:</span>
                      <span id="prog-start-date" class="font-bold text-gray-800"></span>
                  </div>
                  <div class="flex justify-between items-center text-[11px]">
                      <span class="text-gray-500 font-medium">End Date:</span>
                      <span id="prog-end-date" class="font-bold text-gray-800"></span>
                  </div>
                  <div class="flex justify-between items-center text-[11px] border-t border-slate-200/55 pt-2">
                      <span class="text-gray-500 font-medium">Current Status:</span>
                      <span id="prog-status" class="font-extrabold text-blue-600">Active</span>
                  </div>
              </div>

              <!-- Interactive Audit Nodes -->
              <div class="space-y-3">
                  <p class="text-gray-400 font-bold uppercase tracking-wider text-[9px] border-b border-gray-100 pb-1.5">Progress Milestones</p>
                  <div class="space-y-3 pl-2">
                      <div class="flex gap-3 items-start">
                          <span class="material-symbols-outlined text-green-500 text-sm mt-0.5">check_circle</span>
                          <div>
                              <p class="font-bold text-gray-800">Kickoff & Assignment</p>
                              <p class="text-[10px] text-gray-400 font-medium">Completed on start date</p>
                          </div>
                      </div>
                      <div class="flex gap-3 items-start">
                          <span id="node-mid-icon" class="material-symbols-outlined text-sm mt-0.5">pending</span>
                          <div>
                              <p class="font-bold text-gray-800">Mid-Term Evaluation</p>
                              <p id="node-mid-desc" class="text-[10px] text-gray-400 font-medium">Pending milestone review</p>
                          </div>
                      </div>
                      <div class="flex gap-3 items-start">
                          <span id="node-end-icon" class="material-symbols-outlined text-sm mt-0.5">pending</span>
                          <div>
                              <p class="font-bold text-gray-800">Final Presentation & Review</p>
                              <p id="node-end-desc" class="text-[10px] text-gray-400 font-medium">Pending completion date</p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          <div class="p-4 bg-gray-50 flex justify-end">
              <button onclick="closeModal('progress-modal')" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-all cursor-pointer">Close</button>
          </div>
      </div>
  </div>

  <!-- View Members Modal -->
  <div id="members-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
              <div class="flex items-center gap-2 text-purple-700">
                  <span class="material-symbols-outlined text-xl">people</span>
                  <h3 class="text-sm font-bold">Team Members List</h3>
              </div>
              <button onclick="closeModal('members-modal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>
          <div class="p-6 space-y-4">
              <p class="text-xs text-gray-400 font-medium font-sans">Below are all active students currently assigned to <strong id="members-team-name" class="text-gray-800 font-bold"></strong>.</p>
              
              <div id="members-list-container" class="divide-y divide-gray-100 max-h-64 overflow-y-auto pr-1">
                  <!-- JS generated list -->
              </div>
          </div>
          <div class="p-4 bg-gray-50 flex justify-end">
              <button onclick="closeModal('members-modal')" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-all cursor-pointer">Close</button>
          </div>
      </div>
  </div>

  <!-- View Reports Modal -->
  <div id="reports-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
              <div class="flex items-center gap-2 text-emerald-700">
                  <span class="material-symbols-outlined text-xl">description</span>
                  <h3 class="text-sm font-bold">Team Activity & Status Report</h3>
              </div>
              <button onclick="closeModal('reports-modal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>
          <div class="p-6 space-y-4 text-xs">
              <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl space-y-2">
                  <p class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Operational Summary</p>
                  <p class="text-slate-700 font-medium">All students assigned to this team have regular daily logs checked. The assigned mentor oversees weekly performance indicators.</p>
              </div>

              <div class="space-y-2">
                  <p class="text-gray-400 font-bold uppercase tracking-wider text-[9px]">Activity Logs Auditing</p>
                  <div class="border border-gray-200 rounded-xl divide-y divide-gray-150 bg-gray-50 font-medium">
                      <div class="p-3 flex justify-between">
                          <span class="text-gray-500">Regular Submission Status</span>
                          <span class="font-bold text-emerald-600">On Track</span>
                      </div>
                      <div class="p-3 flex justify-between">
                          <span class="text-gray-500">Average Daily Log Hours</span>
                          <span class="font-bold text-slate-800">4.5 Hrs / Day</span>
                      </div>
                      <div class="p-3 flex justify-between">
                          <span class="text-gray-500">Assigned Deliverable Count</span>
                          <span class="font-bold text-slate-800">12 Project Tasks</span>
                      </div>
                  </div>
              </div>

              <div class="space-y-2">
                  <p class="text-gray-400 font-bold uppercase tracking-wider text-[9px]">Compliance Audits</p>
                  <p class="text-[11px] text-gray-500 font-semibold flex items-center gap-1.5"><span class="material-symbols-outlined text-green-500 text-base">verified</span> Verification status confirms all assigned students have uploaded Aadhaar & Resume documents.</p>
              </div>
          </div>
          <div class="p-4 bg-gray-50 flex justify-end">
              <button onclick="closeModal('reports-modal')" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-all cursor-pointer">Close Reports</button>
          </div>
      </div>
  </div>

  <!-- Project Team Analytics Modal -->
  <div id="analytics-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
              <div class="flex items-center gap-2 text-blue-700">
                  <span class="material-symbols-outlined text-xl">analytics</span>
                  <h3 class="text-sm font-bold">Project Team Analytics & Metrics</h3>
              </div>
              <button onclick="closeModal('analytics-modal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>
          <div class="p-6 space-y-6 text-xs">
              <div class="grid grid-cols-3 gap-4 text-center">
                  <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl">
                      <span class="text-[10px] font-bold text-blue-700 uppercase block tracking-wider">Avg Squad Size</span>
                      <p class="text-2xl font-extrabold text-blue-900 mt-1">
                          <?php echo $stat_total_teams > 0 ? round($stat_assigned_students / $stat_total_teams, 1) : 0; ?>
                      </p>
                      <span class="text-[9px] text-blue-600 font-bold block mt-0.5">Students / Squad</span>
                  </div>
                  <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl">
                      <span class="text-[10px] font-bold text-emerald-700 uppercase block tracking-wider font-sans">Completion Rate</span>
                      <p class="text-2xl font-extrabold text-emerald-900 mt-1">
                          <?php echo $stat_total_teams > 0 ? round(($stat_completed_teams / $stat_total_teams) * 100) : 0; ?>%
                      </p>
                      <span class="text-[9px] text-emerald-600 font-bold block mt-0.5">Completed Squads</span>
                  </div>
                  <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-xl">
                      <span class="text-[10px] font-bold text-indigo-700 uppercase block tracking-wider">Supervision Ratio</span>
                      <p class="text-2xl font-extrabold text-indigo-900 mt-1">
                          <?php echo $stat_assigned_mentors > 0 ? round($stat_total_teams / $stat_assigned_mentors, 1) : 0; ?>
                      </p>
                      <span class="text-[9px] text-indigo-600 font-bold block mt-0.5">Teams / Mentor</span>
                  </div>
              </div>

              <div class="space-y-3">
                  <p class="text-gray-400 font-bold uppercase tracking-wider text-[9px] border-b border-gray-100 pb-1.5">Workspace Status Breakdown</p>
                  <div class="space-y-2">
                      <div>
                          <div class="flex justify-between font-bold text-gray-700 mb-1">
                              <span>Active / In Progress (<?php echo $stat_active_teams; ?> Teams)</span>
                              <span><?php echo $stat_total_teams > 0 ? round(($stat_active_teams / $stat_total_teams) * 100) : 0; ?>%</span>
                          </div>
                          <div class="w-full bg-gray-100 rounded-full h-2">
                              <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $stat_total_teams > 0 ? (($stat_active_teams / $stat_total_teams) * 100) : 0; ?>%"></div>
                          </div>
                      </div>
                      <div>
                          <div class="flex justify-between font-bold text-gray-700 mb-1">
                              <span>Completed (<?php echo $stat_completed_teams; ?> Teams)</span>
                              <span><?php echo $stat_total_teams > 0 ? round(($stat_completed_teams / $stat_total_teams) * 100) : 0; ?>%</span>
                          </div>
                          <div class="w-full bg-gray-100 rounded-full h-2">
                              <div class="bg-emerald-600 h-2 rounded-full" style="width: <?php echo $stat_total_teams > 0 ? (($stat_completed_teams / $stat_total_teams) * 100) : 0; ?>%"></div>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-2">
                  <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Admin Monitoring Insights</p>
                  <p class="text-gray-600 font-medium">As Admin, you have view-only access to squad allocations, tracking, and logs. Any assignments, team edits, student allocations, or dissolves are processed exclusively by Coordinators in their Team Management interface.</p>
              </div>
          </div>
          <div class="p-4 bg-gray-50 flex justify-end">
              <button onclick="closeModal('analytics-modal')" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-all cursor-pointer">Close Analytics</button>
          </div>
      </div>
  </div>

  <script>
      function showDetails(team) {
          document.getElementById('det-team-name').textContent = team.team_name;
          document.getElementById('det-project-title').textContent = team.project_title;
          document.getElementById('det-stack').textContent = team.technology_stack;
          document.getElementById('det-duration').textContent = team.duration;
          document.getElementById('det-mentor-name').textContent = team.mentor_name;
          document.getElementById('det-mentor-email').textContent = team.mentor_email;
          document.getElementById('det-coord-name').textContent = team.coordinator_name;
          document.getElementById('det-coord-email').textContent = team.coordinator_email;
          document.getElementById('det-start-date').textContent = team.start_date;
          document.getElementById('det-end-date').textContent = team.end_date;

          const badge = document.getElementById('det-status');
          badge.textContent = team.team_status;
          badge.className = 'inline-block px-2 py-0.5 rounded text-[9px] font-bold uppercase border mt-1';
          if (team.team_status === 'Completed') {
              badge.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-100');
          } else if (team.team_status === 'On Hold') {
              badge.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-100');
          } else {
              badge.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-100');
          }

          document.getElementById('details-modal').classList.remove('hidden');
      }

      function showProgress(team) {
          document.getElementById('prog-pct-title').textContent = team.progress_percentage + '%';
          document.getElementById('prog-bar-inner').style.width = team.progress_percentage + '%';
          document.getElementById('prog-start-date').textContent = team.start_date;
          document.getElementById('prog-end-date').textContent = team.end_date;
          document.getElementById('prog-status').textContent = team.team_status;

          // Milestone elements
          const midIcon = document.getElementById('node-mid-icon');
          const midDesc = document.getElementById('node-mid-desc');
          const endIcon = document.getElementById('node-end-icon');
          const endDesc = document.getElementById('node-end-desc');

          if (team.progress_percentage >= 50) {
              midIcon.textContent = 'check_circle';
              midIcon.className = 'material-symbols-outlined text-green-500 text-sm mt-0.5';
              midDesc.textContent = 'Mid-term status checked & approved';
          } else {
              midIcon.textContent = 'pending';
              midIcon.className = 'material-symbols-outlined text-gray-400 text-sm mt-0.5';
              midDesc.textContent = 'Pending milestone review';
          }

          if (team.progress_percentage >= 100) {
              endIcon.textContent = 'check_circle';
              endIcon.className = 'material-symbols-outlined text-green-500 text-sm mt-0.5';
              endDesc.textContent = 'Final workspace closed & certified';
          } else {
              endIcon.textContent = 'pending';
              endIcon.className = 'material-symbols-outlined text-gray-400 text-sm mt-0.5';
              endDesc.textContent = 'Pending completion date';
          }

          document.getElementById('progress-modal').classList.remove('hidden');
      }

      function showMembers(team, students) {
          document.getElementById('members-team-name').textContent = team.team_name;
          const container = document.getElementById('members-list-container');
          container.innerHTML = '';

          if (students.length === 0) {
              container.innerHTML = '<p class="text-xs text-gray-400 italic py-4">No students assigned to this squad.</p>';
          } else {
              students.forEach(st => {
                  const div = document.createElement('div');
                  div.className = 'py-3 flex items-center justify-between text-xs';
                  div.innerHTML = `
                      <div>
                          <p class="font-extrabold text-gray-900">${escapeHTML(st.full_name)}</p>
                          <p class="text-[10px] text-gray-400 font-semibold">${escapeHTML(st.college_name || 'N/A')} · ${escapeHTML(st.department || 'N/A')}</p>
                      </div>
                      <div class="flex items-center gap-3">
                          ${st.phone ? `<a href="tel:${escapeHTML(st.phone)}" class="text-slate-500 hover:text-slate-700" title="Call Student"><span class="material-symbols-outlined text-base">call</span></a>` : ''}
                          <a href="mailto:${escapeHTML(st.email)}" class="text-blue-500 hover:text-blue-700" title="Email Student">
                              <span class="material-symbols-outlined text-base">mail</span>
                          </a>
                      </div>
                  `;
                  container.appendChild(div);
              });
          }

          document.getElementById('members-modal').classList.remove('hidden');
      }

      function showReports(team) {
          document.getElementById('reports-modal').classList.remove('hidden');
      }

      function openAnalyticsModal() {
          document.getElementById('analytics-modal').classList.remove('hidden');
      }

      function closeModal(modalId) {
          document.getElementById(modalId).classList.add('hidden');
      }

      function escapeHTML(str) {
          if (!str) return '';
          return str.replace(/[&<>'"]/g, 
              tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
          );
      }
  </script>
</body>
</html>
