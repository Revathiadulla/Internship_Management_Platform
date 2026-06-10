<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";
include_once "includes/mail_helper.php";

$admin_id = intval($_SESSION['user_id']);

$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . $admin_id . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg   = "";

// ── Handle Approvals and Rejections ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $action  = $_POST['action'];
        $user_id = intval($_POST['user_id']);

        $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            $user_name  = $row['full_name'];
            $user_email = $row['email'];
            $user_role  = $row['role'];

            $base_url   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $login_link = $base_url . '/IMP/login.php';

            if ($action === 'approve') {
                // ── Approve: activate account ──────────────────────────────
                $upd = mysqli_prepare($conn,
                    "UPDATE users
                     SET status = 'approved',
                         is_active = 1,
                         approval_status = 'approved',
                         approved_by = ?,
                         approved_at = NOW()
                     WHERE id = ?");
                mysqli_stmt_bind_param($upd, "ii", $admin_id, $user_id);

                if (mysqli_stmt_execute($upd)) {
                    // Send approval email (non-fatal if it fails)
                    $email_body = "Dear $user_name,\n\nGreat news! Your Internship Management Platform account has been approved by Admin.\n\nYou can now log in using the link below:\n$login_link\n\nPlease complete your profile after logging in.\n\nRegards,\nIMP Admin Team";
                    $emailErr = '';
                    $emailOk  = notifyUser($user_id, $user_role, $user_email,
                        "✅ Your IMP Account Has Been Approved",
                        $email_body,
                        [
                            'event'          => 'Account Approved',
                            'registered_name'=> $user_name,
                            'role'           => ucfirst($user_role),
                            'action_url'     => $login_link,
                            'action_label'   => 'Log In Now',
                        ],
                        'account_approval'
                    );
                    if (!$emailOk) {
                        error_log("Approval email failed for $user_email (user #$user_id): " . $emailErr);
                    }

                    // In-app notification
                    @mysqli_query($conn,
                        "INSERT INTO notifications (user_id, role, title, message, type)
                         VALUES ($user_id, '" . mysqli_real_escape_string($conn, $user_role) . "',
                         'Account Approved',
                         'Your account has been approved by admin. You can now log in.',
                         'success')");

                    $success_msg = "✅ $user_name approved." . ($emailOk ? " Approval email sent." : " (Email delivery failed — check logs.)");
                } else {
                    $error_msg = "Failed to approve user: " . mysqli_error($conn);
                }
                mysqli_stmt_close($upd);

            } elseif ($action === 'reject') {
                // ── Reject: deactivate account ─────────────────────────────
                $upd = mysqli_prepare($conn,
                    "UPDATE users
                     SET status = 'rejected',
                         is_active = 0,
                         approval_status = 'rejected',
                         approved_by = ?
                     WHERE id = ?");
                mysqli_stmt_bind_param($upd, "ii", $admin_id, $user_id);

                if (mysqli_stmt_execute($upd)) {
                    // Send rejection email (non-fatal)
                    $rej_body = "Dear $user_name,\n\nWe regret to inform you that your registration request for the Internship Management Platform has not been approved at this time.\n\nIf you believe this is an error, please contact the administrator.\n\nRegards,\nIMP Admin Team";
                    $emailOk  = notifyUser($user_id, $user_role, $user_email,
                        "❌ IMP Registration Not Approved",
                        $rej_body,
                        [
                            'event'          => 'Registration Rejected',
                            'registered_name'=> $user_name,
                            'role'           => ucfirst($user_role),
                            'action_url'     => $login_link,
                            'action_label'   => 'Contact Admin',
                        ],
                        'account_rejection'
                    );
                    if (!$emailOk) {
                        error_log("Rejection email failed for $user_email (user #$user_id)");
                    }

                    $success_msg = "❌ $user_name rejected." . ($emailOk ? " Rejection email sent." : " (Email delivery failed — check logs.)");
                } else {
                    $error_msg = "Failed to reject user: " . mysqli_error($conn);
                }
                mysqli_stmt_close($upd);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// ── Fetch Pending Users ───────────────────────────────────────────────────────
$pending_users = [];
$res = mysqli_query($conn,
    "SELECT * FROM users
     WHERE status = 'pending_approval'
     ORDER BY registered_date DESC, id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $pending_users[] = $row;
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
    <title>Pending User Approvals – IMP</title>
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
          <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

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
            <h1 class="text-2xl font-bold text-gray-900">Pending User Approvals</h1>
            <p class="text-gray-500 text-sm mt-1">Review and approve accounts for HR, Mentors, and Coordinators.</p>
          </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Name</th>
                  <th class="px-6 py-4">Email</th>
                  <th class="px-6 py-4">Role</th>
                  <th class="px-6 py-4">Registered Date</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($pending_users)): ?>
                  <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-xs">No pending approvals at this time.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pending_users as $user): ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4 font-bold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                      <td class="px-6 py-4 text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                      <td class="px-6 py-4 uppercase font-bold text-xs"><?php echo htmlspecialchars($user['role']); ?></td>
                      <td class="px-6 py-4 text-gray-400 text-xs font-semibold"><?php echo !empty($user['registered_date']) ? date('d M Y h:i A', strtotime($user['registered_date'])) : 'Not Available'; ?></td>
                      <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-yellow-50 text-yellow-700 border border-yellow-100 uppercase tracking-wider">Pending</span>
                      </td>
                      <td class="px-6 py-4 text-right space-x-2 flex justify-end">
                        <form method="POST" action="admin_user_approvals.php" class="inline">
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                          <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-bold cursor-pointer">Approve</button>
                        </form>
                        <form method="POST" action="admin_user_approvals.php" class="inline" onsubmit="return confirm('Are you sure you want to reject this user?');">
                          <input type="hidden" name="action" value="reject">
                          <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                          <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-bold cursor-pointer ml-3">Reject</button>
                        </form>
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
