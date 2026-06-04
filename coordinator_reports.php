<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";
$notif_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'coordinator' AND is_read = 0");
$notif_unread_row = mysqli_fetch_assoc($notif_unread_res);
$unread_count = $notif_unread_row['count'] ?? 0;

$coord_id = intval($_SESSION['user_id']);

// Fetch total students
$total_students_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.user_id) as cnt 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status IN ('Started', 'Internship Started', 'Active Intern', 'Selected')
      AND i.coordinator_id = $coord_id
");
$total_students_row = mysqli_fetch_assoc($total_students_res);
$total_students = intval($total_students_row['cnt'] ?? 0);

// Fetch daily logs stats
$logs_stats_res = mysqli_query($conn, "
    SELECT COUNT(d.id) as total_logs, COALESCE(SUM(d.time_spent), 0) as total_hours, COALESCE(AVG(d.time_spent), 0) as avg_hours 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE i.coordinator_id = $coord_id
");
$logs_stats = mysqli_fetch_assoc($logs_stats_res);
$total_logs = intval($logs_stats['total_logs'] ?? 0);
$total_hours = floatval($logs_stats['total_hours'] ?? 0);
$avg_hours = floatval($logs_stats['avg_hours'] ?? 0);

// Calculate Average Hours per student
$avg_hours_per_student = $total_students > 0 ? ($total_hours / $total_students) : 0;

// Fetch application status distribution
$app_dist_res = mysqli_query($conn, "
    SELECT a.status, COUNT(*) as cnt 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.coordinator_id = $coord_id
    GROUP BY a.status
");
$app_status_dist = [];
$total_applications = 0;
while ($row = mysqli_fetch_assoc($app_dist_res)) {
    $status = $row['status'] ?: 'Applied';
    $app_status_dist[$status] = intval($row['cnt']);
    $total_applications += intval($row['cnt']);
}

// Ensure all standard statuses exist in the distribution array
$standard_statuses = ['Applied', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];
foreach ($standard_statuses as $st) {
    if (!isset($app_status_dist[$st])) {
        $app_status_dist[$st] = 0;
    }
}

// Fetch active students (logged in last 7 days)
$active_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT d.user_id) as cnt 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND i.coordinator_id = $coord_id
");
$active_students = intval(mysqli_fetch_assoc($active_res)['cnt'] ?? 0);
$inactive_students = max(0, $total_students - $active_students);

// Fetch today's log stats
$today_logs_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT d.user_id) as cnt 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.log_date = CURDATE()
      AND i.coordinator_id = $coord_id
");
$today_logged = intval(mysqli_fetch_assoc($today_logs_res)['cnt'] ?? 0);
$pending_today = max(0, $total_students - $today_logged);

// Fetch recent 7 days log submissions trend
$daily_trend_res = mysqli_query($conn, "
    SELECT d.log_date, COUNT(d.id) as cnt 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND i.coordinator_id = $coord_id
    GROUP BY d.log_date 
    ORDER BY d.log_date ASC
");
$daily_trend = [];
while ($row = mysqli_fetch_assoc($daily_trend_res)) {
    $daily_trend[$row['log_date']] = intval($row['cnt']);
}

// Fill in missing dates for the last 7 days with 0
for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    if (!isset($daily_trend[$date_str])) {
        $daily_trend[$date_str] = 0;
    }
}
ksort($daily_trend);

// Handle Search Filter for Student Progress Reports
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$student_sql = "
    SELECT 
        u.id as student_id,
        u.full_name,
        u.email,
        COUNT(d.id) as total_logs,
        COALESCE(SUM(d.time_spent), 0) as student_hours,
        MAX(d.log_date) as last_log_date
    FROM users u
    JOIN internship_applications a ON u.id = a.user_id
    JOIN internships i ON a.internship_id = i.id
    LEFT JOIN daily_logs d ON u.id = d.user_id AND d.internship_id = i.id
    WHERE u.role = 'student' AND i.coordinator_id = $coord_id
";

if (!empty($search)) {
    $student_sql .= " AND u.full_name LIKE ? ";
}

$student_sql .= " GROUP BY u.id, u.full_name, u.email ORDER BY student_hours DESC";

