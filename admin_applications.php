<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg = "";

// ── Update Application Status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $app_id = intval($_POST['application_id']);
    $new_status = trim($_POST['status']);
    $notes = trim($_POST['notes']);
    $admin_name = $_SESSION['full_name'] ?? 'Admin';

    if ($app_id <= 0 || empty($new_status)) {
        $error_msg = "Invalid inputs for status update.";
    } else {
        // Fetch current status to log history
        $chk_stmt = mysqli_prepare($conn, "SELECT status FROM internship_applications WHERE id = ?");
        mysqli_stmt_bind_param($chk_stmt, "i", $app_id);
        mysqli_stmt_execute($chk_stmt);
        mysqli_stmt_bind_result($chk_stmt, $old_status);
        mysqli_stmt_fetch($chk_stmt);
        mysqli_stmt_close($chk_stmt);

        mysqli_begin_transaction($conn);
        $success = true;

        // Update status
        $upd_stmt = mysqli_prepare($conn, "UPDATE internship_applications SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "si", $new_status, $app_id);
        if (!mysqli_stmt_execute($upd_stmt)) {
            $success = false;
        }
        mysqli_stmt_close($upd_stmt);

        if ($success) {
            // Log history
            $hist_stmt = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, 'admin', ?, ?)");
            mysqli_stmt_bind_param($hist_stmt, "issss", $app_id, $old_status, $new_status, $admin_name, $notes);
            if (!mysqli_stmt_execute($hist_stmt)) {
                $success = false;
            }
            mysqli_stmt_close($hist_stmt);
        }

        if ($success) {
            mysqli_commit($conn);
            // Talent pool logic for 'Selected' status
            if ($new_status === 'Selected') {
                $tp_check_sql = "SELECT id, user_id FROM internship_applications WHERE id = $app_id LIMIT 1";
                $tp_check = mysqli_query($conn, $tp_check_sql);
                if ($tp_check && $tp_check_row = mysqli_fetch_assoc($tp_check)) {
                    $student_user_id_check = intval($tp_check_row['user_id']);
                    // Check if this student already has a talent pool entry
                    $dup_check = mysqli_query($conn, "SELECT id FROM internship_applications WHERE user_id = $student_user_id_check AND in_talent_pool = 1 AND id != $app_id LIMIT 1");
                    if ($dup_check && mysqli_num_rows($dup_check) > 0) {
                        // Prevent duplicate talent pool entries
                        mysqli_query($conn, "UPDATE internship_applications SET talent_pool_status = 'Yes', in_talent_pool = 0 WHERE id = $app_id");
                    } else {
                        // Add to talent pool
                        mysqli_query($conn, "UPDATE internship_applications SET in_talent_pool = 1, talent_pool_status = 'Yes' WHERE id = $app_id");
                    }
                }
            }
            $success_msg = "Application status updated to '" . htmlspecialchars($new_status) . "' successfully!";
        } else {
            mysqli_rollback($conn);
            $error_msg = "Failed to update status. Database transaction error.";
        }
    }
}

// ── AJAX: GET APPLICATION DETAILS ──
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $has_resume_url = false;
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'resume_url'");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        $has_resume_url = true;
    }
    $resume_url_select = $has_resume_url ? "sp.resume_url" : "NULL";

    $details_sql = "
        SELECT a.*, u.full_name as student_name, u.email as student_email, u.phone as student_phone, i.title as internship_title,
               sp.aadhaar_file as profile_aadhaar_file, sp.pan_file as profile_pan_file, sp.resume_file as profile_resume_file,
               sp.verification_status as profile_verification_status, $resume_url_select as profile_resume_url
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN internships i ON a.internship_id = i.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE a.id = $id LIMIT 1
    ";
    $res = mysqli_query($conn, $details_sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        if (empty($row['aadhaar_card_file'])) {
            $row['aadhaar_card_file'] = $row['profile_aadhaar_file'] ?? '';
        }
        if (empty($row['pan_file'])) {
            $row['pan_file'] = $row['profile_pan_file'] ?? '';
        }
        if (empty($row['resume_file'])) {
            $row['resume_file'] = $row['profile_resume_file'] ?? '';
        }
        $row['resume_url'] = $row['profile_resume_url'] ?? '';
        $profile_mock = [
            'resume_file' => $row['resume_file'],
            'resume_url' => $row['resume_url']
        ];
        $row['resume_exists'] = check_resume_exists($profile_mock);
        // Fetch status history as well
        $history = [];
        $hist_res = mysqli_query($conn, "SELECT old_status, new_status, updated_by_role, updated_by_name, notes, created_at FROM application_status_history WHERE application_id = $id ORDER BY created_at DESC");
        while ($h_row = mysqli_fetch_assoc($hist_res)) {
            $history[] = $h_row;
        }
        echo json_encode(['success' => true, 'application' => $row, 'history' => $history]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
    }
    exit();
}

