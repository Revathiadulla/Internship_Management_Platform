<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
require_once __DIR__ . '/../includes/db.php';

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

// ── CSV Export Handling ──
if (isset($_GET['export'])) {
    $export = $_GET['export'];
    
    if ($export === 'users') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="IMP_Users_Report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['User ID', 'Full Name', 'Email', 'Role', 'Phone Number', 'Date Registered']);
        
        $res = mysqli_query($conn, "SELECT id, full_name, email, role, phone, registered_date AS created_at FROM users ORDER BY id ASC");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
    
    elseif ($export === 'placements') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="IMP_Placements_Report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Name', 'College Name', 'Course Program', 'Project Name', 'Assigned Mentor', 'Squad Title', 'Placements Status', 'Total Logs Submitted']);
        
        $res = mysqli_query($conn, "
            SELECT u.full_name as student_name, sp.college_name, sp.course, i.title as project_title,
                   m.full_name as mentor_name, a.team_name, a.status as app_status,
                   (SELECT COUNT(*) FROM daily_logs d WHERE d.user_id = u.id) as log_count
            FROM users u
            JOIN student_profiles sp ON u.id = sp.user_id
            JOIN internship_applications a ON u.id = a.user_id
            LEFT JOIN internships i ON a.internship_id = i.id
            LEFT JOIN users m ON a.mentor_id = m.id
            WHERE u.role = 'student' AND a.status IN ('Started','Internship Started','Active Intern','Selected')
            ORDER BY u.full_name ASC
        ");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
    
    elseif ($export === 'logs') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="IMP_Logs_Summary_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Log Date', 'Student Name', 'Project Assigned', 'Tasks Completed', 'Hours Logged', 'Focus Level', 'Review Status']);
        
        $res = mysqli_query($conn, "
            SELECT d.log_date, u.full_name as student_name, i.title as project_title,
                   d.tasks_completed, d.time_spent, d.focus_level, d.status
            FROM daily_logs d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN internships i ON d.internship_id = i.id
            ORDER BY d.log_date DESC
        ");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}

// ── Fetch Analytics Data ──

// 1. Applications Breakdown (Charts data)
$app_status_res = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM internship_applications GROUP BY status");
$app_statuses = [];
$app_counts = [];
while ($row = mysqli_fetch_assoc($app_status_res)) {
    $app_statuses[] = $row['status'];
    $app_counts[] = intval($row['count']);
}

// 2. Project Domains Breakdown
$domain_res = mysqli_query($conn, "SELECT COALESCE(project_type, 'General') as domain, COUNT(*) as count FROM internships GROUP BY project_type");
$domains = [];
$domain_counts = [];
while ($row = mysqli_fetch_assoc($domain_res)) {
    $domains[] = $row['domain'];
    $domain_counts[] = intval($row['count']);
}

// 3. Log Performance Metrics
$avg_hours = floatval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(time_spent) as avg_h FROM daily_logs"))['avg_h'] ?? 0.0);
$avg_hours = round($avg_hours, 1);

$focus_res = mysqli_query($conn, "SELECT focus_level, COUNT(*) as count FROM daily_logs GROUP BY focus_level");
$focus_levels = [];
$focus_counts = [];
while ($row = mysqli_fetch_assoc($focus_res)) {
    $focus_levels[] = $row['focus_level'];
    $focus_counts[] = intval($row['count']);
}

// 4. Placements Summary list
$placements_res = mysqli_query($conn, "
    SELECT u.full_name as student_name, sp.college_name, sp.course, i.title as project_title,
           m.full_name as mentor_name, a.status as app_status,
           (SELECT COUNT(*) FROM daily_logs d WHERE d.user_id = u.id) as log_count
    FROM users u
    JOIN student_profiles sp ON u.id = sp.user_id
    JOIN internship_applications a ON u.id = a.user_id
    LEFT JOIN internships i ON a.internship_id = i.id
    LEFT JOIN users m ON a.mentor_id = m.id
    WHERE u.role = 'student' AND a.status IN ('Started','Internship Started','Active Intern','Selected')
    ORDER BY u.full_name ASC LIMIT 10
");
$placements_list = [];
while ($row = mysqli_fetch_assoc($placements_res)) {
    $placements_list[] = $row;
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
    <title>Reports & Analytics – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- ChartJS CDN for visual wow factor -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
          <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
          </a>
          <hr class="my-1 border-gray-100">
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
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Platform Analytics & Reports</h1>
            <p class="text-gray-500 text-sm mt-1">Review student performance conversion funnels and export databases</p>
          </div>
          
          <div class="flex gap-2">
            <a href="reports.php?export=users" class="bg-white border border-gray-200 text-slate-700 px-4 py-2 rounded-lg flex items-center gap-2 text-xs font-bold shadow-sm hover:bg-gray-50">
              <span class="material-symbols-outlined text-sm">download</span> Users CSV
            </a>
            <a href="reports.php?export=placements" class="bg-white border border-gray-200 text-slate-700 px-4 py-2 rounded-lg flex items-center gap-2 text-xs font-bold shadow-sm hover:bg-gray-50">
              <span class="material-symbols-outlined text-sm">download</span> Placements CSV
            </a>
            <a href="reports.php?export=logs" class="bg-white border border-gray-200 text-slate-700 px-4 py-2 rounded-lg flex items-center gap-2 text-xs font-bold shadow-sm hover:bg-gray-50">
              <span class="material-symbols-outlined text-sm">download</span> Logs CSV
            </a>
          </div>
        </div>

        <!-- Analytical Graphs (Wow factors) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Application Funnel Distribution</h3>
            <div class="w-full max-w-[300px]">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
          <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Domain Breakdowns</h3>
            <div class="w-full max-w-[300px]">
              <canvas id="domainChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Performance Summary Card -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-6 rounded-xl text-white flex justify-between items-center relative overflow-hidden shadow-sm">
          <div>
            <span class="text-[10px] font-bold text-blue-200 uppercase tracking-widest block mb-1">Time Logging Performance</span>
            <h3 class="text-3xl font-black"><?php echo $avg_hours; ?> Hours</h3>
            <p class="text-xs text-blue-100 mt-1 font-medium">Average time spent per log update submitted by active interns</p>
          </div>
          <div class="text-right">
            <span class="text-[10px] font-bold text-blue-200 uppercase tracking-widest block mb-1">Total Logs Count</span>
            <p class="text-3xl font-black" id="perf-count">
              <?php
                $tot_l = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM daily_logs"))['c'] ?? 0;
                echo $tot_l;
              ?>
            </p>
          </div>
          <span class="material-symbols-outlined absolute right-4 bottom-2 text-blue-600 opacity-20 text-[100px]" style="font-variation-settings: 'FILL' 1;">analytics</span>
        </div>

        <!-- Placements Summary Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-900">Active Student Placements</h2>
            <span class="text-xs text-gray-400 font-semibold">Showing up to 10 active student placements</span>
          </div>
          
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Student</th>
                  <th class="px-6 py-4">College</th>
                  <th class="px-6 py-4">Assigned Project</th>
                  <th class="px-6 py-4">Assigned Mentor</th>
                  <th class="px-6 py-4">Squad Name</th>
                  <th class="px-6 py-4">Daily Logs</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($placements_list)): ?>
                  <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-xs">No active placements found in database.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($placements_list as $row): ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs border border-slate-200">
                          <?php echo strtoupper(substr($row['student_name'], 0, 2)); ?>
                        </div>
                        <div>
                          <p class="font-bold text-gray-900"><?php echo htmlspecialchars($row['student_name']); ?></p>
                          <p class="text-[10px] text-gray-400"><?php echo htmlspecialchars($row['course']); ?></p>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($row['college_name']); ?></td>
                      <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($row['project_title']); ?></td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($row['mentor_name'] ?: 'None'); ?></td>
                      <td class="px-6 py-4 font-bold text-xs text-blue-600">
                        <?php if ($row['team_name']): ?>
                          <span class="bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($row['team_name']); ?></span>
                        <?php else: ?>
                          <span class="text-gray-400 italic">No Squad</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 font-bold text-xs text-gray-800"><?php echo $row['log_count']; ?> logs</td>
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

  <script>
    // Status Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($app_statuses); ?>,
            datasets: [{
                data: <?php echo json_encode($app_counts); ?>,
                backgroundColor: ['#2563eb', '#9333ea', '#db2777', '#059669', '#d97706', '#dc2626'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { family: 'Inter', size: 10, weight: 'semibold' } }
                }
            }
        }
    });

    // Domain Chart
    const ctxDomain = document.getElementById('domainChart').getContext('2d');
    new Chart(ctxDomain, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($domains); ?>,
            datasets: [{
                data: <?php echo json_encode($domain_counts); ?>,
                backgroundColor: ['#1e40af', '#4f46e5', '#7c3aed', '#db2777', '#ea580c', '#0891b2'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { family: 'Inter', size: 10, weight: 'semibold' } }
                }
            }
        }
    });
  </script>
</body>
</html>
