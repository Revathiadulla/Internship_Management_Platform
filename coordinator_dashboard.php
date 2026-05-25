<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$notif_success = "";
$notif_error = "";

// Handle Bulk Notification POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_notif_action'])) {
    $notif_title    = trim($_POST['notif_title'] ?? '');
    $notif_message  = trim($_POST['notif_message'] ?? '');
    $recipient_type = trim($_POST['recipient_type'] ?? 'all');
    $priority       = trim($_POST['priority'] ?? 'Normal');
    $selected_students = isset($_POST['notif_students']) ? $_POST['notif_students'] : [];
    $selected_team  = trim($_POST['notif_team'] ?? '');

    if (empty($notif_title) || empty($notif_message)) {
        $notif_error = "Please fill in both Title and Message.";
    } else {
        $type_label  = ($priority === 'Urgent') ? 'Urgent' : (($priority === 'Important') ? 'Important' : 'Notification');
        $full_message = "[" . htmlspecialchars($notif_title) . "] " . htmlspecialchars($notif_message);
        $target_user_ids = [];

        if ($recipient_type === 'all') {
            $res = mysqli_query($conn, "SELECT id FROM users WHERE role='student'");
            while ($r = mysqli_fetch_assoc($res)) $target_user_ids[] = intval($r['id']);
        } elseif ($recipient_type === 'active') {
            $res = mysqli_query($conn, "SELECT DISTINCT user_id FROM internship_applications WHERE status IN ('Started','Internship Started','Active Intern','Selected')");
            while ($r = mysqli_fetch_assoc($res)) $target_user_ids[] = intval($r['user_id']);
        } elseif ($recipient_type === 'selected') {
            foreach ($selected_students as $sid) $target_user_ids[] = intval($sid);
        } elseif ($recipient_type === 'team' && !empty($selected_team)) {
            $team_stmt = mysqli_prepare($conn, "SELECT DISTINCT user_id FROM internship_applications WHERE team_name = ?");
            mysqli_stmt_bind_param($team_stmt, "s", $selected_team);
            mysqli_stmt_execute($team_stmt);
            $team_res = mysqli_stmt_get_result($team_stmt);
            while ($r = mysqli_fetch_assoc($team_res)) $target_user_ids[] = intval($r['user_id']);
            mysqli_stmt_close($team_stmt);
        }

        if (empty($target_user_ids)) {
            $notif_error = "No recipients found for the selected group.";
        } else {
            $insert_stmt = mysqli_prepare($conn, "INSERT INTO student_notifications (user_id, type, message, is_read) VALUES (?, ?, ?, 0)");
            $sent_count = 0;
            foreach ($target_user_ids as $uid) {
                mysqli_stmt_bind_param($insert_stmt, "iss", $uid, $type_label, $full_message);
                if (mysqli_stmt_execute($insert_stmt)) $sent_count++;
            }
            mysqli_stmt_close($insert_stmt);
            $notif_success = "Notification sent to $sent_count student(s)!";
        }
    }
}

// ── PHP-side metrics (no AJAX on load) ────────────────────────────────────────
$total_interns = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='student'"))['c'] ?? 0);

$active_interns = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT user_id) as c FROM internship_applications WHERE status IN ('Started','Internship Started','Active Intern','Selected')"))['c'] ?? 0);

$total_logs = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM daily_logs"))['c'] ?? 0);

$logged_today = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT user_id) as c FROM daily_logs WHERE log_date = CURDATE()"))['c'] ?? 0);

$pending_logs = max(0, $active_interns - $logged_today);

$completed_internships = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM internship_applications WHERE status = 'Completed'"))['c'] ?? 0);

$total_prog = $completed_internships + $active_interns;
$completion_pct = $total_prog > 0 ? round(($completed_internships / $total_prog) * 100) : 0;

$open_projects = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM internships WHERE status = 'Active'"))['c'] ?? 0);

$assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;

