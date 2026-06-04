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

// Fetch company details
$company_title = 'Nexus Tech';
$plan_selected = null;
$q_prof = mysqli_query($conn, "SELECT company_name, plan_selected FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
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


$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed (CSRF check failed).';
    } elseif (!check_rate_limit('hiring_request_submit', 10, 60)) {
        $error = 'Too many requests. Please wait a minute and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $openings = intval($_POST['openings'] ?? 1);
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');

        if (empty($title) || empty($department) || $openings <= 0) {
            $error = 'Please fill out all required fields.';
        } else {
            // Enforce duplicate check for pending requests
            $dup_check = $conn->prepare("SELECT id FROM hiring_requests WHERE company_id = ? AND title = ? AND department = ? AND status = 'Pending'");
            $dup_check->bind_param("iss", $company_id, $title, $department);
            $dup_check->execute();
            if ($dup_check->get_result()->num_rows > 0) {
                $error = 'You have already submitted a pending hiring request for this role in this department.';
            } else {
                $stmt = $conn->prepare("INSERT INTO hiring_requests (company_id, title, department, openings, description, requirements, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->bind_param("ississ", $company_id, $title, $department, $openings, $description, $requirements);
                if ($stmt->execute()) {
                    $success = 'Hiring request submitted successfully! HR will review it shortly.';
                } else {
                    $error = 'Failed to submit hiring request. Please try again.';
                }
            }
        }
    }
}

// Fetch past hiring requests
$requests = [];
$q_req = mysqli_query($conn, "SELECT * FROM hiring_requests WHERE company_id = $company_id ORDER BY created_at DESC");
if ($q_req) {
    while ($row = mysqli_fetch_assoc($q_req)) {
        $requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Hiring Requests | Company Portal</title>
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
        .form-input { 
            width: 100%; 
            padding: 0.75rem 1rem; 
            border-radius: 0.75rem; 
            border: 1px solid #e2e8f0; 
            background-color: #f8fafc;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #1d4ed8;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.05);
        }
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
            <a href="company_dashboard.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">dashboard</span>
                Hiring Overview
            </a>
            <a href="browse_talent_pool.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">person_search</span>
                Browse Talent Pool
            </a>
            <a href="hiring_requests.php" class="sidebar-link active">
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

            <!-- Title -->
            <div>
                <h2 class="text-3xl font-black text-gray-900 tracking-tight">Hiring Requests</h2>
                <p class="text-gray-500 font-medium mt-1">Submit demand requests for fresh talent to the institution's HR coordinators.</p>
            </div>

            <?php if ($error): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Submit Request Form -->
                <div class="lg:col-span-5 bg-white p-8 rounded-2xl border border-gray-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-gray-900">Create Hiring Request</h3>
                    
                    <form method="POST" action="hiring_requests.php" class="space-y-4">
                        <?php echo csrf_token_field(); ?>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Job / Internship Title *</label>
                            <input type="text" name="title" required placeholder="e.g. Frontend React Intern" class="form-input">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Department / Domain *</label>
                            <select name="department" class="form-input appearance-none">
                                <option>Web Development</option>
                                <option>AI / ML</option>
                                <option>UI / UX</option>
                                <option>Quality Assurance</option>
                                <option>Marketing</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Number of Openings *</label>
                            <input type="number" name="openings" required min="1" value="1" class="form-input">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Job Description</label>
                            <textarea name="description" rows="3" placeholder="Describe the responsibilities and goals..." class="form-input"></textarea>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Technology Stack (comma separated)</label>
                            <textarea name="requirements" rows="2" placeholder="e.g. React.js, TailwindCSS, basic REST APIs..." class="form-input"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-md shadow-blue-100">
                            Submit Hiring Request
                        </button>
                    </form>
                </div>

                <!-- Past Requests List -->
                <div class="lg:col-span-7 bg-white p-8 rounded-2xl border border-gray-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-gray-900">Request History</h3>

                    <?php if (empty($requests)): ?>
                        <div class="flex flex-col items-center justify-center py-12 text-center border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50 p-6">
                            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 mb-4 shadow-inner">
                                <span class="material-symbols-outlined text-3xl">assignment_late</span>
                            </div>
                            <h4 class="text-base font-bold text-slate-800 mb-1">No Hiring Requests</h4>
                            <p class="text-xs text-slate-500 max-w-xs">You haven't submitted any hiring requests to HR yet. Use the form on the left to start hiring talent.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                            <?php foreach ($requests as $req): 
                                $status_color = 'bg-slate-100 text-slate-700 border-slate-200';
                                if ($req['status'] === 'Approved') {
                                    $status_color = 'bg-green-50 text-green-700 border-green-200';
                                } elseif ($req['status'] === 'Rejected') {
                                    $status_color = 'bg-red-50 text-red-700 border-red-200';
                                }
                            ?>
                                <div class="p-5 border border-gray-100 bg-gray-50/50 rounded-2xl flex flex-col sm:flex-row justify-between items-start gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($req['title']); ?></h4>
                                            <span class="text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded"><?php echo htmlspecialchars($req['department']); ?></span>
                                        </div>
                                        <p class="text-xs font-medium text-gray-500"><?php echo htmlspecialchars($req['openings']); ?> Opening(s) · Submitted on <?php echo date('M d, Y', strtotime($req['created_at'])); ?></p>
                                        <?php if ($req['description']): ?>
                                            <p class="text-xs text-gray-600 pt-2 leading-relaxed"><?php echo nl2br(htmlspecialchars($req['description'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($req['requirements']): ?>
                                            <div class="pt-2 text-[10px] text-gray-500">
                                                <span class="font-bold uppercase tracking-wider text-gray-400">Requirements:</span> <?php echo htmlspecialchars($req['requirements']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars($req['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script>
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
