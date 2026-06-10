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

$success_msg = "";
$error_msg = "";

// Handle Review Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $log_id = intval($_POST['log_id']);
    
    // Fetch log details for notification
    $fetch_sql = "SELECT d.user_id, d.log_date, u.full_name FROM daily_logs d JOIN users u ON d.user_id = u.id JOIN internships i ON d.internship_id = i.id WHERE d.id = ? AND i.coordinator_id = ? LIMIT 1";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    $coord_id = intval($_SESSION['user_id']);
    mysqli_stmt_bind_param($fetch_stmt, "ii", $log_id, $coord_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_res = mysqli_stmt_get_result($fetch_stmt);
    
    if ($log_data = mysqli_fetch_assoc($fetch_res)) {
        $student_id = $log_data['user_id'];
        $log_date_formatted = date('M d, Y', strtotime($log_data['log_date']));

        // Update daily_logs status
        $update_sql = "UPDATE daily_logs SET status = 'Reviewed' WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $log_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Notify Student
            $notif_msg = "Your daily log for " . $log_date_formatted . " has been reviewed by the Coordinator.";
            $notif_sql = "INSERT INTO student_notifications (user_id, type, message, is_read) VALUES (?, 'Log Reviewed', ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($notif_stmt, "is", $student_id, $notif_msg);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            header("Location: coordinator_daily_logs.php?success=" . urlencode("Daily log reviewed successfully!"));
            exit();
        } else {
            $error_msg = "Error reviewing daily log: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    } else {
        $error_msg = "Daily log not found.";
    }
    mysqli_stmt_close($fetch_stmt);
}

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// Calculate Summary stats
$coord_id = intval($_SESSION['user_id']);

// 1. Assigned interns
$assigned_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.user_id) as cnt 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status IN ('Started', 'Internship Started', 'Active Intern', 'Selected')
      AND i.coordinator_id = $coord_id
");
$assigned_row = mysqli_fetch_assoc($assigned_res);
$assigned_count = intval($assigned_row['cnt'] ?? 0);

// 2. Missing logs today
$missing_res = mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.user_id) as cnt 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status IN ('Started', 'Internship Started', 'Active Intern')
    AND i.coordinator_id = $coord_id
    AND a.user_id NOT IN (
        SELECT DISTINCT user_id FROM daily_logs WHERE log_date = CURDATE()
    )
");
$missing_row = mysqli_fetch_assoc($missing_res);
$missing_count = intval($missing_row['cnt'] ?? 0);

// 3. Awaiting review
$awaiting_res = mysqli_query($conn, "
    SELECT COUNT(*) as cnt 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE (d.status = 'Submitted' OR d.status IS NULL OR d.status = '')
      AND i.coordinator_id = $coord_id
");
$awaiting_row = mysqli_fetch_assoc($awaiting_res);
$awaiting_count = intval($awaiting_row['cnt'] ?? 0);

// 4. Reviewed logs count
$steady_res = mysqli_query($conn, "
    SELECT COUNT(*) as cnt 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.status = 'Reviewed'
      AND i.coordinator_id = $coord_id
");
$steady_row = mysqli_fetch_assoc($steady_res);
$steady_count = intval($steady_row['cnt'] ?? 0);

// Filters logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "SELECT d.*, u.full_name as student_name, u.email as student_email, sp.college_name, sp.course
        FROM daily_logs d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        JOIN internships i ON d.internship_id = i.id";

$where_parts = ["i.coordinator_id = ?"];
$types = "i";
$params = [$coord_id];

if (!empty($search)) {
    $where_parts[] = "u.full_name LIKE ?";
    $types .= "s";
    $params[] = "%" . $search . "%";
}

if (!empty($date_filter)) {
    $where_parts[] = "d.log_date = ?";
    $types .= "s";
    $params[] = $date_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'Submitted') {
        $where_parts[] = "(d.status = 'Submitted' OR d.status IS NULL OR d.status = '')";
    } else {
        $where_parts[] = "d.status = ?";
        $types .= "s";
        $params[] = $status_filter;
    }
}

if (count($where_parts) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_parts);
}

