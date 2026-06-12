<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
require_once __DIR__ . '/../includes/db.php';

$admin_id = intval($_SESSION['user_id']);

$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . $admin_id . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg   = "";

// ── Handle Dropout Approvals and Rejections ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id']);

        // Fetch details of the request
        $req_stmt = $conn->prepare("
            SELECT dr.*, student.id AS student_id, student.full_name AS student_name, mentor.id AS mentor_id, mentor.full_name AS mentor_name, COALESCE(i.title, app.internship_name) AS internship_title
            FROM dropout_requests dr
            JOIN internship_applications app ON dr.application_id = app.id
            JOIN users student ON app.user_id = student.id
            JOIN users mentor ON dr.mentor_id = mentor.id
            LEFT JOIN internships i ON app.internship_id = i.id
            WHERE dr.id = ? LIMIT 1
        ");
        $req_stmt->bind_param('i', $request_id);
        $req_stmt->execute();
        $request = $req_stmt->get_result()->fetch_assoc();
        $req_stmt->close();

        if ($request && $request['status'] === 'Pending') {
            $application_id = intval($request['application_id']);
            $mentor_id = intval($request['mentor_id']);
            $student_name = $request['student_name'];
            $internship_title = $request['internship_title'];

            // Find the team ID for formatting the notification link
            $team_stmt = $conn->prepare("
                SELECT ptm.project_team_id 
                FROM project_team_members ptm 
                JOIN internship_applications a ON ptm.student_id = a.user_id 
                WHERE a.id = ? 
                LIMIT 1
            ");
            $team_stmt->bind_param('i', $application_id);
            $team_stmt->execute();
            $team_row = $team_stmt->get_result()->fetch_assoc();
            $team_id = intval($team_row['project_team_id'] ?? 0);
            $team_stmt->close();

            mysqli_begin_transaction($conn);
            try {
                if ($action === 'approve') {
                    // Update request status to Approved
                    $up_stmt = $conn->prepare("UPDATE dropout_requests SET status = 'Approved' WHERE id = ?");
                    $up_stmt->bind_param('i', $request_id);
                    $up_stmt->execute();
                    $up_stmt->close();

                    // Update student's application status to Dropout
                    $app_up = $conn->prepare("UPDATE internship_applications SET status = 'Dropout' WHERE id = ?");
                    $app_up->bind_param('i', $application_id);
                    $app_up->execute();
                    $app_up->close();

                    // Create notification for mentor
                    $m_title = 'Dropout Request Approved';
                    $m_msg = "Dropout request for student " . $student_name . " on '" . $internship_title . "' has been approved by Admin.";
                    $m_type = 'success';
                    $m_link = $team_id > 0 ? "mentor/students.php?team_id=" . $team_id . "&tab=students" : "mentor/dashboard.php";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'mentor', ?, ?, ?, ?)");
                    $notif_stmt->bind_param('issss', $mentor_id, $m_title, $m_msg, $m_type, $m_link);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    $success_msg = "Successfully approved dropout request for student " . htmlspecialchars($student_name) . ".";
                } elseif ($action === 'reject') {
                    // Update request status to Rejected
                    $up_stmt = $conn->prepare("UPDATE dropout_requests SET status = 'Rejected' WHERE id = ?");
                    $up_stmt->bind_param('i', $request_id);
                    $up_stmt->execute();
                    $up_stmt->close();

                    // Create notification for mentor
                    $m_title = 'Dropout Request Rejected';
                    $m_msg = "Dropout request for student " . $student_name . " on '" . $internship_title . "' has been rejected by Admin.";
                    $m_type = 'error';
                    $m_link = $team_id > 0 ? "mentor/students.php?team_id=" . $team_id . "&tab=students" : "mentor/dashboard.php";

                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'mentor', ?, ?, ?, ?)");
                    $notif_stmt->bind_param('issss', $mentor_id, $m_title, $m_msg, $m_type, $m_link);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    $success_msg = "Successfully rejected dropout request for student " . htmlspecialchars($student_name) . ".";
                }
                mysqli_commit($conn);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = "Database transaction failed: " . $e->getMessage();
            }
        } else {
            $error_msg = "Request not found or not in Pending status.";
        }
    }
}

