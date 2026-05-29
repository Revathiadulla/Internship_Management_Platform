<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/auth.php';
include_once __DIR__ . '/includes/mail_helper.php';

// Enforce login as company
require_role('company');

$company_id = current_user_id();
$recruiter_name = $_SESSION['full_name'] ?? 'Recruiter';
$recruiter_email = $_SESSION['email'] ?? '';

// Fetch company profile details
$company_title = 'Nexus Tech';
$plan_selected = null;
$q_prof = mysqli_query($conn, "SELECT company_name, plan_selected FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
    $plan_selected = $row['plan_selected'];
}

$upgrade_msg = '';
if (isset($_SESSION['upgrade_message'])) {
    $upgrade_msg = $_SESSION['upgrade_message'];
    unset($_SESSION['upgrade_message']);
}

$is_basic_recommended = ($plan_selected === 'Free' && !empty($upgrade_msg));
$is_premium_recommended = (($plan_selected === 'Free' || $plan_selected === 'Basic') && !empty($upgrade_msg));

$success_msg = '';
$error_msg = '';

// Handle plan activation/upgrade request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_plan'])) {
    $new_plan = trim($_POST['plan'] ?? '');
    
    if (!in_array($new_plan, ['Free', 'Basic', 'Premium'], true)) {
        $error_msg = 'Invalid subscription plan choice.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("UPDATE company_profiles SET plan_selected = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_plan, $company_id);
            $stmt->execute();
            $stmt->close();

            // Insert notification
            $notif_title = "Subscription Plan Activated";
            $notif_message = "Your account has been successfully switched to the " . strtoupper($new_plan) . " Plan.";
            $stmt_notif = $conn->prepare("INSERT INTO company_notifications (company_id, type, title, message) VALUES (?, 'success', ?, ?)");
            $stmt_notif->bind_param("iss", $company_id, $notif_title, $notif_message);
            $stmt_notif->execute();
            $stmt_notif->close();

            // Log activity
            log_activity($conn, 'Subscription Change', "Company \"$company_title\" updated plan from \"" . ($plan_selected ?: 'None') . "\" to \"$new_plan\".");

            mysqli_commit($conn);
            
            // Trigger email notification (outside transactional block to avoid SMTP connection blocking)
            if (function_exists('sendEmailNotification')) {
                $email_subject = "IMP Subscription Activated: " . $new_plan . " Plan";
                $email_body = "Dear $recruiter_name,\n\nYour subscription to the $new_plan Plan has been successfully activated for $company_title on the Internship Management Platform (IMP).\n\n" . 
                              ($new_plan === 'Free' ? "You can now view details of up to 10 verified candidates." : 
                              ($new_plan === 'Basic' ? "You can now view details of up to 75 candidates and utilize advanced filters." : 
                              "You now have unlimited candidate access, advanced stack filters, and direct contact options."));
                sendEmailNotification($recruiter_email, $email_subject, $email_body, [
                    'event' => 'Subscription Update',
                    'company_name' => $company_title,
                    'new_plan' => $new_plan,
                    'status' => 'Active',
                    'action_url' => 'http://localhost/IMP/browse_talent_pool.php',
                    'action_label' => 'Explore Talent Pool'
                ]);
            }
            
            $plan_selected = $new_plan;
            $success_msg = "Your subscription has been successfully updated to the $new_plan Plan!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Failed to update subscription: " . $e->getMessage();
        }
    }
}

// Fetch current viewed candidate count
$q_views = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM company_views WHERE company_id = $company_id");
$views_row = mysqli_fetch_assoc($q_views);
$views_count = intval($views_row['cnt'] ?? 0);

