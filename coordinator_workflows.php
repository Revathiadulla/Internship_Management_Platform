<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$success_msg = "";
$error_msg = "";

$stages = ['Applied', 'Screening', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];

// Candidate stage transitions are disabled in the coordinator module (read-only)

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// Fetch unique internships for the filter dropdown
$internships_list = [];
$int_res = mysqli_query($conn, "SELECT DISTINCT COALESCE(i.title, a.internship_name) as title FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0 ORDER BY title ASC");
while ($ir = mysqli_fetch_assoc($int_res)) {
    if (!empty($ir['title'])) {
        $internships_list[] = $ir['title'];
    }
}

// Get filter inputs
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_internship = isset($_GET['internship']) ? trim($_GET['internship']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build dynamic query
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search_query)) {
    $where_clauses[] = "(u.full_name LIKE ? OR sp.college_name LIKE ? OR COALESCE(i.title, a.internship_name) LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($filter_internship)) {
    $where_clauses[] = "COALESCE(i.title, a.internship_name) = ?";
    $params[] = $filter_internship;
    $types .= "s";
}

if (!empty($filter_status)) {
    if ($filter_status === 'Selected') {
        $where_clauses[] = "a.status IN ('Selected', 'Started', 'Internship Started', 'Active Intern', 'Completed')";
    } else if ($filter_status === 'Applied') {
        // Find Applied or anything not in standard stages
        $stage_placeholders = implode("', '", array_diff($stages, ['Applied']));
        $where_clauses[] = "(a.status = 'Applied' OR a.status NOT IN ('$stage_placeholders', 'Started', 'Internship Started', 'Active Intern', 'Completed'))";
    } else {
        $where_clauses[] = "a.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$sql = "SELECT a.id as app_id, a.status, a.applied_date, a.relevant_skills, a.education_status,
               COALESCE(i.title, a.internship_name) as title, u.full_name as student_name, u.email as student_email, sp.phone, sp.college_name
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
        $where_sql
        ORDER BY a.applied_date DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql);
}