// Pipeline: optimised single query with subquery for assigned count
$pipeline_res = mysqli_query($conn, "
    SELECT i.id, i.title, i.project_subtype, i.duration, i.mode, i.status,
           u.full_name AS mentor_name,
           (SELECT COUNT(*) FROM internship_applications a
            WHERE a.internship_id = i.id
              AND a.status IN ('Started','Internship Started','Active Intern','Selected')) AS assigned_count
    FROM internships i
    LEFT JOIN users u ON i.assigned_mentor = u.id
    WHERE i.status = 'Active'
    ORDER BY i.id DESC
    LIMIT 12
");
$pipeline_projects = [];
while ($proj = mysqli_fetch_assoc($pipeline_res)) $pipeline_projects[] = $proj;

// Recent Logs
$logs_res = mysqli_query($conn, "
    SELECT d.tasks_completed, d.time_spent, d.focus_level, d.status,
           u.full_name,
           COALESCE(sp.course, 'Student') AS course,
           COALESCE(sp.college_name, 'University') AS college_name
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    ORDER BY d.created_at DESC
    LIMIT 8
");
$recent_logs = [];
while ($log = mysqli_fetch_assoc($logs_res)) $recent_logs[] = $log;

// Header user
$header_uid = $_SESSION['user_id'];
$header_res  = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Coordinator';
$header_photo = $header_user['profile_photo'] ?? '';

// Students & teams for bulk notification
$notif_students_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name ASC");
$notif_students_list = [];
while ($r = mysqli_fetch_assoc($notif_students_res)) $notif_students_list[] = $r;

$notif_teams_res = mysqli_query($conn, "SELECT DISTINCT team_name FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' ORDER BY team_name ASC");
$notif_teams_list = [];
while ($r = mysqli_fetch_assoc($notif_teams_res)) $notif_teams_list[] = $r['team_name'];

// Helper: subtype badge colour
function subtypeBadgeClass($subtype) {
    $s = strtolower($subtype ?? '');
    if (str_contains($s, 'web'))     return 'bg-blue-50 text-blue-700 border border-blue-200';
    if (str_contains($s, 'design') || str_contains($s, 'ui') || str_contains($s, 'ux'))
                                      return 'bg-purple-50 text-purple-700 border border-purple-200';
    if (str_contains($s, 'mobile') || str_contains($s, 'apps'))
                                      return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    if (str_contains($s, 'backend') || str_contains($s, 'system'))
                                      return 'bg-amber-50 text-amber-700 border border-amber-200';
    if (str_contains($s, 'market') || str_contains($s, 'seo') || str_contains($s, 'social') || str_contains($s, 'content'))
                                      return 'bg-green-50 text-green-700 border border-green-200';
    return 'bg-slate-50 text-slate-600 border border-slate-200';
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Coordinator Dashboard – IMP</title>
    <meta name="description" content="Internship Management Platform – Coordinator Dashboard" />
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#2563eb',
                        'primary-dark': '#1d4ed8',
                        surface: '#f8f9fa',
                        card: '#ffffff',
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        aside { transition: transform 0.25s ease; }
        main  { transition: margin-left 0.25s ease; min-width: 0; }
        @media (max-width: 767px) {
            aside { transform: translateX(-100%); }
            main  { margin-left: 0 !important; }
            body.sidebar-open aside { transform: translateX(0); }
        }
        @media (min-width: 768px) {
            body.sidebar-closed aside { transform: translateX(-100%); }
            body.sidebar-closed main  { margin-left: 0 !important; }
        }
        .pipeline-scroll::-webkit-scrollbar { height: 6px; }
        .pipeline-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
        .pipeline-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .pipeline-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .pipeline-card { transition: box-shadow 0.15s ease, transform 0.15s ease; }
        .pipeline-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.1); transform: translateY(-1px); }
        @keyframes fade-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fade-in 0.3s ease both; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-white border-r border-gray-200 flex flex-col py-6">
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
        <a href="coordinator_dashboard.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
            <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
        </a>
        <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">work</span> Postings
        </a>
        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">group</span> Candidates
        </a>
        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
        </a>
        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
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
        <a href="logout.php" class="flex items-center gap-3 text-red-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
            <span class="material-symbols-outlined text-[20px]">logout</span> Logout
        </a>
    </div>
</aside>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<main class="ml-60 flex flex-col min-h-screen">

    <!-- TOP HEADER -->
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
        <div class="flex items-center gap-4">
            <button id="sidebar-toggle" class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                <span class="material-symbols-outlined text-gray-600">menu</span>
            </button>
            <div>
                <h1 class="text-base font-bold text-gray-800 leading-none">Coordinator Dashboard</h1>
                <p class="text-xs text-gray-500 mt-0.5"><?php echo $active_interns; ?> active interns &bull; <?php echo date('D, d M Y'); ?></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <!-- Subtype Filter -->
            <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-1.5 shadow-sm">
                <span class="material-symbols-outlined text-blue-600 text-[16px]">category</span>
                <select id="subtype-filter" onchange="handleSubtypeChange(this.value)"
                    class="bg-transparent border-none outline-none text-sm font-semibold text-slate-700 cursor-pointer min-w-[140px]">
                    <option value="">All Subtypes</option>
                    <option value="Web Development">Web Development</option>
                    <option value="Mobile Apps">Mobile Apps</option>
                    <option value="Backend Systems">Backend Systems</option>
                    <option value="UI/UX Design">UI/UX Design</option>
                    <option value="Graphic Design">Graphic Design</option>
                    <option value="Product Design">Product Design</option>
                    <option value="SEO Campaigns">SEO Campaigns</option>
                    <option value="Social Media Strategy">Social Media Strategy</option>
                    <option value="Content Marketing">Content Marketing</option>
                </select>
            </div>
            <!-- Bulk Notification -->
            <button onclick="openBulkNotifModal()"
                class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">mail</span> Notify
            </button>
            <!-- New Internship -->
            <button onclick="window.location.href='coordinator_internships.php'"
                class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-xl text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add</span> New Internship
            </button>
            <!-- Profile -->
            <div class="relative" id="profile-container">
                <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
                        <?php echo htmlspecialchars($header_name); ?>
                    </span>
                    <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
                        <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                            <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=2563eb&color=fff" alt="Profile" class="w-full h-full object-cover">
                        <?php endif; ?>
                    </div>
                    <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
                </button>
                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                    <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <span class="material-symbols-outlined text-gray-400 text-[18px]">account_circle</span> My Profile
                    </a>
                    <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <span class="material-symbols-outlined text-gray-400 text-[18px]">settings</span> Settings
                    </a>
                    <hr class="my-1 border-gray-100">
                    <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- ════════ DASHBOARD BODY ════════ -->
    <div class="p-6 space-y-5 flex-1">

        <!-- ── SECTION 1: Global Intern Overview + Project Filling ── -->
        <div class="flex flex-col lg:flex-row gap-4 items-stretch fade-in">

            <!-- Global Intern Overview (left, ~70%) -->
            <div class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Global Intern Overview</h2>
                        <p class="text-xs text-gray-500 mt-0.5" id="overview-subtitle">All Internships &bull; <?php echo $active_interns; ?> Active Interns</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">On Track</span>
                        <?php if ($pending_logs > 0): ?>
                        <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold"><?php echo $pending_logs; ?> Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <!-- Total Logs -->
                    <div class="stat-card p-4 bg-blue-50 rounded-xl border-l-4 border-blue-500">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Total Logs</p>
                        <p class="text-2xl font-black text-blue-700 mt-1" id="stat-total-logs"><?php echo $total_logs; ?></p>
                    </div>
                    <!-- Active Interns -->
                    <div class="stat-card p-4 bg-red-50 rounded-xl border-l-4 border-red-500">
                        <p class="text-xs font-bold text-red-600 uppercase tracking-wide">Active Interns</p>
                        <p class="text-2xl font-black text-red-600 mt-1" id="stat-active-interns"><?php echo $active_interns; ?></p>
                    </div>
                    <!-- Completion % -->
                    <div class="stat-card p-4 bg-green-50 rounded-xl border-l-4 border-green-500">
                        <p class="text-xs font-bold text-green-600 uppercase tracking-wide">Completion</p>
                        <p class="text-2xl font-black text-green-700 mt-1" id="stat-completion-pct"><?php echo $completion_pct; ?>%</p>
                    </div>
                    <!-- Pending Logs -->
                    <div class="stat-card p-4 bg-amber-50 rounded-xl border-l-4 border-amber-500">
                        <p class="text-xs font-bold text-amber-600 uppercase tracking-wide">Pending Logs</p>
                        <p class="text-2xl font-black text-amber-600 mt-1" id="stat-pending-logs"><?php echo $pending_logs; ?></p>
                    </div>
                </div>
            </div>

            <!-- Project Filling (right, ~30%) -->
            <div class="w-full lg:w-72 shrink-0 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl shadow-sm p-5 text-white relative overflow-hidden flex flex-col justify-between">
                <div class="relative z-10">
                    <h2 class="text-base font-bold mb-4">Project Filling</h2>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm font-semibold mb-1.5">
                                <span>Active Interns</span>
                                <span id="stat-assigned-ratio"><?php echo "$active_interns/$total_interns"; ?></span>
                            </div>
                            <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                <div class="bg-white h-full rounded-full transition-all duration-500" id="stat-assigned-bar" style="width: <?php echo $assigned_pct; ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm font-semibold mb-1.5">
                                <span>Open Projects</span>
                                <span id="stat-open-projects"><?php echo $open_projects; ?></span>
                            </div>
                            <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                <div class="bg-white h-full rounded-full transition-all duration-500" id="stat-open-projects-bar" style="width: <?php echo min(100, $open_projects * 10); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <button onclick="window.location.href='coordinator_teams.php'"
                    class="relative z-10 w-full mt-5 py-2.5 bg-white text-blue-700 rounded-xl text-sm font-bold hover:bg-blue-50 transition-colors cursor-pointer">
                    Review Assignments
                </button>
                <div class="absolute -right-10 -bottom-10 opacity-10">
                    <span class="material-symbols-outlined text-[160px]" style="font-variation-settings:'FILL' 1;">account_tree</span>
                </div>
            </div>
        </div>

        <!-- ── SECTION 2: Pipeline ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden fade-in">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 text-[18px]">account_tree</span>
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Project Assignment Pipeline</h2>
                </div>
                <a href="coordinator_teams.php" class="text-blue-600 text-sm font-semibold hover:underline">View All →</a>
            </div>
            <div class="p-4 flex flex-row gap-4 overflow-x-auto pipeline-scroll pb-5" id="pipeline-grid" style="min-height: 160px;">
                <?php if (empty($pipeline_projects)): ?>
                    <div class="flex items-center justify-center w-full py-10 text-gray-400 text-sm font-medium">
                        <span class="material-symbols-outlined mr-2 text-gray-300">inbox</span>
                        No active projects found. <a href="coordinator_internships.php" class="ml-1 text-blue-600 underline">Add one →</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($pipeline_projects as $proj):
                        $subtype   = $proj['project_subtype'] ?: 'General';
                        $badge_cls = subtypeBadgeClass($subtype);
                        $assigned  = intval($proj['assigned_count']);
                    ?>
                    <div class="pipeline-card flex-shrink-0 w-[300px] bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($subtype); ?>
                                </span>
                                <span class="text-[11px] font-bold px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 rounded-full">
                                    <?php echo htmlspecialchars($proj['status']); ?>
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 mt-2 leading-snug truncate" title="<?php echo htmlspecialchars($proj['title']); ?>">
                                <?php echo htmlspecialchars($proj['title']); ?>
                            </h3>
                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-500">
                                    <span class="font-semibold text-gray-700">Duration:</span> <?php echo htmlspecialchars($proj['duration'] ?: 'N/A'); ?>
                                    &nbsp;&bull;&nbsp;
                                    <span class="font-semibold text-gray-700">Mode:</span> <?php echo htmlspecialchars($proj['mode'] ?: 'N/A'); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-semibold text-gray-700">Mentor:</span> <?php echo htmlspecialchars($proj['mentor_name'] ?: 'Not assigned'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                            <div class="flex -space-x-2">
                                <?php for ($i = 0; $i < min(3, $assigned); $i++): ?>
                                    <img class="w-7 h-7 rounded-full border-2 border-white shadow-sm"
                                         src="https://ui-avatars.com/api/?name=I<?php echo $i+1; ?>&background=2563eb&color=fff&size=64" alt="Intern">
                                <?php endfor; ?>
                                <?php if ($assigned > 3): ?>
                                    <div class="w-7 h-7 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[9px] font-bold text-gray-500">
                                        +<?php echo $assigned - 3; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs font-bold <?php echo $assigned > 0 ? 'text-blue-600' : 'text-gray-400'; ?>">
                                <?php echo $assigned; ?> Assigned
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SECTION 3: Internship Monitoring & Logs ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden fade-in">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 text-[18px]">monitoring</span>
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Internship Monitoring &amp; Logs</h2>
                </div>
                <div class="flex gap-2">
                    <a href="coordinator_daily_logs.php" class="text-blue-600 text-sm font-semibold hover:underline">View All Logs →</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Intern</th>
                            <th class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Tasks Completed</th>
                            <th class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Focus Level</th>
                            <th class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Hours</th>
                            <th class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body" class="divide-y divide-gray-100">
                        <?php if (empty($recent_logs)): ?>
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                                <span class="material-symbols-outlined block mx-auto text-gray-200 text-[40px] mb-2">inbox</span>
                                No daily logs submitted yet.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log):
                                $time_spent    = floatval($log['time_spent']);
                                $progress_pct  = min(100, round(($time_spent / 8.0) * 100));
                                $focus         = $log['focus_level'] ?? 'Medium';
                                $focus_cls     = match(strtolower($focus)) {
                                    'high'   => 'bg-green-100 text-green-700',
                                    'low'    => 'bg-red-100 text-red-700',
                                    default  => 'bg-amber-100 text-amber-700',
                                };
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
                                            <?php echo strtoupper(substr($log['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($log['full_name']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($log['course']); ?> &bull; <?php echo htmlspecialchars($log['college_name']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($log['tasks_completed']); ?>">
                                    <?php echo htmlspecialchars(mb_strimwidth($log['tasks_completed'], 0, 45, '…')); ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $focus_cls; ?>">
                                        <?php echo htmlspecialchars($focus); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-blue-500 h-full rounded-full" style="width: <?php echo $progress_pct; ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo $time_spent; ?>h</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="coordinator_daily_logs.php" class="text-blue-600 text-xs font-bold hover:underline">Details →</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /dashboard body -->

</main>

<!-- ════════════════ BULK NOTIFICATION MODAL ════════════════ -->
<div id="bulk-notif-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4 flex">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold flex items-center gap-2">
                <span class="material-symbols-outlined">campaign</span> Bulk Notification
            </h3>
            <button onclick="closeBulkNotifModal()" class="text-white/80 hover:text-white cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="coordinator_dashboard.php" class="p-6 space-y-4">
            <input type="hidden" name="bulk_notif_action" value="1">
            <div>
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Notification Title</label>
                <input type="text" name="notif_title" required placeholder="e.g. Daily Log Reminder"
                    class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Message</label>
                <textarea name="notif_message" required rows="3" placeholder="Type your notification message..."
                    class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Recipients</label>
                    <select name="recipient_type" id="notif-recipient-type" onchange="toggleRecipientOptions()"
                        class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
                        <option value="all">All Students</option>
                        <option value="active">Active Interns</option>
                        <option value="selected">Selected Students</option>
                        <option value="team">By Project Team</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Priority</label>
                    <select name="priority" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
                        <option value="Normal">Normal</option>
                        <option value="Important">Important</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div id="notif-students-container" class="hidden">
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Select Students</label>
                <div class="border border-gray-200 rounded-lg p-3 bg-gray-50 max-h-40 overflow-y-auto space-y-2">
                    <?php foreach ($notif_students_list as $ns): ?>
                    <label class="flex items-center gap-2 text-xs text-gray-700 font-medium cursor-pointer py-0.5 hover:bg-gray-100 rounded">
                        <input type="checkbox" name="notif_students[]" value="<?php echo $ns['id']; ?>" class="rounded border-gray-300 text-blue-600 cursor-pointer">
                        <span class="font-bold"><?php echo htmlspecialchars($ns['full_name']); ?></span>
                        <span class="text-gray-400"><?php echo htmlspecialchars($ns['email']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="notif-team-container" class="hidden">
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Select Team</label>
                <select name="notif_team" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
                    <option value="">Choose a team...</option>
                    <?php foreach ($notif_teams_list as $tn): ?>
                    <option value="<?php echo htmlspecialchars($tn); ?>"><?php echo htmlspecialchars($tn); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeBulkNotifModal()"
                    class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 cursor-pointer">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm cursor-pointer flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">send</span> Send
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toasts -->
<?php if (!empty($notif_success)): ?>
<div id="notif-toast" class="fixed top-6 right-6 z-[60] bg-emerald-600 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-bold flex items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span> <?php echo htmlspecialchars($notif_success); ?>
</div>
<?php endif; ?>
<?php if (!empty($notif_error)): ?>
<div id="notif-toast" class="fixed top-6 right-6 z-[60] bg-red-600 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-bold flex items-center gap-2">
    <span class="material-symbols-outlined">error</span> <?php echo htmlspecialchars($notif_error); ?>
</div>
<?php endif; ?>

<!-- ════════════════ SCRIPTS ════════════════ -->
<script>
// ── Profile dropdown ──────────────────────────────────────────────────────────
const profileBtn      = document.getElementById('profile-menu-button');
const profileDropdown = document.getElementById('profile-dropdown');
profileBtn?.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('hidden'); });
document.addEventListener('click', () => profileDropdown?.classList.add('hidden'));

// ── Sidebar toggle ────────────────────────────────────────────────────────────
document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
    if (window.innerWidth < 768) {
        document.body.classList.toggle('sidebar-open');
        document.body.classList.remove('sidebar-closed');
    } else {
        document.body.classList.toggle('sidebar-closed');
        document.body.classList.remove('sidebar-open');
    }
});

