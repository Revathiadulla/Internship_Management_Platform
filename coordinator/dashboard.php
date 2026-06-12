<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/progress_helper.php';
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
            $res = mysqli_query($conn, "SELECT DISTINCT user_id FROM internship_applications WHERE status IN ('Started','Internship Started','Active Intern','Internship Active','Selected')");
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

require_once __DIR__ . '/../includes/coordinator_access.php';
$coord_assignments = get_coordinator_assignments($conn, $coordinator_id);
$assigned_subtypes = $coord_assignments['subtype_names'] ?? [];
$assigned_types = $coord_assignments['type_names'] ?? [];

// Select default subtype
$selected_subtype = isset($_GET['subtype']) ? trim($_GET['subtype']) : '';
if (empty($selected_subtype) && !empty($assigned_subtypes)) {
    $selected_subtype = $assigned_subtypes[0];
} elseif (!empty($selected_subtype) && !in_array($selected_subtype, $assigned_subtypes)) {
    // Prevent accessing unassigned subtypes
    $selected_subtype = ''; 
}

if (!function_exists('executeSafeCountQuery')) {
    function executeSafeCountQuery($conn, $query, $metric_name) {
        $res = mysqli_query($conn, $query);
        if (!$res) {
            error_log("IMP Coordinator Dashboard Error ($metric_name): " . mysqli_error($conn));
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        return intval($row['c'] ?? 0);
    }
}

$created_projects_count = 0;
$pending_applications_count = 0;
$selected_students_count = 0;
$assigned_teams_count = 0;
$active_projects_count = 0;
$assigned_mentors_count = 0;
$pending_logs = 0;
$unread_notifications_count = $unread_count;

$total_interns = 0;
$active_interns = 0;
$total_logs = 0;
$logged_today = 0;
$completed_internships = 0;
$completion_pct = 0;
$open_projects = 0;
$assigned_pct = 0;
$pipeline_projects = [];
$recent_logs = [];
$recent_activities = [];

if (empty($assigned_subtypes)) {
    // User has no assignments. We'll show a friendly empty state later in HTML.
    $selected_subtype = '';
} else {
    // Build filter condition
    $filter_cond = "i.coordinator_id = $coordinator_id";
    if (!empty($selected_subtype)) {
        $filter_cond .= " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'";
    }

    // Filter for teams (to catch teams created by this coordinator even if project owner is different)
    $team_filter_cond = "(t.created_by = $coordinator_id OR i.coordinator_id = $coordinator_id)";
    if (!empty($selected_subtype)) {
        $team_filter_cond .= " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'";
    }

    // 1. Created Projects Count
    $created_projects_count = executeSafeCountQuery($conn, "SELECT COUNT(*) as c FROM internships i WHERE $filter_cond AND i.is_deleted = 0", 'Created Projects');

    // 2. Pending Applications Count
    $pending_applications_count = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE $filter_cond 
          AND a.status IN ('Applied', 'Pending', 'Verified', 'HR Review', 'Shortlisted', 'HOD Pending', 'HOD Approval Pending', 'Forwarded to HOD', 'HOD Approved')
          AND i.is_deleted = 0
    ", 'Pending Applications');

    // 3. Assigned Students Count (Replaces Selected Students)
    $selected_students_count = executeSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT ptm.student_id) as c 
        FROM project_team_members ptm
        JOIN project_teams t ON ptm.project_team_id = t.id
        LEFT JOIN internships i ON t.internship_id = i.id
        WHERE $team_filter_cond AND i.is_deleted = 0
    ", 'Assigned Students');

    // 4. Assigned Teams Count
    $assigned_teams_count = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM project_teams t
        LEFT JOIN internships i ON t.internship_id = i.id
        WHERE $team_filter_cond AND i.is_deleted = 0
    ", 'Assigned Teams');

    // 5. Active Projects Count
    $active_projects_count = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM internships i
        WHERE $filter_cond 
          AND i.status IN ('Active', 'Approved', 'Admin-Approved') 
          AND i.is_deleted = 0
    ", 'Active Projects');

    // 6. Assigned Mentors Count
    $assigned_mentors_count = executeSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT t.mentor_id) as c 
        FROM project_teams t
        LEFT JOIN internships i ON t.internship_id = i.id
        WHERE $team_filter_cond AND t.mentor_id IS NOT NULL AND i.is_deleted = 0
    ", 'Assigned Mentors');

    // 7. Total interns (for old ratio display)
    $total_interns = executeSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT a.user_id) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE $filter_cond AND i.is_deleted = 0
    ", 'Total Interns');

    // 8. Active interns (for old display)
    $active_interns = executeSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT a.user_id) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE a.status IN ('Started','Internship Started','Active Intern','Internship Active','Selected', 'Project Assigned')
          AND $filter_cond AND i.is_deleted = 0
    ", 'Active Interns');

    // 9. Total logs
    $total_logs = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM daily_logs d
        JOIN internships i ON d.internship_id = i.id
        WHERE $filter_cond AND i.is_deleted = 0
    ", 'Total Logs');

    // 10. Logged today
    $logged_today = executeSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT d.user_id) as c 
        FROM daily_logs d
        JOIN internships i ON d.internship_id = i.id
        WHERE d.log_date = CURDATE() AND $filter_cond AND i.is_deleted = 0
    ", 'Logged Today');

    $pending_logs = max(0, $active_interns - $logged_today);

    // 11. Completed internships
    $completed_internships = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE a.status = 'Completed' AND $filter_cond AND i.is_deleted = 0
    ", 'Completed Internships');

    $total_prog = $completed_internships + $active_interns;
    $completion_pct = $total_prog > 0 ? round(($completed_internships / $total_prog) * 100) : 0;

    // 12. Open projects
    $open_projects = executeSafeCountQuery($conn, "
        SELECT COUNT(*) as c 
        FROM internships i
        WHERE i.status IN ('Active','Approved','Admin-Approved','Admin Approved')
          AND $filter_cond AND i.is_deleted = 0
    ", 'Open Projects');

    $assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;

    // 13. Pipeline
    $pipeline_sql = "
        SELECT p.id, p.title, p.project_subtype, p.duration, p.mode, p.status,
               p.mentor_name, p.team_name, p.assigned_count, p.source
        FROM (
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
            WHERE $team_filter_cond AND i.is_deleted = 0
            GROUP BY i.id

            UNION ALL

            SELECT i.id, i.title, i.project_subtype, i.duration, i.mode,
                   CASE WHEN i.status IN ('Closed', 'Completed') THEN 'Completed' ELSE 'Available' END AS status,
                   'Mentor Not Assigned' AS mentor_name,
                   NULL AS team_name,
                   0 AS assigned_count,
                   'internship' AS source
            FROM internships i
            WHERE i.status IN ('Active', 'Approved', 'Admin-Approved', 'Admin Approved')
              AND $filter_cond AND i.is_deleted = 0
              AND NOT EXISTS (SELECT 1 FROM project_teams pt WHERE pt.internship_id = i.id)
        ) p
        ORDER BY p.assigned_count DESC, p.id DESC
        LIMIT 12
    ";
    $pipeline_res = mysqli_query($conn, $pipeline_sql);
    if ($pipeline_res) {
        while ($proj = mysqli_fetch_assoc($pipeline_res)) {
            $id = $proj['id'];
            if (!isset($pipeline_projects[$id])) {
                $pipeline_projects[$id] = $proj;
            }
        }
        $pipeline_projects = array_values($pipeline_projects);
    } else {
        error_log("IMP Error - Pipeline Projects: " . mysqli_error($conn));
    }

    // 14. Recent Logs (for the table display)
    $logs_sql = "
        SELECT d.tasks_completed, d.time_spent, d.focus_level, d.status,
               u.full_name,
               COALESCE(sp.course, 'Student') AS course,
               COALESCE(sp.college_name, 'University') AS college_name
        FROM daily_logs d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        JOIN internships i ON d.internship_id = i.id
        WHERE $filter_cond AND i.is_deleted = 0
        ORDER BY d.created_at DESC
        LIMIT 8
    ";
    $logs_res = mysqli_query($conn, $logs_sql);
    if ($logs_res) {
        while ($log = mysqli_fetch_assoc($logs_res)) {
            $recent_logs[] = $log;
        }
    } else {
        error_log("IMP Error - Recent Logs: " . mysqli_error($conn));
    }

    // 15. Recent Activities (unified feed)
    $activity_sql = "
        SELECT * FROM (
            SELECT 
                'application' AS activity_type,
                a.id AS ref_id,
                COALESCE(u.full_name, a.full_name) AS primary_name,
                i.title AS detail_name,
                a.status AS extra_info,
                a.applied_date AS activity_time
            FROM internship_applications a
            JOIN internships i ON a.internship_id = i.id
            LEFT JOIN users u ON a.user_id = u.id
            WHERE $filter_cond AND i.is_deleted = 0

            UNION ALL

            SELECT 
                'team' AS activity_type,
                t.id AS ref_id,
                t.team_name AS primary_name,
                i.title AS detail_name,
                t.status AS extra_info,
                t.created_at AS activity_time
            FROM project_teams t
            LEFT JOIN internships i ON t.internship_id = i.id
            WHERE $team_filter_cond AND (i.is_deleted = 0 OR i.id IS NULL)

            UNION ALL

            SELECT 
                'assignment' AS activity_type,
                ptm.id AS ref_id,
                u.full_name AS primary_name,
                t.team_name AS detail_name,
                i.title AS extra_info,
                ptm.created_at AS activity_time
            FROM project_team_members ptm
            JOIN project_teams t ON ptm.project_team_id = t.id
            LEFT JOIN internships i ON t.internship_id = i.id
            JOIN users u ON ptm.student_id = u.id
            WHERE $team_filter_cond AND (i.is_deleted = 0 OR i.id IS NULL)

            UNION ALL

            SELECT 
                'log' AS activity_type,
                d.id AS ref_id,
                u.full_name AS primary_name,
                d.tasks_completed AS detail_name,
                CAST(d.time_spent AS CHAR) AS extra_info,
                d.created_at AS activity_time
            FROM daily_logs d
            JOIN users u ON d.user_id = u.id
            JOIN internships i ON d.internship_id = i.id
            WHERE $filter_cond AND i.is_deleted = 0
        ) AS combined_activity
        ORDER BY activity_time DESC
        LIMIT 8
    ";
    $activity_res = mysqli_query($conn, $activity_sql);
    if ($activity_res) {
        while ($act = mysqli_fetch_assoc($activity_res)) {
            $recent_activities[] = $act;
        }
    } else {
        error_log("IMP Error - Recent Activities: " . mysqli_error($conn));
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
        <a href="/IMP/coordinator/dashboard.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
            <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
        </a>
        <a href="/IMP/coordinator/internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">work</span> Postings
        </a>
        <a href="/IMP/coordinator/candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">group</span> Candidates
        </a>
        <a href="/IMP/coordinator/daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
        </a>
        <a href="/IMP/coordinator/reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
        </a>
						<a href="/IMP/coordinator/teams.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Teams
        </a>
    </nav>
    <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
        <a href="/IMP/coordinator/profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
        </a>
        <a href="/IMP/coordinator/help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">help</span> Help Center
        </a>
        <a href="/IMP/logout.php" class="flex items-center gap-3 text-red-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
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
            <button onclick="window.location.href='/IMP/coordinator/internships.php'"
                class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-xl text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add</span> New Internship
            </button>
            <button onclick="window.location.href='/IMP/coordinator/notifications.php'"
                class="flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-4 py-1.5 rounded-xl text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">notifications</span> Notifications
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
                            <a href="mark_notification_read.php?action=read_all&redirect=/IMP/coordinator/dashboard.php" class="text-[10px] font-bold text-blue-600 hover:text-blue-800">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                        <?php if (empty($latest_notifications)): ?>
                            <div class="px-4 py-3 text-center text-xs text-gray-400">No notifications.</div>
                        <?php else: ?>
                            <?php foreach ($latest_notifications as $notif): ?>
                                <a href="/IMP/coordinator/notifications.php" class="block px-4 py-2.5 hover:bg-gray-50 transition-colors">
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
                        <a href="/IMP/coordinator/notifications.php" class="block py-2 text-xs font-bold text-blue-600 hover:text-blue-800">View all notifications</a>
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
                    <a href="/IMP/coordinator/profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <span class="material-symbols-outlined text-gray-400 text-[18px]">account_circle</span> My Profile
                    </a>
                    <a href="/IMP/coordinator/profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <span class="material-symbols-outlined text-gray-400 text-[18px]">settings</span> Settings
                    </a>
                    <hr class="my-1 border-gray-100">
                    <a href="/IMP/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- ════════ DASHBOARD BODY ════════ -->
    <div class="p-6 space-y-5 flex-1">

        <?php if (empty($assigned_subtypes)): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700 fade-in">
                <span class="material-symbols-outlined text-[48px] text-red-400 mb-2">block</span>
                <h2 class="text-lg font-bold">Access Restricted</h2>
                <p class="text-sm mt-1">No Project Types or Subtypes have been assigned to you by Admin.</p>
                <p class="text-xs mt-2 opacity-80">You cannot manage internships or view candidates until an assignment is made.</p>
            </div>
        <?php else: ?>

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
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <!-- Created Projects/Internships -->
                    <div class="stat-card p-5 bg-blue-50/80 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">work</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-blue-600 dark:text-blue-400 uppercase tracking-widest truncate">Created Projects</p>
                            <p class="text-2xl font-black text-blue-900 dark:text-white mt-1 leading-none" id="stat-created-projects"><?php echo $created_projects_count; ?></p>
                        </div>
                    </div>
                    <!-- Pending Applications -->
                    <div class="stat-card p-5 bg-amber-50/80 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">rate_review</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-amber-600 dark:text-amber-400 uppercase tracking-widest truncate">Pending Apps</p>
                            <p class="text-2xl font-black text-amber-900 dark:text-white mt-1 leading-none" id="stat-pending-applications"><?php echo $pending_applications_count; ?></p>
                        </div>
                    </div>
                    <!-- Selected Students -->
                    <div class="stat-card p-5 bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">check_circle</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest truncate">Assigned Students</p>
                            <p class="text-2xl font-black text-emerald-900 dark:text-white mt-1 leading-none" id="stat-selected-students"><?php echo $selected_students_count; ?></p>
                        </div>
                    </div>
                    <!-- Assigned Teams -->
                    <div class="stat-card p-5 bg-purple-50/80 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">groups</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-purple-600 dark:text-purple-400 uppercase tracking-widest truncate">Assigned Teams</p>
                            <p class="text-2xl font-black text-purple-900 dark:text-white mt-1 leading-none" id="stat-assigned-teams"><?php echo $assigned_teams_count; ?></p>
                        </div>
                    </div>
                    <!-- Active Projects -->
                    <div class="stat-card p-5 bg-indigo-50/80 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">play_circle</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest truncate">Active Projects</p>
                            <p class="text-2xl font-black text-indigo-900 dark:text-white mt-1 leading-none" id="stat-active-projects"><?php echo $active_projects_count; ?></p>
                        </div>
                    </div>
                    <!-- Assigned Mentors -->
                    <div class="stat-card p-5 bg-pink-50/80 dark:bg-pink-950/20 border border-pink-100 dark:border-pink-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-pink-500/10 rounded-xl flex items-center justify-center text-pink-600 dark:text-pink-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">supervised_user_circle</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-pink-600 dark:text-pink-400 uppercase tracking-widest truncate">Assigned Mentors</p>
                            <p class="text-2xl font-black text-pink-900 dark:text-white mt-1 leading-none" id="stat-assigned-mentors"><?php echo $assigned_mentors_count; ?></p>
                        </div>
                    </div>
                    <!-- Pending Daily Logs -->
                    <div class="stat-card p-5 bg-orange-50/80 dark:bg-orange-950/20 border border-orange-100 dark:border-orange-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-600 dark:text-orange-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">pending_actions</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-orange-600 dark:text-orange-400 uppercase tracking-widest truncate">Pending Logs</p>
                            <p class="text-2xl font-black text-orange-900 dark:text-white mt-1 leading-none" id="stat-pending-logs"><?php echo $pending_logs; ?></p>
                        </div>
                    </div>
                    <!-- Unread Notifications -->
                    <div class="stat-card p-5 bg-red-50/80 dark:bg-red-950/20 border border-red-100 dark:border-red-900/50 rounded-2xl flex items-center gap-4 transition-all duration-300">
                        <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center text-red-600 dark:text-red-400 shrink-0">
                            <span class="material-symbols-outlined text-[28px]">notifications</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-extrabold text-red-600 dark:text-red-400 uppercase tracking-widest truncate">Unread Notifs</p>
                            <p class="text-2xl font-black text-red-900 dark:text-white mt-1 leading-none" id="stat-unread-notifications"><?php echo $unread_count; ?></p>
                        </div>
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
                <button onclick="window.location.href='/IMP/coordinator/teams.php'"
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
                <a href="/IMP/coordinator/teams.php" class="text-blue-600 text-sm font-semibold hover:underline">View All →</a>
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

        <!-- ── SECTION 3: Logs & Activity ── -->
        <div class="flex flex-col lg:flex-row gap-6 items-stretch fade-in">
            <!-- Left Panel: Internship Monitoring & Logs (65%) -->
            <div class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600 text-[18px]">monitoring</span>
                            <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Internship Monitoring &amp; Logs</h2>
                        </div>
                        <div class="flex gap-2">
                            <a href="/IMP/coordinator/daily_logs.php" class="text-blue-600 text-sm font-semibold hover:underline">View All Logs →</a>
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
                                            <a href="/IMP/coordinator/daily_logs.php" class="text-blue-600 text-xs font-bold hover:underline">Details →</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Recent Activity (35%) -->
            <div class="w-full lg:w-96 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col justify-between shrink-0">
                <div>
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600 text-[18px]">campaign</span>
                            <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Recent Activity</h2>
                        </div>
                    </div>
                    <div class="p-5 overflow-y-auto max-h-[380px]" id="activity-feed">
                        <?php if (empty($recent_activities)): ?>
                            <div class="flex flex-col items-center justify-center py-12 text-gray-400 text-sm">
                                <span class="material-symbols-outlined text-[40px] text-gray-200 mb-2">history</span>
                                No recent activity found.
                            </div>
                        <?php else: ?>
                            <div class="relative pl-6 border-l border-gray-200 dark:border-slate-800 space-y-6">
                                <?php foreach ($recent_activities as $act):
                                    $act_type = $act['activity_type'];
                                    $icon = 'info';
                                    $icon_color = 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400';
                                    $title_html = '';
                                    
                                    if ($act_type === 'application') {
                                        $icon = 'send';
                                        $icon_color = 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400';
                                        $title_html = '<strong>' . htmlspecialchars($act['primary_name'] ?: 'Student') . '</strong> applied for <strong>' . htmlspecialchars($act['detail_name']) . '</strong>';
                                    } elseif ($act_type === 'team') {
                                        $icon = 'groups';
                                        $icon_color = 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400';
                                        $title_html = 'Team <strong>' . htmlspecialchars($act['primary_name']) . '</strong> created for <strong>' . htmlspecialchars($act['detail_name']) . '</strong>';
                                    } elseif ($act_type === 'assignment') {
                                        $icon = 'assignment_ind';
                                        $icon_color = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400';
                                        $title_html = '<strong>' . htmlspecialchars($act['primary_name']) . '</strong> assigned to <strong>' . htmlspecialchars($act['detail_name']) . '</strong>';
                                    } elseif ($act_type === 'log') {
                                        $icon = 'event_note';
                                        $icon_color = 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400';
                                        $title_html = '<strong>' . htmlspecialchars($act['primary_name']) . '</strong> logged <strong>' . htmlspecialchars($act['extra_info']) . 'h</strong>: "' . htmlspecialchars(mb_strimwidth($act['detail_name'], 0, 40, '…')) . '"';
                                    }
                                    
                                    $time_display = date('h:i A, M d', strtotime($act['activity_time']));
                                ?>
                                <div class="relative">
                                    <!-- Marker -->
                                    <span class="absolute -left-[35px] top-0.5 w-6 h-6 <?php echo $icon_color; ?> rounded-full flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[14px]"><?php echo $icon; ?></span>
                                    </span>
                                    <div>
                                        <p class="text-xs text-gray-700 dark:text-slate-300 font-normal leading-relaxed"><?php echo $title_html; ?></p>
                                        <span class="text-[10px] text-gray-400 dark:text-slate-500 block mt-1"><?php echo $time_display; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /dashboard body -->
    <?php endif; // end assigned_subtypes check ?>

    <?php
    // Fetch students for Student Progress Overview
    $progress_students = [];
    $prog_stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, sp.college_name, a.internship_id, COALESCE(i.title, a.internship_name) AS project_title, COALESCE(i.project_subtype, a.applied_subtype, 'General') AS project_subtype, a.team_name 
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        LEFT JOIN internships i ON a.internship_id = i.id
        WHERE a.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
        ORDER BY u.full_name ASC
    ");
    if ($prog_stmt) {
        $prog_stmt->execute();
        $prog_res = $prog_stmt->get_result();
        while ($row = $prog_res->fetch_assoc()) {
            $progress_students[] = $row;
        }
        $prog_stmt->close();
    }
    ?>
    <!-- Student Progress Overview -->
    <div class="mt-6 w-full bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-[18px]">track_changes</span>
                <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Student Progress Overview</h2>
            </div>
        </div>
        <div class="p-0 overflow-x-auto max-h-[500px]">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-500 sticky top-0 z-10">
                        <th class="px-5 py-3 font-semibold bg-slate-50">Student Name</th>
                        <th class="px-5 py-3 font-semibold bg-slate-50">Project & Team</th>
                        <th class="px-5 py-3 font-semibold text-center bg-slate-50">Progress</th>
                        <th class="px-5 py-3 font-semibold text-center bg-slate-50">Approved Logs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($progress_students)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400 text-sm">No active students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($progress_students as $student): ?>
                        <?php
                            $prog_data = calculate_internship_progress($conn, $student['id'], $student['internship_id']);
                            $prog_val = $prog_data['progress_percentage'];
                            $approved = $prog_data['approved_logs'];
                            $expected = $prog_data['expected_logs'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                <p class="text-[11px] text-slate-500"><?php echo htmlspecialchars($student['college_name'] ?: $student['email']); ?></p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-xs font-semibold text-slate-700"><?php echo htmlspecialchars($student['project_subtype']); ?></p>
                                <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5"><span class="material-symbols-outlined text-[12px]">groups</span> <?php echo htmlspecialchars($student['team_name'] ?: 'No Team'); ?></p>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center gap-2 max-w-[120px] mx-auto">
                                    <div class="flex-1 bg-slate-100 h-2 rounded-full overflow-hidden">
                                        <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $prog_val; ?>%"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 w-8 text-right"><?php echo $prog_val; ?>%</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700">
                                    <span class="material-symbols-outlined text-[14px] text-blue-500">check_circle</span>
                                    <?php echo $approved; ?> / <?php echo $expected; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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
        <form method="POST" action="/IMP/coordinator/dashboard.php" class="p-6 space-y-4">
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
    ['stat-created-projects','stat-pending-applications','stat-selected-students','stat-assigned-teams','stat-active-projects','stat-assigned-mentors','stat-pending-logs','stat-unread-notifications'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.opacity = '0.4';
    });

    try {
        const url = `/IMP/coordinator/analytics_api.php?subtype=${encodeURIComponent(currentSubtype)}&internship_id=0`;
        const res  = await fetch(url);
        const data = await res.json();
        if (data.error) { console.error(data.error); return; }
        updateDashboard(data);
    } catch(e) {
        console.error('Analytics fetch failed:', e);
    } finally {
        ['stat-created-projects','stat-pending-applications','stat-selected-students','stat-assigned-teams','stat-active-projects','stat-assigned-mentors','stat-pending-logs','stat-unread-notifications'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.opacity = '1';
        });
    }
}

