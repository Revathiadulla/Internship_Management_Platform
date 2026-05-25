<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$success_msg = "";
$error_msg = "";

// Generate Timeline Phases helper function
function generatePhases($conn, $internship_id, $duration, $start_date_str) {
    if (empty($start_date_str)) return;
    $start_date = new DateTime($start_date_str);
    
    $phase_names = [
        1 => 'P1 Learning Phase',
        2 => 'P2 Documentation & Planning',
        3 => 'P3 Designing',
        4 => 'P4 Development',
        5 => 'P5 Testing',
        6 => 'P6 Deployment'
    ];
    
    $days = [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5];
    
    if ($duration === '1 Month') {
        $days = [1 => 3, 2 => 3, 3 => 5, 4 => 10, 5 => 5, 6 => 4];
    } elseif ($duration === '2 Months') {
        $days = [1 => 7, 2 => 7, 3 => 14, 4 => 21, 5 => 7, 6 => 7];
    } elseif ($duration === '3 Months') {
        $days = [1 => 14, 2 => 14, 3 => 21, 4 => 35, 5 => 14, 6 => 14];
    } else {
        // Fallback for custom months (e.g. 6 Months)
        preg_match('/(\d+)/', $duration, $matches);
        $num_months = isset($matches[1]) ? intval($matches[1]) : 3;
        $total_days = $num_months * 30;
        
        $days[1] = round($total_days * 0.15);
        $days[2] = round($total_days * 0.15);
        $days[3] = round($total_days * 0.20);
        $days[4] = round($total_days * 0.30);
        $days[5] = round($total_days * 0.10);
        $days[6] = $total_days - ($days[1] + $days[2] + $days[3] + $days[4] + $days[5]);
    }
    
    // Delete existing phases
    $del_stmt = mysqli_prepare($conn, "DELETE FROM internship_phases WHERE internship_id = ?");
    mysqli_stmt_bind_param($del_stmt, "i", $internship_id);
    mysqli_stmt_execute($del_stmt);
    mysqli_stmt_close($del_stmt);
    
    // Insert new phases
    $current_start = clone $start_date;
    $today = date('Y-m-d');
    for ($p = 1; $p <= 6; $p++) {
        $current_end = clone $current_start;
        $day_offset = $days[$p] - 1;
        if ($day_offset < 0) $day_offset = 0;
        $current_end->modify("+$day_offset days");
        
        $s_str = $current_start->format('Y-m-d');
        $e_str = $current_end->format('Y-m-d');
        
        $status = 'Pending';
        if ($today >= $s_str && $today <= $e_str) {
            $status = 'Active';
        } elseif ($today > $e_str) {
            $status = 'Completed';
        }
        
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO internship_phases (internship_id, phase_number, phase_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "iissss", $internship_id, $p, $phase_names[$p], $s_str, $e_str, $status);
        mysqli_stmt_execute($ins_stmt);
        mysqli_stmt_close($ins_stmt);
        
        $current_start = clone $current_end;
        $current_start->modify("+1 day");
    }
}

// Handle AJAX timeline fetch
if (isset($_GET['action']) && $_GET['action'] === 'get_timeline' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = mysqli_query($conn, "SELECT * FROM internship_phases WHERE internship_id = $id ORDER BY phase_number ASC");
    $phases = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $phases[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($phases);
    exit();
}

// Handle Timeline Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_timeline') {
    $internship_id = intval($_POST['internship_id']);
    $phase_ids = $_POST['phase_id'];
    $start_dates = $_POST['start_date'];
    $end_dates = $_POST['end_date'];
    $statuses = $_POST['status'];
    
    $success = true;
    for ($i = 0; $i < count($phase_ids); $i++) {
        $pid = intval($phase_ids[$i]);
        $s_date = $start_dates[$i];
        $e_date = $end_dates[$i];
        $stat = $statuses[$i];
        
        $stmt = mysqli_prepare($conn, "UPDATE internship_phases SET start_date = ?, end_date = ?, status = ? WHERE id = ? AND internship_id = ?");
        mysqli_stmt_bind_param($stmt, "sssii", $s_date, $e_date, $stat, $pid, $internship_id);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
        }
        mysqli_stmt_close($stmt);
    }
    if ($success) {
        header("Location: coordinator_internships.php?success=" . urlencode("Timeline workflow updated successfully!"));
        exit();
    } else {
        $error_msg = "Error updating timeline.";
    }
}