// ── Bulk notification modal ───────────────────────────────────────────────────
function openBulkNotifModal()  { document.getElementById('bulk-notif-modal').classList.remove('hidden'); }
function closeBulkNotifModal() { document.getElementById('bulk-notif-modal').classList.add('hidden'); }
function toggleRecipientOptions() {
    const type = document.getElementById('notif-recipient-type').value;
    document.getElementById('notif-students-container').classList.toggle('hidden', type !== 'selected');
    document.getElementById('notif-team-container').classList.toggle('hidden', type !== 'team');
}

// ── Auto-dismiss toast ────────────────────────────────────────────────────────
const toast = document.getElementById('notif-toast');
if (toast) {
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// ── Subtype filter → AJAX update ─────────────────────────────────────────────
let currentSubtype = '';

async function handleSubtypeChange(subtype) {
    currentSubtype = subtype;
    await fetchFilteredData();
}

async function fetchFilteredData() {
    const overviewSubtitle = document.getElementById('overview-subtitle');
    if (overviewSubtitle) overviewSubtitle.textContent = 'Loading…';

    // dim stats
    ['stat-total-logs','stat-active-interns','stat-completion-pct','stat-pending-logs'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.opacity = '0.4';
    });

    try {
        const url = `coordinator_analytics_api.php?subtype=${encodeURIComponent(currentSubtype)}&internship_id=0`;
        const res  = await fetch(url);
        const data = await res.json();
        if (data.error) { console.error(data.error); return; }
        updateDashboard(data);
    } catch(e) {
        console.error('Analytics fetch failed:', e);
    } finally {
        ['stat-total-logs','stat-active-interns','stat-completion-pct','stat-pending-logs'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.opacity = '1';
        });
    }
}

