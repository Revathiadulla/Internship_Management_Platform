<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$success_msg = "";
$error_msg = "";

// Process Create/Edit Team Postings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $team_name = trim($_POST['team_name']);
    $internship_id = intval($_POST['internship_id']);
    $mentor_id = intval($_POST['mentor_id']);
    $team_status = trim($_POST['team_status']);
    $students = isset($_POST['students']) ? $_POST['students'] : [];

    if (empty($team_name) || $internship_id <= 0 || $mentor_id <= 0) {
        $error_msg = "Please fill in all required fields.";
    } else {
        mysqli_begin_transaction($conn);
        $success = true;

        $project_type = isset($_POST['project_type']) ? trim($_POST['project_type']) : '';
        $project_subtype = isset($_POST['project_subtype']) ? trim($_POST['project_subtype']) : '';

        if ($action !== 'delete_team' && (empty($project_type) || empty($project_subtype))) {
            $proj_stmt = mysqli_prepare($conn, "SELECT project_type, project_subtype FROM internships WHERE id = ?");
            mysqli_stmt_bind_param($proj_stmt, "i", $internship_id);
            mysqli_stmt_execute($proj_stmt);
            mysqli_stmt_bind_result($proj_stmt, $db_type, $db_subtype);
            if (mysqli_stmt_fetch($proj_stmt)) {
                $project_type = $db_type ?: '';
                $project_subtype = $db_subtype ?: '';
            }
            mysqli_stmt_close($proj_stmt);
        }

        if ($action === 'create_team') {
            // Check if team name already exists
            $check_team_sql = "SELECT id FROM internship_applications WHERE team_name = ? LIMIT 1";
            $check_team_stmt = mysqli_prepare($conn, $check_team_sql);
            mysqli_stmt_bind_param($check_team_stmt, "s", $team_name);
            mysqli_stmt_execute($check_team_stmt);
            mysqli_stmt_store_result($check_team_stmt);
            if (mysqli_stmt_num_rows($check_team_stmt) > 0) {
                $error_msg = "A team with name '" . htmlspecialchars($team_name) . "' already exists.";
                $success = false;
            }
            mysqli_stmt_close($check_team_stmt);

            if ($success) {
                // Assign new team to selected students
                foreach ($students as $student_id) {
                    $student_id = intval($student_id);
                    // Check if student already has application for this project
                    $app_chk_sql = "SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1";
                    $app_chk_stmt = mysqli_prepare($conn, $app_chk_sql);
                    mysqli_stmt_bind_param($app_chk_stmt, "ii", $student_id, $internship_id);
                    mysqli_stmt_execute($app_chk_stmt);
                    $app_chk_res = mysqli_stmt_get_result($app_chk_stmt);
                    
                    if ($app_row = mysqli_fetch_assoc($app_chk_res)) {
                        // Update existing application
                        $update_sql = "UPDATE internship_applications SET team_name = ?, mentor_id = ?, team_status = ?, project_type = ?, project_subtype = ? WHERE user_id = ? AND internship_id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "sisssii", $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $student_id, $internship_id);
                        if (!mysqli_stmt_execute($update_stmt)) {
                            $success = false;
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        // Insert new application with 'Started' status (active intern)
                        $insert_sql = "INSERT INTO internship_applications (user_id, internship_id, status, team_name, mentor_id, team_status, project_type, project_subtype) VALUES (?, ?, 'Started', ?, ?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_sql);
                        mysqli_stmt_bind_param($insert_stmt, "iisissss", $student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype);
                        if (!mysqli_stmt_execute($insert_stmt)) {
                            $success = false;
                        }
                        mysqli_stmt_close($insert_stmt);
                    }
                    mysqli_stmt_close($app_chk_stmt);
                }
            }

        } elseif ($action === 'edit_team') {
            $old_team_name = trim($_POST['old_team_name']);

            // 1. Clear previous assignments for this team name
            $clear_sql = "UPDATE internship_applications SET team_name = NULL, mentor_id = NULL, team_status = 'Active' WHERE team_name = ?";
            $clear_stmt = mysqli_prepare($conn, $clear_sql);
            mysqli_stmt_bind_param($clear_stmt, "s", $old_team_name);
            if (!mysqli_stmt_execute($clear_stmt)) {
                $success = false;
            }
            mysqli_stmt_close($clear_stmt);

            // 2. Re-assign to selected students
            if ($success) {
                foreach ($students as $student_id) {
                    $student_id = intval($student_id);
                    // Check if student has application
                    $app_chk_sql = "SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1";
                    $app_chk_stmt = mysqli_prepare($conn, $app_chk_sql);
                    mysqli_stmt_bind_param($app_chk_stmt, "ii", $student_id, $internship_id);
                    mysqli_stmt_execute($app_chk_stmt);
                    $app_chk_res = mysqli_stmt_get_result($app_chk_stmt);
                    
                    if ($app_row = mysqli_fetch_assoc($app_chk_res)) {
                        $update_sql = "UPDATE internship_applications SET team_name = ?, mentor_id = ?, team_status = ?, project_type = ?, project_subtype = ? WHERE user_id = ? AND internship_id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "sisssii", $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $student_id, $internship_id);
                        if (!mysqli_stmt_execute($update_stmt)) {
                            $success = false;
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $insert_sql = "INSERT INTO internship_applications (user_id, internship_id, status, team_name, mentor_id, team_status, project_type, project_subtype) VALUES (?, ?, 'Started', ?, ?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_sql);
                        mysqli_stmt_bind_param($insert_stmt, "iisissss", $student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype);
                        if (!mysqli_stmt_execute($insert_stmt)) {
                            $success = false;
                        }
                        mysqli_stmt_close($insert_stmt);
                    }
                    mysqli_stmt_close($app_chk_stmt);
                }
            }
        } elseif ($action === 'delete_team') {
            $del_team_name = trim($_POST['team_name']);
            $clear_sql = "UPDATE internship_applications SET team_name = NULL, mentor_id = NULL, team_status = 'Active' WHERE team_name = ?";
            $clear_stmt = mysqli_prepare($conn, $clear_sql);
            mysqli_stmt_bind_param($clear_stmt, "s", $del_team_name);
            if (!mysqli_stmt_execute($clear_stmt)) {
                $success = false;
            }
            mysqli_stmt_close($clear_stmt);
        }

        if ($success) {
            mysqli_commit($conn);
            $success_msg = "Team assignment operation successful!";
        } else {
            mysqli_rollback($conn);
            $error_msg = "Database error executing team operations.";
        }
    }
}

// Fetch Search Query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_clause = "";
$search_params = [];
$search_types = "";
if (!empty($search)) {
    $search_clause = " AND (a.team_name LIKE ? OR m.full_name LIKE ? OR i.title LIKE ?) ";
    $search_param = "%" . $search . "%";
    $search_params = [$search_param, $search_param, $search_param];
    $search_types = "sss";
}

// Fetch all project teams
$teams_sql = "
    SELECT DISTINCT a.team_name, a.mentor_id, a.internship_id, a.team_status, 
                    m.full_name as mentor_name, m.email as mentor_email, m.phone as mentor_phone,
                    i.title as project_title
    FROM internship_applications a
    LEFT JOIN users m ON a.mentor_id = m.id
    LEFT JOIN internships i ON a.internship_id = i.id
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

// Fetch all active students and their applied internships
$students_sql = "
    SELECT u.id, u.full_name, u.email, sp.college_name,
           a.internship_id, a.team_name as assigned_team,
           i.project_subtype as applied_subtype
    FROM users u
    JOIN internship_applications a ON u.id = a.user_id
    LEFT JOIN internships i ON a.internship_id = i.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.role = 'student'
    ORDER BY u.full_name ASC
";
$students_res = mysqli_query($conn, $students_sql);
$students_list = [];
while ($row = mysqli_fetch_assoc($students_res)) {
    $students_list[] = $row;
}

// Fetch all mentors
$mentors_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role='mentor' ORDER BY full_name ASC");
$mentors_list = [];
while ($row = mysqli_fetch_assoc($mentors_res)) {
    $mentors_list[] = $row;
}

// Fetch all active projects
$projects_res = mysqli_query($conn, "SELECT id, title, project_title, task_title, project_type, project_subtype, technology_stack, duration, start_date, end_date FROM internships WHERE status='Active' ORDER BY project_title ASC, title ASC");
$projects_list = [];
while ($row = mysqli_fetch_assoc($projects_res)) {
    $projects_list[] = $row;
}

// Fetch assigned project IDs to filter dropdown
$assigned_res = mysqli_query($conn, "SELECT DISTINCT internship_id FROM internship_applications WHERE team_name IS NOT NULL AND team_name != ''");
$assigned_project_ids = [];
while ($row = mysqli_fetch_assoc($assigned_res)) {
    $assigned_project_ids[] = intval($row['internship_id']);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Team Assignment - Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #191c1d; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
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
        <!-- SideNavBar -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
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
                <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
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
                <a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 duration-200 ease-in-out"
                    href="coordinator_teams.php">
                    <span class="material-symbols-outlined">manage_accounts</span>
                    <span>Team Management</span>
                </a>
            </nav>
            <div class="mt-auto border-t border-gray-200 pt-4 font-medium">
                <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                    href="coordinator_help_center.php">
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
                                <h2 class="text-lg font-bold text-gray-800">Team Management</h2>
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


                <div class="flex-1 p-8 space-y-6">
                
                <?php if ($success_msg): ?>
                    <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2">
                        <span class="material-symbols-outlined text-green-500">check_circle</span>
                        <span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-500">error</span>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Project Team Assignment</h1>
                        <p class="text-gray-500 text-sm mt-1 font-medium">Create project squads, assign mentors, allocate students, and track squad performance statuses.</p>
                    </div>
                    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">add</span> Create Project Team
                    </button>
                </div>

                <!-- Search & Filters -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <form method="GET" action="coordinator_teams.php" class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by team name, mentor, or project..." 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-xs font-bold transition-all">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="coordinator_teams.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Grid of team cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($teams_list)): ?>
                        <div class="col-span-full bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
                            <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">groups</span>
                            <h3 class="text-base font-bold text-gray-800">No project teams assigned yet</h3>
                            <p class="text-xs text-gray-500 mt-1">Click the "Create Project Team" button to initialize team allocations.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($teams_list as $team): 
                            $team_name_val = $team['team_name'];
                            $team_status_val = $team['team_status'] ?: 'Active';

                            // Get assigned students for this team
                            $student_stmt = mysqli_prepare($conn, "
                                SELECT u.id, u.full_name, u.email, sp.college_name 
                                FROM internship_applications a
                                JOIN users u ON a.user_id = u.id
                                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                                WHERE a.team_name = ?
                            ");
                            mysqli_stmt_bind_param($student_stmt, "s", $team_name_val);
                            mysqli_stmt_execute($student_stmt);
                            $student_res = mysqli_stmt_get_result($student_stmt);
                            $assigned_students = [];
                            $assigned_ids = [];
                            while ($s_row = mysqli_fetch_assoc($student_res)) {
                                $assigned_students[] = $s_row;
                                $assigned_ids[] = $s_row['id'];
                            }
                            mysqli_stmt_close($student_stmt);

                            // Status color pill
                            $status_color = match($team_status_val) {
                                'Completed' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                'On Hold' => 'bg-amber-50 text-amber-700 border-amber-100',
                                default => 'bg-blue-50 text-blue-700 border-blue-100'
                            };
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
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($team['project_title'] ?: 'General Project'); ?></p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($team_status_val); ?>
                                        </span>
                                    </div>

                                    <!-- Assigned Mentor Info -->
                                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-100 space-y-1">
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wide">Mentor Coordinator</div>
                                        <p class="text-xs text-gray-800 font-bold"><?php echo htmlspecialchars($team['mentor_name'] ?: 'No Mentor Assigned'); ?></p>
                                        <?php if (!empty($team['mentor_email'])): ?>
                                            <p class="text-[10px] text-gray-500 font-medium truncate"><?php echo htmlspecialchars($team['mentor_email']); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Assigned Students list -->
                                    <div class="space-y-2">
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wide flex justify-between">
                                            <span>Assigned Squad</span>
                                            <span><?php echo count($assigned_students); ?> Students</span>
                                        </div>
                                        <div class="divide-y divide-gray-100 max-h-40 overflow-y-auto pr-1">
                                            <?php if (empty($assigned_students)): ?>
                                                <p class="text-xs text-gray-400 italic py-2">No students assigned.</p>
                                            <?php else: ?>
                                                <?php foreach ($assigned_students as $st_user): ?>
                                                    <div class="py-2 flex items-center justify-between text-xs">
                                                        <div>
                                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($st_user['full_name']); ?></p>
                                                            <p class="text-[10px] text-gray-400 truncate"><?php echo htmlspecialchars($st_user['college_name'] ?: 'College N/A'); ?></p>
                                                        </div>
                                                        <a href="mailto:<?php echo htmlspecialchars($st_user['email']); ?>" class="text-blue-500 hover:text-blue-700">
                                                            <span class="material-symbols-outlined text-sm">mail</span>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="pt-4 border-t border-gray-100 flex gap-2">
                                    <button onclick='openEditModal(<?php echo json_encode($team); ?>, <?php echo json_encode($assigned_ids); ?>)' 
                                            class="flex-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                        Edit Squad
                                    </button>
                                    <form method="POST" action="coordinator_teams.php" onsubmit="return confirm('Are you sure you want to delete and dissolve this project team?');" class="flex-1">
                                        <input type="hidden" name="action" value="delete_team">
                                        <input type="hidden" name="team_name" value="<?php echo htmlspecialchars($team_name_val); ?>">
                                        <button type="submit" class="w-full bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-center py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                            Dissolve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>

    <!-- Team Assignment Overlay Modal -->
    <div id="team-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">groups</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" id="modal-title">Create Project Team</h3>
                        <p class="text-xs text-gray-500 font-medium">Allocate students and design squad assignments.</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form method="POST" action="coordinator_teams.php" id="team-form" class="p-8 space-y-4 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="action" id="form-action" value="create_team">
                <input type="hidden" name="old_team_name" id="form-old-team-name" value="">

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Team Name</label>
                    <input type="text" name="team_name" id="form-team-name" required placeholder="e.g. Squad Phoenix" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project Type</label>
                        <select name="project_type" id="form-project-type" onchange="onProjectTypeChange()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Type...</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project Subtype</label>
                        <select name="project_subtype" id="form-project-subtype" onchange="onProjectSubtypeChange()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Subtype...</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project / Internship</label>
                        <select name="internship_id" id="form-project-id" onchange="updateProjectDetails()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Project...</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Mentor Assignment</label>
                        <select name="mentor_id" id="form-mentor-id" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Mentor...</option>
                            <?php foreach ($mentors_list as $mentor): ?>
                                <option value="<?php echo $mentor['id']; ?>"><?php echo htmlspecialchars($mentor['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Auto-displayed Project Details -->
                <div id="project-details-container" class="hidden bg-blue-50/50 border border-blue-100 rounded-lg p-4 space-y-3">
                    <div class="flex items-start justify-between border-b border-blue-100/50 pb-2">
                        <div>
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider flex items-center gap-1.5 mb-0.5"><span class="material-symbols-outlined text-[14px]">info</span> Project Overview</p>
                            <p id="detail-title" class="font-bold text-gray-900 text-sm"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Timeline</p>
                            <p id="detail-dates" class="font-bold text-blue-700 text-xs mt-0.5"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                        <div><span class="text-gray-500 font-medium">Domain/Type:</span> <span id="detail-type" class="font-bold text-gray-800"></span></div>
                        <div><span class="text-gray-500 font-medium">Subtype:</span> <span id="detail-subtype" class="font-bold text-gray-800"></span></div>
                        <div><span class="text-gray-500 font-medium">Stack:</span> <span id="detail-stack" class="font-bold text-gray-800 truncate block"></span></div>
                        <div><span class="text-gray-500 font-medium">Duration:</span> <span id="detail-duration" class="font-bold text-gray-800"></span></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Squad Progress Status</label>
                    <select name="team_status" id="form-team-status" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                        <option value="Active">Active / In Progress</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Select Students (Check to Assign)</label>
                    <div id="student-checklist-container" class="border border-gray-200 rounded-lg p-3 bg-gray-50 max-h-48 overflow-y-auto space-y-2.5">
                        <p class="text-xs text-gray-400 italic">Please select a project to view applicants.</p>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer">Save Assignments</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const projectsData = <?php echo json_encode($projects_list); ?>;
        const assignedProjectIds = <?php echo json_encode($assigned_project_ids); ?>;
        const studentsData = <?php echo json_encode($students_list); ?>;
        let currentlyAssignedStudentIds = [];

        const modal = document.getElementById('team-modal');
        const modalTitle = document.getElementById('modal-title');
        const formAction = document.getElementById('form-action');
        const formOldTeamName = document.getElementById('form-old-team-name');
        const formTeamName = document.getElementById('form-team-name');
        const formProjectId = document.getElementById('form-project-id');
        const formMentorId = document.getElementById('form-mentor-id');
        const formTeamStatus = document.getElementById('form-team-status');

        const defaultTypesMap = {
            "Web Development": ["Frontend", "Backend", "Full Stack"],
            "Data Analytics": ["Excel Dashboard", "Power BI", "SQL Analysis"],
            "AI / ML": ["Machine Learning", "Deep Learning", "NLP"]
        };

        function populateProjectTypeDropdown(selectedType = '') {
            const typeSelect = document.getElementById('form-project-type');
            while (typeSelect.options.length > 1) {
                typeSelect.remove(1);
            }
            
            const types = new Set();
            projectsData.forEach(p => {
                if (p.project_type) {
                    types.add(p.project_type);
                }
            });
            
            Object.keys(defaultTypesMap).forEach(t => types.add(t));
            
            types.forEach(type => {
                const opt = document.createElement('option');
                opt.value = type;
                opt.textContent = type;
                if (type === selectedType) {
                    opt.selected = true;
                }
                typeSelect.appendChild(opt);
            });
        }

        function populateProjectSubtypeDropdown(type, selectedSubtype = '') {
            const subtypeSelect = document.getElementById('form-project-subtype');
            while (subtypeSelect.options.length > 1) {
                subtypeSelect.remove(1);
            }
            
            if (!type) return;
            
            const subtypes = new Set();
            projectsData.forEach(p => {
                if (p.project_type === type && p.project_subtype) {
                    subtypes.add(p.project_subtype);
                }
            });
            
            if (defaultTypesMap[type]) {
                defaultTypesMap[type].forEach(s => subtypes.add(s));
            }
            
            subtypes.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub;
                opt.textContent = sub;
                if (sub === selectedSubtype) {
                    opt.selected = true;
                }
                subtypeSelect.appendChild(opt);
            });
        }

        function populateProjectDropdown(subtype, currentInternshipId = null) {
            while (formProjectId.options.length > 1) {
                formProjectId.remove(1);
            }
            
            if (!subtype) return;
            
            projectsData.forEach(proj => {
                const pId = parseInt(proj.id);
                const isAssigned = assignedProjectIds.includes(pId);
                
                if (proj.project_subtype === subtype) {
                    if (!isAssigned || pId === currentInternshipId) {
                        const opt = document.createElement('option');
                        opt.value = pId;
                        let displayTitle = proj.project_title || proj.title || 'Untitled Project';
                        if (proj.task_title && proj.task_title !== proj.project_title) {
                            displayTitle += ' - ' + proj.task_title;
                        }
                        opt.textContent = displayTitle;
                        if (pId === currentInternshipId) {
                            opt.selected = true;
                        }
                        formProjectId.appendChild(opt);
                    }
                }
            });
        }

        function onProjectTypeChange() {
            const type = document.getElementById('form-project-type').value;
            populateProjectSubtypeDropdown(type);
            populateProjectDropdown('');
            updateProjectDetails();
        }

        function onProjectSubtypeChange() {
            const subtype = document.getElementById('form-project-subtype').value;
            populateProjectDropdown(subtype);
            updateProjectDetails();
        }

        function renderStudentChecklist(internshipId) {
            const container = document.getElementById('student-checklist-container');
            container.innerHTML = '';
            
            if (isNaN(internshipId)) {
                container.innerHTML = '<p class="text-xs text-gray-400 italic">Please select a project to view applicants.</p>';
                return;
            }
            
            const selectedProject = projectsData.find(p => parseInt(p.id) === internshipId);
            if (!selectedProject || !selectedProject.project_subtype) {
                container.innerHTML = '<p class="text-xs text-gray-400 italic">No subtype defined for this project.</p>';
                return;
            }
            
            const targetSubtype = selectedProject.project_subtype;
            
            const uniqueStudents = [];
            const seenIds = new Set();
            
            studentsData.forEach(st => {
                if (st.applied_subtype === targetSubtype && !seenIds.has(st.id)) {
                    seenIds.add(st.id);
                    uniqueStudents.push(st);
                }
            });
            
            if (uniqueStudents.length === 0) {
                container.innerHTML = `<p class="text-xs text-gray-400 italic">No students have applied for the ${targetSubtype} subtype.</p>`;
                return;
            }
            
            uniqueStudents.forEach(st => {
                const isChecked = currentlyAssignedStudentIds.includes(parseInt(st.id));
                const checkedAttr = isChecked ? 'checked' : '';
                
                let assignmentSpan = '';
                if (st.assigned_team && st.assigned_team !== '') {
                    assignmentSpan = `<span class="text-indigo-600 font-bold ml-1">(Assigned to: ${st.assigned_team})</span>`;
                }
                
                const html = `
                    <label class="flex items-start gap-2.5 text-xs text-gray-700 font-medium cursor-pointer py-0.5 hover:bg-gray-100/50 rounded transition-all">
                        <input type="checkbox" name="students[]" value="${st.id}" ${checkedAttr} class="student-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-0.5 cursor-pointer">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">${st.full_name}</p>
                            <p class="text-[10px] text-gray-500 font-normal">
                                ${st.college_name || 'College N/A'}
                                ${assignmentSpan}
                            </p>
                        </div>
                    </label>
                `;
                container.innerHTML += html;
            });
        }

        function openCreateModal() {
            currentlyAssignedStudentIds = [];
            modalTitle.textContent = "Create Project Team";
            formAction.value = "create_team";
            formOldTeamName.value = "";
            formTeamName.value = "";
            formTeamName.readOnly = false;

            populateProjectTypeDropdown('');
            populateProjectSubtypeDropdown('');
            populateProjectDropdown('');

            formMentorId.value = "";
            formTeamStatus.value = "Active";

            updateProjectDetails();
            modal.classList.remove('hidden');
        }

        function openEditModal(team, assignedStudentIds) {
            currentlyAssignedStudentIds = assignedStudentIds;
            modalTitle.textContent = "Edit Project Team";
            formAction.value = "edit_team";
            formOldTeamName.value = team.team_name;
            formTeamName.value = team.team_name;

            const project = projectsData.find(p => parseInt(p.id) === parseInt(team.internship_id));
            const type = project ? (project.project_type || '') : '';
            const subtype = project ? (project.project_subtype || '') : '';

            populateProjectTypeDropdown(type);
            populateProjectSubtypeDropdown(type, subtype);
            populateProjectDropdown(subtype, parseInt(team.internship_id));

            formMentorId.value = team.mentor_id;
            formTeamStatus.value = team.team_status || "Active";

            updateProjectDetails();
            modal.classList.remove('hidden');
        }

        function updateProjectDetails() {
            const container = document.getElementById('project-details-container');
            const projectId = parseInt(formProjectId.value);
            
            // Trigger student checklist render
            renderStudentChecklist(projectId);
            
            if (isNaN(projectId)) {
                container.classList.add('hidden');
                return;
            }
            
            const project = projectsData.find(p => parseInt(p.id) === projectId);
            if (project) {
                let displayTitle = project.project_title || project.title || 'Untitled Project';
                if (project.task_title && project.task_title !== project.project_title) {
                    displayTitle += ' - ' + project.task_title;
                }
                document.getElementById('detail-title').textContent = displayTitle;
                
                // Format dates if available
                let dateStr = 'Dates TBD';
                if (project.start_date && project.end_date) {
                    const start = new Date(project.start_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
                    const end = new Date(project.end_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
                    dateStr = `${start} - ${end}`;
                } else if (project.start_date) {
                    dateStr = `Starts: ${new Date(project.start_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}`;
                }
                document.getElementById('detail-dates').textContent = dateStr;

                document.getElementById('detail-type').textContent = project.project_type || 'General';
                document.getElementById('detail-subtype').textContent = project.project_subtype || 'N/A';
                document.getElementById('detail-stack').textContent = project.technology_stack || 'Not specified';
                document.getElementById('detail-duration').textContent = project.duration || 'Not specified';
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

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