// Fetch mentors list for dropdowns
$mentors_res = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role='mentor' ORDER BY full_name ASC");
$mentors = [];
while ($row = mysqli_fetch_assoc($mentors_res)) {
    $mentors[] = $row;
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM internships WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: coordinator_internships.php?success=" . urlencode("Project posting deleted successfully!"));
        exit();
    } else {
        $error_msg = "Error deleting posting: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Handle Create Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title']);
    $project_title = $title;
    $task_title = $title;
    $duration = trim($_POST['duration']);
    $mode = trim($_POST['mode']);
    $technology_stack = trim($_POST['technology_stack']);
    $skills = $technology_stack; // sync skills with technology stack
    $status = trim($_POST['status']);
    
    $description = trim($_POST['description']);
    $project_type = trim($_POST['project_type']);
    $project_subtype = trim($_POST['project_subtype']);
    $difficulty_level = trim($_POST['difficulty_level']);
    $assigned_mentor = intval($_POST['assigned_mentor']);
    $mentor_val = $assigned_mentor > 0 ? $assigned_mentor : null;
    $openings = intval($_POST['openings']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    if (empty($title) || empty($project_title) || empty($duration) || empty($mode) || empty($technology_stack) || empty($description)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO internships (title, duration, mode, skills, status, description, project_type, project_subtype, project_title, task_title, technology_stack, difficulty_level, assigned_mentor, openings, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssssssssssiiss", $title, $duration, $mode, $skills, $status, $description, $project_type, $project_subtype, $project_title, $task_title, $technology_stack, $difficulty_level, $mentor_val, $openings, $start_date, $end_date);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            generatePhases($conn, $new_id, $duration, $start_date);
            header("Location: coordinator_internships.php?success=" . urlencode("Project posting created successfully!"));
            exit();
        } else {
            $error_msg = "Error creating project posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $project_title = $title;
    $task_title = $title;
    $duration = trim($_POST['duration']);
    $mode = trim($_POST['mode']);
    $technology_stack = trim($_POST['technology_stack']);
    $skills = $technology_stack; // sync skills with technology stack
    $status = trim($_POST['status']);
    
    $description = trim($_POST['description']);
    $project_type = trim($_POST['project_type']);
    $project_subtype = trim($_POST['project_subtype']);
    $difficulty_level = trim($_POST['difficulty_level']);
    $assigned_mentor = intval($_POST['assigned_mentor']);
    $mentor_val = $assigned_mentor > 0 ? $assigned_mentor : null;
    $openings = intval($_POST['openings']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    if (empty($title) || empty($project_title) || empty($duration) || empty($mode) || empty($technology_stack) || empty($description)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        // Fetch old values to check for changes
        $old_res = mysqli_query($conn, "SELECT start_date, duration FROM internships WHERE id = $id");
        $old_row = mysqli_fetch_assoc($old_res);
        $date_changed = ($old_row && $old_row['start_date'] !== $start_date);
        $duration_changed = ($old_row && $old_row['duration'] !== $duration);

        $stmt = mysqli_prepare($conn, "UPDATE internships SET title = ?, duration = ?, mode = ?, skills = ?, status = ?, description = ?, project_type = ?, project_subtype = ?, project_title = ?, task_title = ?, technology_stack = ?, difficulty_level = ?, assigned_mentor = ?, openings = ?, start_date = ?, end_date = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssssssssiissi", $title, $duration, $mode, $skills, $status, $description, $project_type, $project_subtype, $project_title, $task_title, $technology_stack, $difficulty_level, $mentor_val, $openings, $start_date, $end_date, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Check if timeline phases exist
            $check_phases_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM internship_phases WHERE internship_id = $id");
            $check_phases_row = mysqli_fetch_assoc($check_phases_res);
            $has_phases = intval($check_phases_row['cnt'] ?? 0) > 0;

            if (!$has_phases || $date_changed || $duration_changed) {
                generatePhases($conn, $id, $duration, $start_date);
            }

            header("Location: coordinator_internships.php?success=" . urlencode("Project posting updated successfully!"));
            exit();
        } else {
            $error_msg = "Error updating project posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// Fetch all internships
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$types = "";
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(i.title LIKE ? OR i.project_type LIKE ? OR i.project_subtype LIKE ? OR i.technology_stack LIKE ? OR m.full_name LIKE ?)";
    $search_param = "%" . $search . "%";
    $types = "sssss";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param];
}

$sql = "
    SELECT i.*, m.full_name as mentor_name 
    FROM internships i 
    LEFT JOIN users m ON i.assigned_mentor = m.id 
";

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY i.project_type ASC, i.project_subtype ASC, i.project_title ASC, i.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$internships_res = mysqli_stmt_get_result($stmt);
$internships = [];
while ($row = mysqli_fetch_assoc($internships_res)) {
    $internships[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Postings - Coordinator</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                 body { font-family: 'Inter', sans-serif; }
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
                        <a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 duration-200 ease-in-out"
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
                                <h2 class="text-lg font-bold text-gray-800">Postings</h2>
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
                            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Title and Add button -->
                        <div class="flex justify-between items-center">
                                <div>
                                        <h1 class="text-2xl font-bold text-gray-900">Project / Internship Postings</h1>
                                        <p class="text-gray-500 text-sm mt-1">Create and manage project specifications available for cohorts.</p>
                                </div>
                                <button onclick="openCreateModal()" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 shadow-sm transition-all text-sm cursor-pointer">
                                        <span class="material-symbols-outlined text-md">add</span>
                                        New Project Posting
                                </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                            <form method="GET" action="coordinator_internships.php" class="flex gap-2 max-w-md">
                                <div class="relative flex-grow">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search postings, type, tech stack..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="coordinator_internships.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Postings List Table -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                                <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100 whitespace-nowrap">
                                                        <tr>
                                                        <th class="px-6 py-4">Project Title</th>
                                                        <th class="px-6 py-4">Project Type</th>
                                                        <th class="px-6 py-4">Tech Stack</th>
                                                        <th class="px-6 py-4">Difficulty</th>
                                                        <th class="px-6 py-4">Openings</th>
                                                        <th class="px-6 py-4">Assigned Mentor</th>
                                                        <th class="px-6 py-4">Start Date</th>
                                                        <th class="px-6 py-4">End Date</th>
                                                        <th class="px-6 py-4">Status</th>
                                                        <th class="px-6 py-4 text-right">Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-gray-600">
                                                <?php if (empty($internships)): ?>
                                                    <tr>
                                                        <td colspan="10" class="px-6 py-10 text-center text-gray-400">No project postings found. Click "New Project Posting" to create one.</td>
                                                    </tr>
                                                <?php else: ?>
                                                <?php foreach ($internships as $item):
                                                        
                                                    $today = date('Y-m-d');
                                                    $computed_status = $item['status'] ?? 'Active';
                                                        if ($computed_status === 'Active' && !empty($item['start_date'])) {
                                                            if ($item['start_date'] > $today) {
                                                                $computed_status = 'Upcoming';
                                                            } elseif (!empty($item['end_date']) && $item['end_date'] < $today) {
                                                                $computed_status = 'Completed';
                                                            }
                                                        }
                                                        
                                                        $badge_cls = match($computed_status) {
                                                            'Active' => 'bg-green-50 text-green-700 border-green-200',
                                                            'Upcoming' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                            'Completed' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                            default => 'bg-slate-50 text-slate-700 border-slate-200' // Inactive
                                                        };

                                                        $difficulty_cls = match($item['difficulty_level'] ?? 'Medium') {
                                                            'Easy' => 'bg-emerald-50 text-emerald-600',
                                                            'Hard' => 'bg-red-50 text-red-600',
                                                            default => 'bg-amber-50 text-amber-600'
                                                        };
                                                    ?>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                                <td class="px-6 py-4 font-semibold text-gray-900">
                                                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($item['project_title'] ?: $item['title']); ?></p>
                                                                    <?php if (!empty($item['task_title']) && $item['task_title'] !== $item['project_title']): ?>
                                                                        <p class="text-xs font-semibold text-indigo-600 mt-0.5">↳ <?php echo htmlspecialchars($item['task_title']); ?></p>
                                                                    <?php endif; ?>
                                                                    <p class="text-[10px] text-gray-400 mt-1"><?php echo htmlspecialchars($item['mode'] ?? 'Remote'); ?> • <?php echo htmlspecialchars($item['duration'] ?? '3 Months'); ?></p>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($item['project_type'] ?: 'General'); ?></span>
                                                                    <?php if (!empty($item['project_subtype'])): ?>
                                                                        <p class="text-[10px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($item['project_subtype']); ?></p>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4 max-w-xs truncate" title="<?php echo htmlspecialchars($item['technology_stack'] ?: $item['skills']); ?>"><?php echo htmlspecialchars($item['technology_stack'] ?: $item['skills']); ?></td>
                                                                <td class="px-6 py-4">
                                                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold <?php echo $difficulty_cls; ?>"><?php echo htmlspecialchars($item['difficulty_level'] ?: 'Medium'); ?></span>
                                                                </td>
                                                                <td class="px-6 py-4 font-medium text-gray-700"><?php echo intval($item['openings'] ?? 1); ?></td>
                                                                <td class="px-6 py-4 font-medium text-gray-700"><?php echo htmlspecialchars($item['mentor_name'] ?: 'None'); ?></td>
                                                                <td class="px-6 py-4 text-xs font-semibold text-gray-700 whitespace-nowrap"><?php echo $item['start_date'] ? date('M d, Y', strtotime($item['start_date'])) : '—'; ?></td>
                                                                <td class="px-6 py-4 text-xs font-semibold text-gray-700 whitespace-nowrap"><?php echo $item['end_date'] ? date('M d, Y', strtotime($item['end_date'])) : '—'; ?></td>
                                                                <td class="px-6 py-4">
                                                                        <span class="px-2.5 py-0.5 border rounded-full text-xs font-bold <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($computed_status); ?></span>
                                                                </td>
                                                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                                                        <button onclick='openTimelineModal(<?php echo json_encode($item); ?>)' class="text-indigo-600 hover:text-indigo-800 font-bold text-xs cursor-pointer mr-1">Timeline</button>
                                                                        <button onclick='openEditModal(<?php echo json_encode($item); ?>)' class="text-blue-600 hover:text-blue-800 font-bold text-xs cursor-pointer mr-1">Edit</button>
                                                                        <a href="coordinator_internships.php?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure you want to delete this project posting?');" class="text-red-600 hover:text-red-800 font-bold text-xs">Delete</a>
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

        <!-- Create/Edit Modal -->
        <div id="internship-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 id="modal-title" class="text-lg font-bold text-gray-900 font-sans">New Project Posting</h3>
                                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined">close</span>
                                </button>
                        </div>
                        <form id="internship-form" method="POST" action="coordinator_internships.php">
                                <input type="hidden" name="action" id="form-action" value="create">
                                <input type="hidden" name="id" id="internship-id">
                                
                                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Title</label>
                                                <input type="text" name="title" id="form-title" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. Internship Management Portal">
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Type</label>
                                                        <select name="project_type" id="form-project-type" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Development" selected>Development</option>
                                                                <option value="Design">Design</option>
                                                                <option value="Marketing">Marketing</option>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Subtype</label>
                                                        <select name="project_subtype" id="form-project-subtype" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <!-- Options populated dynamically -->
                                                        </select>
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Duration</label>
                                                        <select name="duration" id="form-duration" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="1 Month">1 Month</option>
                                                                <option value="2 Months">2 Months</option>
                                                                <option value="3 Months" selected>3 Months</option>
                                                                <option value="4 Months">4 Months</option>
                                                                <option value="5 Months">5 Months</option>
                                                                <option value="6 Months">6 Months</option>
                                                                <option value="7 Months">7 Months</option>
                                                                <option value="8 Months">8 Months</option>
                                                                <option value="9 Months">9 Months</option>
                                                                <option value="10 Months">10 Months</option>
                                                                <option value="11 Months">11 Months</option>
                                                                <option value="12 Months">12 Months</option>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Mode</label>
                                                        <select name="mode" id="form-mode" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Remote" selected>Remote</option>
                                                                <option value="Hybrid">Hybrid</option>
                                                                <option value="On-Site">On-Site</option>
                                                                <option value="Online">Online</option>
                                                        </select>
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Difficulty Level</label>
                                                        <select name="difficulty_level" id="form-difficulty-level" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Easy">Easy</option>
                                                                <option value="Medium" selected>Medium</option>
                                                                <option value="Hard">Hard</option>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Openings</label>
                                                        <input type="number" name="openings" id="form-openings" required min="1" value="1" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Start Date</label>
                                                        <input type="date" name="start_date" id="form-start-date" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">End Date</label>
                                                        <input type="date" name="end_date" id="form-end-date" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Assigned Mentor</label>
                                                        <select name="assigned_mentor" id="form-assigned-mentor" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="">Select Mentor...</option>
                                                                <?php foreach ($mentors as $mentor): ?>
                                                                    <option value="<?php echo $mentor['id']; ?>"><?php echo htmlspecialchars($mentor['full_name']); ?></option>
                                                                <?php endforeach; ?>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Status</label>
                                                        <select name="status" id="form-status" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Active" selected>Active</option>
                                                                <option value="Inactive">Inactive</option>
                                                        </select>
                                                </div>
                                        </div>

                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Technology Stack (Comma separated)</label>
                                                <input type="text" name="technology_stack" id="form-tech-stack" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. React.js, Node.js, Express, MySQL">
                                        </div>

                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Description</label>
                                                <textarea name="description" id="form-description" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Describe the milestones, requirements, and deliverables..." rows="3"></textarea>
                                        </div>
                                </div>
                                <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3 font-sans">
                                                                        <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-white transition-colors cursor-pointer">Cancel</button>
                                                                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm cursor-pointer">Save Posting</button>
                                                                </div>
                                                        </form>
                                                </div>
                                        </div>

        <!-- Timeline Modal -->
        <div id="timeline-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <div>
                                        <h3 class="text-lg font-bold text-gray-900 font-sans">Internship Timeline & Phase Workflow</h3>
                                        <p id="timeline-modal-subtitle" class="text-xs text-gray-500 mt-1"></p>
                                </div>
                                <button onclick="closeTimelineModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined">close</span>
                                </button>
                        </div>
                        <form id="timeline-form" method="POST" action="coordinator_internships.php">
                                <input type="hidden" name="action" value="update_timeline">
                                <input type="hidden" name="internship_id" id="timeline-internship-id">
                                
                                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                                        <div id="timeline-phases-container" class="space-y-4">
                                                <div class="flex items-center justify-center py-8">
                                                        <p class="text-slate-400 text-sm">Loading timeline details...</p>
                                                </div>
                                        </div>
                                </div>
                                <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3 font-sans">
                                        <button type="button" onclick="closeTimelineModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-white transition-colors cursor-pointer">Cancel</button>
                                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm cursor-pointer">Save Timeline</button>
                                </div>
                        </form>
                </div>
        </div>

        <script>
                const modal = document.getElementById('internship-modal');
                const form = document.getElementById('internship-form');
                const formAction = document.getElementById('form-action');
                const modalTitle = document.getElementById('modal-title');
                const internshipIdInput = document.getElementById('internship-id');
                const titleInput = document.getElementById('form-title');
                const durationInput = document.getElementById('form-duration');
                const modeInput = document.getElementById('form-mode');
                const statusInput = document.getElementById('form-status');
                
                const typeInput = document.getElementById('form-project-type');
                const subtypeInput = document.getElementById('form-project-subtype');
                const difficultyInput = document.getElementById('form-difficulty-level');
                const openingsInput = document.getElementById('form-openings');
                const startDateInput = document.getElementById('form-start-date');
                const endDateInput = document.getElementById('form-end-date');
                const mentorInput = document.getElementById('form-assigned-mentor');
                const techStackInput = document.getElementById('form-tech-stack');
                const descriptionInput = document.getElementById('form-description');

                const subtypesMap = {
                        'Development': [
                                'Web Development',
                                'Mobile Apps',
                                'Backend Systems'
                        ],
                        'Design': [
                                'UI/UX Design',
                                'Graphic Design',
                                'Product Design'
                        ],
                        'Marketing': [
                                'SEO Campaigns',
                                'Social Media Strategy',
                                'Content Marketing'
                        ]
                };

                function mapOldType(oldType) {
                        if (!oldType) return 'Development';
                        const t = oldType.toLowerCase();
                        if (t.includes('development') || t.includes('general') || t.includes('data') || t.includes('analytics')) {
                                return 'Development';
                        }
                        if (t.includes('design') || t.includes('ux') || t.includes('ui')) {
                                return 'Design';
                        }
                        if (t.includes('marketing') || t.includes('seo')) {
                                return 'Marketing';
                        }
                        return 'Development';
                }

                function updateSubtypes(selectedType, selectedSubtype = '') {
                        subtypeInput.innerHTML = '';
                        const list = subtypesMap[selectedType] || [];
                        list.forEach(sub => {
                                const opt = document.createElement('option');
                                opt.value = sub;
                                opt.textContent = sub;
                                subtypeInput.appendChild(opt);
                        });

                        // Ensure we don't lose existing custom subtype value from DB when editing
                        if (selectedSubtype && !list.includes(selectedSubtype)) {
                                const opt = document.createElement('option');
                                opt.value = selectedSubtype;
                                opt.textContent = selectedSubtype;
                                subtypeInput.appendChild(opt);
                        }

                        if (selectedSubtype) {
                                subtypeInput.value = selectedSubtype;
                        } else if (subtypeInput.options.length > 0) {
                                subtypeInput.value = subtypeInput.options[0].value;
                        }
                }

                typeInput.addEventListener('change', (e) => {
                        updateSubtypes(e.target.value);
                });

                function calculateDuration() {
                        const startVal = startDateInput.value;
                        const endVal = endDateInput.value;
                        if (!startVal || !endVal) return;

                        const start = new Date(startVal);
                        const end = new Date(endVal);
                        if (isNaN(start.getTime()) || isNaN(end.getTime())) return;

                        const diffTime = end - start;
                        if (diffTime <= 0) return;

                        // Calculate month difference
                        let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
                        const dayDiff = end.getDate() - start.getDate();
                        if (dayDiff > 15) {
                                months += 1;
                        } else if (dayDiff < -15) {
                                months -= 1;
                        }

                        if (months < 1) months = 1;

                        const durationText = months === 1 ? "1 Month" : `${months} Months`;

                        // Ensure the option exists in select element
                        let optionExists = false;
                        for (let i = 0; i < durationInput.options.length; i++) {
                                if (durationInput.options[i].value === durationText) {
                                        optionExists = true;
                                        break;
                                }
                        }

                        if (!optionExists) {
                                const opt = document.createElement('option');
                                opt.value = durationText;
                                opt.textContent = durationText;
                                durationInput.appendChild(opt);
                        }

                        durationInput.value = durationText;
                }

                function calculateEndDate() {
                        const startVal = startDateInput.value;
                        const durVal = durationInput.value;
                        
                        if (!startVal || !durVal) return;
                        
                        const start = new Date(startVal);
                        if (isNaN(start.getTime())) return;
                        
                        const match = durVal.match(/^(\d+)/);
                        if (!match) return;
                        const months = parseInt(match[1], 10);
                        
                        const end = new Date(start);
                        end.setMonth(end.getMonth() + months);
                        
                        const yyyy = end.getFullYear();
                        const mm = String(end.getMonth() + 1).padStart(2, '0');
                        const dd = String(end.getDate()).padStart(2, '0');
                        
                        endDateInput.value = `${yyyy}-${mm}-${dd}`;
                }

                startDateInput.addEventListener('change', calculateEndDate);
                durationInput.addEventListener('change', calculateEndDate);
                endDateInput.addEventListener('change', calculateDuration);

                function openCreateModal() {
                        form.reset();
                        formAction.value = 'create';
                        modalTitle.textContent = 'New Project Posting';
                        internshipIdInput.value = '';
                        updateSubtypes('Development');
                        modal.classList.remove('hidden');
                }

                function openEditModal(item) {
                        formAction.value = 'edit';
                        modalTitle.textContent = 'Edit Project Posting';
                        internshipIdInput.value = item.id;
                        titleInput.value = item.title;
                        
                        const mappedType = mapOldType(item.project_type);
                        typeInput.value = mappedType;
                        updateSubtypes(mappedType, item.project_subtype || '');

                        // Ensure option exists in duration select to avoid blank selection
                        let durationExists = false;
                        for (let i = 0; i < durationInput.options.length; i++) {
                                if (durationInput.options[i].value === item.duration) {
                                        durationExists = true;
                                        break;
                                }
                        }
                        if (item.duration && !durationExists) {
                                const opt = document.createElement('option');
                                opt.value = item.duration;
                                opt.textContent = item.duration;
                                durationInput.appendChild(opt);
                        }
                        durationInput.value = item.duration || '3 Months';

                        modeInput.value = item.mode;
                        statusInput.value = item.status;

                        difficultyInput.value = item.difficulty_level || 'Medium';
                        openingsInput.value = item.openings || 1;
                        startDateInput.value = item.start_date || '';
                        endDateInput.value = item.end_date || '';
                        mentorInput.value = item.assigned_mentor || '';
                        techStackInput.value = item.technology_stack || '';
                        descriptionInput.value = item.description || '';

                        modal.classList.remove('hidden');
                }

                function closeModal() {
                        modal.classList.add('hidden');
                }

                const timelineModal = document.getElementById('timeline-modal');
                const timelineInternshipId = document.getElementById('timeline-internship-id');
                const timelineModalSubtitle = document.getElementById('timeline-modal-subtitle');
                const timelinePhasesContainer = document.getElementById('timeline-phases-container');

                function openTimelineModal(item) {
                        timelineInternshipId.value = item.id;
                        timelineModalSubtitle.textContent = `${item.title} (${item.duration})`;
                        timelinePhasesContainer.innerHTML = `
                                <div class="flex flex-col items-center justify-center py-8">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                        <p class="text-slate-400 text-xs mt-2">Loading timeline...</p>
                                </div>
                        `;
                        timelineModal.classList.remove('hidden');

                        fetch(`coordinator_internships.php?action=get_timeline&id=${item.id}`)
                                .then(response => response.json())
                                .then(phases => {
                                        if (phases.length === 0) {
                                                timelinePhasesContainer.innerHTML = `
                                                        <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                                                <span class="material-symbols-outlined text-[36px] text-slate-300 block mb-2">calendar_today</span>
                                                                <p class="text-slate-400 text-sm">No timeline has been generated for this posting yet.</p>
                                                                <p class="text-xs text-slate-400 mt-1">Please ensure the project posting has a start date and save it again to generate.</p>
                                                        </div>
                                                `;
                                                return;
                                        }

                                        timelinePhasesContainer.innerHTML = '';
                                        phases.forEach(phase => {
                                                const card = document.createElement('div');
                                                card.className = 'p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3';
                                                card.innerHTML = `
                                                        <div class="flex items-center justify-between">
                                                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Phase ${phase.phase_number}: ${phase.phase_name}</span>
                                                                <input type="hidden" name="phase_id[]" value="${phase.id}">
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-3">
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Start Date</label>
                                                                        <input type="date" name="start_date[]" value="${phase.start_date}" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                                                </div>
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">End Date (Deadline)</label>
                                                                        <input type="date" name="end_date[]" value="${phase.end_date}" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                                                </div>
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                                                                        <select name="status[]" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                                <option value="Pending" ${phase.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                                                <option value="Active" ${phase.status === 'Active' ? 'selected' : ''}>Active</option>
                                                                                <option value="Completed" ${phase.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                                                        </select>
                                                                </div>
                                                        </div>
                                                `;
                                                timelinePhasesContainer.appendChild(card);
                                        });
                                })
                                .catch(err => {
                                        console.error(err);
                                        timelinePhasesContainer.innerHTML = `
                                                <div class="text-center py-8 text-red-500">
                                                        <p class="text-sm font-semibold">Error loading timeline.</p>
                                                </div>
                                        `;
                                });
                }

                function closeTimelineModal() {
                         timelineModal.classList.add('hidden');
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