$student_stmt = mysqli_prepare($conn, $student_sql);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    mysqli_stmt_bind_param($student_stmt, "s", $search_param);
}
mysqli_stmt_execute($student_stmt);
$students_result = mysqli_stmt_get_result($student_stmt);
$student_reports = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $student_reports[] = $row;
}
mysqli_stmt_close($student_stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Reports & Analytics - Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #191c1d; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        @media print {
            aside { display: none !important; }
            main { margin-left: 0 !important; }
            header { display: none !important; }
            .no-print { display: none !important; }
        }
        aside {
                transition: transform 0.3s ease-in-out;
        }
         main {
                 transition: margin-left 0.3s ease-in-out;
                 min-width: 0;
                 overflow-x: hidden;
         }
        @media (max-width: 767px) {
                aside {
                        transform: translateX(-100%);
                }
                main {
                        margin-left: 0 !important;
                }
                body.sidebar-open aside {
                        transform: translateX(0);
                }
        }
        @media (min-width: 768px) {
                body.sidebar-closed aside {
                        transform: translateX(-100%);
                }
                body.sidebar-closed main {
                        margin-left: 0 !important;
                }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- ════════════════ SIDEBAR ════════════════ -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-white border-r border-gray-200 flex flex-col py-6 no-print">
                <div class="px-6 mb-8">
                        <a href="index.html" class="flex items-center gap-2">
                                <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none">
                                        <rect width="32" height="32" rx="8" fill="currentColor"/>
                                        <circle cx="16" cy="16" r="3" fill="white"/>
                                        <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
                                        <circle cx="16" cy="8" r="1.5" fill="white"/>
                                        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
                                        <line x1="17.8" y1="18.4" x2="20" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="14.2" y1="18.4" x2="12" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
                                </svg>
                                <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
                        </a>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2 ml-0.5">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-0.5 px-3">
                        <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
                        </a>
                        <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">work</span> Postings
                        </a>
                        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">group</span> Candidates
                        </a>
                        <a href="coordinator_generate_test.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">quiz</span> Generate Test
                        </a>
                        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
                        </a>
                        <a href="coordinator_reports.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
                                <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
                        </a>
                        <a href="coordinator_teams.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Teams
                        </a>
                </nav>
                <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
                        <a href="coordinator_profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
                        </a>
                        <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">help</span> Help Center
                        </a>
                        <a href="logout.php" class="flex items-center gap-3 text-red-650 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-red-400">logout</span> Logout
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <?php
                $header_uid = $_SESSION['user_id'];
                $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
                $header_user = mysqli_fetch_assoc($header_res);
                $header_name = $header_user['full_name'] ?? 'Coordinator';
                $header_photo = $header_user['profile_photo'] ?? '';
                ?>
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm no-print">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Reports</h2>
                        </div>
                        
                        <div class="flex items-center gap-6">
                                <!-- Notifications Bell -->
                                <a href="coordinator_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
                                        <span class="material-symbols-outlined">notifications</span>
                                        <?php if ($unread_count > 0): ?>
                                                <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                </a>

                                <!-- Profile Dropdown Section -->
                                <div class="relative" id="profile-container">
                                        <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block">
                                                        <?php echo htmlspecialchars($header_name); ?>
                                                </span>
                                                <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-500 transition-colors">
                                                        <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                                                                <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=0D8ABC&color=fff" alt="Profile" class="w-full h-full object-cover">
                                                        <?php endif; ?>
                                                </div>
                                                <span class="material-symbols-outlined text-gray-500 text-[18px] group-hover:text-blue-600 transition-colors">arrow_drop_down</span>
                                        </button>
                                        
                                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                                                <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                        <span>My Profile</span>
                                                </a>
                                                <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                        <span>Settings</span>
                                                </a>
                                                <hr class="my-1 border-gray-100">
                                                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                        <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                        <span>Logout</span>
                                                </a>
                                        </div>
                                </div>
                        </div>
                </header>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                        const profileBtn = document.getElementById('profile-menu-button');
                        const profileDropdown = document.getElementById('profile-dropdown');
                        
                        if (profileBtn && profileDropdown) {
                                profileBtn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        profileDropdown.classList.toggle('hidden');
                                });
                                
                                document.addEventListener('click', function(e) {
                                        if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                                                profileDropdown.classList.add('hidden');
                                        }
                                });
                        }
                });
                </script>


                <div class="flex-1 p-8 space-y-6">
                    <div class="max-w-6xl mx-auto space-y-8">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
                        <p class="text-gray-500 text-sm mt-1 font-medium">Analyze program stats, candidate pipeline metrics, and student logging progress.</p>
                    </div>
                    <button onclick="window.print()" class="no-print bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-sm">print</span> Print Report
                    </button>
                </div>

                <!-- Stats summary grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Interns Tracked</p>
                            <p class="text-3xl font-extrabold text-gray-900"><?php echo $total_students; ?></p>
                        </div>
                        <p class="text-xs text-gray-400 mt-4 font-medium">Registered student accounts</p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Hours Logged</p>
                            <p class="text-3xl font-extrabold text-blue-600"><?php echo number_format($total_hours, 1); ?> hrs</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-4 font-medium">Sum of hours across all logs</p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Avg Hours / Student</p>
                            <p class="text-3xl font-extrabold text-emerald-600"><?php echo number_format($avg_hours_per_student, 1); ?> hrs</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-4 font-medium">Average dedication rate</p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Log Entries</p>
                            <p class="text-3xl font-extrabold text-purple-600"><?php echo $total_logs; ?></p>
                        </div>
                        <p class="text-xs text-gray-400 mt-4 font-medium">Daily log submissions received</p>
                    </div>
                </div>

                <!-- Visual Charts Section (Pure Tailwind & CSS implementation) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Candidate Pipeline Distribution -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
                        <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Candidate Pipeline Distribution</h3>
                            <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $total_applications; ?> Applications</span>
                        </div>
                        <div class="space-y-3.5">
                            <?php 
                            foreach ($app_status_dist as $status => $count): 
                                $pct = $total_applications > 0 ? round(($count / $total_applications) * 100) : 0;
                                $color = match($status) {
                                    'Applied' => 'bg-blue-500',
                                    'Test Completed' => 'bg-purple-500',
                                    'HR Round' => 'bg-indigo-500',
                                    'HOD Approved' => 'bg-amber-500',
                                    'Selected' => 'bg-emerald-500',
                                    'Rejected' => 'bg-red-500',
                                    default => 'bg-gray-500'
                                };
                            ?>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs font-medium text-gray-700">
                                        <span><?php echo htmlspecialchars($status); ?></span>
                                        <span class="font-bold"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full <?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Daily Activity Logs (Last 7 Days) & Focus Level -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between space-y-6">
                        <div class="space-y-4">
                            <div class="border-b border-gray-100 pb-3">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Daily Log Submissions Trend</h3>
                            </div>
                            <!-- Bar chart -->
                            <div class="flex items-end justify-between h-36 pt-4 px-2">
                                <?php 
                                $max_submissions = count($daily_trend) > 0 ? max($daily_trend) : 0;
                                foreach ($daily_trend as $date => $count): 
                                    $height_pct = $max_submissions > 0 ? round(($count / $max_submissions) * 80) + 10 : 10;
                                    $formatted_date = date('M d', strtotime($date));
                                ?>
                                    <div class="flex flex-col items-center gap-2 group flex-1">
                                        <div class="relative w-full flex justify-center">
                                            <span class="absolute -top-6 bg-gray-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"><?php echo $count; ?> logs</span>
                                            <div class="w-7 bg-blue-600 hover:bg-blue-700 rounded-t transition-all cursor-pointer" style="height: <?php echo $height_pct; ?>px;"></div>
                                        </div>
                                        <span class="text-[9px] font-semibold text-gray-500 whitespace-nowrap"><?php echo $formatted_date; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Activity Overview -->
                        <div class="space-y-3">
                            <div class="border-t border-gray-100 pt-3">
                                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Activity Overview</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100 flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-emerald-600 text-[16px]">person</span>
                                        <span class="text-[10px] font-bold text-emerald-700 uppercase">Active Students</span>
                                    </div>
                                    <p class="text-xl font-black text-emerald-700 mt-1"><?php echo $active_students; ?></p>
                                    <p class="text-[9px] text-emerald-600/70 font-medium">Logged in last 7 days</p>
                                </div>
                                <div class="bg-red-50 p-3 rounded-lg border border-red-100 flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-red-500 text-[16px]">person_off</span>
                                        <span class="text-[10px] font-bold text-red-600 uppercase">Inactive Students</span>
                                    </div>
                                    <p class="text-xl font-black text-red-600 mt-1"><?php echo $inactive_students; ?></p>
                                    <p class="text-[9px] text-red-500/70 font-medium">No logs in 7 days</p>
                                </div>
                                <div class="bg-amber-50 p-3 rounded-lg border border-amber-100 flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-amber-600 text-[16px]">pending_actions</span>
                                        <span class="text-[10px] font-bold text-amber-700 uppercase">Pending Today</span>
                                    </div>
                                    <p class="text-xl font-black text-amber-700 mt-1"><?php echo $pending_today; ?></p>
                                    <p class="text-[9px] text-amber-600/70 font-medium">Haven't logged today</p>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-blue-600 text-[16px]">task_alt</span>
                                        <span class="text-[10px] font-bold text-blue-700 uppercase">Logged Today</span>
                                    </div>
                                    <p class="text-xl font-black text-blue-700 mt-1"><?php echo $today_logged; ?></p>
                                    <p class="text-[9px] text-blue-600/70 font-medium">Submitted today's log</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Student Progress Reports -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 no-print">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Student Progress Reports</h3>
                            <p class="text-xs text-gray-500 mt-1">Detailed list of students with total log hours and activity tracking status.</p>
                        </div>
                        <form method="GET" action="coordinator_reports.php" class="flex gap-2">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name..." class="bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs focus:ring-2 focus:ring-blue-500 outline-none w-56">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition-all">Search</button>
                            <?php if (!empty($search)): ?>
                                <a href="coordinator_reports.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Student</th>
                                <th class="px-6 py-4">Total Logs</th>
                                <th class="px-6 py-4">Total Hours</th>
                                <th class="px-6 py-4">Last Log Date</th>
                                <th class="px-6 py-4">Current Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-600">
                            <?php if (empty($student_reports)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">No students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_reports as $rep): 
                                    $student_logs = intval($rep['total_logs']);
                                    $last_log = $rep['last_log_date'] ? date('M d, Y', strtotime($rep['last_log_date'])) : 'Never';
                                    
                                    // Determine current status
                                    if (!$rep['last_log_date']) {
                                        $status_label = 'No Activity';
                                        $status_cls = 'bg-gray-100 text-gray-600 border-gray-200';
                                    } elseif (strtotime($rep['last_log_date']) >= strtotime('today')) {
                                        $status_label = 'Active Today';
                                        $status_cls = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                    } elseif (strtotime($rep['last_log_date']) >= strtotime('-7 days')) {
                                        $status_label = 'Active';
                                        $status_cls = 'bg-blue-50 text-blue-700 border-blue-200';
                                    } else {
                                        $status_label = 'Inactive';
                                        $status_cls = 'bg-red-50 text-red-600 border-red-200';
                                    }
                                ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($rep['full_name']); ?></p>
                                            <p class="text-xs text-gray-400 font-medium"><?php echo htmlspecialchars($rep['email']); ?></p>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-gray-900"><?php echo $student_logs; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded border border-blue-200"><?php echo number_format(floatval($rep['student_hours']), 1); ?> hrs</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-xs font-semibold text-gray-700"><?php echo $last_log; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?php echo $status_cls; ?>">
                                                <?php echo $status_label; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

            </div>
        </main>
        <script>
                // Sidebar Toggle Handler
                const toggleBtn = document.getElementById('sidebar-toggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        if (window.innerWidth < 768) {
                            document.body.classList.toggle('sidebar-open');
                            document.body.classList.remove('sidebar-closed');
                        } else {
                            document.body.classList.toggle('sidebar-closed');
                            document.body.classList.remove('sidebar-open');
                        }
                    });
                }
        </script>
</body>
</html>
