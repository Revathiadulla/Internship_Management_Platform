<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";

$success_msg = "";
$error_msg = "";

// ── Workflow Oversights Actions ──
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    if ($id > 0) {
        if ($action === 'approve') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Active' WHERE id = ? AND status = 'Pending Approval'");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship approved and activated successfully!";
            } else {
                $error_msg = "Failed to approve internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        elseif ($action === 'reject') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Rejected' WHERE id = ? AND status = 'Pending Approval'");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship rejected successfully!";
            } else {
                $error_msg = "Failed to reject internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        elseif ($action === 'archive') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Archived' WHERE id = ? AND status IN ('Active', 'Completed')");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship archived successfully!";
            } else {
                $error_msg = "Failed to archive internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Calculate counters
$cnt_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Pending Approval'"))['c'] ?? 0;
$cnt_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Active'"))['c'] ?? 0;
$cnt_completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Completed'"))['c'] ?? 0;
$cnt_archived = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Archived'"))['c'] ?? 0;
$cnt_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Rejected'"))['c'] ?? 0;

// ── Search & Filter Logic ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$mode_filter = isset($_GET['mode']) ? trim($_GET['mode']) : '';

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(title LIKE ? OR project_title LIKE ? OR technology_stack LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($mode_filter)) {
    $where_clauses[] = "i.mode = ?";
    $params[] = $mode_filter;
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch all internships
$internships_sql = "
    SELECT i.*, u.full_name as mentor_name, uc.full_name as coordinator_name 
    FROM internships i
    LEFT JOIN users u ON i.assigned_mentor = u.id
    LEFT JOIN users uc ON i.coordinator_id = uc.id
    " . $where_sql . "
    ORDER BY i.id DESC
";
$stmt = mysqli_prepare($conn, $internships_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$internships_res = mysqli_stmt_get_result($stmt);
$internships_list = [];
while ($row = mysqli_fetch_assoc($internships_res)) {
    $internships_list[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch mentors for select dropdown
$mentors_res = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role='mentor' ORDER BY full_name ASC");
$mentors_list = [];
while ($row = mysqli_fetch_assoc($mentors_res)) {
    $mentors_list[] = $row;
}

// Fetch admin header details
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
    <title>Manage Internships – IMP</title>
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
            <a href="admin_internships.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-2.5 rounded-r-lg text-sm font-bold">
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
            <a href="admin_notifications.php" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">
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
            <h1 class="text-2xl font-bold text-gray-900">Internship Oversights</h1>
            <p class="text-gray-500 text-sm mt-1">Review coordinator-created postings and control publishing lifecycle</p>
          </div>
        </div>

        <!-- Dashboard Counters -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <!-- Pending Approval -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Pending Approval</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-amber-600"><?php echo $cnt_pending; ?></span>
              <span class="material-symbols-outlined text-amber-500 text-lg">hourglass_empty</span>
            </div>
          </div>
          <!-- Active -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Active</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-green-600"><?php echo $cnt_active; ?></span>
              <span class="material-symbols-outlined text-green-500 text-lg">play_arrow</span>
            </div>
          </div>
          <!-- Completed -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Completed</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-slate-700"><?php echo $cnt_completed; ?></span>
              <span class="material-symbols-outlined text-slate-500 text-lg">task_alt</span>
            </div>
          </div>
          <!-- Archived -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Archived</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-gray-600"><?php echo $cnt_archived; ?></span>
              <span class="material-symbols-outlined text-gray-500 text-lg">archive</span>
            </div>
          </div>
          <!-- Rejected -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Rejected</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-red-600"><?php echo $cnt_rejected; ?></span>
              <span class="material-symbols-outlined text-red-500 text-lg">cancel</span>
            </div>
          </div>
        </div>

        <!-- Filters Form -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
          <form method="GET" action="admin_internships.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, stacks..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none bg-gray-50">
            </div>
            
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Status:</label>
              <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none cursor-pointer">
                <option value="">All Statuses</option>
                <option value="Pending Approval" <?php if ($status_filter === 'Pending Approval') echo 'selected'; ?>>Pending Approval</option>
                <option value="Active" <?php if ($status_filter === 'Active') echo 'selected'; ?>>Active</option>
                <option value="Completed" <?php if ($status_filter === 'Completed') echo 'selected'; ?>>Completed</option>
                <option value="Archived" <?php if ($status_filter === 'Archived') echo 'selected'; ?>>Archived</option>
                <option value="Rejected" <?php if ($status_filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
              </select>
            </div>
            
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Mode:</label>
              <select name="mode" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none cursor-pointer">
                <option value="">All Modes</option>
                <option value="Remote" <?php if ($mode_filter === 'Remote') echo 'selected'; ?>>Remote</option>
                <option value="Hybrid" <?php if ($mode_filter === 'Hybrid') echo 'selected'; ?>>Hybrid</option>
                <option value="On-site" <?php if ($mode_filter === 'On-site') echo 'selected'; ?>>On-site</option>
              </select>
            </div>
            
            <div class="flex gap-2 justify-end">
              <button type="submit" class="bg-[#003ea8] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors cursor-pointer">Filter</button>
              <?php if (!empty($search) || !empty($status_filter) || !empty($mode_filter)): ?>
                <a href="admin_internships.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition-colors">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Postings Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($internships_list)): ?>
            <div class="col-span-full bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
              <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">work_off</span>
              <h3 class="text-base font-bold text-gray-800">No internship postings found</h3>
              <p class="text-xs text-gray-500 mt-1">Refine your query filters or create a new posting to get started.</p>
            </div>
          <?php else: ?>
            <?php foreach ($internships_list as $item): 
              $status_colors = [
                'pending approval' => 'bg-orange-50 text-orange-700 border-orange-200',
                'active' => 'bg-green-50 text-green-700 border-green-200',
                'completed' => 'bg-slate-50 text-slate-700 border-slate-200',
                'archived' => 'bg-gray-50 text-gray-700 border-gray-200',
                'rejected' => 'bg-red-50 text-red-700 border-red-200'
              ];
              $status_cls = $status_colors[strtolower($item['status'])] ?? 'bg-slate-50 text-slate-700 border-slate-150';
            ?>
              <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow gap-4">
                <div class="space-y-3">
                  <div class="flex justify-between items-start">
                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_cls; ?>">
                      <?php echo htmlspecialchars($item['status']); ?>
                    </span>
                    <span class="text-xs text-gray-400 font-bold"><?php echo htmlspecialchars($item['mode']); ?></span>
                  </div>
                  
                  <div>
                    <h3 class="text-base font-bold text-gray-900 leading-snug"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($item['project_subtype'] ?: ($item['project_type'] ?: 'General Internship')); ?></p>
                  </div>
                  
                  <div class="space-y-1.5 text-xs text-gray-600">
                    <p><span class="font-bold text-gray-700">Created By:</span> <?php echo htmlspecialchars($item['coordinator_name'] ?: 'System/Admin'); ?></p>
                    <p><span class="font-bold text-gray-700">Submitted:</span> <?php echo $item['submission_date'] ? date('M d, Y', strtotime($item['submission_date'])) : 'N/A'; ?></p>
                    <p class="truncate"><span class="font-bold text-gray-700">Stack:</span> <?php echo htmlspecialchars($item['technology_stack'] ?: 'N/A'); ?></p>
                    <p><span class="font-bold text-gray-700">Duration:</span> <?php echo htmlspecialchars($item['duration']); ?> &bull; <span class="font-bold text-gray-700">Slots:</span> <?php echo htmlspecialchars($item['openings']); ?></p>
                    <p><span class="font-bold text-gray-700">Difficulty:</span> <span class="font-bold text-blue-600"><?php echo htmlspecialchars($item['difficulty_level']); ?></span></p>
                    <p class="truncate"><span class="font-bold text-gray-700">Mentor:</span> <?php echo htmlspecialchars($item['mentor_name'] ?: 'None Assigned'); ?></p>
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex flex-wrap gap-1.5 justify-end items-center">
                  <button onclick='openDetailsModal(<?php echo json_encode($item); ?>)' class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">View Details</button>
                  
                  <?php if (strtolower($item['status']) === 'pending approval'): ?>
                    <a href="admin_internships.php?action=approve&id=<?php echo $item['id']; ?>" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold transition-colors">Activate Internship</a>
                    <a href="admin_internships.php?action=reject&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure you want to reject this posting?')" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition-colors">Reject</a>
                  <?php endif; ?>
                  
                  <?php if (in_array(strtolower($item['status']), ['active', 'completed'])): ?>
                    <a href="admin_internships.php?action=archive&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure you want to archive this posting?')" class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs font-bold transition-colors">Archive</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- ── VIEW DETAILS MODAL ── -->
  <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">info</span> Internship Details
        </h3>
        <button onclick="closeModal('details-modal')" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Internship Role Title</span>
          <p id="det-title" class="text-gray-900 font-extrabold text-sm"></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Category Type</span>
            <p id="det-project-type" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Subtype Domain</span>
            <p id="det-project-subtype" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Duration</span>
            <p id="det-duration" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Location Mode</span>
            <p id="det-mode" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Open slots / Openings</span>
            <p id="det-openings" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Difficulty level</span>
            <p id="det-difficulty" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Assignment Title</span>
            <p id="det-project-title" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Milestone / Focus Task</span>
            <p id="det-task-title" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Start Date</span>
            <p id="det-start-date" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">End Date</span>
            <p id="det-end-date" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Technology Stack</span>
          <p id="det-tech-stack" class="text-gray-900 font-bold"></p>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Required Skills</span>
          <p id="det-skills" class="text-gray-900 font-bold"></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Assigned Mentor Guide</span>
            <p id="det-mentor" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Workflow Status</span>
            <p id="det-status" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Created By (Coordinator)</span>
            <p id="det-coordinator" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Submission Date</span>
            <p id="det-submission-date" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Description Details</span>
          <div id="det-description" class="bg-gray-50 border border-gray-150 p-3 rounded-lg text-gray-700 whitespace-pre-line font-medium"></div>
        </div>

        <div class="pt-3 border-t border-gray-100 flex justify-end">
          <button type="button" onclick="closeModal('details-modal')" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm cursor-pointer">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openDetailsModal(item) {
      document.getElementById('det-title').textContent = item.title;
      document.getElementById('det-project-type').textContent = item.project_type || 'N/A';
      document.getElementById('det-project-subtype').textContent = item.project_subtype || 'N/A';
      document.getElementById('det-duration').textContent = item.duration;
      document.getElementById('det-mode').textContent = item.mode;
      document.getElementById('det-openings').textContent = item.openings || '0';
      document.getElementById('det-difficulty').textContent = item.difficulty_level || 'N/A';
      document.getElementById('det-project-title').textContent = item.project_title || 'N/A';
      document.getElementById('det-task-title').textContent = item.task_title || 'N/A';
      document.getElementById('det-start-date').textContent = item.start_date || 'N/A';
      document.getElementById('det-end-date').textContent = item.end_date || 'N/A';
      document.getElementById('det-tech-stack').textContent = item.technology_stack || 'N/A';
      document.getElementById('det-skills').textContent = item.skills || 'N/A';
      document.getElementById('det-mentor').textContent = item.mentor_name || 'None Assigned';
      document.getElementById('det-status').textContent = item.status;
      document.getElementById('det-coordinator').textContent = item.coordinator_name || 'System / Admin';
      document.getElementById('det-submission-date').textContent = item.submission_date || 'N/A';
      document.getElementById('det-description').textContent = item.description || 'No description details provided.';
      
      document.getElementById('details-modal').classList.remove('hidden');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.add('hidden');
    }
  </script>
</body>
</html>
