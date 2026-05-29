<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";
require_once "includes/mail_helper.php";

$success_msg = "";
$error_msg = "";

// ── Process Notification POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    $notif_title = trim($_POST['notif_title'] ?? '');
    $notif_message = trim($_POST['notif_message'] ?? '');
    $channel = trim($_POST['channel'] ?? 'both'); // in-app, email, both
    $target_type = trim($_POST['target_type'] ?? 'all_students'); // all_students, active_students, team, user
    $priority = trim($_POST['priority'] ?? 'Normal');
    $selected_team = trim($_POST['notif_team'] ?? '');
    $selected_user_id = intval($_POST['notif_user_id'] ?? 0);

    if (empty($notif_title) || empty($notif_message)) {
        $error_msg = "Please fill in both Title and Message.";
    } else {
        // Resolve target user IDs
        $target_users = []; // array of ['id' => int, 'email' => string, 'name' => string]
        
        if ($target_type === 'all_students') {
            $res = mysqli_query($conn, "SELECT id, email, full_name FROM users WHERE role='student'");
            while ($r = mysqli_fetch_assoc($res)) {
                $target_users[] = ['id' => intval($r['id']), 'email' => $r['email'], 'name' => $r['full_name']];
            }
        } elseif ($target_type === 'active_students') {
            $res = mysqli_query($conn, "
                SELECT DISTINCT u.id, u.email, u.full_name 
                FROM users u
                JOIN internship_applications a ON u.id = a.user_id 
                WHERE a.status IN ('Started','Internship Started','Active Intern','Selected')
            ");
            while ($r = mysqli_fetch_assoc($res)) {
                $target_users[] = ['id' => intval($r['id']), 'email' => $r['email'], 'name' => $r['full_name']];
            }
        } elseif ($target_type === 'team' && !empty($selected_team)) {
            $team_stmt = mysqli_prepare($conn, "
                SELECT DISTINCT u.id, u.email, u.full_name 
                FROM users u
                JOIN internship_applications a ON u.id = a.user_id 
                WHERE a.team_name = ?
            ");
            mysqli_stmt_bind_param($team_stmt, "s", $selected_team);
            mysqli_stmt_execute($team_stmt);
            $team_res = mysqli_stmt_get_result($team_stmt);
            while ($r = mysqli_fetch_assoc($team_res)) {
                $target_users[] = ['id' => intval($r['id']), 'email' => $r['email'], 'name' => $r['full_name']];
            }
            mysqli_stmt_close($team_stmt);
        } elseif ($target_type === 'user' && $selected_user_id > 0) {
            $user_res = mysqli_query($conn, "SELECT id, email, full_name FROM users WHERE id = $selected_user_id LIMIT 1");
            if ($user_res && $r = mysqli_fetch_assoc($user_res)) {
                $target_users[] = ['id' => intval($r['id']), 'email' => $r['email'], 'name' => $r['full_name']];
            }
        }

        if (empty($target_users)) {
            $error_msg = "No recipients found matching selected parameters.";
        } else {
            $in_app_count = 0;
            $email_count = 0;

            // Type Label for In-app notification
            $type_label = ($priority === 'Urgent') ? 'Urgent' : (($priority === 'Important') ? 'Important' : 'Notification');
            $full_message = "[" . htmlspecialchars($notif_title) . "] " . htmlspecialchars($notif_message);

            // Execute sending
            foreach ($target_users as $u) {
                // In-App Notification channel
                if ($channel === 'in-app' || $channel === 'both') {
                    $insert_stmt = mysqli_prepare($conn, "INSERT INTO student_notifications (user_id, type, message, is_read) VALUES (?, ?, ?, 0)");
                    mysqli_stmt_bind_param($insert_stmt, "iss", $u['id'], $type_label, $full_message);
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $in_app_count++;
                    }
                    mysqli_stmt_close($insert_stmt);
                }

                // Email Notification channel (triggers SMTP sending and database log logging)
                if ($channel === 'email' || $channel === 'both') {
                    $metadata = [
                        'recipient_name' => $u['name'],
                        'priority' => $priority,
                        'event' => 'Admin System Broadcast'
                    ];
                    // We run non-blocking checks to send email notification
                    if (sendEmailNotification($u['id'], $notif_title, $notif_message, $metadata)) {
                        $email_count++;
                    }
                }
            }

            $success_msg = "Broadcast finished! Sent " . $in_app_count . " in-app notifications and " . $email_count . " HTML emails.";
        }
    }
}

