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
    $notif_title = trim($_POST['notif_title'] ?? '');
    $notif_message = trim($_POST['notif_message'] ?? '');
    $recipient_type = trim($_POST['recipient_type'] ?? 'all');
    $priority = trim($_POST['priority'] ?? 'Normal');
    $selected_students = isset($_POST['notif_students']) ? $_POST['notif_students'] : [];
    $selected_team = trim($_POST['notif_team'] ?? '');

    if (empty($notif_title) || empty($notif_message)) {
        $notif_error = "Please fill in both Title and Message.";
    } else {
        $type_label = ($priority === 'Urgent') ? 'Urgent' : (($priority === 'Important') ? 'Important' : 'Notification');
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

            // Optional: attempt email via mail_helper
            if (file_exists('includes/mail_helper.php')) {
                @include_once 'includes/mail_helper.php';
                if (function_exists('sendMail')) {
                    $email_res = mysqli_query($conn, "SELECT email FROM users WHERE id IN (" . implode(',', $target_user_ids) . ")");
                    while ($er = mysqli_fetch_assoc($email_res)) {
                        @sendMail($er['email'], $notif_title, $notif_message);
                    }
                }
            }

            $notif_success = "Notification sent successfully to $sent_count student(s)!";
        }
    }
}

// Count total interns
$total_interns_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE role='student'");
$total_interns_row = mysqli_fetch_assoc($total_interns_res);
$total_interns = intval($total_interns_row['cnt'] ?? 0);

// Count active interns
$active_interns_res = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as cnt FROM internship_applications WHERE status IN ('Started', 'Internship Started', 'Active Intern', 'Selected')");
$active_interns_row = mysqli_fetch_assoc($active_interns_res);
$active_interns = intval($active_interns_row['cnt'] ?? 0);

// Count total logs
$total_logs_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM daily_logs");
$total_logs_row = mysqli_fetch_assoc($total_logs_res);
$total_logs = intval($total_logs_row['cnt'] ?? 0);

// Count missing logs (active interns who haven't logged today)
$missing_logs_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.user_id) as cnt FROM internship_applications a
    WHERE a.status IN ('Started', 'Internship Started', 'Active Intern')
    AND a.user_id NOT IN (
        SELECT DISTINCT user_id FROM daily_logs WHERE log_date = CURDATE()
    )
");
$missing_logs_row = mysqli_fetch_assoc($missing_logs_res);
$missing_logs = intval($missing_logs_row['cnt'] ?? 0);

// Count completed internships
$completed_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM internship_applications WHERE status = 'Completed'");
$completed_row = mysqli_fetch_assoc($completed_res);
$completed_internships = intval($completed_row['cnt'] ?? 0);

// Count open projects
$open_projects_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM internships WHERE status = 'Active'");
$open_projects_row = mysqli_fetch_assoc($open_projects_res);
$open_projects = intval($open_projects_row['cnt'] ?? 0);

// Average productivity from feedback
$avg_prod_res = mysqli_query($conn, "SELECT AVG(rating) as avg_rating FROM mentor_feedback");
$avg_prod_row = mysqli_fetch_assoc($avg_prod_res);
$avg_prod = $avg_prod_row['avg_rating'] !== null ? number_format($avg_prod_row['avg_rating'], 1) : "4.5";

// Program Completion percentage
$total_prog_interns = $completed_internships + $active_interns;
$completion_percentage = $total_prog_interns > 0 ? round(($completed_internships / $total_prog_interns) * 100) : 0;

// Project Filling percentage
$assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;
$open_projects_pct = ($open_projects + $completed_internships) > 0 ? round(($open_projects / ($open_projects + $completed_internships)) * 100) : 0;

// Pending logs (active interns who haven't logged today)
$logged_today_res = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as cnt FROM daily_logs WHERE log_date = CURDATE()");
$logged_today_row = mysqli_fetch_assoc($logged_today_res);
$logged_today = intval($logged_today_row['cnt'] ?? 0);
$pending_logs = max(0, $active_interns - $logged_today);

// Internship list for filter dropdown (dynamic from DB)
$internship_list_res = mysqli_query($conn, "SELECT id, title FROM internships ORDER BY title ASC");
$internship_list = [];
while ($il = mysqli_fetch_assoc($internship_list_res)) $internship_list[] = $il;

// Fetch students for bulk notification
$notif_students_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name ASC");
$notif_students_list = [];
while ($r = mysqli_fetch_assoc($notif_students_res)) $notif_students_list[] = $r;

