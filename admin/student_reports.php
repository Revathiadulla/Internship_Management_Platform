<?php
/**
 * student_reports.php
 * Admin interface to review and manage mentor-submitted student reports.
 */

session_start();
die("Student Reports module has been disabled.");
include_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/mail_helper.php';

$success_msg = "";
$error_msg = "";

// Ensure columns exist (comments and resolution_remarks)
$chk_comments = $conn->query("SHOW COLUMNS FROM mentor_reports LIKE 'comments'");
if ($chk_comments->num_rows == 0) {
    $conn->query("ALTER TABLE mentor_reports ADD COLUMN comments TEXT DEFAULT NULL");
}
$chk_res = $conn->query("SHOW COLUMNS FROM mentor_reports LIKE 'resolution_remarks'");
if ($chk_res->num_rows == 0) {
    $conn->query("ALTER TABLE mentor_reports ADD COLUMN resolution_remarks TEXT DEFAULT NULL");
}

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $report_id = intval($_POST['report_id'] ?? 0);
    $admin_id = intval($_SESSION['user_id']);

    if ($report_id <= 0) {
        $error_msg = "Invalid report ID.";
    } else {
        // Fetch report details first to get student_id, application_id, etc.
        $rep_sql = "SELECT mr.*, u.full_name as student_name, u.email as student_email,
                           COALESCE(i.title, a.internship_name) as internship_title
                    FROM mentor_reports mr
                    LEFT JOIN users u ON mr.student_id = u.id
                    LEFT JOIN internship_applications a ON mr.application_id = a.id
                    LEFT JOIN internships i ON a.internship_id = i.id
                    WHERE mr.id = ? LIMIT 1";
        $rep_stmt = $conn->prepare($rep_sql);
        $rep_stmt->bind_param('i', $report_id);
        $rep_stmt->execute();
        $report_data = $rep_stmt->get_result()->fetch_assoc();
        $rep_stmt->close();

        if (!$report_data) {
            $error_msg = "Report not found.";
        } else {
            $student_id = intval($report_data['student_id']);
            $student_name = $report_data['student_name'] ?? 'Student';
            $student_email = $report_data['student_email'] ?? '';
            $internship_title = $report_data['internship_title'] ?? 'Internship';

            if ($action === 'mark_resolved') {
                $resolution_remarks = trim($_POST['resolution_remarks'] ?? '');
                
                $update_sql = "UPDATE mentor_reports SET status = 'Resolved', resolution_remarks = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('si', $resolution_remarks, $report_id);
                if ($update_stmt->execute()) {
                    $success_msg = "Report marked as Resolved successfully.";
                    
                    // Also notify student that the report has been resolved
                    $notif_title = "Report Resolved";
                    $notif_msg = "The report submitted by your mentor regarding '$internship_title' has been resolved by Admin.";
                    $notif_type = "info";
                    $link = "student_notifications.php";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message, link) VALUES (?, ?, ?, ?, ?)");
                    $notif_stmt->bind_param('issss', $student_id, $notif_title, $notif_type, $notif_msg, $link);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                } else {
                    $error_msg = "Failed to update report status: " . $conn->error;
                }
                $update_stmt->close();

            } elseif ($action === 'send_warning') {
                $warning_message = trim($_POST['warning_message'] ?? '');
                if (empty($warning_message)) {
                    $error_msg = "Warning message cannot be empty.";
                } else {
                    $update_sql = "UPDATE mentor_reports SET warning_sent = 1 WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param('i', $report_id);
                    if ($update_stmt->execute()) {
                        $success_msg = "Warning notification sent to student successfully.";
                        
                        // Insert into student notifications
                        $notif_title = "Official Warning";
                        $notif_type = "Warning";
                        $link = "student_notifications.php";
                        
                        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message, link) VALUES (?, ?, ?, ?, ?)");
                        $notif_stmt->bind_param('issss', $student_id, $notif_title, $notif_type, $warning_message, $link);
                        $notif_stmt->execute();
                        $notif_stmt->close();

                        // Try sending email warning if helper is available
                        if (!empty($student_email)) {
                            $email_subject = "Academic Warning: Mentor Report Action Required";
                            $email_body = "Dear $student_name,\n\nYou have received an official warning regarding your performance in '$internship_title'.\n\nWarning Details:\n$warning_message\n\nPlease contact your coordinator immediately to resolve this issue.\n\nBest regards,\nIMP Admin Team";
                            @sendStudentNotification($student_id, $student_name, $email_subject, $email_body, [
                                'event' => 'Official Warning',
                                'internship' => $internship_title,
                                'warning_message' => $warning_message,
                                'action_url' => 'http://localhost/IMP/student_dashboard.php',
                                'action_label' => 'View Dashboard'
                            ]);
                        }
                    } else {
                        $error_msg = "Failed to update warning status: " . $conn->error;
                    }
                    $update_stmt->close();
                }

            } elseif ($action === 'add_comment') {
                $comment = trim($_POST['comment'] ?? '');
                
                $update_sql = "UPDATE mentor_reports SET comments = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('si', $comment, $report_id);
                if ($update_stmt->execute()) {
                    $success_msg = "Comment added successfully.";
                } else {
                    $error_msg = "Failed to add comment: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
    }
}

