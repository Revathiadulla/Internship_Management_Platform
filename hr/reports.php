<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_hr_or_admin();
require_once __DIR__ . '/../includes/db.php';

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$export_csv = isset($_GET['export']) && $_GET['export'] === '1';

$where_clauses = ["a.is_deleted = 0"];
if ($start_date !== '') {
    $start_date_escaped = mysqli_real_escape_string($conn, $start_date);
    $where_clauses[] = "a.applied_date >= '$start_date_escaped 00:00:00'";
}
if ($end_date !== '') {
    $end_date_escaped = mysqli_real_escape_string($conn, $end_date);
    $where_clauses[] = "a.applied_date <= '$end_date_escaped 23:59:59'";
}
$filter_sql = implode(' AND ', $where_clauses);

// Summary counts
$total_sql = "SELECT COUNT(*) AS total FROM internship_applications a WHERE $filter_sql";
$total_result = mysqli_query($conn, $total_sql);
$total_applications = $total_result ? (int) mysqli_fetch_assoc($total_result)['total'] : 0;

$approved_sql = "SELECT COUNT(*) AS approved_count FROM internship_applications a WHERE $filter_sql AND a.status = 'Selected'";
$approved_result = mysqli_query($conn, $approved_sql);
$approved_count = $approved_result ? (int) mysqli_fetch_assoc($approved_result)['approved_count'] : 0;

$rejected_sql = "SELECT COUNT(*) AS rejected_count FROM internship_applications a WHERE $filter_sql AND a.status = 'Rejected'";
$rejected_result = mysqli_query($conn, $rejected_sql);
$rejected_count = $rejected_result ? (int) mysqli_fetch_assoc($rejected_result)['rejected_count'] : 0;

$approval_rate = $total_applications ? round(($approved_count / $total_applications) * 100, 1) : 0;
$rejection_rate = $total_applications ? round(($rejected_count / $total_applications) * 100, 1) : 0;

// Applications per internship
$per_internship_sql = "SELECT COALESCE(i.title, a.internship_name) AS internship_title, COUNT(*) AS count
                       FROM internship_applications a
                       LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                       WHERE $filter_sql
                       GROUP BY internship_title
                       ORDER BY count DESC
                       LIMIT 12";
$per_internship_result = mysqli_query($conn, $per_internship_sql);
$internship_labels = [];
$internship_counts = [];
while ($row = mysqli_fetch_assoc($per_internship_result)) {
    $internship_labels[] = $row['internship_title'] ?: 'Unnamed Internship';
    $internship_counts[] = (int) $row['count'];
}

// Status distribution
$status_distribution_sql = "SELECT a.status, COUNT(*) AS count
                             FROM internship_applications a
                             WHERE $filter_sql
                             GROUP BY a.status";
$status_distribution_result = mysqli_query($conn, $status_distribution_sql);
$status_labels = [];
$status_counts = [];
while ($row = mysqli_fetch_assoc($status_distribution_result)) {
    $status_labels[] = $row['status'] ?: 'Applied';
    $status_counts[] = (int) $row['count'];
}

// CSV export
if ($export_csv) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="hr_reports_applications.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Application ID', 'Student Name', 'Email', 'Internship Title', 'Status', 'Verification Status', 'Applied Date']);
    $csv_sql = "SELECT a.id AS application_id, COALESCE(sp.full_name, 'Unknown') AS full_name, COALESCE(sp.email, '') AS email,
                       COALESCE(i.title, a.internship_name) AS internship_title, a.status, a.verification_status, a.applied_date
                FROM internship_applications a
                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                WHERE $filter_sql
                ORDER BY a.applied_date DESC";
    $csv_result = mysqli_query($conn, $csv_sql);
    while ($row = mysqli_fetch_assoc($csv_result)) {
        fputcsv($output, [$row['application_id'], $row['full_name'], $row['email'], $row['internship_title'], $row['status'], $row['verification_status'], $row['applied_date']]);
    }
    fclose($output);
    exit();
}
include_once __DIR__ . '/../includes/hr_module_helpers.php';
ensure_module_schema($conn);

$export_url = 'reports.php?export=1'
    . ($start_date !== '' ? '&start_date=' . urlencode($start_date) : '')
    . ($end_date !== '' ? '&end_date=' . urlencode($end_date) : '');

page_shell_start(
    'reports',
    'HR Analytics & Reports',
    'Overview of applications, selection metrics, and status distribution.',
    '<a href="' . $export_url . '" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition"><span class="material-symbols-outlined">download</span> Export CSV</a>'
);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .report-card { background: white; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; }
    .report-card h3 { font-size: 0.85rem; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b; margin-bottom: 0.75rem; }
    .report-card p.value { font-size: 2.5rem; font-weight: 700; color: #0f172a; margin: 0; }
    .report-card p.meta { margin-top: 0.5rem; color: #64748b; font-size: 0.95rem; }
</style>
<div class="space-y-6">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <form method="get" class="grid gap-4 xl:grid-cols-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Start Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700" />
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">End Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700" />
          </div>
          <div class="flex items-end">
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">Apply</button>
          </div>
          <div class="flex items-end">
            <a href="reports.php" class="w-full text-center border border-slate-200 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Reset</a>
          </div>
        </form>
      </div>

      <div class="grid gap-4 xl:grid-cols-4">
        <div class="report-card">
          <h3>Total Applications</h3>
          <p class="value"><?php echo number_format($total_applications); ?></p>
          <p class="meta">Filtered by date range</p>
        </div>
        <div class="report-card">
          <h3>Selection Rate</h3>
          <p class="value"><?php echo number_format($approval_rate, 1); ?>%</p>
          <p class="meta"><?php echo number_format($approved_count); ?> selected applications</p>
        </div>
        <div class="report-card">
          <h3>Rejection Rate</h3>
          <p class="value"><?php echo number_format($rejection_rate, 1); ?>%</p>
          <p class="meta"><?php echo number_format($rejected_count); ?> rejected applications</p>
        </div>
        <div class="report-card">
          <h3>Unique Positions</h3>
          <p class="value"><?php echo number_format(count($internship_counts)); ?></p>
          <p class="meta">Receiving applications</p>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <div class="report-card">
          <h3>Applications per Internship</h3>
          <div class="relative h-[260px] w-full">
            <canvas id="internshipChart"></canvas>
          </div>
        </div>
        <div class="report-card">
          <h3>Status Distribution</h3>
          <div class="relative h-[260px] w-full">
            <canvas id="statusChart"></canvas>
          </div>
        </div>
      </div>
</div>

  <script>
    const internshipCtx = document.getElementById('internshipChart');
    new Chart(internshipCtx, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($internship_labels); ?>,
        datasets: [{
          label: 'Applications',
          data: <?php echo json_encode($internship_counts); ?>,
          backgroundColor: 'rgba(59, 130, 246, 0.7)',
          borderColor: 'rgba(37, 99, 235, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { color: '#475569' } },
          y: { beginAtZero: true, ticks: { color: '#475569' } }
        },
        plugins: { legend: { display: false } }
      }
    });

    const statusCtx = document.getElementById('statusChart');
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($status_counts); ?>,
          backgroundColor: [
            '#3b82f6', '#f59e0b', '#8b5cf6', '#6366f1', '#10b981', '#ef4444'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#475569' } } }
      }
    });
  </script>
<?php page_shell_end(); ?>