$applications_by_stage = [];
foreach ($stages as $stage) {
    $applications_by_stage[$stage] = [];
}
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $status = $row['status'];
        // Handle student active states mapping to Selected
        if ($status === 'Started' || $status === 'Internship Started' || $status === 'Active Intern' || $status === 'Completed') {
            $applications_by_stage['Selected'][] = $row;
        } else if (!in_array($status, $stages)) {
            $applications_by_stage['Applied'][] = $row;
        } else {
            $applications_by_stage[$status][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Workflows - Coordinator</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                body { font-family: 'Inter', sans-serif; }
                .material-symbols-outlined {
                        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                        vertical-align: middle;
                }
                .kanban-board {
                        height: calc(100vh - 140px);
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
                        <a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 duration-200 ease-in-out"
                                href="coordinator_workflows.php">
                                <span class="material-symbols-outlined">account_tree</span>
                                <span>Workflows</span>
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
                                <h2 class="text-lg font-bold text-gray-800">Workflows</h2>
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


                <div class="flex-1 p-6 flex flex-col space-y-4">
                        <?php if ($success_msg): ?>
                            <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 max-w-4xl">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 max-w-4xl">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Title Info -->
                        <div>
                                <h1 class="text-2xl font-bold text-gray-900 font-h1">Pipeline Board</h1>
                                <p class="text-gray-500 text-sm mt-1">Manage candidate hiring stages using quick status progression controls.</p>
                        </div>

                        <!-- Search & Filter Bar -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <form method="GET" action="coordinator_workflows.php" class="flex flex-col md:flex-row gap-4 items-center justify-between">
                                <div class="relative w-full md:max-w-xs">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search candidate, college..." 
                                           class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-blue-600 focus:ring-blue-600/10 transition-colors">
                                </div>
                                <div class="flex flex-wrap gap-3 w-full md:w-auto">
                                    <select name="internship" class="rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                        <option value="">All Internships</option>
                                        <?php foreach ($internships_list as $title_option): ?>
                                            <option value="<?php echo htmlspecialchars($title_option); ?>" <?php echo ($filter_internship === $title_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($title_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="status" class="rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                        <option value="">All Statuses</option>
                                        <?php foreach ($stages as $stage_option): ?>
                                            <option value="<?php echo htmlspecialchars($stage_option); ?>" <?php echo ($filter_status === $stage_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($stage_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm cursor-pointer">
                                        Apply Filters
                                    </button>
                                    <?php if (!empty($search_query) || !empty($filter_internship) || !empty($filter_status)): ?>
                                        <a href="coordinator_workflows.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors text-center">
                                            Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <!-- Kanban Board columns container -->
                        <div class="kanban-board flex gap-4 overflow-x-auto pb-4 select-none items-start">
                                <?php foreach ($stages as $stage_index => $stage): 
                                        $stage_count = count($applications_by_stage[$stage]);
                                        $header_cls = match($stage) {
                                            'Applied' => 'border-t-4 border-blue-500',
                                            'Screening' => 'border-t-4 border-cyan-500',
                                            'Test Completed' => 'border-t-4 border-indigo-500',
                                            'HR Round' => 'border-t-4 border-amber-500',
                                            'HOD Approved' => 'border-t-4 border-purple-500',
                                            'Selected' => 'border-t-4 border-green-500',
                                            'Rejected' => 'border-t-4 border-red-500',
                                            default => 'border-t-4 border-slate-500'
                                        };
                                ?>
                                        <!-- Column -->
                                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 w-80 shrink-0 flex flex-col max-h-full <?php echo $header_cls; ?>">
                                                <!-- Column Header -->
                                                <div class="p-4 flex justify-between items-center bg-gray-55 border-b border-gray-100 rounded-t-xl">
                                                        <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                                            <span><?php echo htmlspecialchars($stage); ?></span>
                                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[11px] rounded-full font-bold"><?php echo $stage_count; ?></span>
                                                        </h3>
                                                </div>

                                                <!-- Cards Area -->
                                                <div class="p-3 overflow-y-auto space-y-3 flex-1 min-h-[200px]">
                                                        <?php if ($stage_count === 0): ?>
                                                            <div class="text-center py-8 text-gray-300 text-xs italic">No candidates</div>
                                                        <?php else: ?>
                                                            <?php foreach ($applications_by_stage[$stage] as $app): ?>
                                                                <!-- Card -->
                                                                <div class="bg-slate-50/50 hover:bg-slate-50 border border-gray-200 rounded-xl p-4 transition-all shadow-inner space-y-2.5">
                                                                        <div>
                                                                                <h4 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($app['student_name']); ?></h4>
                                                                                <p class="text-[11px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($app['college_name'] ?? 'N/A'); ?></p>
                                                                        </div>
                                                                        <div class="bg-white border border-gray-200/50 rounded-lg p-2 text-xs space-y-1">
                                                                                <p class="font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($app['title'] ?: 'General Placement'); ?></p>
                                                                                <p class="text-[9px] text-gray-400 uppercase font-bold">Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></p>
                                                                                <p class="text-[10px] text-slate-500 font-semibold">Status: <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($app['status']); ?></span></p>
                                                                        </div>
                                                                        
                                                                        <!-- Card Action Buttons -->
                                                                        <div class="flex justify-end items-center pt-2 border-t border-gray-100/50 gap-1.5">
                                                                                <button onclick='openDetailsModal(<?php echo json_encode($app); ?>)' class="text-blue-600 hover:text-blue-800 font-bold text-[10px] bg-blue-50/50 border border-blue-100 px-2 py-1 rounded transition-colors cursor-pointer" title="View Candidate Profile">Details</button>
                                                                                <?php if (in_array($app['status'], ['Selected', 'Started', 'Internship Started', 'Active Intern', 'Completed'])): ?>
                                                                                    <a href="coordinator_daily_logs.php?search=<?php echo urlencode($app['student_name']); ?>" class="inline-block text-emerald-600 hover:text-emerald-800 font-bold text-[10px] bg-emerald-50/50 border border-emerald-100 px-2 py-1 rounded transition-colors" title="Monitor Internship Logs">Monitor</a>
                                                                                <?php endif; ?>
                                                                                <?php if (in_array($app['status'], ['Selected', 'HOD Approved'])): ?>
                                                                                    <a href="coordinator_teams.php?student_id=<?php echo $app['user_id']; ?>" class="inline-block text-indigo-600 hover:text-indigo-800 font-bold text-[10px] bg-indigo-50/50 border border-indigo-100 px-2 py-1 rounded transition-colors" title="Assign to Project/Team">Assign</a>
                                                                                <?php endif; ?>
                                                                        </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                </div>
                                        </div>
                                <?php endforeach; ?>
                        </div>
                </div>
        </main>

        <!-- View Details Modal -->
        <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-55/50">
                                <h3 class="text-lg font-bold text-gray-900 font-sans">Candidate Details</h3>
                                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined">close</span>
                                </button>
                        </div>
                        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto text-sm text-left">
                                <div class="grid grid-cols-2 gap-4 border-b border-gray-50 pb-4">
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Candidate Name</p>
                                                <p id="detail-name" class="font-bold text-slate-800 mt-1 text-base"></p>
                                        </div>
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Current Status</p>
                                                <p id="detail-status" class="mt-1 font-bold"></p>
                                        </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 border-b border-gray-50 pb-4">
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Email Address</p>
                                                <p id="detail-email" class="font-semibold text-slate-700 mt-1 truncate"></p>
                                        </div>
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Phone Number</p>
                                                <p id="detail-phone" class="font-semibold text-slate-700 mt-1"></p>
                                        </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 border-b border-gray-50 pb-4">
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">College / Institution</p>
                                                <p id="detail-college" class="font-semibold text-slate-700 mt-1"></p>
                                        </div>
                                        <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Education Status</p>
                                                <p id="detail-education" class="font-semibold text-slate-700 mt-1"></p>
                                        </div>
                                </div>
                                <div class="border-b border-gray-50 pb-4">
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Position Applied For</p>
                                        <p id="detail-position" class="font-bold text-indigo-700 mt-1"></p>
                                        <p id="detail-applied-date" class="text-[10px] text-gray-400 mt-0.5"></p>
                                </div>
                                <div class="border-b border-gray-50 pb-4">
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Skills</p>
                                        <p id="detail-skills" class="text-slate-700 mt-1 leading-relaxed bg-slate-50 p-2 rounded-lg border border-slate-100 text-xs"></p>
                                </div>

                        </div>
                        <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end">
                                <button type="button" onclick="closeDetailsModal()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer">Close</button>
                        </div>
                </div>
        </div>

        <script>
                const detailsModal = document.getElementById('details-modal');
                const detailName = document.getElementById('detail-name');
                const detailStatus = document.getElementById('detail-status');
                const detailEmail = document.getElementById('detail-email');
                const detailPhone = document.getElementById('detail-phone');
                const detailCollege = document.getElementById('detail-college');
                const detailEducation = document.getElementById('detail-education');
                const detailPosition = document.getElementById('detail-position');
                const detailAppliedDate = document.getElementById('detail-applied-date');
                const detailSkills = document.getElementById('detail-skills');


                function openDetailsModal(app) {
                        detailName.textContent = app.student_name;
                        
                        const st = app.status;
                        let badgeClass = 'bg-slate-100 text-slate-700';
                        if (st === 'Applied') badgeClass = 'bg-blue-100 text-blue-800';
                        else if (st === 'Screening') badgeClass = 'bg-cyan-100 text-cyan-800';
                        else if (st === 'Test Completed') badgeClass = 'bg-indigo-100 text-indigo-800';
                        else if (st === 'HR Round') badgeClass = 'bg-amber-100 text-amber-800';
                        else if (st === 'HOD Approved') badgeClass = 'bg-purple-100 text-purple-800';
                        else if (st === 'Selected' || st === 'Started' || st === 'Active Intern') badgeClass = 'bg-green-100 text-green-800';
                        else if (st === 'Rejected') badgeClass = 'bg-red-100 text-red-800';
                        
                        detailStatus.className = `px-2 py-0.5 rounded text-xs inline-block font-bold ${badgeClass}`;
                        detailStatus.textContent = st;

                        detailEmail.textContent = app.student_email || 'N/A';
                        detailPhone.textContent = app.phone || 'N/A';
                        detailCollege.textContent = app.college_name || 'N/A';
                        detailEducation.textContent = app.education_status || 'Undergrad';
                        detailPosition.textContent = app.title || 'General Placement';
                        
                        const d = new Date(app.applied_date);
                        detailAppliedDate.textContent = `Applied on: ${d.toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}`;
                        
                        detailSkills.textContent = app.relevant_skills || 'No skills listed';

                        
                        detailsModal.classList.remove('hidden');
                }

                function closeDetailsModal() {
                        detailsModal.classList.add('hidden');
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
