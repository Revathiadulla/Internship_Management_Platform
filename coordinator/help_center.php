<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../includes/db.php';

// Dynamically create reported_issues table if it doesn't exist
$table_sql = "CREATE TABLE IF NOT EXISTS reported_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    screenshot VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $table_sql);

// Handle issue reporting submission
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_issue'])) {
    $title = trim($_POST['issue_title'] ?? '');
    $description = trim($_POST['issue_description'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if (empty($title) || empty($description)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        $screenshot_path = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['screenshot']['tmp_name'];
            $file_name = $_FILES['screenshot']['name'];
            $file_size = $_FILES['screenshot']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                $error_msg = "Invalid file type. Allowed formats: " . implode(', ', $allowed_exts);
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $error_msg = "File size exceeds the 5MB limit.";
            } else {
                $upload_dir = 'uploads/issues/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_file_name = 'issue_' . $user_id . '_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $screenshot_path = $dest_path;
                } else {
                    $error_msg = "Failed to save uploaded screenshot.";
                }
            }
        }
        
        if (empty($error_msg)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO reported_issues (user_id, title, description, screenshot) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $description, $screenshot_path);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Issue reported successfully! Support team notified.";
            } else {
                $error_msg = "Error submitting issue to the database.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch header user details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Coordinator';
$header_photo = $header_user['profile_photo'] ?? '';

// Check dynamic status components
$db_status = mysqli_ping($conn);
$notif_service_status = false;
$check_notif_table = mysqli_query($conn, "SHOW TABLES LIKE 'student_notifications'");
if ($check_notif_table && mysqli_num_rows($check_notif_table) > 0) {
    $notif_service_status = true;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Help Center - Coordinator</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                 body { font-family: 'Inter', sans-serif; scroll-behavior: smooth; }
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
                 
                 /* Highlight animation */
                 .search-highlight {
                         background-color: rgba(253, 224, 71, 0.6);
                         border-radius: 2px;
                         padding: 0 1px;
                 }
        </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- SideNavBar -->
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
                        <a href="/IMP/coordinator/dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
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
                        <a href="/IMP/coordinator/help_center.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
                                <span class="material-symbols-outlined text-[20px]">help</span> Help Center
                        </a>
                        <a href="/IMP/logout.php" class="flex items-center gap-3 text-red-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">logout</span> Logout
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                         <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Help Center</h2>
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
                                         <a href="/IMP/coordinator/profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                 <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                 <span>My Profile</span>
                                         </a>
                                         <a href="/IMP/coordinator/profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                 <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                 <span>Settings</span>
                                         </a>
                                         <hr class="my-1 border-gray-100">
                                         <a href="/IMP/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                 <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                 <span>Logout</span>
                                         </a>
                                </div>
                        </div>
                </header>

                <div class="flex-1 p-8 space-y-8 max-w-6xl mx-auto w-full">
                        <!-- Toasts -->
                        <?php if ($success_msg): ?>
                            <div id="toast-success" class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 animate-pulse alert-success">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span class="font-semibold"><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div id="toast-error" class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 animate-pulse alert-danger">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span class="font-semibold"><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Title and Hero Header -->
                        <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 rounded-3xl p-8 md:p-12 text-white shadow-lg relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
                            <div class="relative z-10 space-y-4 max-w-xl text-center md:text-left">
                                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Coordinator Help Center</h1>
                                <p class="text-blue-100 text-sm md:text-base leading-relaxed">
                                    Welcome to the knowledge hub. Search topics, read quick guides, explore workflows, check system integrity, or file support issues.
                                </p>
                                
                                <!-- Search bar for help topics -->
                                <div class="relative max-w-md mx-auto md:mx-0">
                                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                                    <input type="text" id="help-search" placeholder="Type keywords to filter help topics..." 
                                           class="w-full bg-white text-gray-800 placeholder-gray-400 border border-transparent rounded-2xl py-3 pl-12 pr-4 text-sm font-medium focus:ring-4 focus:ring-blue-500/30 focus:border-transparent outline-none transition-all shadow-md">
                                    <button id="search-clear" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                        <span class="material-symbols-outlined text-lg">close</span>
                                    </button>
                                </div>
                            </div>
                            <!-- Background overlay graphic -->
                            <div class="absolute -right-20 -bottom-20 opacity-15 pointer-events-none">
                                <span class="material-symbols-outlined text-[280px]" style="font-variation-settings:'FILL' 1;">help</span>
                            </div>
                            
                            <!-- Help Categories Quick Links -->
                            <div class="w-full md:w-auto grid grid-cols-2 gap-3 relative z-10">
                                <a href="#quick-guide" class="flex flex-col items-center p-4 bg-white/10 hover:bg-white/20 border border-white/10 rounded-2xl text-center transition-all duration-200 group">
                                    <span class="material-symbols-outlined text-white text-2xl mb-2 group-hover:scale-110 transition-transform">menu_book</span>
                                    <span class="text-xs font-bold uppercase tracking-wider">Quick Guide</span>
                                </a>
                                <a href="#workflow-guide" class="flex flex-col items-center p-4 bg-white/10 hover:bg-white/20 border border-white/10 rounded-2xl text-center transition-all duration-200 group">
                                    <span class="material-symbols-outlined text-white text-2xl mb-2 group-hover:scale-110 transition-transform">account_tree</span>
                                    <span class="text-xs font-bold uppercase tracking-wider">Workflow</span>
                                </a>
                                <a href="#faq-section" class="flex flex-col items-center p-4 bg-white/10 hover:bg-white/20 border border-white/10 rounded-2xl text-center transition-all duration-200 group">
                                    <span class="material-symbols-outlined text-white text-2xl mb-2 group-hover:scale-110 transition-transform">forum</span>
                                    <span class="text-xs font-bold uppercase tracking-wider">FAQs</span>
                                </a>
                                <a href="#support-section" class="flex flex-col items-center p-4 bg-white/10 hover:bg-white/20 border border-white/10 rounded-2xl text-center transition-all duration-200 group">
                                    <span class="material-symbols-outlined text-white text-2xl mb-2 group-hover:scale-110 transition-transform">support_agent</span>
                                    <span class="text-xs font-bold uppercase tracking-wider">Support</span>
                                </a>
                            </div>
                        </div>

                        <!-- Main Column Layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            
                            <!-- Left 2 Columns: Guides, Workflow, FAQs -->
                            <div class="lg:col-span-2 space-y-8">
                                
                                <!-- 1. Coordinator Quick Guide -->
                                <section id="quick-guide" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6 scroll-mt-24 help-section">
                                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                                        <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                            <span class="material-symbols-outlined text-2xl">menu_book</span>
                                        </div>
                                        <div>
                                            <h2 class="text-lg font-bold text-gray-900">Coordinator Quick Guide</h2>
                                            <p class="text-xs text-gray-500 mt-0.5">Essential tasks checklist and instructions</p>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        
                                        <!-- Postings Guide -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-blue-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">work</span>
                                                <h3>Internship Postings</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Navigate to <strong>Postings</strong> and click <strong>New Project Posting</strong>. Supply key parameters (title, open count, timeline) and tech requirements to build application criteria.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/internships.php'" class="mt-3 text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                                Go to Postings <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                        <!-- Review Candidates -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-indigo-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">group</span>
                                                <h3>Review Candidates</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Access the <strong>Candidates</strong> panel to check applicant entries. Toggle filters to inspect resume attachment links and academic statistics, and monitor status updates.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/candidates.php'" class="mt-3 text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                                View Candidates <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                        <!-- Assign Teams -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-purple-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">diversity_3</span>
                                                <h3>Assign Teams</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Use <strong>Team Management</strong> to place candidates into active team projects. Assigning student groups speeds up milestone deliverables and encourages peer support.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/teams.php'" class="mt-3 text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1">
                                                Manage Teams <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                        <!-- Assign Mentors -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-emerald-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">school</span>
                                                <h3>Assign Mentors</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Under <strong>Postings</strong>, edit an active listing to link a qualified mentor. Mentors supervise interns, review technical outputs, and supply project ratings.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/internships.php'" class="mt-3 text-xs font-bold text-emerald-600 hover:text-emerald-800 flex items-center gap-1">
                                                Assign Mentor <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                        <!-- Monitor Daily Logs -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-amber-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">monitoring</span>
                                                <h3>Monitor Daily Logs</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Open <strong>Daily Logs</strong> to review hours spent, completed assignments, issues faced, and task logs. Flags are raised when logins remain inactive.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/daily_logs.php'" class="mt-3 text-xs font-bold text-amber-600 hover:text-amber-800 flex items-center gap-1">
                                                Check Daily Logs <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                        <!-- Generate Reports -->
                                        <div class="p-4 bg-slate-50 border border-gray-100 rounded-xl hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 text-red-600 font-bold text-sm mb-2">
                                                <span class="material-symbols-outlined text-lg">analytics</span>
                                                <h3>Generate Reports</h3>
                                            </div>
                                            <p class="text-xs text-gray-600 leading-relaxed">
                                                Visit <strong>Reports</strong> to build PDF summaries of student cohorts, milestones reached, individual progress reports, and overall team performance.
                                            </p>
                                            <button onclick="window.location.href='/IMP/coordinator/reports.php'" class="mt-3 text-xs font-bold text-red-600 hover:text-red-800 flex items-center gap-1">
                                                View Reports <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                            </button>
                                        </div>

                                    </div>
                                </section>

                                <!-- 2. Internship Workflow Guide (Timeline) -->
                                <section id="workflow-guide" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6 scroll-mt-24 help-section">
                                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                            <span class="material-symbols-outlined text-2xl">account_tree</span>
                                        </div>
                                        <div>
                                            <h2 class="text-lg font-bold text-gray-900">Internship Workflow Guide</h2>
                                            <p class="text-xs text-gray-500 mt-0.5">Visual representation of the overall internship timeline</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Horizontal Timeline Flow for desktop, vertical stack for mobile -->
                                    <div class="relative px-2">
                                        <div class="absolute left-6 md:left-1/2 top-4 bottom-4 w-1 bg-indigo-100 md:-translate-x-1/2 pointer-events-none"></div>
                                        
                                        <div class="space-y-8 relative">
                                            
                                            <!-- Step 1: Application -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right">
                                                    <h4 class="font-bold text-slate-800 text-sm">1. Candidate Application</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Student registers, submits profile data, academic details, and files via form.</p>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto">1</div>
                                                <div class="md:w-1/2 md:pl-8">
                                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-full border border-blue-200">Role: Student</span>
                                                </div>
                                            </div>

                                            <!-- Step 2: HR Screening -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right md:order-1">
                                                    <span class="px-2 py-0.5 bg-cyan-50 text-cyan-700 text-[10px] font-bold rounded-full border border-cyan-200">Role: HR Panel</span>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto md:order-2">2</div>
                                                <div class="md:w-1/2 md:pl-8 md:order-3">
                                                    <h4 class="font-bold text-slate-800 text-sm">2. HR Screening</h4>
                                                    <p class="text-xs text-gray-500 mt-1">First review of qualifications and initial screening checks are recorded by HR.</p>
                                                </div>
                                            </div>

                                            <!-- Step 3: HOD Approval -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right">
                                                    <h4 class="font-bold text-slate-800 text-sm">3. HOD Approval</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Academic department head grants clearance validation for the internship.</p>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto">3</div>
                                                <div class="md:w-1/2 md:pl-8">
                                                    <span class="px-2 py-0.5 bg-purple-50 text-purple-700 text-[10px] font-bold rounded-full border border-purple-200">Role: HOD</span>
                                                </div>
                                            </div>

                                            <!-- Step 4: Selection -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right md:order-1">
                                                    <span class="px-2 py-0.5 bg-green-50 text-green-700 text-[10px] font-bold rounded-full border border-green-200">Role: Coordinator</span>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto md:order-2">4</div>
                                                <div class="md:w-1/2 md:pl-8 md:order-3">
                                                    <h4 class="font-bold text-slate-800 text-sm">4. Candidate Selection</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Final decision and onboarding validation is processed under Candidates section.</p>
                                                </div>
                                            </div>

                                            <!-- Step 5: Team Assignment -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right">
                                                    <h4 class="font-bold text-slate-800 text-sm">5. Team &amp; Mentor Assignment</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Selected candidates are placed in a team cohort and matched with active mentors.</p>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto">5</div>
                                                <div class="md:w-1/2 md:pl-8">
                                                    <span class="px-2 py-0.5 bg-orange-50 text-orange-700 text-[10px] font-bold rounded-full border border-orange-200">Role: Coordinator</span>
                                                </div>
                                            </div>

                                            <!-- Step 6: Daily Logs -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right md:order-1">
                                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-full border border-blue-200">Role: Student</span>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto md:order-2">6</div>
                                                <div class="md:w-1/2 md:pl-8 md:order-3">
                                                    <h4 class="font-bold text-slate-800 text-sm">6. Daily Logs Tracking</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Student logs hours, tasks, focus levels, and issues daily in the system.</p>
                                                </div>
                                            </div>

                                            <!-- Step 7: Mentor Review -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right">
                                                    <h4 class="font-bold text-slate-800 text-sm">7. Mentor Review &amp; Feedback</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Assigned mentors review the student output and submit evaluations/ratings.</p>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto">7</div>
                                                <div class="md:w-1/2 md:pl-8">
                                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-full border border-emerald-200">Role: Mentor</span>
                                                </div>
                                            </div>

                                            <!-- Step 8: Final Evaluation -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right md:order-1">
                                                    <span class="px-2 py-0.5 bg-red-50 text-red-700 text-[10px] font-bold rounded-full border border-red-200">Role: Coordinator</span>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto md:order-2">8</div>
                                                <div class="md:w-1/2 md:pl-8 md:order-3">
                                                    <h4 class="font-bold text-slate-800 text-sm">8. Final Evaluation</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Coordinator closes the timeline and calculates overall performance grade scores.</p>
                                                </div>
                                            </div>

                                            <!-- Step 9: Certification -->
                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-0">
                                                <div class="md:w-1/2 md:pr-8 md:text-right">
                                                    <h4 class="font-bold text-slate-800 text-sm">9. Certification &amp; Completion</h4>
                                                    <p class="text-xs text-gray-500 mt-1">Successful interns download their completion certificate badge.</p>
                                                </div>
                                                <div class="w-8 h-8 rounded-full bg-emerald-600 border-4 border-white text-white flex items-center justify-center font-bold text-xs shrink-0 z-10 md:mx-auto">✓</div>
                                                <div class="md:w-1/2 md:pl-8">
                                                    <span class="px-2 py-0.5 bg-gray-50 text-gray-700 text-[10px] font-bold rounded-full border border-gray-200">Role: System</span>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </section>

                                <!-- 3. Detailed Help Topics -->
                                <section id="detailed-help" class="space-y-6 scroll-mt-24 help-section">
                                    
                                    <!-- Daily Logs Monitoring Help -->
                                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                                        <div class="flex items-center gap-2 text-amber-600 font-bold border-b border-gray-100 pb-3">
                                            <span class="material-symbols-outlined text-2xl">monitoring</span>
                                            <h3 class="text-lg">Daily Logs Monitoring Help</h3>
                                        </div>
                                        <div class="text-xs text-gray-600 space-y-3 leading-relaxed">
                                            <p>
                                                Monitoring intern activity ensures milestones are met and lags are caught early. Under the **Daily Logs Monitoring** tab, you have access to three main dashboard features:
                                            </p>
                                            <ul class="list-disc list-inside space-y-2 ml-2">
                                                <li>
                                                    <strong>Track Overdue Logs:</strong> The system automatically logs when an active intern fails to submit details by the designated cutoff. Filter the logs table by sorting by submission date to spot overdue students.
                                                </li>
                                                <li>
                                                    <strong>Review Submitted Logs:</strong> Examine the entries to inspect description logs, hours filed (target: 4-8 hours/day), next plans, focus levels (Low/Medium/High), and specific blocker descriptions.
                                                </li>
                                                <li>
                                                    <strong>Monitor Intern Activity:</strong> The dashboard includes progress metrics showing the proportion of active vs inactive interns. Send direct reminders using the <strong>Notify</strong> action on the header.
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Reports Help -->
                                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                                        <div class="flex items-center gap-2 text-red-600 font-bold border-b border-gray-100 pb-3">
                                            <span class="material-symbols-outlined text-2xl">analytics</span>
                                            <h3 class="text-lg">Reports Help</h3>
                                        </div>
                                        <div class="text-xs text-gray-600 space-y-3 leading-relaxed">
                                            <p>
                                                The reports engine groups complex SQL database results into clean analytical exports. Review the available reports structure below:
                                            </p>
                                            <ul class="list-disc list-inside space-y-2 ml-2">
                                                <li>
                                                    <strong>Internship Reports:</strong> Summary of registered applicants, status breakdown percentages, selection statistics, and completion ratios.
                                                </li>
                                                <li>
                                                    <strong>Student Progress Reports:</strong> Detailed reports per intern detailing total days logged, cumulative time spent, average ratings, and mentor evaluations.
                                                </li>
                                                <li>
                                                    <strong>Team Performance Reports:</strong> Aggregated progress reports grouped by project teams, showing task delivery milestones and team completion grades.
                                                </li>
                                            </ul>
                                            <p class="pt-2 text-[11px] text-gray-400 font-medium">
                                                Note: Reports can be filtered by specific technology stacks, domains, dates, and difficulty levels, and exported directly as PDF or Excel files.
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <!-- 4. Expandable/Collapsible FAQ Section -->
                                <section id="faq-section" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6 scroll-mt-24 help-section">
                                    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                                        <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                                            <span class="material-symbols-outlined text-2xl">forum</span>
                                        </div>
                                        <div>
                                            <h2 class="text-lg font-bold text-gray-900">Frequently Asked Questions</h2>
                                            <p class="text-xs text-gray-500 mt-0.5">Quick solutions to common coordinator questions</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        
                                        <!-- FAQ 1 -->
                                        <div class="border border-gray-150 rounded-xl overflow-hidden faq-item transition-all duration-200">
                                            <button class="w-full flex items-center justify-between p-4 font-semibold text-slate-800 text-xs md:text-sm text-left hover:bg-gray-50 outline-none transition-colors" onclick="toggleFaq(this)">
                                                <span>How to create a new internship posting?</span>
                                                <span class="material-symbols-outlined transition-transform duration-200">expand_more</span>
                                            </button>
                                            <div class="max-h-0 overflow-hidden transition-all duration-300 bg-slate-50">
                                                <p class="p-4 text-xs text-gray-600 leading-relaxed border-t border-gray-100">
                                                    Go to the **Postings** section from the sidebar, click **New Project Posting**, fill out the project form parameters (Title, Type, Subtype, Openings, Tech Stack, Duration, Mode, Difficulty, Dates, and Description) and click **Create**. This automatically creates 6 default timeline workflow phases based on the duration.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- FAQ 2 -->
                                        <div class="border border-gray-150 rounded-xl overflow-hidden faq-item transition-all duration-200">
                                            <button class="w-full flex items-center justify-between p-4 font-semibold text-slate-800 text-xs md:text-sm text-left hover:bg-gray-50 outline-none transition-colors" onclick="toggleFaq(this)">
                                                <span>How to assign students to projects?</span>
                                                <span class="material-symbols-outlined transition-transform duration-200">expand_more</span>
                                            </button>
                                            <div class="max-h-0 overflow-hidden transition-all duration-300 bg-slate-50">
                                                <p class="p-4 text-xs text-gray-600 leading-relaxed border-t border-gray-100">
                                                    Navigate to **Candidates**, search for selected students, click **View Details**, and click **Assign Project** (or go to **Team Management**). Choose the student and enter a matching Team Name. This links their profile to the project pipeline and generates active daily logs.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- FAQ 3 -->
                                        <div class="border border-gray-150 rounded-xl overflow-hidden faq-item transition-all duration-200">
                                            <button class="w-full flex items-center justify-between p-4 font-semibold text-slate-800 text-xs md:text-sm text-left hover:bg-gray-50 outline-none transition-colors" onclick="toggleFaq(this)">
                                                <span>How to monitor inactive interns?</span>
                                                <span class="material-symbols-outlined transition-transform duration-200">expand_more</span>
                                            </button>
                                            <div class="max-h-0 overflow-hidden transition-all duration-300 bg-slate-50">
                                                <p class="p-4 text-xs text-gray-600 leading-relaxed border-t border-gray-100">
                                                    On the **Dashboard** and **Daily Logs** sections, check the "Pending Logs" metric. It calculates the difference between active interns and logs submitted today. Sort logs by date to check which interns have overdue entries, and use the **Notify** option in the top header to send warning notifications.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- FAQ 4 -->
                                        <div class="border border-gray-150 rounded-xl overflow-hidden faq-item transition-all duration-200">
                                            <button class="w-full flex items-center justify-between p-4 font-semibold text-slate-800 text-xs md:text-sm text-left hover:bg-gray-50 outline-none transition-colors" onclick="toggleFaq(this)">
                                                <span>How to review daily logs?</span>
                                                <span class="material-symbols-outlined transition-transform duration-200">expand_more</span>
                                            </button>
                                            <div class="max-h-0 overflow-hidden transition-all duration-300 bg-slate-50">
                                                <p class="p-4 text-xs text-gray-600 leading-relaxed border-t border-gray-100">
                                                    Under **Daily Logs Monitoring**, search by student name or filter by date. Inspect the time spent, tasks completed, blocker issues, and focus levels. You can filter logs by focus levels (e.g. low focus logs) to audit student challenges.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- FAQ 5 -->
                                        <div class="border border-gray-150 rounded-xl overflow-hidden faq-item transition-all duration-200">
                                            <button class="w-full flex items-center justify-between p-4 font-semibold text-slate-800 text-xs md:text-sm text-left hover:bg-gray-50 outline-none transition-colors" onclick="toggleFaq(this)">
                                                <span>How to export reports?</span>
                                                <span class="material-symbols-outlined transition-transform duration-200">expand_more</span>
                                            </button>
                                            <div class="max-h-0 overflow-hidden transition-all duration-300 bg-slate-50">
                                                <p class="p-4 text-xs text-gray-600 leading-relaxed border-t border-gray-100">
                                                    Go to the **Reports** tab from the sidebar. Use the filtering interface to narrow metrics down to specific project types, teams, or student lists. Click the **Export Report** button to generate clean, print-friendly PDF or XLS summaries.
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                </section>

                            </div>

                            <!-- Right Column: System Status, Support, Report Issue -->
                            <div class="space-y-8">
                                
                                <!-- 5. System Status Section -->
                                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                                    <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-gray-100 pb-3">
                                        <span class="material-symbols-outlined text-gray-500">grid_view</span>
                                        <h3 class="text-sm uppercase tracking-wider">System Status</h3>
                                    </div>
                                    <div class="space-y-3">
                                        
                                        <!-- Server Status -->
                                        <div class="flex items-center justify-between p-3 bg-emerald-50/50 border border-emerald-100 rounded-xl text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="relative flex h-2 w-2">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                </span>
                                                <span class="font-semibold text-slate-700">Server Status</span>
                                            </div>
                                            <span class="font-bold text-emerald-700">✓ Online</span>
                                        </div>

                                        <!-- DB Connection Status -->
                                        <div class="flex items-center justify-between p-3 <?php echo $db_status ? 'bg-emerald-50/50 border-emerald-100' : 'bg-red-50 border-red-100'; ?> border rounded-xl text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="relative flex h-2 w-2">
                                                    <?php if($db_status): ?>
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                    <?php else: ?>
                                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="font-semibold text-slate-700">Database Connection</span>
                                            </div>
                                            <span class="font-bold <?php echo $db_status ? 'text-emerald-700' : 'text-red-700'; ?>">
                                                <?php echo $db_status ? '✓ Connected' : '✗ Disconnected'; ?>
                                            </span>
                                        </div>

                                        <!-- Notification Service Status -->
                                        <div class="flex items-center justify-between p-3 <?php echo $notif_service_status ? 'bg-emerald-50/50 border-emerald-100' : 'bg-red-50 border-red-100'; ?> border rounded-xl text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="relative flex h-2 w-2">
                                                    <?php if($notif_service_status): ?>
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                    <?php else: ?>
                                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="font-semibold text-slate-700">Notification Service</span>
                                            </div>
                                            <span class="font-bold <?php echo $notif_service_status ? 'text-emerald-700' : 'text-red-700'; ?>">
                                                <?php echo $notif_service_status ? '✓ Active' : '✗ Inactive'; ?>
                                            </span>
                                        </div>

                                    </div>
                                </section>

                                <!-- 6. Contact Support Section -->
                                <section id="support-section" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4 scroll-mt-24">
                                    <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-gray-100 pb-3">
                                        <span class="material-symbols-outlined text-gray-500">support_agent</span>
                                        <h3 class="text-sm uppercase tracking-wider">Contact Support</h3>
                                    </div>
                                    <div class="space-y-4">
                                        
                                        <!-- Support Card 1: Technical Support -->
                                        <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl relative overflow-hidden">
                                            <div class="flex items-center gap-2 font-bold text-blue-800 text-xs mb-2">
                                                <span class="material-symbols-outlined text-[16px]">build</span>
                                                <span>Technical Support Desk</span>
                                            </div>
                                            <div class="space-y-1.5 text-xs text-slate-700">
                                                <p class="flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-gray-400 text-[14px]">mail</span>
                                                    <a href="mailto:techsupport@imp.com" class="hover:underline font-semibold">techsupport@imp.com</a>
                                                </p>
                                                <p class="flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-gray-400 text-[14px]">call</span>
                                                    <span>+1 (555) 019-2834 (Ext: 104)</span>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Support Card 2: Coordinator Support -->
                                        <div class="p-4 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100 rounded-xl relative overflow-hidden">
                                            <div class="flex items-center gap-2 font-bold text-emerald-800 text-xs mb-2">
                                                <span class="material-symbols-outlined text-[16px]">admin_panel_settings</span>
                                                <span>Coordinator Help Desk</span>
                                            </div>
                                            <div class="space-y-1.5 text-xs text-slate-700">
                                                <p class="flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-gray-400 text-[14px]">mail</span>
                                                    <a href="mailto:coordsupport@imp.com" class="hover:underline font-semibold">coordsupport@imp.com</a>
                                                </p>
                                                <p class="flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-gray-400 text-[14px]">call</span>
                                                    <span>+1 (555) 019-5820 (Ext: 201)</span>
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                </section>

                                <!-- 7. Report an Issue Form -->
                                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                                    <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-gray-100 pb-3">
                                        <span class="material-symbols-outlined text-gray-500">report_problem</span>
                                        <h3 class="text-sm uppercase tracking-wider">Report an Issue</h3>
                                    </div>
                                    <form action="/IMP/coordinator/help_center.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="submit_issue" value="1">
                                        
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Issue Title</label>
                                            <input type="text" name="issue_title" required placeholder="e.g. Blank page on logs" 
                                                   class="w-full bg-slate-50 border border-gray-200 rounded-xl py-2.5 px-3 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Description</label>
                                            <textarea name="issue_description" required rows="3" placeholder="Provide step-by-step details about what went wrong..." 
                                                      class="w-full bg-slate-50 border border-gray-200 rounded-xl py-2.5 px-3 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none resize-none"></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Screenshot (Optional)</label>
                                            <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 bg-slate-50 hover:bg-slate-100 rounded-xl py-4 px-3 cursor-pointer transition-colors relative" id="screenshot-dropzone">
                                                <span class="material-symbols-outlined text-gray-400 text-2xl mb-1">cloud_upload</span>
                                                <span class="text-[10px] text-gray-500 font-medium">Click to select screenshot</span>
                                                <input type="file" name="screenshot" id="screenshot-file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                                                
                                                <!-- Image Preview -->
                                                <div id="screenshot-preview-container" class="hidden absolute inset-0 bg-white rounded-xl flex items-center justify-between px-3 border border-gray-200">
                                                    <div class="flex items-center gap-2">
                                                        <img id="screenshot-preview" src="#" alt="Preview" class="w-8 h-8 object-cover rounded border border-gray-200">
                                                        <span id="screenshot-filename" class="text-[10px] text-slate-600 font-semibold truncate max-w-[120px]">file.png</span>
                                                    </div>
                                                    <button type="button" onclick="removeScreenshot(event)" class="p-1 hover:bg-red-50 text-red-500 rounded-full cursor-pointer transition-colors">
                                                        <span class="material-symbols-outlined text-sm">delete</span>
                                                    </button>
                                                </div>
                                            </label>
                                            <p class="text-[9px] text-gray-400 mt-1">Supported formats: JPG, PNG, GIF, WEBP. Max size: 5MB</p>
                                        </div>

                                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all hover:translate-y-[-1px] cursor-pointer flex items-center justify-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs">send</span> Submit Issue
                                        </button>
                                    </form>
                                </section>

                            </div>

                        </div>
                </div>
        </main>

        <!-- Dynamic JS Functionality -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
                
                // 1. Sidebar Toggle Mobile/Desktop
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

                // 2. Profile Dropdown Menu Toggle
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

                // 3. Auto dismiss toasts
                ['toast-success', 'toast-error'].forEach(id => {
                        const toast = document.getElementById(id);
                        if (toast) {
                                setTimeout(() => {
                                        toast.style.opacity = '0';
                                        toast.style.transition = 'opacity 0.5s ease-out';
                                        setTimeout(() => toast.remove(), 500);
                                }, 5000);
                        }
                });

                // 4. Client-side Screenshot Preview
                const fileInput = document.getElementById('screenshot-file');
                const previewContainer = document.getElementById('screenshot-preview-container');
                const previewImg = document.getElementById('screenshot-preview');
                const filenameText = document.getElementById('screenshot-filename');

                if (fileInput) {
                        fileInput.addEventListener('change', function() {
                                const file = this.files[0];
                                if (file) {
                                        // Validate file size (5MB)
                                        if (file.size > 5 * 1024 * 1024) {
                                                alert('File exceeds 5MB limit.');
                                                this.value = '';
                                                return;
                                        }

                                        filenameText.textContent = file.name;
                                        const reader = new FileReader();
                                        reader.onload = function(e) {
                                                previewImg.src = e.target.result;
                                                previewContainer.classList.remove('hidden');
                                        }
                                        reader.readAsDataURL(file);
                                }
                        });
                }

                // 5. Live Search & Highlight Functionality
                const searchInput = document.getElementById('help-search');
                const clearBtn = document.getElementById('search-clear');
                const helpSections = document.querySelectorAll('.help-section');

                if (searchInput) {
                        searchInput.addEventListener('input', function() {
                                const query = this.value.trim().toLowerCase();
                                
                                if (query.length > 0) {
                                        clearBtn.classList.remove('hidden');
                                } else {
                                        clearBtn.classList.add('hidden');
                                }

                                filterContent(query);
                        });

                        clearBtn.addEventListener('click', function() {
                                searchInput.value = '';
                                this.classList.add('hidden');
                                filterContent('');
                        });
                }
        });

        // Toggle Expandable FAQs
        function toggleFaq(button) {
                const item = button.parentElement;
                const body = button.nextElementSibling;
                const icon = button.querySelector('.material-symbols-outlined');
                
                // Get sibling items and collapse them (accordion behavior)
                const faqItems = document.querySelectorAll('.faq-item');
                faqItems.forEach(el => {
                        if (el !== item) {
                                const siblingBody = el.querySelector('div');
                                const siblingIcon = el.querySelector('.material-symbols-outlined');
                                siblingBody.style.maxHeight = null;
                                siblingIcon.style.transform = null;
                                el.classList.remove('border-blue-500', 'ring-2', 'ring-blue-500/10');
                        }
                });

                if (body.style.maxHeight) {
                        body.style.maxHeight = null;
                        icon.style.transform = null;
                        item.classList.remove('border-blue-500', 'ring-2', 'ring-blue-500/10');
                } else {
                        body.style.maxHeight = body.scrollHeight + "px";
                        icon.style.transform = "rotate(180deg)";
                        item.classList.add('border-blue-500', 'ring-2', 'ring-blue-500/10');
                }
        }

        // Remove screenshot helper
        function removeScreenshot(event) {
                event.preventDefault();
                event.stopPropagation();
                
                const fileInput = document.getElementById('screenshot-file');
                const previewContainer = document.getElementById('screenshot-preview-container');
                
                if (fileInput) {
                        fileInput.value = '';
                }
                if (previewContainer) {
                        previewContainer.classList.add('hidden');
                }
        }

        // Search filtering function
        function filterContent(query) {
                const helpSections = document.querySelectorAll('.help-section');
                
                helpSections.forEach(section => {
                        let matches = false;
                        
                        // Check if section is FAQ
                        if (section.id === 'faq-section') {
                                const faqItems = section.querySelectorAll('.faq-item');
                                let faqMatches = 0;
                                
                                faqItems.forEach(item => {
                                        const text = item.textContent.toLowerCase();
                                        if (text.includes(query)) {
                                                item.classList.remove('hidden');
                                                faqMatches++;
                                                matches = true;
                                        } else {
                                                item.classList.add('hidden');
                                        }
                                });
                                
                                if (faqMatches === 0 && query !== '') {
                                        section.classList.add('hidden');
                                } else {
                                        section.classList.remove('hidden');
                                }
                        } else {
                                // For regular sections, search all text
                                const contentText = section.textContent.toLowerCase();
                                if (contentText.includes(query)) {
                                        section.classList.remove('hidden');
                                        matches = true;
                                } else {
                                        section.classList.add('hidden');
                                }
                        }
                });
        }
        </script>
<script src="js/alerts.js"></script>
</body>
</html>
