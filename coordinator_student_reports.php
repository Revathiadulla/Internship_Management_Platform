<?php
/**
 * coordinator_student_reports.php
 * Coordinator interface to review and manage mentor-submitted student reports.
 */

session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";
include_once __DIR__ . '/includes/mail_helper.php';

$success_msg = "";
$error_msg = "";
$coordinator_id = intval($_SESSION['user_id']);

// Ensure columns exist (comments and resolution_remarks)
$chk_comments = $conn->query("SHOW COLUMNS FROM mentor_reports LIKE 'comments'");
if ($chk_comments->num_rows == 0) {
    $conn->query("ALTER TABLE mentor_reports ADD COLUMN comments TEXT DEFAULT NULL");
}
$chk_res = $conn->query("SHOW COLUMNS FROM mentor_reports LIKE 'resolution_remarks'");
if ($chk_res->num_rows == 0) {
    $conn->query("ALTER TABLE mentor_reports ADD COLUMN resolution_remarks TEXT DEFAULT NULL");
}

// Handle Coordinator Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $report_id = intval($_POST['report_id'] ?? 0);

    if ($report_id <= 0) {
        $error_msg = "Invalid report ID.";
    } else {
        // Verify this coordinator has permission (student's internship belongs to this coordinator)
        $perm_sql = "SELECT mr.*, u.full_name as student_name, u.email as student_email,
                            COALESCE(i.title, a.internship_name) as internship_title,
                            i.coordinator_id
                     FROM mentor_reports mr
                     LEFT JOIN users u ON mr.student_id = u.id
                     LEFT JOIN internship_applications a ON mr.application_id = a.id
                     LEFT JOIN internships i ON a.internship_id = i.id
                     WHERE mr.id = ? AND i.coordinator_id = ? LIMIT 1";
        $perm_stmt = $conn->prepare($perm_sql);
        $perm_stmt->bind_param('ii', $report_id, $coordinator_id);
        $perm_stmt->execute();
        $report_data = $perm_stmt->get_result()->fetch_assoc();
        $perm_stmt->close();

        if (!$report_data) {
            $error_msg = "Report not found or unauthorized access.";
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
                    
                    // Notify student
                    $notif_title = "Report Resolved";
                    $notif_msg = "The report submitted by your mentor regarding '$internship_title' has been resolved by Coordinator.";
                    $notif_type = "info";
                    $link = "student_notifications.php";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message, link) VALUES (?, ?, ?, ?, ?)");
                    $notif_stmt->bind_param('issss', $student_id, $notif_title, $notif_type, $notif_msg, $link);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                } else {
                    $error_msg = "Failed to update report: " . $conn->error;
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
                        
                        // Insert warning notification
                        $notif_title = "Official Warning";
                        $notif_type = "Warning";
                        $link = "student_notifications.php";
                        
                        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message, link) VALUES (?, ?, ?, ?, ?)");
                        $notif_stmt->bind_param('issss', $student_id, $notif_title, $notif_type, $warning_message, $link);
                        $notif_stmt->execute();
                        $notif_stmt->close();

                        // Email student warning
                        if (!empty($student_email)) {
                            $email_subject = "Academic Warning: Mentor Report Action Required";
                            $email_body = "Dear $student_name,\n\nYou have received an official warning from your coordinator regarding your performance in '$internship_title'.\n\nWarning Details:\n$warning_message\n\nPlease contact your coordinator immediately to resolve this issue.\n\nBest regards,\nIMP Coordinator Team";
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
$header_name = $header_user['full_name'] ?? 'Coordinator';
$header_photo = $header_user['profile_photo'] ?? '';

// Fetch coordinator notifications info
$notif_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = $coordinator_id AND role = 'coordinator' AND is_read = 0");
$notif_unread_row = mysqli_fetch_assoc($notif_unread_res);
$unread_count = $notif_unread_row['count'] ?? 0;

$notif_latest_res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $coordinator_id AND role = 'coordinator' ORDER BY created_at DESC LIMIT 5");
$latest_notifications = [];
while ($row = mysqli_fetch_assoc($notif_latest_res)) {
    $latest_notifications[] = $row;
}

// Fetch reports for coordinator's assigned students only
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
                WHERE i.coordinator_id = ?
                ORDER BY mr.created_at DESC";
$reports_stmt = $conn->prepare($reports_sql);
$reports_stmt->bind_param('i', $coordinator_id);
$reports_stmt->execute();
$reports_res = $reports_stmt->get_result();
$reports = [];
while ($row = $reports_res->fetch_assoc()) {
    $reports[] = $row;
}
$reports_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Reports - Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#2563eb',
                        'primary-dark': '#1d4ed8',
                        surface: '#f8f9fa',
                        card: '#ffffff',
                    }
                }
            }
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200">

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
        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
        </a>
        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
        </a>
        <a href="coordinator_student_reports.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
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
        <a href="logout.php" class="flex items-center gap-3 text-red-650 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
            <span class="material-symbols-outlined text-[20px] text-red-400">logout</span> Logout
        </a>
    </div>
</aside>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<main class="ml-60 flex flex-col min-h-screen">
    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-gray-800">Student Reports</h2>
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
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=2563eb&color=fff" alt="Profile" class="w-full h-full object-cover">
                    </div>
                </button>
            </div>
        </div>
    </header>

    <div class="flex-1 p-8 space-y-6">
        <div class="max-w-6xl mx-auto space-y-6">
            
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Student Reports</h1>
                <p class="text-gray-500 text-sm mt-1">Review and manage mentor-submitted student reports for your assigned projects</p>
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

            <!-- Reports List -->
            <div class="space-y-4">
                <?php if (empty($reports)): ?>
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                    <span class="material-symbols-outlined text-gray-300 text-6xl">inbox</span>
                    <p class="text-gray-500 mt-2">No student reports found for your assigned projects</p>
                </div>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <!-- Student Info -->
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Student</p>
                                    <p class="text-base font-bold text-gray-900"><?php echo htmlspecialchars($report['student_name']); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($report['student_email']); ?></p>
                                </div>

                                <!-- Internship -->
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Internship</p>
                                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($report['internship_title']); ?></p>
                                </div>

                                <!-- Status -->
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Status</p>
                                    <p class="inline-block px-3 py-1 rounded-full text-xs font-bold
                                        <?php echo ($report['status'] === 'Resolved') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars($report['status']); ?>
                                    </p>
                                </div>

                                <!-- Report Date -->
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Reported Date</p>
                                    <p class="text-sm font-bold text-gray-900"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></p>
                                    <p class="text-xs text-gray-600">By Mentor: <?php echo htmlspecialchars($report['mentor_name']); ?></p>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="border-t pt-6 space-y-4">
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Reason</p>
                                    <p class="text-sm font-bold text-red-700"><?php echo htmlspecialchars($report['reason']); ?></p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Mentor Remarks</p>
                                    <p class="text-sm text-gray-700 bg-slate-50 p-3 rounded"><?php echo htmlspecialchars($report['mentor_remarks'] ?: 'No remarks'); ?></p>
                                </div>

                                <?php if (!empty($report['comments'])): ?>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Coordinator Comments</p>
                                    <p class="text-sm text-gray-800 bg-blue-50/50 p-3 rounded border border-blue-100"><?php echo htmlspecialchars($report['comments']); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($report['status'] === 'Resolved'): ?>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Resolution Remarks</p>
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
    </div>
</main>

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
</script>
</body>
</html>
