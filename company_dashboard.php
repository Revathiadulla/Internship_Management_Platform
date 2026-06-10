<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/auth.php';

// Enforce login as company
require_role('company');

$company_id = current_user_id();
$recruiter_name = $_SESSION['full_name'] ?? 'Recruiter';
$recruiter_email = $_SESSION['email'] ?? '';

// Fetch company profile details
$company_title = 'Nexus Tech';
$industry_type = 'Software & IT';
$plan_selected = null;
$q_prof = mysqli_query($conn, "SELECT * FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
    $industry_type = $row['industry_type'];
    $plan_selected = $row['plan_selected'];
}

if (empty($plan_selected)) {
    $profile = ensure_company_profile($conn, $company_id, $_SESSION['full_name'] ?? 'Nexus Tech');
    $plan_selected = $profile['plan_selected'];
    $company_title = $profile['company_name'];
}

// Fetch unread notifications count
$unread_count = 0;
$notifications = [];
$notif_count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM company_notifications WHERE company_id = ? AND is_read = 0");
if ($notif_count_stmt) {
    $notif_count_stmt->bind_param("i", $company_id);
    $notif_count_stmt->execute();
    $notif_count_res = $notif_count_stmt->get_result()->fetch_assoc();
    $unread_count = $notif_count_res['unread'] ?? 0;
    $notif_count_stmt->close();
}

$notif_list_stmt = $conn->prepare("SELECT * FROM company_notifications WHERE company_id = ? ORDER BY created_at DESC LIMIT 5");
if ($notif_list_stmt) {
    $notif_list_stmt->bind_param("i", $company_id);
    $notif_list_stmt->execute();
    $notifications = $notif_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notif_list_stmt->close();
}


// ── POST / AJAX Endpoint: Handle shortlists and contacts ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $candidate_id = intval($_POST['candidate_id'] ?? 0);

    if ($candidate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid candidate ID.']);
        exit();
    }

    // Fetch candidate name for logging
    $cand_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $cand_stmt->bind_param("i", $candidate_id);
    $cand_stmt->execute();
    $cand_row = $cand_stmt->get_result()->fetch_assoc();
    $candidate_name = $cand_row ? $cand_row['full_name'] : 'Candidate';

    if ($action === 'toggle_shortlist') {
        $check = mysqli_query($conn, "SELECT id FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $candidate_id");
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "DELETE FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $candidate_id");
            log_activity($conn, 'Shortlist Remove', "Company \"$company_title\" removed $candidate_name from shortlist.");
            echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Candidate removed from shortlist.']);
        } else {
            mysqli_query($conn, "INSERT INTO company_shortlists (company_id, candidate_id) VALUES ($company_id, $candidate_id)");
            log_activity($conn, 'Shortlist Add', "Company \"$company_title\" shortlisted $candidate_name.");
            echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Candidate added to shortlist.']);
        }
        exit();
    }

    if ($action === 'contact_candidate') {
        $message = mysqli_real_escape_string($conn, $_POST['message'] ?? 'We are interested in your profile. Please get in touch.');
        mysqli_query($conn, "INSERT INTO company_contacts (company_id, candidate_id, message) VALUES ($company_id, $candidate_id, '$message') ON DUPLICATE KEY UPDATE message = '$message'");
        log_activity($conn, 'Candidate Contact', "Company \"$company_title\" contacted $candidate_name with message: " . $message);
        echo json_encode(['success' => true, 'message' => 'Candidate marked as contacted!']);
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();

}

// ── GET: Query Stats ──
// Available Talent: Candidates who completed test, or HR round, HOD approved, or Selected
$q_available = mysqli_query($conn, "SELECT COUNT(*) AS total FROM candidates WHERE current_status IN ('Test Completed', 'HR Round', 'HOD Approved', 'Selected')");
$available_count = $q_available ? mysqli_fetch_assoc($q_available)['total'] : 0;