// ── Search, Tabs & Logs Pagination ──
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'in_app';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// History In-App Logs
$in_app_logs = [];
$total_in_app = 0;
if ($active_tab === 'in_app') {
    $count_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM student_notifications");
    $total_in_app = mysqli_fetch_assoc($count_res)['c'] ?? 0;
    
    $logs_res = mysqli_query($conn, "
        SELECT n.id, n.type, n.message, n.is_read, n.created_at, u.full_name as student_name
        FROM student_notifications n
        JOIN users u ON n.user_id = u.id
        ORDER BY n.id DESC LIMIT $limit OFFSET $offset
    ");
    while ($row = mysqli_fetch_assoc($logs_res)) {
        $in_app_logs[] = $row;
    }
}

// History Email Logs
$email_logs = [];
$total_emails = 0;
if ($active_tab === 'email') {
    $count_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM email_notifications_log");
    $total_emails = mysqli_fetch_assoc($count_res)['c'] ?? 0;
    
    $logs_res = mysqli_query($conn, "
        SELECT id, recipient_name, recipient_email, subject, status, sent_at
        FROM email_notifications_log
        ORDER BY id DESC LIMIT $limit OFFSET $offset
    ");
    while ($row = mysqli_fetch_assoc($logs_res)) {
        $email_logs[] = $row;
    }
}

// Fetch lists for modals / dropdown targets
$all_students_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name ASC");
$students_list = [];
while ($row = mysqli_fetch_assoc($all_students_res)) {
    $students_list[] = $row;
}

$all_users_res = mysqli_query($conn, "SELECT id, full_name, role, email FROM users ORDER BY full_name ASC");
$users_list = [];
while ($row = mysqli_fetch_assoc($all_users_res)) {
    $users_list[] = $row;
}

$teams_res = mysqli_query($conn, "SELECT DISTINCT team_name FROM internship_applications WHERE team_name IS NOT NULL AND team_name != '' ORDER BY team_name ASC");
$teams_list = [];
while ($row = mysqli_fetch_assoc($teams_res)) {
    $teams_list[] = $row['team_name'];
}

// Fetch admin details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Platform Notifications – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#003ea8",
            "primary-hover": "#002a75",
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
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">
  <!-- Top Nav -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-8">
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
      <div class="hidden md:flex gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest border-l border-gray-200 pl-6">
        Platform Administration
      </div>
    </div>
    
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-semibold text-slate-700">System Online</span>
      </div>
      
      <!-- Profile Button -->
      <div class="relative">
        <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
          <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
            <?php echo htmlspecialchars($header_name); ?> (Admin)
          </span>
          <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
            <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
              <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
            <?php else: ?>
              <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
        </button>
        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
          <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
          </a>
          <hr class="my-1 border-gray-100">
          <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col justify-between overflow-y-auto shrink-0">
      <div class="space-y-6">
        <div>
          <h2 class="text-[10px] font-bold text-gray-400 tracking-widest mb-4 uppercase">Main Menu</h2>
          <nav class="flex flex-col gap-1">
            <a href="admin_dashboard.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">dashboard</span>
              Dashboard
            </a>
            <a href="admin_users.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">group</span>
              Users
            </a>
            <a href="admin_internships.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">work</span>
              Internships
            </a>
            <a href="admin_applications.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">assignment</span>
              Applications
            </a>
            <a href="admin_projects.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">account_tree</span>
              Projects
            </a>
            <a href="admin_daily_logs.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">monitoring</span>
              Daily Logs
            </a>
            <a href="admin_reports.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">analytics</span>
              Reports
            </a>
            <a href="admin_notifications.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-2.5 rounded-r-lg text-sm font-bold">
              <span class="material-symbols-outlined text-xl">campaign</span>
              Notifications
            </a>
            <a href="admin_talent_pool.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-xl">stars</span>
              Talent Pool
            </a>
          </nav>
        </div>
      </div>
      <div>
        <nav class="flex flex-col gap-1 border-t border-gray-150 pt-4">
          <a href="logout.php" class="flex items-center gap-3 text-red-600 px-4 py-2.5 rounded-lg hover:bg-red-50 text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-xl">logout</span>
            Logout
          </a>
        </nav>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6 flex flex-col lg:flex-row gap-6 items-start">
        
        <!-- Left Column: Form (Col span 1) -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 w-full lg:w-96 shrink-0 space-y-4">
          <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center">
              <span class="material-symbols-outlined text-xl">campaign</span>
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 leading-none">Broadcast Console</h3>
              <p class="text-[10px] text-gray-400 font-bold uppercase mt-1">Send Alert / Email</p>
            </div>
          </div>
          
          <!-- Banners Inside Form Column -->
          <?php if (!empty($success_msg)): ?>
            <div class="p-3 bg-green-50 border border-green-200 text-green-800 text-xs font-bold rounded-lg leading-snug">
              ✓ <?php echo htmlspecialchars($success_msg); ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($error_msg)): ?>
            <div class="p-3 bg-red-50 border border-red-200 text-red-800 text-xs font-bold rounded-lg leading-snug">
              ⚠ <?php echo htmlspecialchars($error_msg); ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="admin_notifications.php" class="space-y-4">
            <input type="hidden" name="action" value="send_broadcast">
            
            <div>
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Subject / Title *</label>
              <input type="text" name="notif_title" required placeholder="e.g. Schedule Update Alert" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            
            <div>
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Message Content *</label>
              <textarea name="notif_message" required rows="4" placeholder="Enter notification markdown or body text..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Channel *</label>
                <select name="channel" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs outline-none cursor-pointer font-bold">
                  <option value="both">In-App & Email</option>
                  <option value="in-app">In-App Alert Only</option>
                  <option value="email">Email Only</option>
                </select>
              </div>
              
              <div>
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Priority *</label>
                <select name="priority" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs outline-none cursor-pointer">
                  <option value="Normal">Normal</option>
                  <option value="Important">Important</option>
                  <option value="Urgent">Urgent</option>
                </select>
              </div>
            </div>

            <div>
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Target Group *</label>
              <select name="target_type" id="target-type" onchange="toggleTargetContainers()" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs outline-none cursor-pointer">
                <option value="all_students">All Students</option>
                <option value="active_students">Active Placements Only</option>
                <option value="team">Project Team Squad</option>
                <option value="user">Specific User</option>
              </select>
            </div>

            <div id="target-team-container" class="hidden">
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Select Project Team *</label>
              <select name="notif_team" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs outline-none cursor-pointer">
                <option value="">Choose a team...</option>
                <?php foreach ($teams_list as $tn): ?>
                  <option value="<?php echo htmlspecialchars($tn); ?>"><?php echo htmlspecialchars($tn); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="target-user-container" class="hidden">
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Select User *</label>
              <select name="notif_user_id" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs outline-none cursor-pointer">
                <option value="">Choose a user...</option>
                <?php foreach ($users_list as $user): ?>
                  <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="w-full bg-[#003ea8] hover:bg-blue-800 text-white font-bold py-2.5 rounded-lg text-xs shadow-sm transition-colors cursor-pointer flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-sm">send</span> Broadcast Notifications
            </button>
          </form>
        </div>

        <!-- Right Column: Tabs & Audit Logs (Col span 1) -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-1 w-full overflow-hidden">
          <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50/50">
            <h2 class="text-base font-bold text-gray-900">Communication Audit Logs</h2>
            
            <div class="flex border border-gray-200 rounded-lg overflow-hidden text-xs">
              <a href="admin_notifications.php?tab=in_app" class="px-3 py-1.5 font-bold transition-all <?php echo $active_tab === 'in_app' ? 'bg-[#003ea8] text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">In-App Alerts</a>
              <a href="admin_notifications.php?tab=email" class="px-3 py-1.5 font-bold transition-all <?php echo $active_tab === 'email' ? 'bg-[#003ea8] text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">SMTP Emails</a>
            </div>
          </div>
          
          <div class="divide-y divide-gray-100">
            <?php if ($active_tab === 'in_app'): ?>
              <!-- In-App table -->
              <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                  <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                    <tr>
                      <th class="px-5 py-4">Recipient</th>
                      <th class="px-5 py-4">Message Content Summary</th>
                      <th class="px-5 py-4">Sent At</th>
                      <th class="px-5 py-4">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 text-xs">
                    <?php if (empty($in_app_logs)): ?>
                      <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-gray-400">No in-app alerts found.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($in_app_logs as $log): ?>
                        <tr class="hover:bg-gray-50/50">
                          <td class="px-5 py-3 font-semibold text-gray-900"><?php echo htmlspecialchars($log['student_name']); ?></td>
                          <td class="px-5 py-3 text-gray-500 truncate max-w-[250px]" title="<?php echo htmlspecialchars($log['message']); ?>"><?php echo htmlspecialchars($log['message']); ?></td>
                          <td class="px-5 py-3 text-gray-400 font-semibold"><?php echo date('h:i A - d M Y', strtotime($log['created_at'])); ?></td>
                          <td class="px-5 py-3">
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $log['is_read'] ? 'bg-green-50 text-green-700 border-green-100' : 'bg-blue-50 text-blue-700 border-blue-100'; ?>">
                              <?php echo $log['is_read'] ? 'Read' : 'Unread'; ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <?php if ($total_in_app > $limit): $total_pages = ceil($total_in_app / $limit); ?>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-xs">
                  <span class="text-gray-500 font-semibold">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                  <div class="flex gap-1.5">
                    <?php if ($page > 1): ?>
                      <a href="admin_notifications.php?tab=in_app&page=<?php echo $page - 1; ?>" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded font-bold hover:bg-gray-50">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                      <a href="admin_notifications.php?tab=in_app&page=<?php echo $page + 1; ?>" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded font-bold hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <!-- Emails table -->
              <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                  <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                    <tr>
                      <th class="px-5 py-4">Recipient</th>
                      <th class="px-5 py-4">Email Subject</th>
                      <th class="px-5 py-4">Sent At</th>
                      <th class="px-5 py-4">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 text-xs">
                    <?php if (empty($email_logs)): ?>
                      <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-gray-400">No outgoing email logs found.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($email_logs as $log): 
                        $status_colors = [
                          'sent' => 'bg-green-50 text-green-700 border-green-100',
                          'failed' => 'bg-red-50 text-red-700 border-red-100'
                        ];
                        $status_cls = $status_colors[strtolower($log['status'])] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                      ?>
                        <tr class="hover:bg-gray-50/50">
                          <td class="px-5 py-3">
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($log['recipient_name']); ?></p>
                            <p class="text-[9px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($log['recipient_email']); ?></p>
                          </td>
                          <td class="px-5 py-3 text-gray-500 truncate max-w-[200px]" title="<?php echo htmlspecialchars($log['subject']); ?>"><?php echo htmlspecialchars($log['subject']); ?></td>
                          <td class="px-5 py-3 text-gray-400 font-semibold"><?php echo date('h:i A - d M Y', strtotime($log['sent_at'])); ?></td>
                          <td class="px-5 py-3">
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_cls; ?>">
                              <?php echo htmlspecialchars($log['status']); ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <?php if ($total_emails > $limit): $total_pages = ceil($total_emails / $limit); ?>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-xs">
                  <span class="text-gray-500 font-semibold">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                  <div class="flex gap-1.5">
                    <?php if ($page > 1): ?>
                      <a href="admin_notifications.php?tab=email&page=<?php echo $page - 1; ?>" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded font-bold hover:bg-gray-50">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                      <a href="admin_notifications.php?tab=email&page=<?php echo $page + 1; ?>" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded font-bold hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script>
    function toggleTargetContainers() {
        const type = document.getElementById('target-type').value;
        document.getElementById('target-team-container').classList.toggle('hidden', type !== 'team');
        document.getElementById('target-user-container').classList.toggle('hidden', type !== 'user');
    }
  </script>
</body>
</html>
