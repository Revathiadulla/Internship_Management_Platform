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

$notif_latest_res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'coordinator' ORDER BY created_at DESC LIMIT 5");
$latest_notifications = [];
while ($row = mysqli_fetch_assoc($notif_latest_res)) {
    $latest_notifications[] = $row;
}

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
$coordinator_id = intval($_SESSION['user_id']);

// Fetch coordinator's assigned subtypes dynamically
$assigned_subtypes = [];
$sub_stmt = mysqli_prepare($conn, "
    SELECT DISTINCT ps.subtype_name 
    FROM project_subtypes ps 
    JOIN coordinator_assignments ca ON ps.project_type_id = ca.project_type_id 
    WHERE ca.coordinator_id = ? AND ps.status = 'Active'
    ORDER BY ps.subtype_name ASC
");
if ($sub_stmt) {
    mysqli_stmt_bind_param($sub_stmt, "i", $coordinator_id);
    mysqli_stmt_execute($sub_stmt);
    $sub_res = mysqli_stmt_get_result($sub_stmt);
    while ($row = mysqli_fetch_assoc($sub_res)) {
        $assigned_subtypes[] = $row['subtype_name'];
    }
    mysqli_stmt_close($sub_stmt);
}

// Select default subtype
$selected_subtype = isset($_GET['subtype']) ? trim($_GET['subtype']) : '';
if (empty($selected_subtype) && !empty($assigned_subtypes)) {
    $selected_subtype = $assigned_subtypes[0];
}

$total_interns = 0;
$active_interns = 0;
$total_logs = 0;
$logged_today = 0;
$pending_logs = 0;
$completed_internships = 0;
$completion_pct = 0;
$open_projects = 0;
$assigned_pct = 0;
$pipeline_projects = [];
$recent_logs = [];

if (!empty($selected_subtype)) {
    // 1. Total interns for selected subtype
    $total_interns_stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT a.user_id) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.project_subtype = ?
    ");
    if ($total_interns_stmt) {
        mysqli_stmt_bind_param($total_interns_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($total_interns_stmt);
        $res = mysqli_stmt_get_result($total_interns_stmt);
        if ($row = mysqli_fetch_assoc($res)) $total_interns = intval($row['c']);
        mysqli_stmt_close($total_interns_stmt);
    }

    // 2. Active interns for selected subtype
    $active_interns_stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT a.user_id) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE a.status IN ('Started','Internship Started','Active Intern','Selected')
          AND i.coordinator_id = ? AND i.project_subtype = ?
    ");
    if ($active_interns_stmt) {
        mysqli_stmt_bind_param($active_interns_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($active_interns_stmt);
        $res = mysqli_stmt_get_result($active_interns_stmt);
        if ($row = mysqli_fetch_assoc($res)) $active_interns = intval($row['c']);
        mysqli_stmt_close($active_interns_stmt);
    }

    // 3. Total logs
    $total_logs_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as c 
        FROM daily_logs d
        JOIN internships i ON d.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.project_subtype = ?
    ");
    if ($total_logs_stmt) {
        mysqli_stmt_bind_param($total_logs_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($total_logs_stmt);
        $res = mysqli_stmt_get_result($total_logs_stmt);
        if ($row = mysqli_fetch_assoc($res)) $total_logs = intval($row['c']);
        mysqli_stmt_close($total_logs_stmt);
    }

    // 4. Logged today
    $logged_today_stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT d.user_id) as c 
        FROM daily_logs d
        JOIN internships i ON d.internship_id = i.id
        WHERE d.log_date = CURDATE() AND i.coordinator_id = ? AND i.project_subtype = ?
    ");
    if ($logged_today_stmt) {
        mysqli_stmt_bind_param($logged_today_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($logged_today_stmt);
        $res = mysqli_stmt_get_result($logged_today_stmt);
        if ($row = mysqli_fetch_assoc($res)) $logged_today = intval($row['c']);
        mysqli_stmt_close($logged_today_stmt);
    }

    $pending_logs = max(0, $active_interns - $logged_today);

    // 5. Completed internships
    $completed_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE a.status = 'Completed' AND i.coordinator_id = ? AND i.project_subtype = ?
    ");
    if ($completed_stmt) {
        mysqli_stmt_bind_param($completed_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($completed_stmt);
        $res = mysqli_stmt_get_result($completed_stmt);
        if ($row = mysqli_fetch_assoc($res)) $completed_internships = intval($row['c']);
        mysqli_stmt_close($completed_stmt);
    }

    $total_prog = $completed_internships + $active_interns;
    $completion_pct = $total_prog > 0 ? round(($completed_internships / $total_prog) * 100) : 0;

    // 6. Open projects
    $open_projects_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as c 
        FROM internships 
        WHERE status IN ('Active','Approved','Admin-Approved','Admin Approved')
          AND coordinator_id = ? AND project_subtype = ?
    ");
    if ($open_projects_stmt) {
        mysqli_stmt_bind_param($open_projects_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($open_projects_stmt);
        $res = mysqli_stmt_get_result($open_projects_stmt);
        if ($row = mysqli_fetch_assoc($res)) $open_projects = intval($row['c']);
        mysqli_stmt_close($open_projects_stmt);
    }

    $assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;

    // 7. Pipeline: fetch from confirmed project_teams + fallback internships
    $pipeline_sql = "
        SELECT p.id, p.title, p.project_subtype, p.duration, p.mode, p.status,
               p.mentor_name, p.team_name, p.assigned_count, p.source
        FROM (
            /* Source 1: Confirmed project_teams linked to internships */
            SELECT i.id,
                   COALESCE(i.title, MIN(t.team_name)) AS title,
                   COALESCE(i.project_subtype, MIN(t.project_subtype)) AS project_subtype,
                   i.duration,
                   i.mode,
                   CASE WHEN i.status IN ('Closed', 'Completed') THEN 'Completed' ELSE 'Active' END AS status,
                   COALESCE(MIN(mu.full_name), 'Mentor Not Assigned') AS mentor_name,
                   MIN(t.team_name) AS team_name,
                   COUNT(ptm.id) AS assigned_count,
                   'team' AS source
            FROM internships i
            JOIN project_teams t ON t.internship_id = i.id
            LEFT JOIN users mu ON t.mentor_id = mu.id
            LEFT JOIN project_team_members ptm ON ptm.project_team_id = t.id
            WHERE i.coordinator_id = ? AND i.project_subtype = ?
            GROUP BY i.id

            UNION ALL

            /* Source 2: Internships without any linked team or applications */
            SELECT i.id, i.title, i.project_subtype, i.duration, i.mode,
                   CASE WHEN i.status IN ('Closed', 'Completed') THEN 'Completed' ELSE 'Available' END AS status,
                   'Mentor Not Assigned' AS mentor_name,
                   NULL AS team_name,
                   0 AS assigned_count,
                   'internship' AS source
            FROM internships i
            WHERE i.status IN ('Approved', 'Admin-Approved', 'Admin Approved')
              AND i.coordinator_id = ? AND i.project_subtype = ?
              AND NOT EXISTS (SELECT 1 FROM project_teams pt WHERE pt.internship_id = i.id)
              AND NOT EXISTS (SELECT 1 FROM internship_applications a WHERE a.internship_id = i.id)
        ) p
        ORDER BY p.assigned_count DESC, p.id DESC
        LIMIT 12
    ";
    $pipeline_stmt = mysqli_prepare($conn, $pipeline_sql);
    if ($pipeline_stmt) {
        mysqli_stmt_bind_param($pipeline_stmt, "isis", $coordinator_id, $selected_subtype, $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($pipeline_stmt);
        $pipeline_res = mysqli_stmt_get_result($pipeline_stmt);
        $pipeline_projects = [];
    while ($proj = mysqli_fetch_assoc($pipeline_res)) {
        $id = $proj['id'];
        // If not already added, store it. Prefer the first occurrence (team source)
        if (!isset($pipeline_projects[$id])) {
            $pipeline_projects[$id] = $proj;
        }
    }
    // Re-index to numeric array for later foreach usage
    $pipeline_projects = array_values($pipeline_projects);
        mysqli_stmt_close($pipeline_stmt);
    }

    // 8. Recent Logs
    $logs_stmt = mysqli_prepare($conn, "
        SELECT d.tasks_completed, d.time_spent, d.focus_level, d.status,
               u.full_name,
               COALESCE(sp.course, 'Student') AS course,
               COALESCE(sp.college_name, 'University') AS college_name
        FROM daily_logs d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        JOIN internships i ON d.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.project_subtype = ?
        ORDER BY d.created_at DESC
        LIMIT 8
    ");
    if ($logs_stmt) {
        mysqli_stmt_bind_param($logs_stmt, "is", $coordinator_id, $selected_subtype);
        mysqli_stmt_execute($logs_stmt);
        $logs_res = mysqli_stmt_get_result($logs_stmt);
        while ($log = mysqli_fetch_assoc($logs_res)) $recent_logs[] = $log;
        mysqli_stmt_close($logs_stmt);
    }
}

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
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Coordinator Dashboard – IMP</title>
    <meta name="description" content="Internship Management Platform – Coordinator Dashboard" />
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
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
        };
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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
<body class="bg-gray-50 text-gray-900 antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200">

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
        <a href="coordinator_generate_test.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">quiz</span> Generate Test
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
        <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">help</span> Help Center
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
                    <?php if (empty($assigned_subtypes)): ?>
                        <option value="">No assigned subtypes</option>
                    <?php endif; ?>
                    <?php foreach ($assigned_subtypes as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($st === $selected_subtype) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- New Internship -->
            <button onclick="window.location.href='coordinator_internships.php'"
                class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-xl text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add</span> New Internship
            </button>
            <button onclick="window.location.href='manual_message.php'"
                class="flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-4 py-1.5 rounded-xl text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">chat</span> Send Message
            </button>
            <!-- Theme Switcher -->
            <button id="theme-toggle" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors rounded-full flex items-center justify-center cursor-pointer">
                <span class="material-symbols-outlined text-[20px]" id="theme-toggle-icon">dark_mode</span>
            </button>
            <!-- Notifications Bell -->
            <div class="relative mr-1" id="notifications-container-menu">
                <button id="notifications-menu-button" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative focus:outline-none cursor-pointer flex items-center justify-center">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                        <span class="font-bold text-xs text-gray-800">Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <a href="mark_notification_read.php?action=read_all&redirect=coordinator_dashboard.php" class="text-[10px] font-bold text-blue-600 hover:text-blue-800">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                        <?php if (empty($latest_notifications)): ?>
                            <div class="px-4 py-3 text-center text-xs text-gray-400">No notifications.</div>
                        <?php else: ?>
                            <?php foreach ($latest_notifications as $notif): ?>
                                <a href="coordinator_notifications.php" class="block px-4 py-2.5 hover:bg-gray-50 transition-colors">
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
                        <a href="coordinator_notifications.php" class="block py-2 text-xs font-bold text-blue-600 hover:text-blue-800">View all notifications</a>
                    </div>
                </div>
            </div>
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
                        <p class="text-xs text-gray-500 mt-0.5" id="overview-subtitle">Subtype: <?php echo htmlspecialchars($selected_subtype ?: 'None'); ?> &bull; <?php echo $active_interns; ?> Active Interns</p>
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
                        No internships found for this subtype.
                    </div>
                <?php else: ?>
                    <?php foreach ($pipeline_projects as $proj):
                        $subtype   = $proj['project_subtype'] ?: 'General';
                        $badge_cls = subtypeBadgeClass($subtype);
                        $assigned  = intval($proj['assigned_count']);
                        $proj_status = $proj['status'] ?: 'Active';
                        $status_cls = 'bg-green-50 text-green-700 border border-green-200';
                        if (stripos($proj_status, 'approved') !== false || stripos($proj_status, 'available') !== false) {
                            $status_cls = 'bg-blue-50 text-blue-700 border border-blue-200';
                        } elseif (stripos($proj_status, 'completed') !== false) {
                            $status_cls = 'bg-purple-50 text-purple-700 border border-purple-200';
                        } elseif (stripos($proj_status, 'confirmed') !== false) {
                            $status_cls = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                        } elseif (stripos($proj_status, 'pending') !== false) {
                            $status_cls = 'bg-amber-50 text-amber-700 border border-amber-200';
                        }
                        $display_title = $proj['title'] ?: 'Internship not assigned yet';
                        $has_team = !empty($proj['team_name']);
                    ?>
                    <div class="pipeline-card flex-shrink-0 w-[300px] bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                         <div>
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($subtype); ?>
                                </span>
                                <span class="text-[11px] font-bold px-2 py-0.5 <?php echo $status_cls; ?> rounded-full">
                                    <?php echo htmlspecialchars($proj_status); ?>
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 mt-2 leading-snug truncate" title="<?php echo htmlspecialchars($display_title); ?>">
                                <?php echo htmlspecialchars($display_title); ?>
                            </h3>
                            <?php if ($has_team): ?>
                            <p class="text-xs text-indigo-600 font-semibold mt-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">groups</span>
                                Team: <?php echo htmlspecialchars($proj['team_name']); ?>
                            </p>
                            <?php endif; ?>
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
                                <?php echo $assigned; ?> Students Assigned
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
profileBtn?.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('hidden'); if(notifDropdown) notifDropdown.classList.add('hidden'); });
document.addEventListener('click', () => profileDropdown?.classList.add('hidden'));

// ── Notifications dropdown ────────────────────────────────────────────────────
const notifBtn      = document.getElementById('notifications-menu-button');
const notifDropdown = document.getElementById('notifications-dropdown');
notifBtn?.addEventListener('click', e => { e.stopPropagation(); notifDropdown?.classList.toggle('hidden'); profileDropdown?.classList.add('hidden'); });
document.addEventListener('click', () => notifDropdown?.classList.add('hidden'));

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
let currentSubtype = '<?php echo addslashes($selected_subtype); ?>';

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
        const label = currentSubtype ? `Subtype: ${currentSubtype}` : 'None';
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
                    <span class="material-symbols-outlined mr-2 text-gray-300">inbox</span>No internships found for this subtype.</div>`;
            } else {
                // Deduplicate projects client‑side, prefer entries with a team assignment
                const projMap = {};
                data.pipeline_projects.forEach(p => {
                    const id = p.id;
                    if (!projMap[id] || (p.team_name && !projMap[id].team_name)) {
                        projMap[id] = p;
                    }
                });
                const projects = Object.values(projMap);
                grid.innerHTML = projects.map(p => {
                    const subtype = p.project_subtype || 'General';
                    const badgeCls = subtypeBadgeCls(subtype);
                    const assigned = parseInt(p.assigned_count) || 0;
                    const avatars = Array.from({length: Math.min(3, assigned)}, (_, i) =>
                        `<img class="w-7 h-7 rounded-full border-2 border-white shadow-sm"
                              src="https://ui-avatars.com/api/?name=I${i+1}&background=2563eb&color=fff&size=64" alt="Intern">`
                    ).join('');
                    const extra = assigned > 3 ? `<div class="w-7 h-7 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[9px] font-bold text-gray-500">+${assigned - 3}</div>` : '';
                    const assignedCls = assigned > 0 ? 'text-blue-600' : 'text-gray-400';
                    const pStatus = p.status || 'Active';
                    let statusCls = 'bg-green-50 text-green-700 border border-green-200';
                    if (/approved/i.test(pStatus) || /available/i.test(pStatus)) statusCls = 'bg-blue-50 text-blue-700 border border-blue-200';
                    else if (/completed/i.test(pStatus)) statusCls = 'bg-purple-50 text-purple-700 border border-purple-200';
                    else if (/confirmed/i.test(pStatus)) statusCls = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                    else if (/pending/i.test(pStatus)) statusCls = 'bg-amber-50 text-amber-700 border border-amber-200';
                    const displayTitle = p.title || 'Internship not assigned yet';
                    const teamHtml = p.team_name ? `<p class="text-xs text-indigo-600 font-semibold mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">groups</span>Team: ${h(p.team_name)}</p>` : '';
                    return `<div class="pipeline-card flex-shrink-0 w-[300px] bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full ${badgeCls}">${h(subtype)}</span>
                                <span class="text-[11px] font-bold px-2 py-0.5 ${statusCls} rounded-full">${h(pStatus)}</span>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 mt-2 truncate" title="${h(displayTitle)}">${h(displayTitle)}</h3>
                            ${teamHtml}
                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Duration:</span> ${h(p.duration||'N/A')} &bull; <span class="font-semibold text-gray-700">Mode:</span> ${h(p.mode||'N/A')}</p>
                                <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Mentor:</span> ${h(p.mentor_name||'Not assigned')}</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                            <div class="flex -space-x-2">${avatars}${extra}</div>
                            <span class="text-xs font-bold ${assignedCls}">${assigned} Students Assigned</span>
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