// Shortlisted
$q_shortlist = mysqli_query($conn, "SELECT COUNT(*) AS total FROM company_shortlists WHERE company_id = $company_id");
$shortlist_count = $q_shortlist ? mysqli_fetch_assoc($q_shortlist)['total'] : 0;

// Contacted
$q_contacted = mysqli_query($conn, "SELECT COUNT(*) AS total FROM company_contacts WHERE company_id = $company_id");
$contacted_count = $q_contacted ? mysqli_fetch_assoc($q_contacted)['total'] : 0;

// Hired (Shortlisted candidates who became Selected or Active Interns)
$q_our_hires = mysqli_query($conn, "SELECT COUNT(*) AS total FROM company_shortlists cs JOIN candidates c ON cs.candidate_id = c.user_id WHERE cs.company_id = $company_id AND c.current_status IN ('Selected', 'Active Intern')");
$our_hires_count = $q_our_hires ? mysqli_fetch_assoc($q_our_hires)['total'] : 0;

// Fetch current viewed candidate count
$q_views = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM company_views WHERE company_id = $company_id");
$views_row = mysqli_fetch_assoc($q_views);
$views_count = intval($views_row['cnt'] ?? 0);

$show_limit_warning = false;
if ($plan_selected === 'Free' && $views_count >= 8 && $views_count < 10) {
    $show_limit_warning = true;
} elseif ($plan_selected === 'Basic' && $views_count >= 65 && $views_count < 75) {
    $show_limit_warning = true;
}