function formatDateTime(sqlDate) {
    if (!sqlDate) return '';
    const date = new Date(sqlDate.replace(/-/g, "/"));
    let hours = date.getHours();
    let minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0'+minutes : minutes;
    const strTime = hours + ':' + minutes + ' ' + ampm;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return strTime + ', ' + months[date.getMonth()] + ' ' + date.getDate();
}

function updateDashboard(data) {
    // Subtitle
    const sub = document.getElementById('overview-subtitle');
    if (sub) {
        const label = currentSubtype ? `Subtype: ${currentSubtype}` : 'None';
        sub.textContent = `${label} • ${data.active_interns} Active Interns`;
    }

    // Stat cards
    animateCount('stat-created-projects',     data.created_projects);
    animateCount('stat-pending-applications', data.pending_applications);
    animateCount('stat-selected-students',    data.selected_students);
    animateCount('stat-assigned-teams',       data.assigned_teams);
    animateCount('stat-active-projects',      data.active_projects);
    animateCount('stat-assigned-mentors',     data.assigned_mentors);
    animateCount('stat-pending-logs',         data.pending_logs);
    animateCount('stat-unread-notifications', data.unread_notifications);

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
                    <td class="px-5 py-3.5 text-right"><a href="/IMP/coordinator/daily_logs.php" class="text-blue-600 text-xs font-bold hover:underline">Details →</a></td>
                </tr>`;
            }).join('');
        }
    }

    // Recent Activity feed
    const feed = document.getElementById('activity-feed');
    if (feed) {
        if (!data.recent_activities?.length) {
            feed.innerHTML = `<div class="flex flex-col items-center justify-center py-12 text-gray-400 text-sm">
                <span class="material-symbols-outlined text-[40px] text-gray-200 mb-2">history</span>No recent activity found.</div>`;
        } else {
            feed.innerHTML = `<div class="relative pl-6 border-l border-gray-200 dark:border-slate-800 space-y-6">` + 
            data.recent_activities.map(act => {
                let icon = 'info';
                let iconColor = 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400';
                let titleHtml = '';
                
                const type = act.activity_type;
                const pName = h(act.primary_name || 'Student');
                const dName = h(act.detail_name || '');
                const extra = h(act.extra_info || '');
                
                if (type === 'application') {
                    icon = 'send';
                    iconColor = 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400';
                    titleHtml = `<strong>${pName}</strong> applied for <strong>${dName}</strong>`;
                } else if (type === 'team') {
                    icon = 'groups';
                    iconColor = 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400';
                    titleHtml = `Team <strong>${pName}</strong> created for <strong>${dName}</strong>`;
                } else if (type === 'assignment') {
                    icon = 'assignment_ind';
                    iconColor = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400';
                    titleHtml = `<strong>${pName}</strong> assigned to <strong>${dName}</strong>`;
                } else if (type === 'log') {
                    icon = 'event_note';
                    iconColor = 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400';
                    titleHtml = `<strong>${pName}</strong> logged <strong>${extra}h</strong>: "${dName.length > 40 ? dName.substr(0,40) + '…' : dName}"`;
                }
                
                const timeDisplay = formatDateTime(act.activity_time);
                
                return `<div class="relative">
                    <span class="absolute -left-[35px] top-0.5 w-6 h-6 ${iconColor} rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-[14px]">${icon}</span>
                    </span>
                    <div>
                        <p class="text-xs text-gray-700 dark:text-slate-300 font-normal leading-relaxed">${titleHtml}</p>
                        <span class="text-[10px] text-gray-400 dark:text-slate-500 block mt-1">${timeDisplay}</span>
                    </div>
                </div>`;
            }).join('') + `</div>`;
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