// ── Search & Filter Logic ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR a.college_name LIKE ? OR i.title LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "ssss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Count Query
$count_query = "
    SELECT COUNT(*) as c 
    FROM internship_applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN internships i ON a.internship_id = i.id
    " . $where_sql;
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_stmt_get_result($count_stmt);
$total_rows = mysqli_fetch_assoc($count_res)['c'] ?? 0;
mysqli_stmt_close($count_stmt);

$total_pages = ceil($total_rows / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch Applications
$apps_sql = "
    SELECT a.id, a.status, a.college_name, a.applied_date, u.full_name as student_name, u.email as student_email, COALESCE(i.title, 'General Internship') as internship_title
    FROM internship_applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN internships i ON a.internship_id = i.id
    " . $where_sql . "
    ORDER BY a.id DESC LIMIT ? OFFSET ?
";
$apps_stmt = mysqli_prepare($conn, $apps_sql);
$bind_types = $types . "ii";
$bind_params = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($apps_stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($apps_stmt);
$apps_res = mysqli_stmt_get_result($apps_stmt);
$apps_list = [];
while ($row = mysqli_fetch_assoc($apps_res)) {
    $apps_list[] = $row;
}
mysqli_stmt_close($apps_stmt);

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
    <title>Internship Applications – IMP</title>
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
      
      <!-- Notifications Bell -->
      <a href="admin_received_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($admin_unread_count > 0): ?>
          <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $admin_unread_count; ?></span>
        <?php endif; ?>
      </a>

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
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Banners -->
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

        <!-- Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Internship Applications</h1>
            <p class="text-gray-500 text-sm mt-1">Review student applications, verify credentials, and allocate placement status</p>
          </div>
        </div>

        <!-- Search & Filters -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
          <form method="GET" action="admin_applications.php" class="flex flex-col md:flex-row gap-4 items-center">
            <div class="relative flex-1 w-full">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student, email, college, role..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none bg-gray-50">
            </div>
            
            <div class="flex items-center gap-2 w-full md:w-auto">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Status:</label>
              <select name="status" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-[#003ea8] outline-none cursor-pointer min-w-[140px]">
                <option value="">All Statuses</option>
                <option value="Applied" <?php if ($status_filter === 'Applied') echo 'selected'; ?>>Applied</option>
                <option value="Test Completed" <?php if ($status_filter === 'Test Completed') echo 'selected'; ?>>Test Completed</option>
                <option value="HR Round" <?php if ($status_filter === 'HR Round') echo 'selected'; ?>>HR Round</option>
                <option value="HOD Approved" <?php if ($status_filter === 'HOD Approved') echo 'selected'; ?>>HOD Approved</option>
                <option value="Selected" <?php if ($status_filter === 'Selected') echo 'selected'; ?>>Selected</option>
                <option value="Rejected" <?php if ($status_filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
              </select>
            </div>
            
            <div class="flex gap-2">
              <button type="submit" class="bg-[#003ea8] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors cursor-pointer">Filter</button>
              <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="admin_applications.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition-colors">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Table Display -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Student</th>
                  <th class="px-6 py-4">Applied Internship</th>
                  <th class="px-6 py-4">College Name</th>
                  <th class="px-6 py-4">Applied Date</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($apps_list)): ?>
                  <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-xs">No internship applications found matching query.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($apps_list as $app): 
                    $stat_colors = [
                      'applied' => 'bg-blue-50 text-blue-700 border-blue-150',
                      'test completed' => 'bg-purple-50 text-purple-700 border-purple-150',
                      'hr round' => 'bg-indigo-50 text-indigo-700 border-indigo-150',
                      'hod approved' => 'bg-teal-50 text-teal-700 border-teal-150',
                      'selected' => 'bg-green-50 text-green-700 border-green-150',
                      'rejected' => 'bg-red-50 text-red-700 border-red-150'
                    ];
                    $stat_cls = $stat_colors[strtolower($app['status'])] ?? 'bg-slate-50 text-slate-700 border-slate-150';
                  ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs border border-slate-200">
                          <?php echo strtoupper(substr($app['student_name'], 0, 2)); ?>
                        </div>
                        <div>
                          <p class="font-bold text-gray-900"><?php echo htmlspecialchars($app['student_name']); ?></p>
                          <p class="text-[10px] text-gray-400"><?php echo htmlspecialchars($app['student_email']); ?></p>
                        </div>
                      </td>
                      <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($app['internship_title']); ?></td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($app['college_name'] ?: 'N/A'); ?></td>
                      <td class="px-6 py-4 text-gray-400 text-xs font-semibold"><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                      <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border <?php echo $stat_cls; ?>">
                          <?php echo htmlspecialchars($app['status']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 text-right">
                        <button onclick="viewApplication(<?php echo $app['id']; ?>)" class="bg-[#003ea8] text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-800 cursor-pointer">Review Details</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-xs">
              <span class="text-gray-500 font-medium">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
              <div class="flex gap-1.5">
                <?php if ($page > 1): ?>
                  <a href="admin_applications.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <a href="admin_applications.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="px-3 py-1.5 border rounded-lg font-bold transition-colors <?php echo $i === $page ? 'bg-[#003ea8] border-[#003ea8] text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                  <a href="admin_applications.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Next</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- ── APPLICATION REVIEW MODAL ── -->
  <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">assignment_ind</span> Review Candidate Application
        </h3>
        <button onclick="closeModal()" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-150">
        <!-- Left: Application Details -->
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
          <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-100">
            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm border-2 border-white shadow-sm" id="detail-avatar">C</div>
            <div>
              <h4 class="font-bold text-gray-900 text-sm" id="detail-student-name">Student Name</h4>
              <p class="text-[10px] text-gray-400" id="detail-student-phone">Phone: N/A</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs text-gray-700 font-semibold">
            <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block">College / University</span>
              <span id="detail-college" class="text-gray-900 font-bold block truncate">N/A</span>
            </div>
            <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block">Course Program</span>
              <span id="detail-course" class="text-gray-900 font-bold block truncate">N/A</span>
            </div>
            <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block">Year / Grad Year</span>
              <span id="detail-grad-year" class="text-gray-900 font-bold block">N/A</span>
            </div>
            <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block">Aadhaar / PAN</span>
              <span id="detail-aadhaar-pan" class="text-gray-900 font-bold block">N/A</span>
            </div>
          </div>



          <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-xs">
            <span class="text-[9px] text-gray-400 uppercase block mb-1">Relevant Skills</span>
            <p id="detail-skills" class="text-gray-700 font-bold">N/A</p>
          </div>

          <!-- Document links -->
          <div class="space-y-1.5 text-xs">
            <h5 class="text-[9px] text-gray-400 uppercase font-bold">Verification Documents</h5>
            <div class="flex justify-between items-center bg-slate-50 p-2 rounded border border-slate-100" id="doc-resume">
              <span class="font-semibold text-gray-700 block truncate max-w-[150px]">Student Resume</span>
              <a href="#" id="link-resume" target="_blank" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-150 text-[10px] font-bold rounded-lg transition-colors cursor-pointer">View Resume</a>
              <span id="no-resume" class="text-gray-400 font-semibold italic text-[10px] hidden">Not Uploaded</span>
            </div>
            <div class="flex justify-between items-center bg-slate-50 p-2 rounded border border-slate-100" id="doc-pan">
              <span class="font-semibold text-gray-700 block truncate max-w-[150px]">PAN Card</span>
              <a href="#" id="link-pan" target="_blank" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-150 text-[10px] font-bold rounded-lg transition-colors cursor-pointer">View PAN</a>
              <span id="no-pan" class="text-gray-400 font-semibold italic text-[10px] hidden">Not Uploaded</span>
            </div>
            <div class="flex justify-between items-center bg-slate-50 p-2 rounded border border-slate-100" id="doc-aadhaar">
              <span class="font-semibold text-gray-700 block truncate max-w-[150px]">Aadhaar Card</span>
              <a href="#" id="link-aadhaar" target="_blank" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-150 text-[10px] font-bold rounded-lg transition-colors cursor-pointer">View Aadhaar</a>
              <span id="no-aadhaar" class="text-gray-400 font-semibold italic text-[10px] hidden">Not Uploaded</span>
            </div>
          </div>
        </div>

        <!-- Right: Auditing & Monitoring Dashboard (Read-Only) -->
        <div class="p-6 flex flex-col justify-between max-h-[70vh] overflow-y-auto gap-5">
          <!-- Status Cards Grid -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Current Status Card -->
            <div class="p-3.5 rounded-xl border shadow-sm flex flex-col justify-between min-h-[75px]" id="card-app-status">
              <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Application Status</span>
              <span class="font-extrabold text-sm mt-1" id="text-app-status">N/A</span>
            </div>
            <!-- Verification Status Card -->
            <div class="p-3.5 rounded-xl border shadow-sm flex flex-col justify-between min-h-[75px]" id="card-verif-status">
              <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Verification Status</span>
              <span class="font-extrabold text-sm mt-1" id="text-verif-status">N/A</span>
            </div>
          </div>

          <!-- Complete Application Timeline -->
          <div class="space-y-3">
            <h5 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Complete Application Timeline</h5>
            <div class="relative pl-6 space-y-4 border-l-2 border-gray-150 ml-3" id="progress-timeline-flow">
              <!-- Dynamically populated -->
            </div>
          </div>

          <!-- Audit Logs Section -->
          <div class="space-y-2 pt-2 border-t border-gray-100">
            <h5 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Detailed Audit History</h5>
            <div class="space-y-2 max-h-40 overflow-y-auto pr-1" id="audit-history-logs">
              <!-- Dynamically populated -->
              <p class="text-xs text-gray-400 italic">No audit history found.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    async function viewApplication(appId) {
      try {
        const response = await fetch(`admin_applications.php?action=get_details&id=${appId}`);
        const data = await response.json();
        
        if (data.success) {
          const app = data.application;
          
          // Setup left details
          document.getElementById('detail-avatar').textContent = app.student_name.substring(0,2).toUpperCase();
          document.getElementById('detail-student-name').textContent = app.student_name;
          document.getElementById('detail-student-phone').textContent = "Phone: " + (app.student_phone || "N/A");
          document.getElementById('detail-college').textContent = app.college_name || "N/A";
          document.getElementById('detail-course').textContent = app.department || (app.course || "N/A");
          document.getElementById('detail-grad-year').textContent = (app.year_of_study || "Year N/A") + " / " + (app.graduation_year || "Grad N/A");
          document.getElementById('detail-aadhaar-pan').textContent = (app.aadhaar_number || "Aadhaar N/A") + " / " + (app.pan_number || "PAN N/A");

          document.getElementById('detail-skills').textContent = app.relevant_skills || "None listed.";
          
          // Documents
          const baseUploadPath = 'view_doc.php?file=';
          
          function getJsDocViewUrl(url) {
            if (!url) return '#';
            url = url.trim();
            if (url.toLowerCase().endsWith('.pdf') || url.includes('/raw/upload/') || /\.pdf/i.test(url)) {
              return 'https://docs.google.com/gview?embedded=true&url=' + encodeURIComponent(url);
            }
            return url;
          }
          
          if (app.resume_url && (app.resume_url.startsWith('http://') || app.resume_url.startsWith('https://'))) {
            document.getElementById('link-resume').classList.remove('hidden');
            document.getElementById('link-resume').setAttribute('href', getJsDocViewUrl(app.resume_url));
            document.getElementById('link-resume').setAttribute('target', '_blank');
            document.getElementById('link-resume').setAttribute('rel', 'noopener noreferrer');
            document.getElementById('no-resume').classList.add('hidden');
          } else if (app.resume_file && (app.resume_file.startsWith('http://') || app.resume_file.startsWith('https://'))) {
            document.getElementById('link-resume').classList.remove('hidden');
            document.getElementById('link-resume').setAttribute('href', getJsDocViewUrl(app.resume_file));
            document.getElementById('link-resume').setAttribute('target', '_blank');
            document.getElementById('link-resume').setAttribute('rel', 'noopener noreferrer');
            document.getElementById('no-resume').classList.add('hidden');
          } else if (app.resume_file && app.resume_file.trim() !== '') {
            document.getElementById('link-resume').classList.remove('hidden');
            document.getElementById('link-resume').setAttribute('href', getJsDocViewUrl('resume_serve.php?file=' + encodeURIComponent(app.resume_file) + '&mode=view'));
            document.getElementById('link-resume').setAttribute('target', '_blank');
            document.getElementById('link-resume').setAttribute('rel', 'noopener noreferrer');
            document.getElementById('no-resume').classList.add('hidden');
          } else {
            document.getElementById('link-resume').classList.add('hidden');
            document.getElementById('no-resume').classList.remove('hidden');
          }
          if (document.getElementById('link-resume')) {
            document.getElementById('link-resume').setAttribute('data-resume-exists', app.resume_exists ? 'true' : 'false');
          }

          if (app.pan_file && app.pan_file.trim() !== '') {
            document.getElementById('link-pan').classList.remove('hidden');
            let pLink = (app.pan_file.startsWith('http://') || app.pan_file.startsWith('https://'))
                        ? app.pan_file
                        : baseUploadPath + app.pan_file;
            document.getElementById('link-pan').setAttribute('href', getJsDocViewUrl(pLink));
            document.getElementById('link-pan').setAttribute('target', '_blank');
            document.getElementById('link-pan').setAttribute('rel', 'noopener noreferrer');
            document.getElementById('no-pan').classList.add('hidden');
          } else {
            document.getElementById('link-pan').classList.add('hidden');
            document.getElementById('no-pan').classList.remove('hidden');
          }

          if (app.aadhaar_card_file && app.aadhaar_card_file.trim() !== '') {
            document.getElementById('link-aadhaar').classList.remove('hidden');
            let aLink = (app.aadhaar_card_file.startsWith('http://') || app.aadhaar_card_file.startsWith('https://'))
                        ? app.aadhaar_card_file
                        : baseUploadPath + app.aadhaar_card_file;
            document.getElementById('link-aadhaar').setAttribute('href', getJsDocViewUrl(aLink));
            document.getElementById('link-aadhaar').setAttribute('target', '_blank');
            document.getElementById('link-aadhaar').setAttribute('rel', 'noopener noreferrer');
            document.getElementById('no-aadhaar').classList.add('hidden');
          } else {
            document.getElementById('link-aadhaar').classList.add('hidden');
            document.getElementById('no-aadhaar').classList.remove('hidden');
          }

          // Setup Right-Side Status Cards
          document.getElementById('text-app-status').textContent = app.status;
          const statusCard = document.getElementById('card-app-status');
          // Clear previous color classes
          statusCard.className = "p-3.5 rounded-xl border shadow-sm flex flex-col justify-between min-h-[75px]";
          
          const statusLower = app.status.toLowerCase();
          if (statusLower === 'applied') {
            statusCard.classList.add('bg-slate-50', 'text-slate-700', 'border-slate-200');
          } else if (statusLower === 'test completed') {
            statusCard.classList.add('bg-purple-50', 'text-purple-700', 'border-purple-200');
          } else if (statusLower === 'hr round') {
            statusCard.classList.add('bg-orange-50', 'text-orange-700', 'border-orange-200');
          } else if (statusLower === 'hod approved') {
            statusCard.classList.add('bg-cyan-50', 'text-cyan-700', 'border-cyan-200');
          } else if (statusLower === 'selected') {
            statusCard.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
          } else if (statusLower === 'rejected') {
            statusCard.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
          } else if (statusLower === 'active intern' || statusLower === 'started' || statusLower === 'internship started') {
            statusCard.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
          } else if (statusLower === 'completed') {
            statusCard.classList.add('bg-indigo-50', 'text-indigo-700', 'border-indigo-200');
          } else {
            statusCard.classList.add('bg-gray-50', 'text-gray-700', 'border-gray-200');
          }

          // Verification Status Card
          const verifStatus = app.profile_verification_status || 'Pending';
          document.getElementById('text-verif-status').textContent = verifStatus;
          const verifCard = document.getElementById('card-verif-status');
          verifCard.className = "p-3.5 rounded-xl border shadow-sm flex flex-col justify-between min-h-[75px]";
          const verifLower = verifStatus.toLowerCase();
          if (verifLower === 'verified' || verifLower === 'approved') {
            verifCard.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
          } else if (verifLower === 'rejected' || verifLower === 'failed') {
            verifCard.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
          } else {
            verifCard.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-200');
          }

          // Document links on the left side are already set up above.

          // Populate Complete Application Timeline (7 stages)
          const timelineFlow = document.getElementById('progress-timeline-flow');
          timelineFlow.innerHTML = '';

          const timelineStages = [
            { name: 'Applied', statusKeys: ['applied'] },
            { name: 'Test Completed', statusKeys: ['test completed'] },
            { name: 'HR Round', statusKeys: ['hr round'] },
            { name: 'HOD Approved', statusKeys: ['hod approved'] },
            { name: 'Selected', statusKeys: ['selected'] },
            { name: 'Active Intern', statusKeys: ['active intern', 'started', 'internship started'] },
            { name: 'Completed', statusKeys: ['completed'] }
          ];

          let currentStageIdx = -1;
          for (let i = 0; i < timelineStages.length; i++) {
            if (timelineStages[i].statusKeys.includes(statusLower)) {
              currentStageIdx = i;
              break;
            }
          }

          timelineStages.forEach((stage, idx) => {
            const histMatch = data.history ? data.history.find(h => 
              stage.statusKeys.includes(h.new_status.toLowerCase())
            ) : null;

            let isCompleted = false;
            let isCurrent = false;
            let operator = 'System';
            let datetime = '';
            let remarks = '';

            if (histMatch) {
              isCompleted = true;
              operator = `${histMatch.updated_by_role.toUpperCase()} (${histMatch.updated_by_name})`;
              datetime = new Date(histMatch.created_at).toLocaleString();
              remarks = histMatch.notes || 'No remarks provided.';
            } else if (idx === 0) {
              isCompleted = true;
              operator = 'Student';
              datetime = app.applied_date ? new Date(app.applied_date).toLocaleString() : 'N/A';
              remarks = 'Application submitted.';
            } else if (currentStageIdx !== -1 && idx < currentStageIdx) {
              isCompleted = true;
              operator = 'System (Auto)';
              datetime = 'N/A';
              remarks = 'Completed in previous stage.';
            }

            if (currentStageIdx !== -1 && idx === currentStageIdx) {
              isCurrent = true;
              isCompleted = false;
              if (histMatch) {
                remarks = histMatch.notes || 'Current active stage.';
                operator = `${histMatch.updated_by_role.toUpperCase()} (${histMatch.updated_by_name})`;
                datetime = new Date(histMatch.created_at).toLocaleString();
              } else {
                remarks = 'Current active stage.';
                datetime = '';
                operator = '';
              }
            }

            const itemDiv = document.createElement('div');
            itemDiv.className = 'relative flex flex-col gap-1';

            let indicatorHTML = '';
            let textClass = 'text-gray-400';
            let detailsHTML = '';

            if (isCompleted) {
              indicatorHTML = `
                <span class="absolute -left-[30px] top-0.5 w-[14px] h-[14px] rounded-full bg-green-500 border-2 border-white flex items-center justify-center shadow-sm">
                  <span class="material-symbols-outlined text-white text-[9px] font-bold">check</span>
                </span>
              `;
              textClass = 'text-gray-950 font-semibold';
              detailsHTML = `
                <div class="text-[10px] text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100 mt-0.5">
                  <div class="flex justify-between font-bold text-gray-600">
                    <span>By: ${operator}</span>
                    <span>${datetime}</span>
                  </div>
                  <p class="mt-0.5 italic text-gray-500">${remarks}</p>
                </div>
              `;
            } else if (isCurrent) {
              indicatorHTML = `
                <span class="absolute -left-[33px] top-0 w-[18px] h-[18px] rounded-full bg-blue-600 border-4 border-white flex items-center justify-center shadow-md animate-pulse"></span>
              `;
              textClass = 'text-blue-700 font-bold';
              detailsHTML = `
                <div class="text-[10px] text-blue-700 bg-blue-50/50 p-1.5 rounded border border-blue-100 mt-0.5">
                  <div class="flex justify-between font-bold">
                    <span>Current Stage</span>
                    <span>${datetime}</span>
                  </div>
                  <p class="mt-0.5 italic">${remarks}</p>
                </div>
              `;
            } else {
              indicatorHTML = `
                <span class="absolute -left-[28px] top-1.5 w-[10px] h-[10px] rounded-full bg-gray-200 border-2 border-white shadow-sm"></span>
              `;
              textClass = 'text-gray-400 font-medium';
            }

            itemDiv.innerHTML = `
              ${indicatorHTML}
              <span class="text-xs ${textClass}">${stage.name}</span>
              ${detailsHTML}
            `;
            timelineFlow.appendChild(itemDiv);
          });

          if (statusLower === 'rejected') {
            const rejMatch = data.history ? data.history.find(h => h.new_status.toLowerCase() === 'rejected') : null;
            const operator = rejMatch ? `${rejMatch.updated_by_role.toUpperCase()} (${rejMatch.updated_by_name})` : 'HR / Coordinator';
            const datetime = rejMatch ? new Date(rejMatch.created_at).toLocaleString() : '';
            const remarks = rejMatch ? rejMatch.notes : 'Application rejected.';

            const rejDiv = document.createElement('div');
            rejDiv.className = 'relative flex flex-col gap-1';
            rejDiv.innerHTML = `
              <span class="absolute -left-[30px] top-0.5 w-[14px] h-[14px] rounded-full bg-red-500 border-2 border-white flex items-center justify-center shadow-sm">
                <span class="material-symbols-outlined text-white text-[9px] font-bold">close</span>
              </span>
              <span class="text-xs text-red-700 font-bold">Rejected</span>
              <div class="text-[10px] text-red-700 bg-red-50 p-1.5 rounded border border-red-100 mt-0.5">
                <div class="flex justify-between font-bold">
                  <span>By: ${operator}</span>
                  <span>${datetime}</span>
                </div>
                <p class="mt-0.5 italic">${remarks}</p>
              </div>
            `;
            timelineFlow.appendChild(rejDiv);
          }

          // Populate Detailed Audit logs Section
          const auditHistoryLogs = document.getElementById('audit-history-logs');
          auditHistoryLogs.innerHTML = '';
          if (data.history && data.history.length > 0) {
            data.history.forEach(hist => {
              const div = document.createElement('div');
              div.className = 'p-2 bg-gray-50 rounded border border-gray-150 text-[10px]';
              div.innerHTML = `
                <div class="flex justify-between font-bold text-gray-800">
                  <span>${hist.old_status || 'Applied'} → ${hist.new_status}</span>
                  <span class="text-gray-400 font-semibold">${new Date(hist.created_at).toLocaleString()}</span>
                </div>
                <p class="text-gray-500 mt-1">${hist.notes || 'No notes provided.'}</p>
                <div class="text-[8px] text-gray-400 mt-1 uppercase font-bold">Updated By: ${hist.updated_by_name} (${hist.updated_by_role})</div>
              `;
              auditHistoryLogs.appendChild(div);
            });
          } else {
            auditHistoryLogs.innerHTML = '<p class="text-[10px] text-gray-400 italic">No audit logs found.</p>';
          }

          // Show Modal
          document.getElementById('details-modal').classList.remove('hidden');
        } else {
          alert(data.message);
        }
      } catch (err) {
        console.error("AJAX Error: ", err);
        alert("Failed to load application details.");
      }
    }

    function closeModal() {
      document.getElementById('details-modal').classList.add('hidden');
    }
  </script>
<?php print_resume_not_found_js(); ?>
</body>
</html>
