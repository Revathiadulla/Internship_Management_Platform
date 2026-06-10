<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = strtolower($_SESSION['role'] ?? '');
if ($role !== 'coordinator') {
    header("Location: login.php");
    exit();
}
$coordinator_id = (int)$_SESSION['user_id'];
$coordinator_name = $_SESSION['name'] ?? 'Coordinator';
$notif_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'coordinator' AND is_read = 0");
$notif_unread_row = mysqli_fetch_assoc($notif_unread_res);
$unread_count = $notif_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg = "";

// Status updates are disabled in the coordinator module (read-only)

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// Filtering
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [
    "i.coordinator_id = " . intval($_SESSION['user_id']),
    "a.status NOT IN ('Rejected', 'Deleted')"
];
$types = "";
$params = [];

if (!empty($status_filter)) {
    $where_clauses[] = "a.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR sp.college_name LIKE ? OR COALESCE(i.title, a.internship_name) LIKE ?)";
    $search_param = "%" . $search . "%";
    $types .= "ssss";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql = "SELECT a.id as app_id, a.id as id, a.status, a.applied_date, a.education_status,
               COALESCE(i.title, a.internship_name) as title, u.full_name as student_name, u.email as student_email, sp.phone, sp.college_name, sp.skills as student_skills, ss.percentage as test_percentage
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
                JOIN internships i ON a.internship_id = i.id
        LEFT JOIN student_scores ss ON ss.application_id = a.id
        LEFT JOIN project_teams pt ON i.id = pt.internship_id";

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
        $sql .= " ORDER BY a.applied_date DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$applications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $applications[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Candidates - Coordinator</title>
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
                        <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
                        </a>
                        <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">work</span> Postings
                        </a>
                        <a href="coordinator_candidates.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
                                <span class="material-symbols-outlined text-[20px]">group</span> Candidates
                        </a>
                        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
                        </a>
                        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
                        </a>
						<a href="coordinator_student_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
							<span class="material-symbols-outlined text-[20px]">warning</span> Student Reports
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
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Candidates</h2>
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
                        <?php if ($success_msg): ?>
                            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 alert-success">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 alert-danger">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Title and filters -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                        <h1 class="text-2xl font-bold text-gray-900">Student Applications</h1>
                                        <p class="text-gray-500 text-sm mt-1">Review applicant profiles and coordinate hiring rounds.</p>
                                </div>
                        </div>

                        <!-- Search and Filters -->
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                            <form method="GET" action="coordinator_candidates.php" class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
                                <div class="relative flex-grow max-w-md">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name, email, college..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                                <div class="flex items-center gap-2">
                                    <select name="status" class="rounded-lg border-gray-200 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer bg-gray-50">
                                        <option value="">All Statuses</option>
                                        <option value="Applied" <?php echo $status_filter === 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                        <option value="Test Completed" <?php echo $status_filter === 'Test Completed' ? 'selected' : ''; ?>>Test Completed</option>
                                        <option value="HR Review" <?php echo $status_filter === 'HR Review' ? 'selected' : ''; ?>>HR Review</option>
                                        <option value="HOD Approved" <?php echo $status_filter === 'HOD Approved' ? 'selected' : ''; ?>>HOD Approved</option>
                                        <option value="Selected" <?php echo $status_filter === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                        <option value="Active Intern" <?php echo $status_filter === 'Active Intern' ? 'selected' : ''; ?>>Active Intern</option>
                                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all">Search & Filter</button>
                                    <?php if (!empty($search) || !empty($status_filter)): ?>
                                        <a href="coordinator_candidates.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <!-- Candidates List -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                                                <tr>
                                                        <th class="px-6 py-4">Student</th>
                                                        <th class="px-6 py-4">Applied Position</th>
                                                        <th class="px-6 py-4">Applied Date</th>
                                                        <th class="px-6 py-4">Education / College</th>
                                                        <th class="px-6 py-4">Status</th>
                                                        <th class="px-6 py-4 text-right">Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-gray-600">
                                                <?php if (empty($applications)): ?>
                                                    <tr>
                                                        <td colspan="6" class="px-6 py-10 text-center text-gray-400">No student applications found.</td>
                                                    </tr>
                                                <?php else: ?>
                                                     <?php foreach ($applications as $app): 
                                                         $application = $app;
                                                         $application_id = $application['id'] ?? 0;
                                                         $badge_cls = match($app['status']) {
                                                             'Applied' => 'bg-blue-50 text-blue-700 border-blue-100',
                                                             'Test Completed' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                                                             'HR Round' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                             'HOD Approved' => 'bg-purple-50 text-purple-700 border-purple-100',
                                                             'Selected' => 'bg-green-50 text-green-700 border-green-100',
                                                             'Rejected' => 'bg-red-50 text-red-700 border-red-100',
                                                             default => 'bg-slate-50 text-slate-700 border-slate-100'
                                                         };
                                                     ?>
                                                         <tr class="hover:bg-gray-50 transition-colors">
                                                                 <td class="px-6 py-4">
                                                                         <div>
                                                                                 <p class="font-bold text-gray-900"><?php echo htmlspecialchars($app['student_name'] ?? ''); ?></p>
                                                                                 <p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($app['student_email'] ?? ''); ?> • <?php echo htmlspecialchars($app['phone'] ?? 'N/A'); ?></p>
                                                                         </div>
                                                                 </td>
                                                                 <td class="px-6 py-4 font-semibold text-gray-700"><?php echo htmlspecialchars($app['title'] ?: 'General Placement'); ?></td>
                                                                 <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                                                                 <td class="px-6 py-4">
                                                                         <p class="text-xs"><?php echo htmlspecialchars($app['education_status'] ?? 'Undergrad'); ?></p>
                                                                         <p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($app['college_name'] ?? 'N/A'); ?></p>
                                                                 </td>
                                                                 <td class="px-6 py-4">
                                                                         <span class="px-2.5 py-0.5 border rounded-full text-xs font-bold <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                                                                 </td>
                                                                 <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                                                         <button onclick='openDetailsModal(<?php echo json_encode($app); ?>)' class="text-blue-600 hover:text-blue-800 font-bold text-xs bg-blue-50 px-2.5 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors cursor-pointer">View Details</button>
                                                                         <?php if (in_array($app['status'], ['Selected', 'Started', 'Internship Started', 'Active Intern', 'Completed'])): ?>
                                                                             <a href="coordinator_daily_logs.php?search=<?php echo urlencode($app['student_name'] ?? ''); ?>" class="inline-block text-emerald-600 hover:text-emerald-800 font-bold text-xs bg-emerald-50 px-2.5 py-1.5 rounded-lg border border-emerald-200 hover:bg-emerald-100 transition-colors">Monitor Progress</a>
                                                                         <?php endif; ?>
                                                                         <?php if (in_array($app['status'], ['Selected', 'HOD Approved'])): ?>
    <?php if (!empty($app['id'])): ?>
        <a href="assign_project.php?application_id=<?php echo urlencode($app['id']); ?>" class="inline-block text-indigo-800 font-bold text-xs bg-indigo-50 px-2.5 py-1.5 rounded-lg border border-indigo-200 hover:bg-indigo-100 transition-colors">Assign Project</a>
    <?php endif; ?>
<?php endif; ?>
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
        <!-- View Details Modal -->
        <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
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
                        
                        detailSkills.textContent = app.student_skills || 'No skills listed';

                        
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
<script src="js/alerts.js"></script>
</body>
</html>