// Fetch team names for bulk notification
$notif_teams_res = mysqli_query($conn, "SELECT DISTINCT team_name FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' ORDER BY team_name ASC");
$notif_teams_list = [];
while ($r = mysqli_fetch_assoc($notif_teams_res)) $notif_teams_list[] = $r['team_name'];
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
                rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
                rel="stylesheet" />
        <script id="tailwind-config">
                tailwind.config = {
                        darkMode: "class",
                        theme: {
                                extend: {
                                        "colors": {
                                                "on-surface-variant": "#434655",
                                                "secondary-fixed-dim": "#c0c7d0",
                                                "on-primary-container": "#eeefff",
                                                "surface-container-low": "#f3f4f5",
                                                "primary-fixed": "#dbe1ff",
                                                "outline": "#737686",
                                                "secondary": "#585f67",
                                                "tertiary-container": "#bc4800",
                                                "error": "#ba1a1a",
                                                "primary-container": "#2563eb",
                                                "surface": "#f8f9fa",
                                                "on-primary-fixed-variant": "#003ea8",
                                                "on-primary": "#ffffff",
                                                "on-secondary-container": "#5e656d",
                                                "on-background": "#191c1d",
                                                "surface-container": "#edeeef",
                                                "on-tertiary": "#ffffff",
                                                "on-secondary": "#ffffff",
                                                "secondary-fixed": "#dce3ec",
                                                "on-tertiary-container": "#ffede6",
                                                "on-tertiary-fixed-variant": "#7d2d00",
                                                "outline-variant": "#c3c6d7",
                                                "inverse-primary": "#b4c5ff",
                                                "secondary-container": "#dce3ec",
                                                "surface-dim": "#d9dadb",
                                                "on-surface": "#191c1d",
                                                "on-secondary-fixed-variant": "#40484f",
                                                "inverse-surface": "#2e3132",
                                                "on-error": "#ffffff",
                                                "background": "#f8f9fa",
                                                "primary": "#004ac6",
                                                "tertiary": "#943700",
                                                "tertiary-fixed": "#ffdbcd",
                                                "surface-variant": "#e1e3e4",
                                                "surface-container-highest": "#e1e3e4",
                                                "on-tertiary-fixed": "#360f00",
                                                "surface-container-lowest": "#ffffff",
                                                "error-container": "#ffdad6",
                                                "on-primary-fixed": "#00174b",
                                                "surface-tint": "#0053db",
                                                "primary-fixed-dim": "#b4c5ff",
                                                "on-error-container": "#93000a",
                                                "surface-bright": "#f8f9fa",
                                                "inverse-on-surface": "#f0f1f2",
                                                "on-secondary-fixed": "#151c23",
                                                "surface-container-high": "#e7e8e9",
                                                "tertiary-fixed-dim": "#ffb596"
                                        },
                                        "borderRadius": {
                                                "DEFAULT": "0.25rem",
                                                "lg": "0.5rem",
                                                "xl": "0.75rem",
                                                "full": "9999px"
                                        },
                                        "spacing": {
                                                "xl": "32px",
                                                "lg": "24px",
                                                "container-margin": "40px",
                                                "md": "16px",
                                                "sm": "8px",
                                                "xs": "4px",
                                                "gutter": "20px",
                                                "unit": "4px"
                                        },
                                        "fontFamily": {
                                                "body-lg": ["Inter"],
                                                "label-md": ["Inter"],
                                                "body-md": ["Inter"],
                                                "h1": ["Inter"],
                                                "label-sm": ["Inter"],
                                                "h3": ["Inter"],
                                                "h2": ["Inter"]
                                        },
                                        "fontSize": {
                                                "body-lg": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                                                "label-md": ["14px", { "lineHeight": "20px", "fontWeight": "500" }],
                                                "body-md": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                                                "h1": ["30px", { "lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                                                "label-sm": ["12px", { "lineHeight": "16px", "fontWeight": "600" }],
                                                "h3": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                                                "h2": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600" }]
                                        }
                                },
                        },
                }
        </script>
        <style>
                .material-symbols-outlined {
                        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                        vertical-align: middle;
                }

                body {
                        font-family: 'Inter', sans-serif;
                }

                .bento-grid {
                        display: grid;
                        grid-template-columns: repeat(12, 1fr);
                        gap: 20px;
                }
                @media (max-width: 1023px) {
                        .bento-grid {
                                grid-template-columns: 1fr;
                        }
                        .bento-grid > div {
                                grid-column: span 1 / span 1 !important;
                        }
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

<body class="bg-background text-on-surface">
        <!-- SideNavBar -->
        <aside
                class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
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
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-1">
                        <a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 duration-200 ease-in-out"
                                href="coordinator_dashboard.php">
                                <span class="material-symbols-outlined">dashboard</span>
                                <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_internships.php">
                                <span class="material-symbols-outlined">work</span>
                                <span>Postings</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_candidates.php">
                                <span class="material-symbols-outlined">group</span>
                                <span>Candidates</span>
                        </a>

                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_daily_logs.php">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span>Daily Logs Monitoring</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_reports.php">
                                <span class="material-symbols-outlined">analytics</span>
                                <span>Reports</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_teams.php">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <span>Team Management</span>
                        </a>
                </nav>
                <div class="mt-auto border-t border-gray-200 pt-4">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">help</span>
                                <span>Help Center</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="logout.php">
                                <span class="material-symbols-outlined">logout</span>
                                <span>Logout</span>
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
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Dashboard</h2>
                        </div>
                        
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

                <!-- Dashboard Content -->
                <div class="p-8 space-y-6">
                        <!-- Page Title & Bulk Actions -->
                        <div class="flex flex-wrap justify-between items-end gap-4">
                                <div>
                                        <h2 class="font-h1 text-h1 text-on-background">Coordinator Dashboard</h2>
                                        <p class="font-body-md text-body-md text-secondary" id="subtitle-text">All Internships • <span id="subtitle-active"><?php echo $active_interns; ?></span> Active Interns</p>
                                </div>
                                <div class="flex flex-wrap gap-sm items-center">
                                         <!-- ── Subtype Filter Dropdown ── -->
                                         <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-2 shadow-sm">
                                                 <span class="material-symbols-outlined text-blue-600 text-[18px]">category</span>
                                                 <select id="subtype-filter"
                                                         class="bg-transparent border-none outline-none text-sm font-semibold text-slate-700 cursor-pointer pr-2 min-w-[150px]"
                                                         onchange="handleSubtypeChange(this.value)">
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
                                        <button onclick="openBulkNotifModal()"
                                                class="flex items-center gap-2 bg-secondary-container text-on-secondary-container px-4 py-2 rounded-lg font-label-md text-label-md hover:bg-gray-200 transition-all cursor-pointer">
                                                <span class="material-symbols-outlined">mail</span>
                                                Bulk Notification
                                        </button>
                                        <button onclick="window.location.href='coordinator_internships.php'"
                                                class="flex items-center gap-2 bg-primary-container text-on-primary px-4 py-2 rounded-lg font-label-md text-label-md hover:opacity-90 transition-all shadow-sm">
                                                <span class="material-symbols-outlined">add</span>
                                                New Internship
                                        </button>
                                </div>
                                      <!-- Two-Column Responsive Layout -->
                      <!-- Top Layout Section: Overview & Project Filling -->
                      <div class="flex flex-col lg:flex-row gap-4 items-stretch">
                              <!-- Left Content Area (70%) -->
                              <div class="flex-1 min-w-0">
                                      <!-- Intern Cohort Status Card -->
                                      <div class="bg-surface-container-lowest p-lg rounded-xl shadow-sm border border-outline-variant h-full flex flex-col justify-between">
                                              <div class="flex justify-between items-start mb-lg">
                                                      <h3 class="font-h3 text-h3">Global Intern Overview</h3>
                                                      <div class="flex gap-2">
                                                              <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-label-sm font-label-sm">92% On Track</span>
                                                              <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-label-sm font-label-sm">8 Flagged</span>
                                                      </div>
                                              </div>
                                              <div class="space-y-4">
                                                      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                                              <div class="p-md bg-surface-container-low rounded-lg border-l-4 border-primary shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                      <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Total Logs</p>
                                                                      <p class="font-h2 text-h2 mt-1 text-primary" id="stat-total-logs"><?php echo $total_logs; ?></p>
                                                              </div>
                                                              <div class="p-md bg-surface-container-low rounded-lg border-l-4 border-red-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                      <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Active Interns</p>
                                                                      <p class="font-h2 text-h2 mt-1 text-red-600" id="stat-active-interns"><?php echo $active_interns; ?></p>
                                                              </div>
                                                              <div class="p-md bg-surface-container-low rounded-lg border-l-4 border-green-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                      <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Completion %</p>
                                                                      <p class="font-h2 text-h2 mt-1 text-green-700" id="stat-completion-pct"><?php echo $completion_percentage; ?>%</p>
                                                              </div>
                                                              <div class="p-md bg-surface-container-low rounded-lg border-l-4 border-amber-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                      <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Pending Logs</p>
                                                                      <p class="font-h2 text-h2 mt-1 text-amber-600" id="stat-pending-logs"><?php echo $pending_logs; ?></p>
                                                              </div>
                                                      </div>
                                              </div>
                                      </div>
                              </div>

                              <!-- Right Sidebar Widgets (30%) -->
                              <div class="w-full lg:w-[30%] shrink-0 lg:max-w-[380px]">
                                      <!-- Assignment Stats Card (Project Filling) -->
                                      <div class="bg-primary-container text-on-primary-container p-lg rounded-xl shadow-sm relative overflow-hidden h-full flex flex-col justify-between">
                                              <div class="relative z-10">
                                                      <h3 class="font-h3 text-h3 mb-md">Project Filling</h3>
                                                      <div class="space-y-lg">
                                                              <div>
                                                                      <div class="flex justify-between font-label-md text-label-md mb-2">
                                                                              <span>Active Interns</span>
                                                                              <span id="stat-assigned-ratio"><?php echo "$active_interns/$total_interns"; ?></span>
                                                                      </div>
                                                                      <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                                                              <div class="bg-white h-full transition-all duration-500" id="stat-assigned-bar" style="width: <?php echo $assigned_pct; ?>%"></div>
                                                                      </div>
                                                              </div>
                                                              <div>
                                                                      <div class="flex justify-between font-label-md text-label-md mb-2">
                                                                              <span>Open Projects</span>
                                                                              <span id="stat-open-projects"><?php echo $open_projects; ?></span>
                                                                      </div>
                                                                      <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                                                              <div class="bg-white h-full transition-all duration-500" id="stat-open-projects-bar" style="width: <?php echo $open_projects_pct; ?>%"></div>
                                                                      </div>
                                                                      <button onclick="window.location.href='coordinator_teams.php'"
                                                                              class="w-full py-3 bg-white text-primary-container rounded-lg font-label-md text-label-md font-bold mt-4 cursor-pointer hover:bg-opacity-95 transition-all">
                                                                              Review Assignments
                                                                      </button>
                                                              </div>
                                                      </div>
                                              </div>
                                              <div class="absolute -right-12 -bottom-12 opacity-10">
                                                      <span class="material-symbols-outlined text-[200px]" style="font-variation-settings: 'FILL' 1;">account_tree</span>
                                              </div>
                                      </div>
                              </div>
                      </div>

                      <!-- Assignment Panel (Project Assignment Pipeline) -->
                      <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant overflow-hidden w-full mt-4">
                              <div class="px-lg py-md border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                      <h3 class="font-label-md text-label-md text-on-background uppercase tracking-widest">
                                              Pipeline</h3>
                                      <button onclick="window.location.href='coordinator_teams.php'" class="text-primary font-label-sm text-label-sm hover:underline">View All</button>
                              </div>
                              <div class="p-lg flex flex-row overflow-x-auto gap-4 scrollbar-thin scrollbar-thumb-gray-200 pb-4" id="pipeline-grid">
                                  <?php
                                  $pipeline_res = mysqli_query($conn, "
                                      SELECT i.*, u.full_name as mentor_name 
                                      FROM internships i 
                                      LEFT JOIN users u ON i.assigned_mentor = u.id 
                                      WHERE i.status = 'Active' 
                                      LIMIT 12
                                  ");
                                  $pipeline_projects = [];
                                  while ($proj = mysqli_fetch_assoc($pipeline_res)) {
                                      $assigned_count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM internship_applications WHERE internship_id = {$proj['id']} AND status IN ('Started', 'Internship Started', 'Active Intern', 'Selected')");
                                      $assigned_count_row = mysqli_fetch_assoc($assigned_count_res);
                                      $proj['assigned_count'] = intval($assigned_count_row['cnt'] ?? 0);
                                      $pipeline_projects[] = $proj;
                                  }
                                  if (empty($pipeline_projects)): ?>
                                      <div class="text-center py-6 text-secondary text-body-md w-full">No active projects found.</div>
                                  <?php else: ?>
                                      <?php foreach ($pipeline_projects as $proj):
                                          $subtype = $proj['project_subtype'] ?: 'General';
                                          $bg_color = "bg-slate-50 text-slate-600";
                                          if (stripos($subtype, 'web') !== false) {
                                              $bg_color = "bg-blue-50 text-blue-600";
                                          } elseif (stripos($subtype, 'design') !== false || stripos($subtype, 'ui') !== false) {
                                              $bg_color = "bg-purple-50 text-purple-600";
                                          } elseif (stripos($subtype, 'marketing') !== false || stripos($subtype, 'seo') !== false || stripos($subtype, 'social') !== false) {
                                              $bg_color = "bg-green-50 text-green-600";
                                          } elseif (stripos($subtype, 'apps') !== false || stripos($subtype, 'mobile') !== false) {
                                              $bg_color = "bg-indigo-50 text-indigo-600";
                                          } elseif (stripos($subtype, 'system') !== false || stripos($subtype, 'backend') !== false) {
                                              $bg_color = "bg-amber-50 text-amber-600";
                                          }
                                      ?>
                                      <div class="border border-outline-variant rounded-lg p-md flex flex-col justify-between hover:shadow-md transition-all flex-shrink-0 w-[320px] min-w-[320px] max-w-[320px]">
                                              <div>
                                                      <div class="flex justify-between items-start mb-2">
                                                              <span class="text-label-sm font-label-sm px-2 py-0.5 <?php echo $bg_color; ?> rounded"><?php echo htmlspecialchars($subtype); ?></span>
                                                              <span class="material-symbols-outlined text-gray-400">more_vert</span>
                                                      </div>
                                                      <h4 class="font-label-md text-label-md font-bold text-on-surface truncate" title="<?php echo htmlspecialchars($proj['title']); ?>">
                                                              <?php echo htmlspecialchars($proj['title']); ?></h4>
                                                      <p class="text-body-md font-body-md text-secondary mt-1">
                                                              Duration: <?php echo htmlspecialchars($proj['duration']); ?> • Mode: <?php echo htmlspecialchars($proj['mode']); ?></p>
                                                      <p class="text-xs text-gray-600 mt-1">
                                                              <span class="font-semibold">Mentor:</span> <?php echo htmlspecialchars($proj['mentor_name'] ?: 'None'); ?>
                                                      </p>
                                                      <p class="text-xs text-gray-600 mt-0.5">
                                                              <span class="font-semibold">Status:</span> <span class="text-green-600 font-bold"><?php echo htmlspecialchars($proj['status']); ?></span>
                                                      </p>
                                              </div>
                                              <div class="mt-lg flex justify-between items-center">
                                                      <div class="flex -space-x-2">
                                                          <?php for ($i = 0; $i < min(2, $proj['assigned_count']); $i++): ?>
                                                              <img alt="Intern" class="w-8 h-8 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=I&background=0D8ABC&color=fff" />
                                                          <?php endfor; ?>
                                                          <?php if ($proj['assigned_count'] > 2): ?>
                                                              <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                                                      +<?php echo ($proj['assigned_count'] - 2); ?></div>
                                                          <?php endif; ?>
                                                      </div>
                                                      <span class="text-label-sm text-green-600 font-label-sm"><?php echo $proj['assigned_count']; ?> Assigned</span>
                                              </div>
                                      </div>
                                      <?php endforeach; ?>
                                  <?php endif; ?>
                              </div>
                      </div>

                      <!-- Recent Activity Table (Internship Monitoring & Logs) -->
                      <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant overflow-hidden w-full mt-4">
                              <div class="px-lg py-md border-b border-gray-100 flex justify-between items-center">
                                      <h3 class="font-label-md text-label-md text-on-background uppercase tracking-widest">
                                              Internship Monitoring & Logs</h3>
                                      <div class="flex gap-2">
                                              <button class="p-1 hover:bg-gray-100 rounded"><span class="material-symbols-outlined text-gray-400">filter_list</span></button>
                                              <button class="p-1 hover:bg-gray-100 rounded"><span class="material-symbols-outlined text-gray-400">download</span></button>
                                      </div>
                              </div>
                              <div class="overflow-x-auto">
                                      <table class="w-full text-left">
                                              <thead>
                                                      <tr class="bg-gray-50/50 border-b border-gray-100">
                                                              <th class="px-lg py-3 font-label-sm text-label-sm text-secondary">INTERN NAME</th>
                                                              <th class="px-lg py-3 font-label-sm text-label-sm text-secondary">TASKS</th>
                                                              <th class="px-lg py-3 font-label-sm text-label-sm text-secondary">ACTIVITY STATUS</th>
                                                              <th class="px-lg py-3 font-label-sm text-label-sm text-secondary">HOURS</th>
                                                              <th class="px-lg py-3 font-label-sm text-label-sm text-secondary text-right">ACTION</th>
                                                      </tr>
                                              </thead>
                                              <tbody id="logs-table-body" class="divide-y divide-gray-100">
                                                  <?php
                                                  $logs_res = mysqli_query($conn, "
                                                      SELECT d.*, u.full_name, u.email, sp.college_name, sp.course
                                                      FROM daily_logs d
                                                      JOIN users u ON d.user_id = u.id
                                                      LEFT JOIN student_profiles sp ON u.id = sp.user_id
                                                      ORDER BY d.created_at DESC LIMIT 5
                                                  ");
                                                  if (mysqli_num_rows($logs_res) === 0): ?>
                                                      <tr>
                                                          <td colspan="5" class="px-lg py-4 text-center text-secondary text-body-md">No daily logs submitted yet.</td>
                                                      </tr>
                                                  <?php else:
                                                      while ($log = mysqli_fetch_assoc($logs_res)):
                                                          $time_spent = floatval($log['time_spent']);
                                                          $progress_width = min(100, round(($time_spent / 8.0) * 100));
                                                          $status_label = htmlspecialchars($log['status'] ?? 'Reviewed');
                                                          $badge_cls = $status_label === 'Reviewed' ? 'bg-blue-100 text-blue-700 border-blue-200' : 'bg-green-100 text-green-700 border-green-200';
                                                  ?>
                                                      <tr class="hover:bg-gray-50 transition-colors">
                                                              <td class="px-lg py-4">
                                                                      <div class="flex items-center gap-3">
                                                                              <div class="w-10 h-10 rounded-full bg-primary-fixed overflow-hidden flex items-center justify-center text-primary font-bold">
                                                                                  <?php echo strtoupper(substr($log['full_name'], 0, 1)); ?>
                                                                              </div>
                                                                              <div>
                                                                                      <p class="font-label-md text-label-md font-bold">
                                                                                              <?php echo htmlspecialchars($log['full_name']); ?></p>
                                                                                      <p class="text-[11px] text-secondary">
                                                                                              <?php echo htmlspecialchars($log['course'] ?? 'Student'); ?> • <?php echo htmlspecialchars($log['college_name'] ?? 'University'); ?></p>
                                                                              </div>
                                                                      </div>
                                                              </td>
                                                              <td class="px-lg py-4 font-body-md text-body-md truncate max-w-xs" title="<?php echo htmlspecialchars($log['tasks_completed']); ?>">
                                                                  <?php echo htmlspecialchars(mb_strimwidth($log['tasks_completed'], 0, 35, '...')); ?>
                                                              </td>
                                                              <td class="px-lg py-4">
                                                                      <span class="px-2 py-0.5 rounded-full text-[11px] font-bold flex items-center gap-1 w-max <?php echo $badge_cls; ?>">
                                                                          <span class="w-1.5 h-1.5 rounded-full bg-current inline-block"></span><?php echo $status_label; ?>
                                                                      </span>
                                                              </td>
                                                              <td class="px-lg py-4">
                                                                      <div class="w-32 bg-gray-100 h-1.5 rounded-full" title="<?php echo $time_spent; ?> hours">
                                                                              <div class="bg-primary h-full rounded-full" style="width: <?php echo $progress_width; ?>%"></div>
                                                                      </div>
                                                              </td>
                                                              <td class="px-lg py-4 text-right">
                                                                      <button onclick="window.location.href='coordinator_daily_logs.php'" class="text-primary font-label-sm text-label-sm font-bold">Details</button>
                                                              </td>
                                                      </tr>
                                                  <?php endwhile; endif; ?>
                                              </tbody>
                                      </table>
                                </div>
                                <div class="px-lg py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                                        <p class="text-label-sm text-secondary">Showing recent activity logs</p>
                                        <div class="flex gap-2">
                                                <button onclick="window.location.href='coordinator_daily_logs.php'" class="px-3 py-1 border border-outline-variant rounded bg-white text-label-sm hover:bg-gray-50">View All Logs</button>
                                        </div>
                                </div>
                      </div>
                </div>
        </main>

    <!-- Bulk Notification Modal -->
    <div id="bulk-notif-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-base flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">campaign</span> Bulk Notification
                </h3>
                <button onclick="closeBulkNotifModal()" class="text-white/80 hover:text-white cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" action="coordinator_dashboard.php" class="p-6 space-y-4">
                <input type="hidden" name="bulk_notif_action" value="1">

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Notification Title</label>
                    <input type="text" name="notif_title" required placeholder="e.g. Daily Log Reminder" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Message</label>
                    <textarea name="notif_message" required rows="3" placeholder="Type your notification message..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Recipient Type</label>
                        <select name="recipient_type" id="notif-recipient-type" onchange="toggleRecipientOptions()" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="all">All Students</option>
                            <option value="active">Active Interns</option>
                            <option value="selected">Selected Students</option>
                            <option value="team">By Project Team</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Priority</label>
                        <select name="priority" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="Normal">Normal</option>
                            <option value="Important">Important</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Student Checklist (shown when Selected Students) -->
                <div id="notif-students-container" class="hidden space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Select Students</label>
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50 max-h-40 overflow-y-auto space-y-2">
                        <?php if (empty($notif_students_list)): ?>
                            <p class="text-xs text-gray-400 italic">No students found.</p>
                        <?php else: ?>
                            <?php foreach ($notif_students_list as $ns): ?>
                                <label class="flex items-center gap-2 text-xs text-gray-700 font-medium cursor-pointer py-0.5 hover:bg-gray-100/50 rounded transition-all">
                                    <input type="checkbox" name="notif_students[]" value="<?php echo $ns['id']; ?>" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($ns['full_name']); ?></span>
                                    <span class="text-[10px] text-gray-400 font-normal"><?php echo htmlspecialchars($ns['email']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Team Dropdown (shown when By Project Team) -->
                <div id="notif-team-container" class="hidden space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Select Team</label>
                    <select name="notif_team" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                        <option value="">Choose a team...</option>
                        <?php foreach ($notif_teams_list as $tn): ?>
                            <option value="<?php echo htmlspecialchars($tn); ?>"><?php echo htmlspecialchars($tn); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeBulkNotifModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">send</span> Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Toast -->
    <?php if (!empty($notif_success)): ?>
    <div id="notif-toast" class="fixed top-6 right-6 z-[60] bg-emerald-600 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-bold flex items-center gap-2 animate-slide-in">
        <span class="material-symbols-outlined text-lg">check_circle</span>
        <?php echo htmlspecialchars($notif_success); ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($notif_error)): ?>
    <div id="notif-toast" class="fixed top-6 right-6 z-[60] bg-red-600 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-bold flex items-center gap-2 animate-slide-in">
        <span class="material-symbols-outlined text-lg">error</span>
        <?php echo htmlspecialchars($notif_error); ?>
    </div>
    <?php endif; ?>

    <script>
        function openBulkNotifModal() {
            document.getElementById('bulk-notif-modal').classList.remove('hidden');
        }
        function closeBulkNotifModal() {
            document.getElementById('bulk-notif-modal').classList.add('hidden');
        }
        function toggleRecipientOptions() {
            const type = document.getElementById('notif-recipient-type').value;
            document.getElementById('notif-students-container').classList.toggle('hidden', type !== 'selected');
            document.getElementById('notif-team-container').classList.toggle('hidden', type !== 'team');
        }
        // Auto-dismiss toast after 4 seconds
        const toast = document.getElementById('notif-toast');
        if (toast) {
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(20px)'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
    </script>

    <!-- ══ ANALYTICS FILTER JS ══ -->
    <script>
    // ── State ────────────────────────────────────────────────────────────────
    let currentSubtype = '';
    let currentProjectId = 0;

    // ── Handle subtype change ─────────────────────────────────────────────────
    async function handleSubtypeChange(subtype) {
        currentSubtype = subtype;
        currentProjectId = 0;
        await fetchFilteredData();
    }

    // ── Fetch filtered data ───────────────────────────────────────────────────
    async function fetchFilteredData() {
        setLoadingState(true);
        try {
            const url = `coordinator_analytics_api.php?subtype=${encodeURIComponent(currentSubtype)}&internship_id=0`;
            const res = await fetch(url);
            const data = await res.json();
            if (data.error) { console.error(data.error); return; }
            
            updateDashboard(data);
        } catch (e) {
            console.error('Analytics fetch failed:', e);
        } finally {
            setLoadingState(false);
        }
    }

    // ── Loading state ─────────────────────────────────────────────────────────
    function setLoadingState(loading) {
        const subtypeFilter = document.getElementById('subtype-filter');
        if (subtypeFilter) subtypeFilter.disabled = loading;
        const stats = ['stat-total-logs','stat-active-interns','stat-completion-pct','stat-pending-logs'];
        stats.forEach(id => {
            const el = document.getElementById(id);
            if (el && loading) el.style.opacity = '0.4';
            else if (el) el.style.opacity = '1';
        });
    }

    // ── Update all dashboard sections ─────────────────────────────────────────
    function updateDashboard(data) {
        // Subtitle
        const subtitleActive = document.getElementById('subtitle-active');
        if (subtitleActive) subtitleActive.textContent = data.active_interns;
        const subtitleText = document.getElementById('subtitle-text');
        if (subtitleText) {
            let label = 'All Internships';
            if (currentProjectId > 0 && data.internship_title) {
                label = `"${data.internship_title}"`;
            } else if (currentSubtype) {
                label = `Subtype: ${currentSubtype}`;
            }
            subtitleText.innerHTML = `${label} • <span id="subtitle-active">${data.active_interns}</span> Active Interns`;
        }

        // ── Metric cards ──────────────────────────────────────────────────────
        animateCount('stat-total-logs',    data.total_logs);
        animateCount('stat-active-interns',  data.active_interns);
        animateCount('stat-pending-logs',  data.pending_logs);
        setTextWithTransition('stat-completion-pct', data.completion_pct + '%');


        // ── Project Filling card ──────────────────────────────────────────────
        const assignedRatio = document.getElementById('stat-assigned-ratio');
        if (assignedRatio) assignedRatio.textContent = `${data.active_interns}/${data.total_interns}`;
        const assignedBar = document.getElementById('stat-assigned-bar');
        if (assignedBar) assignedBar.style.width = data.assigned_pct + '%';
        const openProj = document.getElementById('stat-open-projects');
        if (openProj) openProj.textContent = data.open_projects;
        const openProjBar = document.getElementById('stat-open-projects-bar');
        if (openProjBar) {
            const pct = data.open_projects > 0 ? Math.min(100, data.open_projects * 10) : 0;
            openProjBar.style.width = pct + '%';
        }

        // ── Pipeline grid ─────────────────────────────────────────────────────
        const pipelineGrid = document.getElementById('pipeline-grid');
        if (pipelineGrid) {
            if (!data.pipeline_projects || data.pipeline_projects.length === 0) {
                pipelineGrid.innerHTML = `<div class="text-center py-6 text-secondary text-body-md w-full">No active projects found.</div>`;
            } else {
                pipelineGrid.innerHTML = data.pipeline_projects.map(p => {
                    const subtype = p.project_subtype || 'General';
                    let bgColor = 'bg-slate-50 text-slate-600';
                    if (/web/i.test(subtype)) { bgColor = 'bg-blue-50 text-blue-600'; }
                    else if (/design|ui/i.test(subtype)) { bgColor = 'bg-purple-50 text-purple-600'; }
                    else if (/marketing|seo|social/i.test(subtype)) { bgColor = 'bg-green-50 text-green-600'; }
                    else if (/apps|mobile/i.test(subtype)) { bgColor = 'bg-indigo-50 text-indigo-600'; }
                    else if (/system|backend/i.test(subtype)) { bgColor = 'bg-amber-50 text-amber-600'; }

                    const avatars = Array.from({length: Math.min(2, p.assigned_count)}, () =>
                        `<img alt="Intern" class="w-8 h-8 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=I&background=0D8ABC&color=fff" />`
                    ).join('');
                    const extra = p.assigned_count > 2
                        ? `<div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">+${p.assigned_count - 2}</div>` : '';
                    return `<div class="border border-outline-variant rounded-lg p-md flex flex-col justify-between hover:shadow-md transition-all flex-shrink-0 w-[320px] min-w-[320px] max-w-[320px]">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-label-sm font-label-sm px-2 py-0.5 ${bgColor} rounded">${escHtml(subtype)}</span>
                                <span class="material-symbols-outlined text-gray-400">more_vert</span>
                            </div>
                            <h4 class="font-label-md text-label-md font-bold text-on-surface truncate" title="${escHtml(p.title)}">${escHtml(p.title)}</h4>
                            <p class="text-body-md font-body-md text-secondary mt-1">Duration: ${escHtml(p.duration)} • Mode: ${escHtml(p.mode)}</p>
                            <p class="text-xs text-gray-600 mt-1">
                                <span class="font-semibold">Mentor:</span> ${escHtml(p.mentor_name || 'None')}
                            </p>
                            <p class="text-xs text-gray-600 mt-0.5">
                                <span class="font-semibold">Status:</span> <span class="text-green-600 font-bold">${escHtml(p.status)}</span>
                            </p>
                        </div>
                        <div class="mt-lg flex justify-between items-center">
                            <div class="flex -space-x-2">${avatars}${extra}</div>
                            <span class="text-label-sm text-green-600 font-label-sm">${p.assigned_count} Assigned</span>
                        </div>
                    </div>`;
                }).join('');
            }
        }

        // ── Recent logs table ─────────────────────────────────────────────────
        const tbody = document.getElementById('logs-table-body');
        if (tbody) {
            if (!data.recent_logs || data.recent_logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-lg py-4 text-center text-secondary text-body-md">No daily logs submitted yet.</td></tr>`;
            } else {
                tbody.innerHTML = data.recent_logs.map(log => {
                    const pct = Math.min(100, Math.round((log.time_spent / 8.0) * 100));
                    const focusCls = log.focus_level === 'High' ? 'bg-green-100 text-green-700 border-green-200'
                                   : log.focus_level === 'Low'  ? 'bg-red-100 text-red-700 border-red-200'
                                   : 'bg-amber-100 text-amber-700 border-amber-200';
                    return `<tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-lg py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary-fixed overflow-hidden flex items-center justify-center text-primary font-bold">
                                    ${escHtml(log.full_name.charAt(0).toUpperCase())}
                                </div>
                                <div>
                                    <p class="font-label-md text-label-md font-bold">${escHtml(log.full_name)}</p>
                                    <p class="text-[11px] text-secondary">${escHtml(log.course)} • ${escHtml(log.college_name)}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-lg py-4 font-body-md text-body-md truncate max-w-xs">${escHtml(log.tasks_completed)}</td>
                        <td class="px-lg py-4">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold flex items-center gap-1 w-max ${focusCls}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current inline-block"></span>${escHtml(log.focus_level)}
                            </span>
                        </td>
                        <td class="px-lg py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-100 h-1.5 rounded-full">
                                    <div class="bg-primary h-full rounded-full" style="width:${pct}%"></div>
                                </div>
                                <span class="text-xs text-secondary">${log.time_spent}h</span>
                            </div>
                        </td>
                        <td class="px-lg py-4 text-right">
                            <button onclick="window.location.href='coordinator_daily_logs.php'" class="text-primary font-label-sm text-label-sm font-bold">Details</button>
                        </td>
                    </tr>`;
                }).join('');
            }
        }

    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function animateCount(id, target) {
        const el = document.getElementById(id);
        if (!el) return;
        const start = parseInt(el.textContent) || 0;
        const diff  = target - start;
        const steps = 20;
        let step = 0;
        const timer = setInterval(() => {
            step++;
            el.textContent = Math.round(start + (diff * step / steps));
            if (step >= steps) { el.textContent = target; clearInterval(timer); }
        }, 20);
    }

    function setTextWithTransition(id, text) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.opacity = '0';
        setTimeout(() => { el.textContent = text; el.style.opacity = '1'; }, 150);
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init: load global data on page load ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        fetchFilteredData();

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
    });
    </script>
</body>
</html>