// Recommended Talent Preview: Top 2 candidates based on score
$recommended_candidates = [];
$q_rec = mysqli_query($conn, "
    SELECT c.*, COALESCE(jp.title, a.internship_name) AS project_title
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE c.current_status IN ('Test Completed', 'HR Round', 'HOD Approved', 'Selected')
    ORDER BY c.updated_at DESC
    LIMIT 2
");
if ($q_rec) {
    while ($row = mysqli_fetch_assoc($q_rec)) {
        // Check shortlist & contact status
        $cand_user_id = $row['user_id'];
        $check_s = mysqli_query($conn, "SELECT id FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $cand_user_id LIMIT 1");
        $row['is_shortlisted'] = ($check_s && mysqli_num_rows($check_s) > 0);

        $check_c = mysqli_query($conn, "SELECT id FROM company_contacts WHERE company_id = $company_id AND candidate_id = $cand_user_id LIMIT 1");
        $row['is_contacted'] = ($check_c && mysqli_num_rows($check_c) > 0);

        $recommended_candidates[] = $row;
    }
}

// Top Skills aggregation
$skills_dist = ['Web Development' => 0, 'AI / Machine Learning' => 0, 'UI / UX Design' => 0];
$q_skills = mysqli_query($conn, "SELECT skills FROM candidates");
if ($q_skills) {
    while ($row = mysqli_fetch_assoc($q_skills)) {
        $skills_lower = strtolower($row['skills'] ?? '');
        if (strpos($skills_lower, 'react') !== false || strpos($skills_lower, 'web') !== false || strpos($skills_lower, 'html') !== false || strpos($skills_lower, 'js') !== false) {
            $skills_dist['Web Development']++;
        }
        if (strpos($skills_lower, 'python') !== false || strpos($skills_lower, 'ai') !== false || strpos($skills_lower, 'ml') !== false || strpos($skills_lower, 'nlp') !== false || strpos($skills_lower, 'tensorflow') !== false) {
            $skills_dist['AI / Machine Learning']++;
        }
        if (strpos($skills_lower, 'figma') !== false || strpos($skills_lower, 'design') !== false || strpos($skills_lower, 'ux') !== false || strpos($skills_lower, 'ui') !== false) {
            $skills_dist['UI / UX Design']++;
        }
    }
}
$max_skill = max(1, max(array_values($skills_dist)));

// New Certifications: Candidates who completed tests recently
$recent_certifications = [];
$q_recent = mysqli_query($conn, "
    SELECT c.*, a.applied_date
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    WHERE c.current_status IN ('Test Completed', 'Selected')
    ORDER BY c.updated_at DESC
    LIMIT 3
");
if ($q_recent) {
    while ($row = mysqli_fetch_assoc($q_recent)) {
        $recent_certifications[] = $row;
    }
}

// Fetch recent activity logs
$activity_logs = [];
$q_act = mysqli_query($conn, "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
if ($q_act) {
    while ($row = mysqli_fetch_assoc($q_act)) {
        $activity_logs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Hiring Overview | Company Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1d4ed8",
                        secondary: "#64748b",
                        surface: "#f8f9fa",
                        "surface-container": "#ffffff",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #f8f9fa; color: #1e293b; }
        .sidebar-link { display: flex; items-center: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #64748b; }
        .sidebar-link:hover { background-color: #f1f5f9; color: #1d4ed8; }
        .sidebar-link.active { background-color: #1d4ed8; color: #ffffff; box-shadow: 0 4px 6px -1px rgb(29 78 216 / 0.1), 0 2px 4px -2px rgb(29 78 216 / 0.1); }
        .stat-card { background: white; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1); }
        .pipeline-step { position: relative; display: flex; flex-direction: column; align-items: center; flex: 1; }
        .pipeline-step::after { content: ''; position: absolute; top: 1.25rem; left: 50%; width: 100%; height: 2px; background-color: #e2e8f0; z-index: 0; }
        .pipeline-step:last-child::after { display: none; }
        .pipeline-dot { position: relative; z-index: 10; width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: white; border: 2px solid #e2e8f0; display: flex; items-center: center; justify-content: center; }
        .pipeline-active .pipeline-dot { border-color: #1d4ed8; color: #1d4ed8; background-color: #eff6ff; }
        .pipeline-completed .pipeline-dot { background-color: #1d4ed8; border-color: #1d4ed8; color: white; }
    </style>
</head>
<body class="min-h-screen flex font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col fixed h-screen z-50">
        <div class="flex flex-col mb-10 px-2">
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
            <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mt-2 ml-1">Recruitment Hub</p>
        </div>

        <nav class="flex-1 space-y-1">
            <a href="company_dashboard.php" class="sidebar-link active">
                <span class="material-symbols-outlined text-xl">dashboard</span>
                Hiring Overview
            </a>
            <a href="browse_talent_pool.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">person_search</span>
                Browse Talent Pool
            </a>
            <a href="hiring_requests.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">handshake</span>
                Hiring Requests
            </a>
            <a href="company_subscription.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">payments</span>
                My Subscription
            </a>
        </nav>

        <div class="mt-auto pt-6 border-t border-gray-100">
            <a href="logout.php" class="sidebar-link text-red-600 hover:bg-red-50">
                <span class="material-symbols-outlined">logout</span>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Top Nav -->
            <header class="flex justify-between items-center bg-white p-4 rounded-2xl border border-gray-200 shadow-sm mb-8">
                <div class="flex items-center gap-4 px-2">
                    <span class="material-symbols-outlined text-gray-400">search</span>
                    <input type="text" onclick="window.location.href='browse_talent_pool.php'" placeholder="Search talent pool..." class="bg-transparent border-none text-sm focus:ring-0 w-80 cursor-pointer">
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <button class="relative p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-all">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-black rounded-full h-4 w-4 flex items-center justify-center border-2 border-white"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown menu -->
                        <div class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                                <h4 class="font-bold text-slate-800 text-sm">Notifications</h4>
                                <?php if ($unread_count > 0): ?>
                                    <button type="button" onclick="markAllNotificationsRead()" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 transition-colors">Mark all as read</button>
                                <?php endif; ?>
                            </div>
                            <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                                <?php if (empty($notifications)): ?>
                                    <div class="p-6 text-center text-xs text-slate-400 italic">No notifications yet</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <div class="p-3.5 hover:bg-slate-50 transition-colors text-left <?php echo !$n['is_read'] ? 'bg-blue-50/30' : ''; ?>">
                                            <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($n['title']); ?></p>
                                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($n['message']); ?></p>
                                            <p class="text-[9px] text-slate-400 mt-1.5 font-medium"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="h-8 w-px bg-gray-100"></div>
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden md:block">
                            <p class="text-xs font-bold text-gray-900 leading-none"><?php echo htmlspecialchars($recruiter_name); ?></p>
                            <p class="text-[10px] text-blue-600 font-bold mt-1"><?php echo htmlspecialchars($company_title); ?> Recruiter</p>
                        </div>
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white"><?php echo strtoupper(substr($recruiter_name, 0, 1)); ?></span>
                    </div>
                </div>
            </header>

            <!-- Welcome Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Hiring Overview</h2>
                    <p class="text-gray-500 font-medium mt-1">Track your recruitment progress and certified internship graduates.</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='browse_talent_pool.php'" class="bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">
                        <span class="material-symbols-outlined text-lg">person_search</span> View Talent Pool
                    </button>
                </div>
            </div>

            <?php if ($show_limit_warning): ?>
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600">warning</span>
                    <span>Your subscription is close to its profile viewing limit. <a href="company_subscription.php" class="text-blue-600 hover:underline">Upgrade your plan now</a>.</span>
                </div>
            <?php endif; ?>

            <!-- Subscription Info -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">payments</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 tracking-tight">Subscription Plan</h3>
                        <p class="text-xs text-gray-500 font-medium mt-0.5">Manage your candidate access limits and upgrade packages.</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-left">
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Current Plan</span>
                        <p class="text-sm font-extrabold text-blue-600 uppercase mt-0.5"><?php echo htmlspecialchars($plan_selected); ?></p>
                    </div>
                    <div class="h-8 w-px bg-gray-200"></div>
                    <div class="text-left">
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Profiles Viewed</span>
                        <p class="text-sm font-extrabold text-gray-800 mt-0.5">
                            <?php 
                            $max = ($plan_selected === 'Free') ? 10 : (($plan_selected === 'Basic') ? 75 : 'Unlimited');
                            echo $views_count . ' / ' . $max;
                            ?>
                        </p>
                    </div>
                    <a href="company_subscription.php" class="bg-gray-150 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-xl text-xs font-bold transition-all">
                        Upgrade / Change Plan
                    </a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Available Talent</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $available_count; ?></h3>
                        <span class="text-blue-600 text-[10px] font-bold bg-blue-50 px-2 py-1 rounded-lg">Certified</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Shortlisted</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $shortlist_count; ?></h3>
                        <span class="text-amber-600 text-[10px] font-bold bg-amber-50 px-2 py-1 rounded-lg">Active</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Contacted</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $contacted_count; ?></h3>
                        <span class="text-indigo-600 text-[10px] font-bold bg-indigo-50 px-2 py-1 rounded-lg">Interviews</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Hired</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $our_hires_count; ?></h3>
                        <span class="text-green-600 text-[10px] font-bold bg-green-50 px-2 py-1 rounded-lg">Our Hires</span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Widgets -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Recruitment Pipeline (Left) -->
                <div class="lg:col-span-8 space-y-8">
                    <div class="bg-white p-8 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-bold text-gray-900 mb-10">Recruitment Pipeline</h3>
                        <div class="flex justify-between items-start">
                            <div class="pipeline-step pipeline-completed">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">fact_check</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Shortlisted (<?php echo $shortlist_count; ?>)</p>
                            </div>
                            <div class="pipeline-step <?php echo $contacted_count > 0 ? 'pipeline-completed' : 'pipeline-active'; ?>">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">mail</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Contacted (<?php echo $contacted_count; ?>)</p>
                            </div>
                            <div class="pipeline-step <?php echo $contacted_count > 0 ? 'pipeline-active' : ''; ?>">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">forum</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Interview</p>
                            </div>
                            <div class="pipeline-step <?php echo $our_hires_count > 0 ? 'pipeline-completed' : ''; ?>">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">handshake</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Hired (<?php echo $our_hires_count; ?>)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recommended Talent Preview -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600">verified_user</span>
                            Recommended Talent Preview
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if (empty($recommended_candidates)): ?>
                                <div class="col-span-2 bg-white rounded-2xl border border-gray-200 p-8 text-center text-gray-500 font-medium">
                                    No recommended candidates available at the moment. Run seeder to populate data.
                                </div>
                            <?php else: foreach ($recommended_candidates as $cand): ?>
                                <!-- Preview Card -->
                                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden group flex flex-col justify-between">
                                    <div class="p-6">
                                        <div class="flex justify-between items-start mb-6">
                                            <div class="flex items-center gap-4">
                                                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 border border-indigo-150 text-indigo-700 font-black text-lg"><?php echo strtoupper(substr($cand['full_name'], 0, 2)); ?></span>
                                                <div>
                                                    <h4 class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($cand['full_name']); ?></h4>
                                                    <div class="flex items-center gap-1 mt-1">
                                                        <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">Certified</span>
                                                        <span class="text-xs font-black text-gray-900 ml-2">N/A</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-3 mb-6">
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">College</p>
                                                <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($cand['college'] ?: 'Not added'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internship Project</p>
                                                <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($cand['project_title'] ?: 'General Internship'); ?></p>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5">
                                                <?php 
                                                $skills_arr = explode(',', $cand['skills'] ?? '');
                                                foreach (array_slice($skills_arr, 0, 3) as $skill): if (trim($skill)):
                                                ?>
                                                    <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                                <?php endif; endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2 pt-4 border-t border-gray-50">
                                            <div class="flex gap-2">
                                                <button onclick="toggleShortlist(this, <?php echo $cand['user_id']; ?>)" class="flex-1 py-2 rounded-xl text-xs font-bold transition-all shadow-sm <?php echo $cand['is_shortlisted'] ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-gray-900 text-white hover:bg-gray-800'; ?>">
                                                    <?php echo $cand['is_shortlisted'] ? 'Shortlisted' : 'Shortlist'; ?>
                                                </button>
                                                <button onclick="contactCandidate(this, <?php echo $cand['user_id']; ?>)" class="flex-1 bg-white border border-gray-200 text-gray-700 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all <?php echo $cand['is_contacted'] ? 'opacity-50 pointer-events-none' : ''; ?>">
                                                    <?php echo $cand['is_contacted'] ? 'Contacted' : 'Contact Now'; ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-6 py-2 bg-blue-50/30 text-[10px] font-bold text-blue-700 border-t border-blue-50">
                                        Status: <?php echo htmlspecialchars($cand['current_status']); ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar Widgets -->
                <div class="lg:col-span-4 space-y-6">
                    
                    <!-- Quick Actions Widget -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Recruitment Actions</h3>
                        <div class="space-y-2">
                            <button onclick="window.location.href='browse_talent_pool.php'" class="w-full text-left p-3 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-3 group border border-transparent hover:border-blue-100">
                                <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-600">person_search</span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900">Browse Talent Pool</p>
                                    <p class="text-[10px] text-gray-500">View <?php echo $available_count; ?> certified interns</p>
                                </div>
                            </button>
                            <button onclick="window.location.href='browse_talent_pool.php?filter=shortlist'" class="w-full text-left p-3 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-3 group border border-transparent hover:border-blue-100">
                                <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-600">fact_check</span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900">Shortlisted Candidates</p>
                                    <p class="text-[10px] text-gray-500"><?php echo $shortlist_count; ?> profiles awaiting action</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Top Skills Widget -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Top Skills Available</h3>
                        <div class="space-y-4">
                            <?php foreach ($skills_dist as $skill => $count): 
                                $pct = round(($count / $max_skill) * 100);
                            ?>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-xs font-bold text-gray-700">
                                        <span><?php echo htmlspecialchars($skill); ?></span>
                                        <span><?php echo $count; ?></span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-full rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- New Certifications -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">New Certifications</h3>
                            <span class="w-2 h-2 bg-red-500 rounded-full animate-ping"></span>
                        </div>
                        <div class="space-y-4">
                            <?php if (empty($recent_certifications)): ?>
                                <p class="text-xs text-gray-400 font-semibold italic">No recent certifications found.</p>
                            <?php else: foreach ($recent_certifications as $cert): ?>
                                <div class="flex gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-900 leading-tight">
                                            <?php echo htmlspecialchars($cert['full_name']); ?> 
                                            <span class="font-medium text-gray-500">certified with score</span> 
                                            N/A
                                        </p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">verified graduate</p>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                    </div>

                    <!-- Recent System Activity -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm mt-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Recent System Activity</h3>
                            <span class="material-symbols-outlined text-gray-400 text-sm">history</span>
                        </div>
                        <div class="space-y-4">
                            <?php if (empty($activity_logs)): ?>
                                <p class="text-xs text-gray-400 font-semibold italic">No recent platform activity logged.</p>
                            <?php else: foreach ($activity_logs as $log): ?>
                                <div class="flex gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                    <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 shrink-0">
                                        <span class="material-symbols-outlined text-xs">history</span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-700 font-semibold leading-tight">
                                            <span class="font-extrabold text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></span> 
                                            (<?php echo htmlspecialchars(ucfirst($log['user_role'])); ?>)
                                        </p>
                                        <p class="text-[11px] text-gray-500 mt-1 leading-normal"><?php echo htmlspecialchars($log['details']); ?></p>
                                        <p class="text-[9px] text-gray-450 mt-1 font-bold"><?php echo date('M d, g:i A', strtotime($log['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                </div>
            </div>


        </div>

        <!-- Footer -->
        <footer class="max-w-7xl mx-auto mt-16 pt-8 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400 font-medium tracking-tight">© 2026 InternshipHub Enterprise Portal. Talent verified via Internship Management Platform.</p>
        </footer>
    </main>

    <!-- Toast Notification Banner -->
    <div id="toast-banner" class="fixed bottom-5 right-5 z-[999] max-w-sm w-full bg-white border border-green-150 rounded-2xl shadow-2xl p-4 flex gap-3 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="w-10 h-10 rounded-xl bg-green-50 text-green-700 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-black text-slate-800">Success</p>
            <p class="text-xs text-slate-500 mt-1 font-semibold leading-relaxed" id="toast-message">Action processed successfully.</p>
        </div>
    </div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast-banner');
            document.getElementById('toast-message').innerText = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            
            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        async function toggleShortlist(btn, candidateId) {
            const formData = new FormData();
            formData.append('action', 'toggle_shortlist');
            formData.append('candidate_id', candidateId);

            try {
                const res = await fetch('company_dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    if (data.action === 'added') {
                        btn.innerText = 'Shortlisted';
                        btn.classList.remove('bg-gray-900', 'hover:bg-gray-800');
                        btn.classList.add('bg-amber-600', 'hover:bg-amber-700');
                    } else {
                        btn.innerText = 'Shortlist';
                        btn.classList.remove('bg-amber-600', 'hover:bg-amber-700');
                        btn.classList.add('bg-gray-900', 'hover:bg-gray-800');
                    }
                    setTimeout(() => location.reload(), 1000);
                }
            } catch(e) {
                console.error(e);
            }
        }

        async function contactCandidate(btn, candidateId) {
            const message = prompt("Enter invitation message to student:", "We are impressed by your credentials and would love to schedule a interview. Please contact us back!");
            if (message === null) return; // cancelled

            const formData = new FormData();
            formData.append('action', 'contact_candidate');
            formData.append('candidate_id', candidateId);
            formData.append('message', message);

            try {
                const res = await fetch('company_dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    btn.innerText = 'Contacted';
                    btn.classList.add('opacity-50', 'pointer-events-none');
                    setTimeout(() => location.reload(), 1000);
                }
            } catch(e) {
                console.error(e);
            }
        }
        async function markAllNotificationsRead() {
            try {
                const res = await fetch('mark_company_notifications_read.php', {
                    method: 'POST'
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                }
            } catch(e) {
                console.error(e);
            }
        }
    </script>

</body>
</html>
