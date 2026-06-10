<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}

include "db.php";
include "ensure_extended_schema.php";
require_once "notification_helpers.php";

$user_id = intval($_SESSION['user_id']);
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $user_id");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Coordinator';
$header_photo = $header_user['profile_photo'] ?? '';

$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'inbox';
if (!in_array($active_tab, ['inbox', 'sent', 'compose'], true)) {
    $active_tab = 'inbox';
}

$notification_error = $_SESSION['notification_error'] ?? '';
$notification_success = $_SESSION['notification_success'] ?? '';
$old_input = $_SESSION['notification_old'] ?? [];
unset($_SESSION['notification_error'], $_SESSION['notification_success'], $_SESSION['notification_old']);

$unread_count = getCoordinatorUnreadCount($conn, $user_id);
$latest_notifications = getCoordinatorLatestNotifications($conn, $user_id, 5);
$inbox_notifications = ($active_tab === 'inbox') ? getInboxNotificationsForCoordinator($conn, $user_id) : [];
$sent_notifications = ($active_tab === 'sent') ? getCoordinatorSentNotificationGroups($conn, $user_id) : [];
$students_list = fetchAllStudents($conn);
$admins_list = fetchAllAdmins($conn);
$internships_list = fetchAssignedInternships($conn);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Notifications - Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        aside { transition: transform 0.3s ease-in-out; }
        main { transition: margin-left 0.3s ease-in-out; min-width: 0; overflow-x: hidden; }
        @media (max-width: 767px) {
            aside { transform: translateX(-100%); }
            main { margin-left: 0 !important; }
            body.sidebar-open aside { transform: translateX(0); }
        }
        @media (min-width: 768px) {
            body.sidebar-closed aside { transform: translateX(-100%); }
            body.sidebar-closed main { margin-left: 0 !important; }
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
            <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
            </a>
            <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">work</span> Postings
            </a>
            <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
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
            <a href="coordinator_notifications.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-bold">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Notifications
            </a>
        </nav>
        <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
            <a href="coordinator_profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
            </a>
            <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">help</span> Help Center
            </a>
            <a href="logout.php" class="flex items-center gap-3 text-red-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
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
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Notifications</h2>
                    <p class="text-gray-500 text-sm">Send new messages and review the latest coordinator notices.</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative" id="notif-dropdown-container">
                    <button id="notif-menu-button" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative focus:outline-none cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-xs text-gray-800">Latest Notifications</span>
                                <a href="coordinator_notifications.php?tab=inbox" class="text-[10px] font-bold text-blue-600 hover:text-blue-800">View all</a>
                            </div>
                        </div>
                        <?php if (empty($latest_notifications)): ?>
                            <div class="px-4 py-3 text-xs text-gray-500">No recent notifications.</div>
                        <?php else: ?>
                            <?php foreach ($latest_notifications as $notif): ?>
                                <a href="coordinator_notifications.php?tab=inbox" class="block px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($notif['title']); ?></span>
                                        <span class="text-[10px] text-gray-400"><?php echo date('M d', strtotime($notif['created_at'])); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="relative" id="profile-container">
                    <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                        <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block"><?php echo htmlspecialchars($header_name); ?></span>
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

        <div class="flex-1 p-8 space-y-6 max-w-6xl w-full mx-auto">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-gray-200 pb-5">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">Coordinator Notifications</h1>
                    <p class="text-gray-500 text-sm mt-1">Send and review messages sent to students and administrators.</p>
                </div>
                <a href="coordinator_notifications.php?tab=compose" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
                    <span class="material-symbols-outlined">add</span> Send Notification
                </a>
            </div>

            <?php if (!empty($notification_success)): ?>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
                    <?php echo htmlspecialchars($notification_success); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($notification_error)): ?>
                <div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                    <?php echo htmlspecialchars($notification_error); ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 overflow-x-auto pb-2">
                <?php
                $tabs = [
                    'inbox' => 'Inbox',
                    'sent' => 'Sent',
                    'compose' => 'Compose'
                ];
                foreach ($tabs as $key => $label):
                    $active_class = ($active_tab === $key) ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50';
                ?>
                    <a href="coordinator_notifications.php?tab=<?php echo $key; ?>" class="px-4 py-2 rounded-full text-sm font-semibold transition-all border border-gray-200 <?php echo $active_class; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($active_tab === 'inbox'): ?>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button id="btn-mark-all-read" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">done_all</span> Mark All Read
                            </button>
                            <button id="btn-clear-read" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">delete_sweep</span> Clear Read
                            </button>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($inbox_notifications); ?> notification<?php echo count($inbox_notifications) !== 1 ? 's' : ''; ?> in inbox</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button data-filter="all" class="filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm">All</button>
                        <button data-filter="unread" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Unread</button>
                        <button data-filter="info" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Info</button>
                        <button data-filter="success" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Success</button>
                        <button data-filter="alert" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Alert</button>
                    </div>
                    <div id="notifications-container" class="space-y-4">
                        <?php if (!empty($inbox_notifications)): ?>
                            <?php foreach ($inbox_notifications as $row):
                                $type = strtolower(trim($row['type'] ?? 'info'));
                                $is_read = intval($row['is_read']) === 1;
                                $icon = 'notifications';
                                $bg_class = 'bg-blue-50 text-blue-600';
                                if ($type === 'success') {
                                    $icon = 'check_circle';
                                    $bg_class = 'bg-green-50 text-green-600';
                                } elseif ($type === 'alert' || $type === 'reminder') {
                                    $icon = 'warning';
                                    $bg_class = 'bg-red-50 text-red-600';
                                }
                            ?>
                                <?php $notif_link = !empty($row['link']) ? $row['link'] : ''; ?>
                                <?php if ($notif_link): ?>
                                <a href="mark_notification_read.php?action=read_redirect&id=<?php echo $row['id']; ?>&fallback=coordinator_notifications.php" class="notification-card block bg-white rounded-2xl border <?php echo $is_read ? 'border-gray-200' : 'border-blue-200 shadow-sm'; ?> p-5 transition-all flex items-start gap-4 hover:border-blue-400 cursor-pointer" data-id="<?php echo $row['id']; ?>" data-type="<?php echo htmlspecialchars(strtolower($type)); ?>" data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                                <?php else: ?>
                                <div class="notification-card bg-white rounded-2xl border <?php echo $is_read ? 'border-gray-200' : 'border-blue-200 shadow-sm'; ?> p-5 transition-all flex items-start gap-4" data-id="<?php echo $row['id']; ?>" data-type="<?php echo htmlspecialchars(strtolower($type)); ?>" data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                                <?php endif; ?>
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo $bg_class; ?>">
                                        <span class="material-symbols-outlined text-[20px]"><?php echo $icon; ?></span>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400"><?php echo htmlspecialchars($row['title']); ?></span>
                                            <?php if (!$is_read): ?>
                                                <span class="new-dot w-2 h-2 bg-blue-600 rounded-full shrink-0 mt-1"></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="font-semibold text-gray-800 text-sm mt-1 leading-relaxed"><?php echo htmlspecialchars($row['message']); ?></p>
                                        <?php if (!empty($row['attachment_path'])): ?>
                                            <div class="mt-3 flex items-center gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100 max-w-fit">
                                                <span class="material-symbols-outlined text-[16px] text-gray-500">attachment</span>
                                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($row['attachment_name']); ?></span>
                                                <span class="text-gray-400"> (<?php echo round($row['attachment_size'] / 1024, 1); ?> KB)</span>
                                                <span class="text-gray-300">|</span>
                                                <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank" class="text-blue-600 font-bold hover:underline">View</a>
                                                <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" download class="text-indigo-600 font-bold hover:underline ml-1">Download</a>
                                            </div>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-gray-400 font-medium flex items-center gap-1 mt-3">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                                            <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!$is_read): ?>
                                        <button class="btn-mark-single-read self-center text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50/50 hover:bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 transition-colors shrink-0 cursor-pointer z-10 relative" onclick="event.preventDefault(); event.stopPropagation();">Mark Read</button>
                                    <?php endif; ?>
                                <?php if ($notif_link): ?>
                                </a>
                                <?php else: ?>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="py-16 text-center bg-white border border-gray-200 rounded-2xl shadow-sm">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="material-symbols-outlined text-gray-300 text-3xl">notifications_off</span>
                                </div>
                                <h3 class="font-bold text-gray-700 mb-1">No Notifications Yet</h3>
                                <p class="text-gray-500 text-sm">We'll alert you as soon as student submissions or review events happen.</p>
                            </div>
                        <?php endif; ?>
                        <div id="no-filtered-notifs" class="hidden py-16 text-center bg-white border border-gray-200 rounded-2xl shadow-sm">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="material-symbols-outlined text-gray-300 text-3xl">filter_list_off</span>
                            </div>
                            <h3 class="font-bold text-gray-700 mb-1">No Notifications Found</h3>
                            <p class="text-gray-500 text-sm">There are no notifications inside the selected category.</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($active_tab === 'sent'): ?>
                <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-5">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Sent Notifications</h2>
                            <p class="text-gray-500 text-sm">Review history, recipient totals, and read status for messages you have sent.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-2 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">
                            <span class="material-symbols-outlined text-[14px]">inventory_2</span>
                            <?php echo count($sent_notifications); ?> batches
                        </span>
                    </div>
                    <?php if (!empty($sent_notifications)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm text-gray-600">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">Sent Date</th>
                                        <th class="px-4 py-3 font-semibold">Title</th>
                                        <th class="px-4 py-3 font-semibold">Type</th>
                                        <th class="px-4 py-3 font-semibold">Recipients</th>
                                        <th class="px-4 py-3 font-semibold">Attachment</th>
                                        <th class="px-4 py-3 font-semibold">Read Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sent_notifications as $batch): ?>
                                        <?php $status = intval($batch['read_count']) === intval($batch['recipient_count']) ? 'Completed' : 'Pending'; ?>
                                        <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($batch['created_at'])); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap"><?php echo htmlspecialchars($batch['title']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap uppercase text-xs font-bold <?php echo htmlspecialchars($batch['type']) === 'success' ? 'text-green-600' : (htmlspecialchars($batch['type']) === 'alert' ? 'text-red-600' : 'text-blue-600'); ?>"><?php echo htmlspecialchars($batch['type']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap"><?php echo intval($batch['recipient_count']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php if (!empty($batch['attachment_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($batch['attachment_path']); ?>" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                                        <span class="material-symbols-outlined text-[14px]">attachment</span>
                                                        <?php echo htmlspecialchars($batch['attachment_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $status === 'Completed' ? 'text-green-600' : 'text-amber-600'; ?>">
                                                <?php echo intval($batch['read_count']); ?>/<?php echo intval($batch['recipient_count']); ?> read • <?php echo $status; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-16 text-center bg-slate-50 border border-gray-200 rounded-2xl">
                            <span class="material-symbols-outlined text-gray-300 text-4xl">mark_email_read</span>
                            <h3 class="font-bold text-gray-700 mt-4">No Sent Notifications</h3>
                            <p class="text-gray-500 text-sm mt-2">Use the Compose tab to send messages to students or admin.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($active_tab === 'compose'): ?>
                <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Compose Notification</h2>
                            <p class="text-gray-500 text-sm">Choose a recipient group and message type to send a notification.</p>
                        </div>
                    </div>
                    <form method="POST" action="send_notification.php" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="action" value="send_notification">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-gray-700">Recipient Type</span>
                                <select id="recipient_type" name="recipient_type" required class="mt-2 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select recipient type</option>
                                    <option value="all_students" <?php echo ($old_input['recipient_type'] ?? '') === 'all_students' ? 'selected' : ''; ?>>All Students</option>
                                    <option value="specific_student" <?php echo ($old_input['recipient_type'] ?? '') === 'specific_student' ? 'selected' : ''; ?>>Specific Student</option>
                                    <option value="students_in_internship" <?php echo ($old_input['recipient_type'] ?? '') === 'students_in_internship' ? 'selected' : ''; ?>>Students in Selected Internship</option>
                                    <option value="admin" <?php echo ($old_input['recipient_type'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </label>
                            <label class="block" id="recipient-container" style="display: none;">
                                <span class="text-sm font-semibold text-gray-700">Recipient</span>
                                <select id="recipient_id" name="recipient_id" class="mt-2 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Choose a recipient</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-gray-700">Notification Title</span>
                                <input type="text" name="notification_title" value="<?php echo htmlspecialchars($old_input['notification_title'] ?? ''); ?>" required class="mt-2 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Enter title" />
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-gray-700">Notification Type</span>
                                <select name="notification_type" required class="mt-2 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="info" <?php echo ($old_input['notification_type'] ?? '') === 'info' ? 'selected' : ''; ?>>Info</option>
                                    <option value="success" <?php echo ($old_input['notification_type'] ?? '') === 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="reminder" <?php echo ($old_input['notification_type'] ?? '') === 'reminder' ? 'selected' : ''; ?>>Reminder</option>
                                    <option value="alert" <?php echo ($old_input['notification_type'] ?? '') === 'alert' ? 'selected' : ''; ?>>Alert</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block">
                                <span class="text-sm font-semibold text-gray-700">Notification Message</span>
                                <textarea name="notification_message" rows="5" required class="mt-2 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Write the notification text here..."><?php echo htmlspecialchars($old_input['notification_message'] ?? ''); ?></textarea>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <label class="block">
                                <span class="text-sm font-semibold text-gray-700">Attachment (Optional)</span>
                                <input type="file" name="attachment" class="mt-2 w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-2xl p-2 bg-white" />
                                <p class="text-xs text-gray-500 mt-1">Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, JPG, JPEG, PNG. Max size: 10 MB.</p>
                            </label>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl text-sm font-semibold transition-all shadow-sm">
                                <span class="material-symbols-outlined">send</span> Send Notification
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileBtn = document.getElementById('profile-menu-button');
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                    }
                });
            }

            const notifBtn = document.getElementById('notif-menu-button');
            const notifDropdown = document.getElementById('notif-dropdown');
            if (notifBtn && notifDropdown) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', (e) => {
                    if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                        notifDropdown.classList.add('hidden');
                    }
                });
            }

            const sidebarToggle = document.getElementById('sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    if (window.innerWidth < 768) {
                        document.body.classList.toggle('sidebar-open');
                        document.body.classList.remove('sidebar-closed');
                    } else {
                        document.body.classList.toggle('sidebar-closed');
                        document.body.classList.remove('sidebar-open');
                    }
                });
            }

            const filterPills = document.querySelectorAll('.filter-pill');
            const cards = document.querySelectorAll('.notification-card');
            const noFilteredNotifs = document.getElementById('no-filtered-notifs');

            filterPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    filterPills.forEach(p => {
                        p.className = 'filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all';
                    });
                    pill.className = 'filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm';

                    const filter = pill.getAttribute('data-filter');
                    let hasMatches = false;

                    cards.forEach(card => {
                        const type = card.getAttribute('data-type') || '';
                        const read = card.getAttribute('data-read') || '';
                        let matches = false;

                        if (filter === 'all') {
                            matches = true;
                        } else if (filter === 'unread') {
                            matches = (read === 'false');
                        } else {
                            matches = (type === filter);
                        }

                        if (matches) {
                            card.style.display = 'flex';
                            hasMatches = true;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    if (noFilteredNotifs) {
                        noFilteredNotifs.classList.toggle('hidden', hasMatches);
                    }
                });
            });

            const singleReadButtons = document.querySelectorAll('.btn-mark-single-read');
            singleReadButtons.forEach(btn => {
                btn.addEventListener('click', async () => {
                    const card = btn.closest('.notification-card');
                    const notifId = card.getAttribute('data-id');
                    try {
                        const response = await fetch(`mark_notification_read.php?action=read&id=${notifId}`);
                        const data = await response.json();
                        if (data.success) {
                            card.setAttribute("data-read", "true");
                            card.className = card.tagName.toLowerCase() === 'a'
                                ? "notification-card block bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4 hover:border-blue-400"
                                : "notification-card bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4";
                            const dot = card.querySelector(".new-dot");
                            if (dot) dot.remove();
                            btn.remove();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (err) {
                        console.error('AJAX Error: ', err);
                    }
                });
            });

            const markAllButton = document.getElementById('btn-mark-all-read');
            if (markAllButton) {
                markAllButton.addEventListener('click', async () => {
                    try {
                        const response = await fetch('mark_notification_read.php?action=read_all');
                        const data = await response.json();
                        if (data.success) {
                            cards.forEach(card => {
                                card.setAttribute("data-read", "true");
                                card.className = card.tagName.toLowerCase() === 'a'
                                    ? "notification-card block bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4 hover:border-blue-400"
                                    : "notification-card bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4";
                                const dot = card.querySelector(".new-dot");
                                if (dot) dot.remove();
                                const btn = card.querySelector(".btn-mark-single-read");
                                if (btn) btn.remove();
                            });
                            markAllButton.remove();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (err) {
                        console.error('AJAX Error: ', err);
                    }
                });
            }

            const clearReadButton = document.getElementById('btn-clear-read');
            if (clearReadButton) {
                clearReadButton.addEventListener('click', async () => {
                    if (!confirm('Are you sure you want to clear all read notifications?')) return;
                    try {
                        const response = await fetch('mark_notification_read.php?action=delete_read');
                        const data = await response.json();
                        if (data.success) {
                            const readCards = document.querySelectorAll('.notification-card[data-read="true"]');
                            readCards.forEach(card => card.remove());
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (err) {
                        console.error('AJAX Error: ', err);
                    }
                });
            }

            const recipientType = document.getElementById('recipient_type');
            const recipientContainer = document.getElementById('recipient-container');
            const recipientSelect = document.getElementById('recipient_id');
            const students = <?php echo json_encode($students_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const admins = <?php echo json_encode($admins_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const internships = <?php echo json_encode($internships_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const oldInput = <?php echo json_encode($old_input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            function refreshRecipientOptions() {
                const typeValue = recipientType.value;
                recipientSelect.innerHTML = '<option value="">Choose a recipient</option>';
                let items = [];

                if (typeValue === 'specific_student') {
                    items = students.map(student => ({ id: student.id, label: student.full_name + ' (' + student.email + ')' }));
                } else if (typeValue === 'students_in_internship') {
                    items = internships.map(item => ({ id: item.id, label: item.title }));
                } else if (typeValue === 'admin') {
                    items = admins.map(admin => ({ id: admin.id, label: admin.full_name + ' (' + admin.email + ')' }));
                }

                if (items.length > 0) {
                    recipientContainer.style.display = 'block';
                    items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.label;
                        if (oldInput.recipient_id && parseInt(oldInput.recipient_id, 10) === item.id) {
                            option.selected = true;
                        }
                        recipientSelect.appendChild(option);
                    });
                } else {
                    recipientContainer.style.display = typeValue === 'specific_student' || typeValue === 'students_in_internship' ? 'block' : 'none';
                }
            }

            if (recipientType && recipientSelect) {
                if (oldInput.recipient_type) {
                    recipientType.value = oldInput.recipient_type;
                }
                recipientType.addEventListener('change', refreshRecipientOptions);
                refreshRecipientOptions();
            }
        });
    </script>
</body>
</html>