// Get unread notifications
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
    $notif_res = $notif_list_stmt->get_result();
    while ($n_row = $notif_res->fetch_assoc()) {
        $notifications[] = $n_row;
    }
    $notif_list_stmt->close();
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>My Subscription | Company Portal</title>
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
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #64748b; }
        .sidebar-link:hover { background-color: #f1f5f9; color: #1d4ed8; }
        .sidebar-link.active { background-color: #1d4ed8; color: #ffffff; box-shadow: 0 4px 6px -1px rgb(29 78 216 / 0.1), 0 2px 4px -2px rgb(29 78 216 / 0.1); }
        .plan-card {
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }
        .plan-card.active {
            border-color: #1d4ed8;
            background-color: #f0f7ff;
            box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.1);
        }
        .plan-card.recommended-upgrade {
            border-color: #f59e0b; /* amber-500 */
            box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.15), 0 8px 10px -6px rgba(245, 158, 11, 0.15);
            transform: scale(1.02);
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
            <a href="hiring_requests.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">handshake</span>
                Hiring Requests
            </a>
            <a href="company_subscription.php" class="sidebar-link active">
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
                        <div class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                                <h4 class="font-bold text-slate-800 text-sm">Notifications</h4>
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
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($recruiter_name); ?>&background=0D8ABC&color=fff" alt="Avatar" class="w-9 h-9 rounded-full border">
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            <?php if ($success_msg !== ''): ?>
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error_msg !== ''): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($upgrade_msg)): ?>
                <div class="p-5 bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl space-y-2 flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-600 text-2xl shrink-0 mt-0.5">upgrade</span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-800 tracking-tight">Subscription Action Required</h4>
                        <p class="text-xs font-semibold text-amber-700 mt-1 leading-relaxed"><?php echo htmlspecialchars($upgrade_msg); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($plan_selected)): ?>
                <!-- Gating Notice -->
                <div class="p-6 bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600 text-2xl">warning</span>
                        <h4 class="text-base font-bold">Subscription Plan Required</h4>
                    </div>
                    <p class="text-sm">Welcome to IMP Recruitment Hub! To begin searching the talent pool, posting jobs, and reviewing candidate credentials, you must first select a subscription tier below. The <strong>Free Plan</strong> is available to start immediately.</p>
                </div>
            <?php endif; ?>

            <!-- Subscription Header Info -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-lg font-black text-gray-900 tracking-tight">Active Plan Overview</h3>
                    <p class="text-xs text-gray-500 font-medium mt-1">Review your subscription metrics and account permissions.</p>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-left">
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Current Tier</span>
                        <p class="text-sm font-extrabold text-blue-600 uppercase mt-0.5"><?php echo $plan_selected ? htmlspecialchars($plan_selected) : 'No Plan Selected'; ?></p>
                    </div>
                    <div class="h-8 w-px bg-gray-150"></div>
                    <div class="text-left">
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Candidate Views Used</span>
                        <p class="text-sm font-extrabold text-gray-800 mt-0.5">
                            <?php 
                            $max = ($plan_selected === 'Free') ? 10 : (($plan_selected === 'Basic') ? 75 : 'Unlimited');
                            echo $views_count . ' / ' . $max;
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Plans Grid -->
            <div>
                <h3 class="text-xl font-black text-gray-950 tracking-tight mb-6">Choose Your Plan</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Free Plan -->
                    <div class="plan-card p-6 bg-white rounded-2xl flex flex-col <?php echo ($plan_selected === 'Free') ? 'active' : ''; ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Free Plan</h4>
                                <p class="text-3xl font-black text-gray-900 mt-2">$0 <span class="text-xs text-gray-400 font-semibold">/mo</span></p>
                            </div>
                            <?php if ($plan_selected === 'Free'): ?>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Active</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 font-medium mb-6 leading-relaxed">Perfect for exploring the talent pool and testing system functionality.</p>
                        
                        <ul class="space-y-3 mb-8 flex-1">
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                View details of up to 10 candidates
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                Browse and read basic accomplishments
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-300 font-medium line-through">
                                <span class="material-symbols-outlined text-sm">close</span>
                                Advanced tech-stack filters
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-300 font-medium line-through">
                                <span class="material-symbols-outlined text-sm">close</span>
                                Direct candidate contact system
                            </li>
                        </ul>
                        
                        <form method="POST" action="company_subscription.php">
                            <input type="hidden" name="plan" value="Free">
                            <button type="submit" name="select_plan" class="w-full py-3 rounded-xl border border-gray-200 text-xs font-bold hover:bg-gray-50 transition-all <?php echo ($plan_selected === 'Free') ? 'bg-blue-600 text-white border-none cursor-default pointer-events-none' : 'bg-white text-gray-800 cursor-pointer'; ?>" <?php echo ($plan_selected === 'Free') ? 'disabled' : ''; ?>>
                                <?php echo ($plan_selected === 'Free') ? 'Currently Active' : 'Select Free Plan'; ?>
                            </button>
                        </form>
                    </div>

                    <!-- Basic Plan -->
                    <div class="plan-card p-6 bg-white rounded-2xl flex flex-col relative <?php echo ($plan_selected === 'Basic') ? 'active' : ''; ?> <?php echo $is_basic_recommended ? 'recommended-upgrade' : ''; ?>">
                        <?php if ($is_basic_recommended): ?>
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-lg animate-pulse">Recommended Upgrade</div>
                        <?php else: ?>
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[9px] font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-lg">Most Popular</div>
                        <?php endif; ?>
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Basic Plan</h4>
                                <p class="text-3xl font-black text-gray-900 mt-2">$199 <span class="text-xs text-gray-400 font-semibold">/mo</span></p>
                            </div>
                            <?php if ($plan_selected === 'Basic'): ?>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Active</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 font-medium mb-6 leading-relaxed">Designed for growing companies with monthly internship positions.</p>
                        
                        <ul class="space-y-3 mb-8 flex-1">
                            <li class="flex items-center gap-2.5 text-xs text-gray-700 font-bold">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check_circle</span>
                                View details of up to 75 candidates
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-700 font-bold">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check_circle</span>
                                Advanced performance score filters
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-700 font-bold">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check_circle</span>
                                Direct recruiter contact invitations
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-300 font-medium line-through">
                                <span class="material-symbols-outlined text-sm">close</span>
                                Tech stack advanced search (Premium)
                            </li>
                        </ul>
                        
                        <form method="POST" action="company_subscription.php">
                            <input type="hidden" name="plan" value="Basic">
                            <button type="submit" name="select_plan" class="w-full py-3 rounded-xl text-xs font-bold transition-all shadow-md <?php echo ($plan_selected === 'Basic') ? 'bg-blue-600 text-white border-none cursor-default pointer-events-none' : 'bg-gray-900 text-white hover:bg-black cursor-pointer'; ?>" <?php echo ($plan_selected === 'Basic') ? 'disabled' : ''; ?>>
                                <?php echo ($plan_selected === 'Basic') ? 'Currently Active' : (($plan_selected === 'Premium') ? 'Downgrade to Basic' : 'Upgrade to Basic'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- Premium Plan -->
                    <div class="plan-card p-6 bg-white rounded-2xl flex flex-col relative <?php echo ($plan_selected === 'Premium') ? 'active' : ''; ?> <?php echo $is_premium_recommended ? 'recommended-upgrade' : ''; ?>">
                        <?php if ($is_premium_recommended): ?>
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-lg animate-pulse">Recommended Upgrade</div>
                        <?php endif; ?>
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Premium Plan</h4>
                                <p class="text-3xl font-black text-gray-900 mt-2">$499 <span class="text-xs text-gray-400 font-semibold">/mo</span></p>
                            </div>
                            <?php if ($plan_selected === 'Premium'): ?>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Active</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 font-medium mb-6 leading-relaxed">Full access for enterprises and recruitment agencies with high volume needs.</p>
                        
                        <ul class="space-y-3 mb-8 flex-1">
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                **Unlimited** candidate profile details
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                Advanced tech stack & domain search
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                Direct candidate contact invitations
                            </li>
                            <li class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                                <span class="material-symbols-outlined text-blue-600 text-sm">check</span>
                                Dedicated 24/7 account support
                            </li>
                        </ul>
                        
                        <form method="POST" action="company_subscription.php">
                            <input type="hidden" name="plan" value="Premium">
                            <button type="submit" name="select_plan" class="w-full py-3 rounded-xl border border-gray-200 text-xs font-bold hover:bg-gray-50 transition-all <?php echo ($plan_selected === 'Premium') ? 'bg-blue-600 text-white border-none cursor-default pointer-events-none' : 'bg-white text-gray-800 cursor-pointer'; ?>" <?php echo ($plan_selected === 'Premium') ? 'disabled' : ''; ?>>
                                <?php echo ($plan_selected === 'Premium') ? 'Currently Active' : 'Upgrade to Premium'; ?>
                            </button>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </main>

</body>
</html>
