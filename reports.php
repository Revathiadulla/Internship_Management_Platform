<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('reports');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$start = trim($_GET['start_date'] ?? '');
$end = trim($_GET['end_date'] ?? '');
$search = trim($_GET['search'] ?? '');
$where = ['a.is_deleted = 0'];
if ($start !== '') $where[] = "a.applied_date >= '" . mysqli_real_escape_string($conn, $start) . " 00:00:00'";
if ($end !== '') $where[] = "a.applied_date <= '" . mysqli_real_escape_string($conn, $end) . " 23:59:59'";
if ($search !== '') {
    $escaped_search = mysqli_real_escape_string($conn, $search);
    $where[] = "(COALESCE(a.college_name, '') LIKE '%$escaped_search%' OR COALESCE(a.internship_name, '') LIKE '%$escaped_search%' OR COALESCE(a.status, '') LIKE '%$escaped_search%')";
}
$where_sql = implode(' AND ', $where);

$total = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) total FROM internship_applications a WHERE $where_sql");
if ($r) $total = (int) mysqli_fetch_assoc($r)['total'];

$status_rows = [];
$status_res = mysqli_query($conn, "SELECT COALESCE(a.status, 'Applied') label, COUNT(*) count FROM internship_applications a WHERE $where_sql GROUP BY label ORDER BY count DESC");
if ($status_res) while ($row = mysqli_fetch_assoc($status_res)) $status_rows[] = $row;

$monthly_rows = [];
$monthly_res = mysqli_query($conn, "SELECT DATE_FORMAT(a.applied_date, '%Y-%m') label, COUNT(*) count FROM internship_applications a WHERE $where_sql GROUP BY label ORDER BY label DESC LIMIT 12");
if ($monthly_res) while ($row = mysqli_fetch_assoc($monthly_res)) $monthly_rows[] = $row;
$monthly_rows = array_reverse($monthly_rows);

$college_rows = [];
$college_res = mysqli_query($conn, "SELECT COALESCE(NULLIF(sp.college_name, ''), NULLIF(a.college_name, ''), 'Unknown') label, COUNT(*) count FROM internship_applications a LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE $where_sql GROUP BY label ORDER BY count DESC LIMIT 10");
if ($college_res) while ($row = mysqli_fetch_assoc($college_res)) $college_rows[] = $row;

$decision_rows = [];
$decision_res = mysqli_query($conn, "SELECT a.status label, COUNT(*) count FROM internship_applications a WHERE $where_sql AND a.status IN ('Approved', 'Rejected') GROUP BY a.status ORDER BY a.status");
if ($decision_res) while ($row = mysqli_fetch_assoc($decision_res)) $decision_rows[] = $row;