function updateDashboard(data) {
    // Subtitle
    const sub = document.getElementById('overview-subtitle');
    if (sub) {
        const label = currentSubtype ? `Subtype: ${currentSubtype}` : 'All Internships';
        sub.textContent = `${label} • ${data.active_interns} Active Interns`;
    }

    // Stat cards
    animateCount('stat-total-logs',    data.total_logs);
    animateCount('stat-active-interns', data.active_interns);
    animateCount('stat-pending-logs',  data.pending_logs);
    setTextFade('stat-completion-pct', data.completion_pct + '%');

    // Project Filling
    const ar = document.getElementById('stat-assigned-ratio');
    if (ar) ar.textContent = `${data.active_interns}/${data.total_interns}`;
    const ab = document.getElementById('stat-assigned-bar');
    if (ab) ab.style.width = data.assigned_pct + '%';
    const op = document.getElementById('stat-open-projects');
    if (op) op.textContent = data.open_projects;
    const ob = document.getElementById('stat-open-projects-bar');
    if (ob) ob.style.width = Math.min(100, data.open_projects * 10) + '%';

    // Pipeline
    const grid = document.getElementById('pipeline-grid');
    if (grid) {
        if (!data.pipeline_projects?.length) {
            grid.innerHTML = `<div class="flex items-center justify-center w-full py-10 text-gray-400 text-sm font-medium">
                <span class="material-symbols-outlined mr-2 text-gray-300">inbox</span>No active projects found.</div>`;
        } else {
            grid.innerHTML = data.pipeline_projects.map(p => {
                const subtype  = p.project_subtype || 'General';
                const badgeCls = subtypeBadgeCls(subtype);
                const assigned = parseInt(p.assigned_count) || 0;
                const avatars  = Array.from({length: Math.min(3, assigned)}, (_, i) =>
                    `<img class="w-7 h-7 rounded-full border-2 border-white shadow-sm"
                          src="https://ui-avatars.com/api/?name=I${i+1}&background=2563eb&color=fff&size=64" alt="Intern">`
                ).join('');
                const extra    = assigned > 3
                    ? `<div class="w-7 h-7 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[9px] font-bold text-gray-500">+${assigned - 3}</div>` : '';
                const assignedCls = assigned > 0 ? 'text-blue-600' : 'text-gray-400';
                return `<div class="pipeline-card flex-shrink-0 w-[300px] bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-2">
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full ${badgeCls}">${h(subtype)}</span>
                            <span class="text-[11px] font-bold px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 rounded-full">${h(p.status)}</span>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mt-2 truncate" title="${h(p.title)}">${h(p.title)}</h3>
                        <div class="mt-2 space-y-1">
                            <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Duration:</span> ${h(p.duration||'N/A')} &bull; <span class="font-semibold text-gray-700">Mode:</span> ${h(p.mode||'N/A')}</p>
                            <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Mentor:</span> ${h(p.mentor_name||'Not assigned')}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                        <div class="flex -space-x-2">${avatars}${extra}</div>
                        <span class="text-xs font-bold ${assignedCls}">${assigned} Assigned</span>
                    </div>
                </div>`;
            }).join('');
        }
    }

    // Logs table
    const tbody = document.getElementById('logs-table-body');
    if (tbody) {
        if (!data.recent_logs?.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">No daily logs submitted yet.</td></tr>`;
        } else {
            tbody.innerHTML = data.recent_logs.map(log => {
                const pct  = Math.min(100, Math.round((log.time_spent / 8) * 100));
                const fl   = (log.focus_level || 'Medium').toLowerCase();
                const fcls = fl === 'high' ? 'bg-green-100 text-green-700' : fl === 'low' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';
                return `<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
                                ${h(log.full_name.charAt(0).toUpperCase())}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">${h(log.full_name)}</p>
                                <p class="text-xs text-gray-400">${h(log.course)} &bull; ${h(log.college_name)}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-600 max-w-xs truncate">${h(log.tasks_completed)}</td>
                    <td class="px-5 py-3.5"><span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${fcls}">${h(log.focus_level||'Medium')}</span></td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <div class="w-20 bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-blue-500 h-full rounded-full" style="width:${pct}%"></div>
                            </div>
                            <span class="text-xs text-gray-500">${log.time_spent}h</span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-right"><a href="coordinator_daily_logs.php" class="text-blue-600 text-xs font-bold hover:underline">Details →</a></td>
                </tr>`;
            }).join('');
        }
    }
}

function subtypeBadgeCls(s) {
    s = (s || '').toLowerCase();
    if (/web/.test(s))                            return 'bg-blue-50 text-blue-700 border border-blue-200';
    if (/design|ui|ux/.test(s))                   return 'bg-purple-50 text-purple-700 border border-purple-200';
    if (/mobile|apps/.test(s))                    return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    if (/backend|system/.test(s))                 return 'bg-amber-50 text-amber-700 border border-amber-200';
    if (/market|seo|social|content/.test(s))      return 'bg-green-50 text-green-700 border border-green-200';
    return 'bg-slate-50 text-slate-600 border border-slate-200';
}

function animateCount(id, target) {
    const el = document.getElementById(id);
    if (!el) return;
    const start = parseInt(el.textContent) || 0;
    const diff  = target - start;
    const steps = 20;
    let step = 0;
    const t = setInterval(() => {
        step++;
        el.textContent = Math.round(start + diff * step / steps);
        if (step >= steps) { el.textContent = target; clearInterval(t); }
    }, 20);
}

function setTextFade(id, text) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.opacity = '0';
    setTimeout(() => { el.textContent = text; el.style.opacity = '1'; el.style.transition = 'opacity 0.2s'; }, 150);
}

function h(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>