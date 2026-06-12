<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'hr') {
    header("Location: ../../login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
require_once __DIR__ . '/../includes/db.php';

// Fetch admin notifications unread count for badge
$hr_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'hr' AND is_read = 0");
$hr_unread_row = mysqli_fetch_assoc($hr_unread_res);
$hr_unread_count = $hr_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg = "";

// ── Update Log Status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_log_status') {
    $log_id = intval($_POST['log_id']);
    $new_status = trim($_POST['status']);

    if ($log_id <= 0 || empty($new_status)) {
        $error_msg = "Invalid parameters.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE daily_logs SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $log_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Daily log status updated to '" . htmlspecialchars($new_status) . "' successfully!";
        } else {
            $error_msg = "Failed to update log status. Database error.";
        }
        mysqli_stmt_close($stmt);
    }
}

// ── AJAX: GET LOG DETAILS ──
if (isset($_GET['action']) && $_GET['action'] === 'get_log_details') {
    header('Content-Type: application/json');
    $log_id = intval($_GET['id']);
    $log_sql = "
        SELECT d.*, u.full_name, u.email, i.title as internship_title
        FROM daily_logs d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN internships i ON d.internship_id = i.id
        WHERE d.id = $log_id LIMIT 1
    ";
    $res = mysqli_query($conn, $log_sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        echo json_encode(['success' => true, 'log' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Daily log not found.']);
    }
    exit();
}

// ── Search & Filter Logic ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$focus_filter = isset($_GET['focus']) ? trim($_GET['focus']) : '';
$date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR d.tasks_completed LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "ss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "d.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($focus_filter)) {
    $where_clauses[] = "d.focus_level = ?";
    $params[] = $focus_filter;
    $types .= "s";
}

if (!empty($date_start)) {
    $where_clauses[] = "d.log_date >= ?";
    $params[] = $date_start;
    $types .= "s";
}

if (!empty($date_end)) {
    $where_clauses[] = "d.log_date <= ?";
    $params[] = $date_end;
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Count total
$count_query = "
    SELECT COUNT(*) as c 
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
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

// Fetch logs
$logs_sql = "
    SELECT d.id, d.tasks_completed, d.time_spent, d.focus_level, d.status, d.log_date, u.full_name as student_name, COALESCE(i.title, 'General Internship') as internship_title
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN internships i ON d.internship_id = i.id
    " . $where_sql . "
    ORDER BY d.log_date DESC, d.id DESC LIMIT ? OFFSET ?
";
$logs_stmt = mysqli_prepare($conn, $logs_sql);
$bind_types = $types . "ii";
$bind_params = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($logs_stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($logs_stmt);
$logs_res = mysqli_stmt_get_result($logs_stmt);
$logs_list = [];
while ($row = mysqli_fetch_assoc($logs_res)) {
    $logs_list[] = $row;
}
mysqli_stmt_close($logs_stmt);

// Fetch admin details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'hr';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Daily Logs Oversight – IMP</title>
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
      <a href="/IMP/admin/notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($hr_unread_count > 0): ?>
          <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $hr_unread_count; ?></span>
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
          <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
          </a>
          <hr class="my-1 border-gray-100">
          <a href="../../logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../includes/hr_module_helpers.php'; hr_sidebar('student_logs'); ?>

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
            <h1 class="text-2xl font-bold text-gray-900">Daily Logs Oversight</h1>
            <p class="text-gray-500 text-sm mt-1">Audit daily student updates, completion hours, and log statuses</p>
          </div>
        </div>

        <!-- Search & Filters -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
          <form method="GET" action="/IMP/admin/student_logs.php" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
            <div class="relative w-full">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student, tasks..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none bg-gray-50">
            </div>
            
            <div class="flex items-center gap-1.5 w-full">
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider shrink-0">Status:</label>
              <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs outline-none cursor-pointer">
                <option value="">All Statuses</option>
                <option value="Submitted" <?php if ($status_filter === 'Submitted') echo 'selected'; ?>>Submitted</option>
                <option value="Approved" <?php if ($status_filter === 'Approved') echo 'selected'; ?>>Approved</option>
                <option value="Needs Revision" <?php if ($status_filter === 'Needs Revision') echo 'selected'; ?>>Needs Revision</option>
              </select>
            </div>

            <div class="flex items-center gap-1.5 w-full">
              <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider shrink-0">Focus:</label>
              <select name="focus" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs outline-none cursor-pointer">
                <option value="">All Levels</option>
                <option value="High" <?php if ($focus_filter === 'High') echo 'selected'; ?>>High</option>
                <option value="Medium" <?php if ($focus_filter === 'Medium') echo 'selected'; ?>>Medium</option>
                <option value="Low" <?php if ($focus_filter === 'Low') echo 'selected'; ?>>Low</option>
              </select>
            </div>
            
            <div class="flex items-center gap-1.5 w-full col-span-1">
              <input type="date" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-[#003ea8] outline-none cursor-pointer" title="Start log date">
              <span class="text-xs text-gray-400">to</span>
              <input type="date" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-[#003ea8] outline-none cursor-pointer" title="End log date">
            </div>

            <div class="flex gap-2 justify-end w-full">
              <button type="submit" class="bg-[#003ea8] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors cursor-pointer">Filter</button>
              <?php if (!empty($search) || !empty($status_filter) || !empty($focus_filter) || !empty($date_start) || !empty($date_end)): ?>
                <a href="/IMP/admin/student_logs.php"" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition-colors">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Table Grid -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Student</th>
                  <th class="px-6 py-4">Internship Project</th>
                  <th class="px-6 py-4">Logged Date</th>
                  <th class="px-6 py-4">Tasks Description Summary</th>
                  <th class="px-6 py-4">Hours</th>
                  <th class="px-6 py-4">Focus</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($logs_list)): ?>
                  <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400 text-xs">No daily logs found matching query.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($logs_list as $log): 
                    $focus_colors = [
                      'high' => 'bg-green-50 text-green-700 border-green-150',
                      'medium' => 'bg-amber-50 text-amber-700 border-amber-150',
                      'low' => 'bg-red-50 text-red-700 border-red-150'
                    ];
                    $focus_cls = $focus_colors[strtolower($log['focus_level'])] ?? 'bg-slate-50 text-slate-700 border-slate-150';
                    
                    $status_colors = [
                      'submitted' => 'bg-blue-50 text-blue-700 border-blue-150',
                      'approved' => 'bg-green-50 text-green-700 border-green-150',
                      'needs revision' => 'bg-red-50 text-red-700 border-red-150'
                    ];
                    $status_cls = $status_colors[strtolower($log['status'])] ?? 'bg-slate-50 text-slate-700 border-slate-150';
                  ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs border border-slate-200">
                          <?php echo strtoupper(substr($log['student_name'], 0, 2)); ?>
                        </div>
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($log['student_name']); ?></p>
                      </td>
                      <td class="px-6 py-4 font-semibold text-gray-900 truncate max-w-[150px]"><?php echo htmlspecialchars($log['internship_title']); ?></td>
                      <td class="px-6 py-4 text-gray-500 font-semibold text-xs"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                      <td class="px-6 py-4 text-gray-500 font-medium truncate max-w-[200px]"><?php echo htmlspecialchars($log['tasks_completed']); ?></td>
                      <td class="px-6 py-4 font-bold text-gray-800 text-xs"><?php echo htmlspecialchars($log['time_spent']); ?>h</td>
                      <td class="px-6 py-4">
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $focus_cls; ?>">
                          <?php echo htmlspecialchars($log['focus_level']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4">
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_cls; ?>">
                          <?php echo htmlspecialchars($log['status']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 text-right">
                        <button onclick="reviewLog(<?php echo $log['id']; ?>)" class="bg-[#003ea8] text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-800 cursor-pointer">Review Log</button>
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
                  <a href="student_logs.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&focus=<?php echo urlencode($focus_filter); ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <a href="student_logs.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&focus=<?php echo urlencode($focus_filter); ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>" class="px-3 py-1.5 border rounded-lg font-bold transition-colors <?php echo $i === $page ? 'bg-[#003ea8] border-[#003ea8] text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                  <a href="student_logs.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&focus=<?php echo urlencode($focus_filter); ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Next</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- ── LOG REVIEW MODAL ── -->
  <div id="review-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">rate_review</span> Review Daily Log Entry
        </h3>
        <button onclick="closeModal()" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      
      <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
        <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-100">
          <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm border-2 border-white shadow-sm" id="detail-avatar">ST</div>
          <div>
            <h4 class="font-bold text-gray-900 text-sm" id="detail-student-name">Student Name</h4>
            <p class="text-[10px] text-gray-500" id="detail-student-email">student@example.com</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs text-gray-700 font-semibold">
          <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
            <span class="text-[9px] text-gray-400 uppercase block">Log Date</span>
            <span id="detail-log-date" class="text-gray-900 font-bold block">N/A</span>
          </div>
          <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
            <span class="text-[9px] text-gray-400 uppercase block">Time Spent / Focus</span>
            <span id="detail-hours-focus" class="text-gray-900 font-bold block">N/A</span>
          </div>
        </div>

        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-xs">
          <span class="text-[9px] text-gray-400 uppercase block mb-1">Tasks Completed</span>
          <p id="detail-tasks" class="text-gray-700 leading-relaxed font-semibold">N/A</p>
        </div>

        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-xs">
          <span class="text-[9px] text-gray-400 uppercase block mb-1">Issues Faced</span>
          <p id="detail-issues" class="text-gray-700 leading-relaxed font-semibold">None reported.</p>
        </div>

        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-xs">
          <span class="text-[9px] text-gray-400 uppercase block mb-1">Next Plan of Action</span>
          <p id="detail-next" class="text-gray-700 leading-relaxed font-semibold">N/A</p>
        </div>

        <!-- Form for quick action status update -->
        <form method="POST" action="/IMP/admin/student_logs.php" class="pt-4 border-t border-gray-150 space-y-3">
          <input type="hidden" name="action" value="update_log_status">
          <input type="hidden" name="log_id" id="form-log-id">
          
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Action Audit Status *</label>
            <select name="status" id="form-status" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer font-bold">
              <option value="Submitted">Submitted (Pending review)</option>
              <option value="Approved">Approve Log Entry</option>
              <option value="Needs Revision">Needs Revision (Flag changes)</option>
            </select>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" onclick="closeModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 cursor-pointer">Cancel</button>
            <button type="submit" class="px-5 py-2 bg-[#003ea8] hover:bg-blue-800 text-white rounded-lg text-sm font-semibold shadow-sm cursor-pointer">Apply Status</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    async function reviewLog(logId) {
      try {
        const response = await fetch(`/IMP/hr/student_logs.php?action=get_log_details&id=${logId}`);
        const data = await response.json();
        
        if (data.success) {
          const log = data.log;
          
          document.getElementById('detail-avatar').textContent = log.full_name.substring(0,2).toUpperCase();
          document.getElementById('detail-student-name').textContent = log.full_name;
          document.getElementById('detail-student-email').textContent = log.email;
          document.getElementById('detail-log-date').textContent = new Date(log.log_date).toLocaleDateString();
          document.getElementById('detail-hours-focus').textContent = log.time_spent + " hours (" + log.focus_level + ")";
          document.getElementById('detail-tasks').textContent = log.tasks_completed;
          document.getElementById('detail-issues').textContent = log.issues_faced || 'None reported.';
          document.getElementById('detail-next').textContent = log.next_plan || 'N/A';
          
          document.getElementById('form-log-id').value = log.id;
          document.getElementById('form-status').value = log.status;
          
          document.getElementById('review-modal').classList.remove('hidden');
        } else {
          alert(data.message);
        }
      } catch (err) {
        console.error("AJAX Error: ", err);
        alert("Failed to load daily log details.");
      }
    }

    function closeModal() {
      document.getElementById('review-modal').classList.add('hidden');
    }
  </script>
</body>
</html>