$top_posting_rows = [];
$top_posting_res = mysqli_query($conn, "SELECT COALESCE(jp.title, i.title, a.internship_name, 'Unlinked Posting') label, COUNT(*) count
    FROM internship_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
    WHERE $where_sql
    GROUP BY label
    ORDER BY count DESC
    LIMIT 8");
if ($top_posting_res) while ($row = mysqli_fetch_assoc($top_posting_res)) $top_posting_rows[] = $row;

$approved_count = 0;
$rejected_count = 0;
foreach ($decision_rows as $decision_row) {
    if ($decision_row['label'] === 'Approved') $approved_count = (int) $decision_row['count'];
    if ($decision_row['label'] === 'Rejected') $rejected_count = (int) $decision_row['count'];
}

$query = http_build_query(['start_date' => $start, 'end_date' => $end, 'search' => $search]);
page_head('Reports');
hr_sidebar('reports');
$notification_count = 0;
$notif_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM internship_applications WHERE is_deleted = 0 AND (COALESCE(verification_status, 'Pending') = 'Pending' OR DATE(applied_date) = CURDATE())");
if ($notif_result) {
    $notification_count = (int) mysqli_fetch_assoc($notif_result)['total'];
}
$current_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User';
$current_role = ucfirst((string) ($_SESSION['role'] ?? 'HR'));
$avatar_initial = strtoupper(substr((string) $current_name, 0, 1)) ?: 'U';
?>
<main class="min-h-screen pl-60 bg-[#f8f9fa]">
  <header class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm">
    <div class="mx-auto flex flex-col gap-4 px-8 py-4 xl:flex-row xl:items-center xl:justify-between">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Reports</h1>
        <p class="text-sm text-slate-500 mt-2">Application analytics, status distribution, monthly hiring, and college-wise reports.</p>
      </div>
      <div class="flex items-center gap-4">
        <button type="button" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-50 transition" title="Pending/new application items">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($notification_count > 0): ?><span class="absolute -right-1 -top-1 grid h-5 min-w-[20px] place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"><?php echo $notification_count > 99 ? '99+' : $notification_count; ?></span><?php endif; ?>
        </button>
        <div class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-3 py-2">
          <div class="grid h-10 w-10 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white"><?php echo e($avatar_initial); ?></div>
          <div class="hidden text-left sm:block">
            <p class="text-sm font-semibold text-slate-900"><?php echo e($current_name); ?></p>
            <p class="text-xs uppercase tracking-[0.16em] text-slate-500"><?php echo e($current_role); ?></p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="mx-auto max-w-[1600px] px-8 py-6">
    <form method="get" action="reports.php" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
      <div class="relative">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
        <input name="search" value="<?php echo e($search); ?>" placeholder="Search applications, candidates, colleges..." class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100" type="search">
      </div>
    </form>

    <div class="mt-6 flex flex-wrap gap-2">
      <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">
        <span class="material-symbols-outlined">filter_list</span>
        Filters
      </button>
      <a href="export_report.php?format=csv&<?php echo e($query); ?>" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
        <span class="material-symbols-outlined">download</span>
        Export
      </a>
    </div>
    <?php echo module_flash(); ?>
    <form method="get" class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-4">
        <input type="date" name="start_date" value="<?php echo e($start); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="date" name="end_date" value="<?php echo e($end); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply</button>
        <a href="reports.php" class="rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
    </form>
<?php if ($total === 0): ?>
<div class="rounded-lg border border-slate-200 bg-white p-10 text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400"><span class="material-symbols-outlined">analytics</span></div>
    <h2 class="mt-4 text-lg font-bold text-slate-900">No reports generated yet</h2>
    <p class="mt-2 text-sm text-slate-500">Applications will appear here once candidates start applying.</p>
</div>
<?php else: ?>
<div class="grid gap-5 lg:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-6"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Applications</p><p class="mt-3 text-4xl font-extrabold"><?php echo number_format($total); ?></p></div>
    <div class="rounded-lg border border-slate-200 bg-white p-6"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Approved</p><p class="mt-3 text-4xl font-extrabold text-emerald-700"><?php echo number_format($approved_count); ?></p></div>
    <div class="rounded-lg border border-slate-200 bg-white p-6"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Rejected</p><p class="mt-3 text-4xl font-extrabold text-red-700"><?php echo number_format($rejected_count); ?></p></div>
    <div class="rounded-lg border border-slate-200 bg-white p-6"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Top Colleges</p><p class="mt-3 text-4xl font-extrabold"><?php echo number_format(count($college_rows)); ?></p></div>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-white p-6"><h2 class="text-lg font-bold">Applications by Status</h2><canvas class="mt-5" id="statusChart" height="150"></canvas></div>
    <div class="rounded-lg border border-slate-200 bg-white p-6"><h2 class="text-lg font-bold">Monthly Applications</h2><canvas class="mt-5" id="monthlyChart" height="150"></canvas></div>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-white p-6"><h2 class="text-lg font-bold">Approved vs Rejected</h2><canvas class="mt-5" id="decisionChart" height="150"></canvas></div>
    <div class="rounded-lg border border-slate-200 bg-white p-6"><h2 class="text-lg font-bold">Postings with Highest Applicants</h2><canvas class="mt-5" id="postingChart" height="150"></canvas></div>
</div>
<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-bold">College-wise Report</h2>
    <div class="mt-4 divide-y divide-slate-100">
        <?php if (!empty($college_rows)): foreach ($college_rows as $row): ?>
            <div class="flex justify-between py-3 text-sm"><span><?php echo e($row['label']); ?></span><strong><?php echo number_format((int) $row['count']); ?></strong></div>
        <?php endforeach; else: ?>
            <p class="py-6 text-sm text-slate-500">No college data available yet.</p>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartPalette = ['#2563eb', '#f59e0b', '#7c3aed', '#06b6d4', '#059669', '#dc2626', '#16a34a', '#64748b'];
new Chart(document.getElementById('statusChart'), {type:'doughnut', data:{labels:<?php echo json_encode(array_column($status_rows, 'label')); ?>, datasets:[{label:'Applications', data:<?php echo json_encode(array_map('intval', array_column($status_rows, 'count'))); ?>, backgroundColor:chartPalette}]}, options:{responsive:true, plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('monthlyChart'), {type:'line', data:{labels:<?php echo json_encode(array_column($monthly_rows, 'label')); ?>, datasets:[{label:'Applications', data:<?php echo json_encode(array_map('intval', array_column($monthly_rows, 'count'))); ?>, borderColor:'#059669', backgroundColor:'rgba(5,150,105,.12)', fill:true, tension:.35}]}, options:{responsive:true, plugins:{legend:{display:false}}}});
new Chart(document.getElementById('decisionChart'), {type:'pie', data:{labels:<?php echo json_encode(array_column($decision_rows, 'label')); ?>, datasets:[{data:<?php echo json_encode(array_map('intval', array_column($decision_rows, 'count'))); ?>, backgroundColor:['#059669', '#dc2626']}]}, options:{responsive:true, plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('postingChart'), {type:'bar', data:{labels:<?php echo json_encode(array_column($top_posting_rows, 'label')); ?>, datasets:[{label:'Applicants', data:<?php echo json_encode(array_map('intval', array_column($top_posting_rows, 'count'))); ?>, backgroundColor:'#2563eb'}]}, options:{indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true, ticks:{precision:0}}}}});
</script>
<?php endif; ?>
  </div>
</main>