// Fetch header user info
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';

// Fetch admin notifications info
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$admin_latest_res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' ORDER BY created_at DESC LIMIT 5");
$admin_latest_notifications = [];
if ($admin_latest_res) {
    while ($row = mysqli_fetch_assoc($admin_latest_res)) {
        $admin_latest_notifications[] = $row;
    }
}

// Fetch all mentor reports
$reports_sql = "SELECT mr.id, mr.student_id, mr.mentor_id, mr.application_id, mr.reason, mr.remarks as mentor_remarks, 
                       mr.status, mr.warning_sent, mr.comments, mr.resolution_remarks, mr.created_at,
                       u.full_name as student_name, u.email as student_email,
                       m.full_name as mentor_name,
                       COALESCE(i.title, a.internship_name) as internship_title
                FROM mentor_reports mr
                LEFT JOIN users u ON mr.student_id = u.id
                LEFT JOIN users m ON mr.mentor_id = m.id
                LEFT JOIN internship_applications a ON mr.application_id = a.id
                LEFT JOIN internships i ON a.internship_id = i.id
                ORDER BY mr.created_at DESC";
$reports_res = mysqli_query($conn, $reports_sql);
$reports = [];
if ($reports_res) {
    while ($row = mysqli_fetch_assoc($reports_res)) {
        $reports[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Student Reports – IMP Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
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
    };
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
      .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; }
      .modal.show { display: flex; }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200">
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

      <!-- Theme Switcher -->
      <button id="theme-toggle" class="p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors rounded-full flex items-center justify-center cursor-pointer">
          <span class="material-symbols-outlined text-[20px]" id="theme-toggle-icon">dark_mode</span>
      </button>

      <!-- Notifications Bell -->
      <div class="relative mr-1" id="notifications-container-menu">
          <button id="notifications-menu-button" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative focus:outline-none cursor-pointer flex items-center justify-center">
              <span class="material-symbols-outlined">notifications</span>
              <?php if ($admin_unread_count > 0): ?>
                  <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $admin_unread_count; ?></span>
              <?php endif; ?>
          </button>
          <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
              <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                  <span class="font-bold text-xs text-gray-800">Notifications</span>
              </div>
              <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                  <?php if (empty($admin_latest_notifications)): ?>
                      <div class="px-4 py-3 text-center text-xs text-gray-400">No notifications.</div>
                  <?php else: ?>
                      <?php foreach ($admin_latest_notifications as $notif): ?>
                          <a href="/IMP/admin/notifications.php" class="block px-4 py-2.5 hover:bg-gray-50 transition-colors">
                              <p class="text-xs text-gray-700 font-medium truncate"><?php echo htmlspecialchars($notif['message']); ?></p>
                          </a>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
          </div>
      </div>
      
      <!-- Profile Button -->
      <div class="relative">
        <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
          <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
            <?php echo htmlspecialchars($header_name); ?> (Admin)
          </span>
          <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
          </div>
        </button>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Student Reports</h1>
            <p class="text-gray-500 text-sm mt-1">Review, add comments, resolve and warn student reports submitted by mentors</p>
          </div>
        </div>

        <?php if ($success_msg): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg">
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
        <?php endif; ?>

        <!-- Reports Grid -->
        <div class="space-y-4">
            <?php if (empty($reports)): ?>
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <span class="material-symbols-outlined text-slate-300 text-6xl">inbox</span>
                <p class="text-slate-500 mt-2">No student reports found</p>
            </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <!-- Student Info -->
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Student</p>
                                <p class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($report['student_name']); ?></p>
                                <p class="text-xs text-slate-600"><?php echo htmlspecialchars($report['student_email']); ?></p>
                            </div>

                            <!-- Internship -->
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Internship</p>
                                <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($report['internship_title']); ?></p>
                            </div>

                            <!-- Status -->
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Status</p>
                                <p class="inline-block px-3 py-1 rounded-full text-xs font-bold
                                    <?php echo ($report['status'] === 'Resolved') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars($report['status']); ?>
                                </p>
                            </div>

                            <!-- Report Date -->
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Reported Date</p>
                                <p class="text-sm font-bold text-slate-900"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></p>
                                <p class="text-xs text-slate-600">By Mentor: <?php echo htmlspecialchars($report['mentor_name']); ?></p>
                            </div>
                        </div>

                        <!-- Report Details -->
                        <div class="border-t pt-6 space-y-4">
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Reason</p>
                                <p class="text-sm font-bold text-red-700"><?php echo htmlspecialchars($report['reason']); ?></p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Mentor Remarks</p>
                                <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded"><?php echo htmlspecialchars($report['mentor_remarks'] ?: 'No remarks'); ?></p>
                            </div>

                            <?php if (!empty($report['comments'])): ?>
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Admin/Coordinator Comments</p>
                                <p class="text-sm text-slate-800 bg-blue-50/50 p-3 rounded border border-blue-100"><?php echo htmlspecialchars($report['comments']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($report['status'] === 'Resolved'): ?>
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Resolution Remarks</p>
                                <p class="text-sm text-emerald-800 bg-emerald-50 p-3 rounded border border-emerald-100"><?php echo htmlspecialchars($report['resolution_remarks'] ?: 'No resolution remarks provided.'); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="flex flex-wrap gap-2 pt-2">
                                <button onclick="openCommentModal(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars(json_encode($report['comments'] ?? '')); ?>')"
                                        class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-750 transition text-xs flex items-center gap-1.5 shadow-sm">
                                    <span class="material-symbols-outlined text-sm">comment</span> Add/Edit Comment
                                </button>

                                <?php if ($report['status'] !== 'Resolved'): ?>
                                <button onclick="openResolveModal(<?php echo $report['id']; ?>)"
                                        class="px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition text-xs flex items-center gap-1.5 shadow-sm">
                                    <span class="material-symbols-outlined text-sm">check_circle</span> Mark Resolved
                                </button>
                                <?php endif; ?>

                                <button onclick="openWarningModal(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['student_name']); ?>')"
                                        class="px-4 py-2 bg-amber-600 text-white font-semibold rounded-lg hover:bg-amber-700 transition text-xs flex items-center gap-1.5 shadow-sm">
                                    <span class="material-symbols-outlined text-sm">warning</span> Send Warning to Student
                                    <?php if ($report['warning_sent']): ?>
                                    <span class="ml-1 bg-amber-800 text-white px-1.5 py-0.25 rounded-full text-[9px]">Sent</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Comment Modal -->
  <div id="commentModal" class="modal items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="bg-slate-900 text-white p-6 flex items-center justify-between">
            <h2 class="text-xl font-bold">Add/Edit Comment</h2>
            <button onclick="closeModals()" class="text-slate-300 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_comment">
            <input type="hidden" name="report_id" id="commentReportId" value="">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Comment</label>
                <textarea name="comment" id="commentText" rows="4" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Add custom notes or comments..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">Save Comment</button>
                <button type="button" onclick="closeModals()" class="flex-1 px-4 py-2 bg-slate-200 text-slate-800 font-semibold rounded-lg hover:bg-slate-300 transition">Cancel</button>
            </div>
        </form>
    </div>
  </div>

  <!-- Resolve Modal -->
  <div id="resolveModal" class="modal items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="bg-slate-900 text-white p-6 flex items-center justify-between">
            <h2 class="text-xl font-bold">Mark Report as Resolved</h2>
            <button onclick="closeModals()" class="text-slate-300 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="mark_resolved">
            <input type="hidden" name="report_id" id="resolveReportId" value="">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Resolution Remarks</label>
                <textarea name="resolution_remarks" rows="4" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Provide details on how the issue was resolved..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition">Confirm Resolve</button>
                <button type="button" onclick="closeModals()" class="flex-1 px-4 py-2 bg-slate-200 text-slate-800 font-semibold rounded-lg hover:bg-slate-300 transition">Cancel</button>
            </div>
        </form>
    </div>
  </div>

  <!-- Warning Modal -->
  <div id="warningModal" class="modal items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="bg-slate-900 text-white p-6 flex items-center justify-between">
            <h2 class="text-xl font-bold">Send Warning to Student</h2>
            <button onclick="closeModals()" class="text-slate-300 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="send_warning">
            <input type="hidden" name="report_id" id="warningReportId" value="">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Student Name</label>
                <p class="text-sm font-bold text-slate-900" id="warningStudentName"></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Warning Message</label>
                <textarea name="warning_message" rows="4" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Type warning description that will be visible to student..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-amber-600 text-white font-semibold rounded-lg hover:bg-amber-700 transition">Send Warning</button>
                <button type="button" onclick="closeModals()" class="flex-1 px-4 py-2 bg-slate-200 text-slate-800 font-semibold rounded-lg hover:bg-slate-300 transition">Cancel</button>
            </div>
        </form>
    </div>
  </div>

  <script>
  function openCommentModal(reportId, existingCommentJson) {
      document.getElementById('commentReportId').value = reportId;
      try {
          const val = JSON.parse(existingCommentJson);
          document.getElementById('commentText').value = val || '';
      } catch (e) {
          document.getElementById('commentText').value = '';
      }
      document.getElementById('commentModal').classList.add('show');
  }

  function openResolveModal(reportId) {
      document.getElementById('resolveReportId').value = reportId;
      document.getElementById('resolveModal').classList.add('show');
  }

  function openWarningModal(reportId, studentName) {
      document.getElementById('warningReportId').value = reportId;
      document.getElementById('warningStudentName').textContent = studentName;
      document.getElementById('warningModal').classList.add('show');
  }

  function closeModals() {
      document.getElementById('commentModal').classList.remove('show');
      document.getElementById('resolveModal').classList.remove('show');
      document.getElementById('warningModal').classList.remove('show');
  }

  // Profile Dropdown toggle
  document.getElementById('profile-menu-button')?.addEventListener('click', function(e) {
      e.stopPropagation();
  });
  </script>
  <script src="js/alerts.js"></script>
</body>
</html>