// ── Fetch Dropout Requests ───────────────────────────────────────────────────
$requests = [];
$res = mysqli_query($conn, "
    SELECT 
        dr.id, 
        dr.application_id, 
        dr.mentor_id, 
        dr.reason, 
        dr.remarks, 
        dr.status, 
        dr.created_at, 
        student.full_name AS student_name, 
        student.email AS student_email,
        mentor.full_name AS mentor_name, 
        mentor.email AS mentor_email,
        COALESCE(i.title, app.internship_name) AS internship_title
    FROM dropout_requests dr
    JOIN internship_applications app ON dr.application_id = app.id
    JOIN users student ON app.user_id = student.id
    JOIN users mentor ON dr.mentor_id = mentor.id
    LEFT JOIN internships i ON app.internship_id = i.id
    ORDER BY dr.created_at DESC, dr.id DESC
");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $requests[] = $row;
    }
}

// Fetch admin header details
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $admin_id");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Student Dropout Requests – IMP</title>
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
          <a href="../logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <?php if (!empty($success_msg)): ?>
          <div class="p-4 bg-green-50 border border-green-200 text-green-800 font-bold rounded-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
          <div class="p-4 bg-red-50 border border-red-200 text-red-800 font-bold rounded-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-red-600">error</span>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
          </div>
        <?php endif; ?>

        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Student Dropout Requests</h1>
            <p class="text-gray-500 text-sm mt-1">Review, approve, or reject dropout requests submitted by mentors.</p>
          </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Student</th>
                  <th class="px-6 py-4">Internship</th>
                  <th class="px-6 py-4">Requested By</th>
                  <th class="px-6 py-4">Reason &amp; Remarks</th>
                  <th class="px-6 py-4">Date Submitted</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($requests)): ?>
                  <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-xs font-semibold">No dropout requests submitted yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($requests as $req): ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4">
                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($req['student_name']); ?></div>
                        <div class="text-xs text-gray-450 mt-0.5"><?php echo htmlspecialchars($req['student_email']); ?></div>
                      </td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($req['internship_title']); ?></td>
                      <td class="px-6 py-4">
                        <div class="font-semibold text-gray-700"><?php echo htmlspecialchars($req['mentor_name']); ?></div>
                        <div class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($req['mentor_email']); ?></div>
                      </td>
                      <td class="px-6 py-4 max-w-xs">
                        <div class="text-gray-900 font-semibold text-xs"><?php echo htmlspecialchars($req['reason']); ?></div>
                        <?php if (!empty($req['remarks'])): ?>
                          <div class="text-xs text-gray-550 mt-1 italic leading-relaxed">"<?php echo htmlspecialchars($req['remarks']); ?>"</div>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 text-gray-400 text-xs font-semibold"><?php echo !empty($req['created_at']) ? date('d M Y h:i A', strtotime($req['created_at'])) : '—'; ?></td>
                      <td class="px-6 py-4">
                        <?php
                          $status_class = "bg-yellow-50 text-yellow-700 border-yellow-100";
                          $display_status = "Pending";
                          if ($req['status'] === 'Approved') {
                              $status_class = "bg-emerald-50 text-emerald-700 border-emerald-100";
                              $display_status = "Approved";
                          } elseif ($req['status'] === 'Rejected') {
                              $status_class = "bg-red-50 text-red-700 border-red-100";
                              $display_status = "Rejected";
                          }
                        ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $status_class; ?> border uppercase tracking-wider"><?php echo $display_status; ?></span>
                      </td>
                      <td class="px-6 py-4 text-right space-x-2">
                        <?php if ($req['status'] === 'Pending'): ?>
                          <div class="flex items-center justify-end gap-3">
                            <form method="POST" action="dropout_requests.php" class="inline" onsubmit="return confirm('Are you sure you want to approve this dropout request?');">
                              <input type="hidden" name="action" value="approve">
                              <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                              <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-bold cursor-pointer transition-colors">Approve</button>
                            </form>
                            <form method="POST" action="dropout_requests.php" class="inline" onsubmit="return confirm('Are you sure you want to reject this dropout request?');">
                              <input type="hidden" name="action" value="reject">
                              <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                              <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-bold cursor-pointer transition-colors">Reject</button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span class="text-gray-400 text-xs font-medium">—</span>
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
  </div>
</body>
</html>