$sql .= " ORDER BY d.log_date DESC, d.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (count($where_parts) > 0) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$logs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Intern Activity Tracker | Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; color: #191c1d; font-family: 'Inter', sans-serif; }
        .status-pill { padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-submitted { background-color: #ecfdf5; color: #059669; }
        .status-reviewed { background-color: #eff6ff; color: #2563eb; }
        .status-missing { background-color: #fef2f2; color: #dc2626; }
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
                        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">group</span> Candidates
                        </a>
                        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
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
                                <h2 class="text-lg font-bold text-gray-800">Daily Logs</h2>
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
                
                <?php if ($success_msg): ?>
                    <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 alert-success">
                        <span class="material-symbols-outlined text-green-500">check_circle</span>
                        <span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 alert-danger">
                        <span class="material-symbols-outlined text-red-500">error</span>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Intern Activity Tracker</h1>
                        <p class="text-gray-500 text-sm mt-1">Monitor progress and submission status across your assigned batches.</p>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Assigned Interns</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $assigned_count; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Missing Logs</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $missing_count; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Awaiting Review</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $awaiting_count; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Reviewed Logs</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $steady_count; ?></p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <form method="GET" action="coordinator_daily_logs.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase">Search Student</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase">Date</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div class="space-y-1 flex flex-col justify-between">
                            <div class="flex-1 space-y-1">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Status</label>
                                <div class="flex gap-2">
                                    <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                                        <option value="">All Statuses</option>
                                        <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted (Awaiting Review)</option>
                                        <option value="Reviewed" <?php echo $status_filter === 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                    </select>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all">Filter</button>
                                    <a href="coordinator_daily_logs.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Intern Name</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Hours</th>
                                <th class="px-6 py-4">Focus Level</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-600">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">No logs found. Try adjusting your filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): 
                                    $status = htmlspecialchars($log['status'] ?: 'Submitted');
                                    $pill_cls = $status === 'Reviewed' ? 'status-reviewed' : 'status-submitted';
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($log['student_name']); ?></p>
                                            <p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($log['course'] ?? 'Student'); ?> • <?php echo htmlspecialchars($log['college_name'] ?? 'N/A'); ?></p>
                                        </td>
                                        <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                                        <td class="px-6 py-4"><?php echo floatval($log['time_spent']); ?> hrs</td>
                                        <td class="px-6 py-4 text-xs font-semibold"><?php echo htmlspecialchars($log['focus_level']); ?></td>
                                        <td class="px-6 py-4"><span class="status-pill <?php echo $pill_cls; ?>"><?php echo $status; ?></span></td>
                                        <td class="px-6 py-4 text-right">
                                            <button onclick='openDetailsModal(<?php echo json_encode($log); ?>)' class="text-blue-600 font-bold text-xs hover:underline bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100 transition-all">View Details</button>
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

    <!-- Log Detail Modal -->
    <div id="log-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold" id="modal-avatar">
                        JD
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Activity Details</h3>
                        <p class="text-xs text-gray-500 font-medium" id="modal-subheader">Student details</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-8 space-y-6 overflow-y-auto max-h-[60vh]">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-500 uppercase mb-1">Time Spent</p>
                        <p class="text-sm font-semibold text-gray-900" id="modal-hours">8.0 hours</p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-500 uppercase mb-1">Focus Level</p>
                        <p class="text-sm font-semibold text-gray-900" id="modal-focus">Steady Progress</p>
                    </div>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-2">Tasks Completed</h4>
                    <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" id="modal-tasks">
                        Tasks completed content
                    </div>
                </div>
                <div id="modal-blockers-container">
                    <h4 class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Blockers / Issues Faced</h4>
                    <div class="bg-red-50/50 p-4 rounded-xl border border-red-100 text-sm text-red-800 leading-relaxed whitespace-pre-wrap" id="modal-blockers">
                        None
                    </div>
                </div>
                <div id="modal-next-container">
                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Next Day Plan</h4>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" id="modal-next">
                        None
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3">
                <button onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-white transition-colors">Close</button>
                <form id="modal-review-form" method="POST" action="coordinator_daily_logs.php">
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="log_id" id="modal-log-id">
                    <button type="submit" id="modal-review-btn" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors">Mark as Reviewed</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('log-modal');
        const modalAvatar = document.getElementById('modal-avatar');
        const modalSubheader = document.getElementById('modal-subheader');
        const modalHours = document.getElementById('modal-hours');
        const modalFocus = document.getElementById('modal-focus');
        const modalTasks = document.getElementById('modal-tasks');
        const modalBlockersContainer = document.getElementById('modal-blockers-container');
        const modalBlockers = document.getElementById('modal-blockers');
        const modalNextContainer = document.getElementById('modal-next-container');
        const modalNext = document.getElementById('modal-next');
        const modalLogIdInput = document.getElementById('modal-log-id');
        const modalReviewBtn = document.getElementById('modal-review-btn');

        function openDetailsModal(log) {
            modalLogIdInput.value = log.id;
            modalAvatar.textContent = log.student_name.substring(0, 2).toUpperCase();
            modalSubheader.textContent = log.student_name + " | Log Date: " + log.log_date;
            modalHours.textContent = log.time_spent + " hours";
            modalFocus.textContent = log.focus_level;
            modalTasks.textContent = log.tasks_completed;
            
            if (log.issues_faced && log.issues_faced.trim() !== '') {
                modalBlockers.textContent = log.issues_faced;
                modalBlockersContainer.classList.remove('hidden');
            } else {
                modalBlockersContainer.classList.add('hidden');
            }

            if (log.next_plan && log.next_plan.trim() !== '') {
                modalNext.textContent = log.next_plan;
                modalNextContainer.classList.remove('hidden');
            } else {
                modalNextContainer.classList.add('hidden');
            }

            if (log.status === 'Reviewed') {
                modalReviewBtn.classList.add('hidden');
            } else {
                modalReviewBtn.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
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
<script src="js/alerts.js"></script>
</body>
</html>
