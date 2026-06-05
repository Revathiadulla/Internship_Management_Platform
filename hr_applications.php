<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('applications');
include "db.php";
include "status_utils.php";
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

// Ensure verification_status and soft delete columns exist
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'verification_status'");
if ($col_check && mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN verification_status VARCHAR(20) DEFAULT 'Pending'");
}
$delete_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'is_deleted'");
if ($delete_col_check && mysqli_num_rows($delete_col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
}
// Ensure test-related columns exist
$test_cols = [
    'test_score' => "ALTER TABLE internship_applications ADD COLUMN test_score INT DEFAULT NULL",
    'test_result' => "ALTER TABLE internship_applications ADD COLUMN test_result VARCHAR(20) DEFAULT NULL",
    'test_answers' => "ALTER TABLE internship_applications ADD COLUMN test_answers TEXT DEFAULT NULL",
    'test_submitted_date' => "ALTER TABLE internship_applications ADD COLUMN test_submitted_date DATETIME DEFAULT NULL"
];
foreach ($test_cols as $col => $sql) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Ensure document verification columns exist in internship_applications
$verif_cols = [
    'aadhaar_verification_status' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_verification_status VARCHAR(50) DEFAULT 'Pending'",
    'pan_verification_status' => "ALTER TABLE internship_applications ADD COLUMN pan_verification_status VARCHAR(50) DEFAULT 'Pending'",
    'document_verification_status' => "ALTER TABLE internship_applications ADD COLUMN document_verification_status VARCHAR(50) DEFAULT 'Pending'",
    'aadhaar_status' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_status ENUM('pending','verified','rejected') DEFAULT 'pending'",
    'pan_status' => "ALTER TABLE internship_applications ADD COLUMN pan_status ENUM('pending','verified','rejected') DEFAULT 'pending'",
    'aadhaar_verified_by' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_verified_by INT NULL",
    'pan_verified_by' => "ALTER TABLE internship_applications ADD COLUMN pan_verified_by INT NULL",
    'aadhaar_verified_at' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_verified_at DATETIME NULL",
    'pan_verified_at' => "ALTER TABLE internship_applications ADD COLUMN pan_verified_at DATETIME NULL",
    'hod_status' => "ALTER TABLE internship_applications ADD COLUMN hod_status ENUM('not_required','pending','approved','rejected') DEFAULT 'pending'",
    'hod_id' => "ALTER TABLE internship_applications ADD COLUMN hod_id INT NULL",
    'hod_action_at' => "ALTER TABLE internship_applications ADD COLUMN hod_action_at DATETIME NULL",
    'final_status' => "ALTER TABLE internship_applications ADD COLUMN final_status ENUM('pending','selected','rejected') DEFAULT 'pending'"
];
foreach ($verif_cols as $col => $sql) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Ensure document verification columns exist in student_profiles
$sp_verif_cols = [
    'aadhaar_verification_status' => "ALTER TABLE student_profiles ADD COLUMN aadhaar_verification_status VARCHAR(50) DEFAULT 'Pending'",
    'pan_verification_status' => "ALTER TABLE student_profiles ADD COLUMN pan_verification_status VARCHAR(50) DEFAULT 'Pending'",
    'student_type' => "ALTER TABLE student_profiles ADD COLUMN student_type ENUM('pursuing','passed_out') DEFAULT 'pursuing'"
];
foreach ($sp_verif_cols as $col => $sql) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Filter and search values
$status_options       = ['Applied', 'Test Completed', 'Documents Verified', 'HR Round', 'HOD Approval Pending', 'HOD Approved', 'Selected', 'Interview Scheduled', 'Offer Sent', 'Onboarding Completed', 'Rejected'];
$verification_options = ['Pending', 'Verified', 'Rejected'];
// Determine view mode: 'review' shows only test‑completed applications, 'all' shows every applicant
$view = isset($_GET['view']) ? trim($_GET['view']) : 'review';
// Base where clause
$where_clauses = ["a.is_deleted = 0"]; 
// Apply view‑specific filter
if ($view === 'review') {
    $where_clauses[] = "a.test_score IS NOT NULL";
}
// Previously, 'review' filtered to Test Completed and passing score. This is removed to show all.
// if ($view === 'review') {
//     $where_clauses[] = "a.status = 'Test Completed'";
//     $where_clauses[] = "a.test_score >= 60";
// }

// No additional filter for review mode; show all applications

// Existing filters remain unchanged
$status_filter       = isset($_GET['status'])              ? trim($_GET['status'])              : '';;
$verification_filter = isset($_GET['verification_status']) ? trim($_GET['verification_status']) : '';
$title_filter        = isset($_GET['title'])               ? trim($_GET['title'])               : '';
$job_posting_filter  = isset($_GET['job_posting_id'])      ? intval($_GET['job_posting_id'])    : 0;
$search_query        = isset($_GET['search'])              ? trim($_GET['search'])              : '';
// Pagination
$per_page    = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;
// Build shared WHERE clause (used for both COUNT and data query)
// Additional filters will be appended below if provided
if (in_array($status_filter, $status_options, true) && $status_filter !== '') {
    $where_clauses[] = "a.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if (in_array($verification_filter, $verification_options, true) && $verification_filter !== '') {
    $where_clauses[] = "a.verification_status = '" . mysqli_real_escape_string($conn, $verification_filter) . "'";
}
if ($title_filter !== '') {
    $title_escaped   = mysqli_real_escape_string($conn, $title_filter);
    $where_clauses[] = "COALESCE(i.title, a.internship_name) LIKE '%$title_escaped%'";
}
if ($job_posting_filter > 0) {
    $where_clauses[] = "a.job_posting_id = $job_posting_filter";
}
if ($search_query !== '') {
    $search_escaped  = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(sp.full_name LIKE '%$search_escaped%' OR sp.email LIKE '%$search_escaped%')";
}
// Build WHERE clause for current view
$where_sql = implode(' AND ', $where_clauses);

// Prepare WHERE clauses for tab badge counts
$where_clauses_no_view = $where_clauses;
// Remove any view-specific filter (test_score condition)
foreach ($where_clauses_no_view as $key => $clause) {
    if (strpos($clause, 'a.test_score') !== false) {
        unset($where_clauses_no_view[$key]);
    }
}
// Removed outdated exclusion filters for review view
    // WHERE clause for HR Review tab (test_score IS NOT NULL)
    $where_clauses_review = $where_clauses_no_view;
    $where_clauses_review[] = "a.test_score IS NOT NULL";
    // Show ONLY HR Round or HR Review
    $where_clauses_review[] = "a.status = 'HR Review'";
$review_where_sql = implode(' AND ', $where_clauses_review);
if (empty($review_where_sql)) { $review_where_sql = '1'; }
// When showing the HR Review tab, apply the same filter to the main query and pagination
if ($view === 'review') {
    $where_sql = $review_where_sql; // ensures pagination total and data rows match the count
}

// WHERE clause for All Applicants tab (no additional view filter)
$all_where_sql = implode(' AND ', $where_clauses_no_view);
if (empty($all_where_sql)) { $all_where_sql = '1'; }
// Count for HR Review tab
$review_count_sql = "SELECT COUNT(*) as total FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0 LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE $review_where_sql";
$review_count_result = mysqli_query($conn, $review_count_sql);
$review_total = $review_count_result ? (int)mysqli_fetch_assoc($review_count_result)['total'] : 0;
// Count for All Applicants tab
$all_count_sql = "SELECT COUNT(*) as total FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0 LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE $all_where_sql";
$all_count_result = mysqli_query($conn, $all_count_sql);
$all_total = $all_count_result ? (int)mysqli_fetch_assoc($all_count_result)['total'] : 0;

// Total count for pagination (same filters, no LIMIT)
$count_sql    = "SELECT COUNT(*) as total
                 FROM internship_applications a
                 LEFT JOIN internships i      ON a.internship_id = i.id AND a.internship_id > 0
                 LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                 WHERE $where_sql";
$count_result = mysqli_query($conn, $count_sql);
if (!$count_result) {
    die("Count Query Failed: " . mysqli_error($conn) . " | SQL: " . htmlspecialchars($count_sql));
}
$total_rows   = (int) mysqli_fetch_assoc($count_result)['total'];
$total_pages  = max(1, (int) ceil($total_rows / $per_page));

// Clamp current page to valid range
if ($current_page > $total_pages) $current_page = $total_pages;

// Paginated data query
$has_resume_url = false;
$_col_check_res = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'resume_url'");
if ($_col_check_res && mysqli_num_rows($_col_check_res) > 0) {
    $has_resume_url = true;
}
$resume_url_select = $has_resume_url ? "sp.resume_url" : "NULL as resume_url";

$app_sql = "SELECT a.id as app_id, a.user_id, a.status, a.applied_date, a.education_status,
                   a.test_score, a.test_result,
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.verification_status, a.hod_approval_status,
                   sp.full_name, sp.email, sp.college_name, sp.course,
                   sp.resume_file, $resume_url_select,
                   sp.aadhaar_file, sp.pan_file,
                   a.aadhaar_verification_status, a.pan_verification_status,
                   a.aadhaar_status, a.pan_status, a.hod_status, a.final_status,
                   sp.student_type
            FROM internship_applications a
            LEFT JOIN internships i       ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE $where_sql
            ORDER BY a.applied_date DESC
            LIMIT $per_page OFFSET $offset";
$app_result = mysqli_query($conn, $app_sql);
if (!$app_result) {
    die("Database Query Failed: " . mysqli_error($conn) . " | SQL: " . htmlspecialchars($app_sql));
}

// Build query string helper — preserves all active filters when changing page
function paginate_url(int $page, array $filters): string {
    $params = array_filter($filters, fn($v) => $v !== '');
    $params['page'] = $page;
    return 'hr_applications.php?' . http_build_query($params);
}

page_shell_start('applications', 'Applications', 'Review, update status, and manage all internship applications', '<a href="archived_applications.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all"><span class="material-symbols-outlined">archive</span> Archived Applications</a>');
?>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
<div class="flex mb-4 space-x-2">
  <a href="hr_applications.php?view=review" class="px-4 py-2 rounded <?= $view === 'review' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800' ?>">HR Review (<?php echo $review_total; ?>)</a>
  <a href="hr_applications.php?view=all" class="px-4 py-2 rounded <?= $view === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800' ?>">All Applicants (<?php echo $all_total; ?>)</a>
</div>
        <form method="get" class="grid gap-4 xl:grid-cols-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Status</label>
            <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white">
              <option value="">All Statuses</option>
              <?php foreach ($status_options as $status_option): ?>
                <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $status_filter === $status_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Verification</label>
            <select name="verification_status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white">
              <option value="">All Verifications</option>
              <?php foreach ($verification_options as $verification_option): ?>
                <option value="<?php echo htmlspecialchars($verification_option); ?>" <?php echo $verification_filter === $verification_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($verification_option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Internship title</label>
            <input name="title" value="<?php echo htmlspecialchars($title_filter); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700" placeholder="Filter by title" />
          </div>
          <div class="flex items-end gap-2">
            <input type="hidden" name="page" value="1">
            <?php if ($search_query !== ''): ?>
              <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <?php endif; ?>
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">Apply</button>
            <a href="hr_applications.php" class="w-full text-center border border-slate-200 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Reset</a>
          </div>
        </form>
      </div>

      <!-- Applications Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <!-- Table header: record count — hidden when empty state is shown -->
        <?php if ($total_rows > 0): ?>
        <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between">
          <p class="text-sm text-slate-500">
            Showing
            <span class="font-semibold text-slate-800"><?php echo $offset + 1; ?></span>
            –
            <span class="font-semibold text-slate-800"><?php echo min($offset + $per_page, $total_rows); ?></span>
            of
            <span class="font-semibold text-slate-800"><?php echo $total_rows; ?></span>
            application<?php echo $total_rows !== 1 ? 's' : ''; ?>
            <?php if ($status_filter || $verification_filter || $title_filter || $search_query): ?>
              <span class="text-blue-600 font-medium">(filtered)</span>
            <?php endif; ?>
          </p>
          <p class="text-xs text-slate-400">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></p>
        </div>
        <?php endif; ?>
        <?php if ($total_rows === 0): ?>
        <!-- ── Empty state ── -->
        <?php
          $has_filters = $status_filter || $verification_filter || $title_filter || $search_query;
          // Build a human-readable summary of what was searched/filtered
          $active_labels = [];
          if ($search_query)        $active_labels[] = '"' . htmlspecialchars($search_query) . '"';
          if ($status_filter)       $active_labels[] = 'status: ' . htmlspecialchars($status_filter);
          if ($verification_filter) $active_labels[] = 'verification: ' . htmlspecialchars($verification_filter);
          if ($title_filter)        $active_labels[] = 'title: "' . htmlspecialchars($title_filter) . '"';
        ?>
        <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
          <!-- Icon -->
          <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-5">
            <span class="material-symbols-outlined text-[32px] text-slate-400">
              <?php echo $has_filters ? 'filter_list_off' : 'inbox'; ?>
            </span>
          </div>
          <!-- Heading -->
          <h3 class="text-base font-bold text-slate-700 mb-1">
            <?php if ($view === 'review'): ?>
                No test completed applications available for HR review.
            <?php else: ?>
                <?php echo $has_filters ? 'No applications found' : 'No applications yet'; ?>
            <?php endif; ?>
          </h3>
          <!-- Subtitle -->
          <p class="text-sm text-slate-400 max-w-sm">
            <?php if ($has_filters): ?>
              No results for <?php echo implode(', ', $active_labels); ?>.
              Try adjusting your filters or search keywords.
            <?php else: ?>
              Applications will appear here once candidates start applying.
            <?php endif; ?>
          </p>
          <!-- Reset button — only shown when filters are active -->
          <?php if ($has_filters): ?>
          <a href="hr_applications.php"
             class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
            Reset filters
          </a>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
              <input id="bulk-select-all-top" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
              <span>Select all</span>
            </label>
            <span id="bulk-selected-count" class="text-sm text-slate-500 hidden"></span>
          </div>
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <select id="bulk-action-select" class="w-full sm:w-auto border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white">
              <option value="">Bulk action</option>
              <optgroup label="Application status">
                <option value="move_to_test_completed">Move to Test Completed</option>
                <option value="move_to_hr_round">Move to HR Round</option>
                <option value="move_to_hod_approved">Move to HOD Approved</option>
                <option value="select_candidate">Select</option>
                <option value="reject">Reject</option>
              </optgroup>
              <optgroup label="Verification status">
                <option value="verification_pending">Verification Pending</option>
                <option value="verify">Verification Verified</option>
                <option value="verification_rejected">Verification Rejected</option>
              </optgroup>
              <option value="delete">Delete</option>
            </select>
            <button id="bulk-action-apply" type="button" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Apply</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                <th class="py-4 px-6">Student Name</th>
                <th class="py-4 px-6">Internship Applied</th>
                <th class="py-4 px-6">Applied Date</th>
                <th class="py-4 px-6">Test Percentage</th>
                <th class="py-4 px-6">Current Status</th>
                <th class="py-4 px-6">Aadhaar Status</th>
                <th class="py-4 px-6">PAN Status</th>
                <th class="py-4 px-6 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
              <?php while ($app = mysqli_fetch_assoc($app_result)): 
                  $a_status = $app['aadhaar_status'] ?? 'pending';
                  $p_status = $app['pan_status'] ?? 'pending';
              ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <!-- Student Name -->
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-3">
                      <img class="w-10 h-10 rounded-full border border-slate-200" src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['full_name']); ?>&background=random" alt="<?php echo htmlspecialchars($app['full_name']); ?>">
                      <div>
                        <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($app['full_name']); ?></p>
                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['college_name']); ?></p>
                      </div>
                    </div>
                  </td>
                  <!-- Internship Applied -->
                  <td class="py-4 px-6">
                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($app['title']); ?></p>
                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['duration']); ?> • <?php echo htmlspecialchars($app['mode']); ?></p>
                  </td>
                  <!-- Applied Date -->
                  <td class="py-4 px-6 text-slate-500 font-medium">
                    <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                  </td>
                  <!-- Test Score -->
                  <td class="py-4 px-6">
                      <?php if (!empty($app['test_score'])): ?>
                        <span class="font-medium text-slate-800"><?php echo $app['test_score']; ?>%</span>
                      <?php else: ?>
                        <span class="text-slate-400">N/A</span>
                      <?php endif; ?>
                  </td>
                  <!-- Current Status -->
                  <td class="py-4 px-6">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getStatusBadgeClass($app['status']); ?>">
                      <?php echo htmlspecialchars($app['status']); ?>
                    </span>
                  </td>
                  <!-- Aadhaar Status -->
                  <td class="py-4 px-6">
                    <?php
                    $a_badge = 'bg-gray-100 text-gray-700 border-gray-200';
                    if ($a_status === 'verified') $a_badge = 'bg-green-100 text-green-700 border-green-200';
                    if ($a_status === 'rejected') $a_badge = 'bg-red-100 text-red-700 border-red-200';
                    ?>
                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?php echo $a_badge; ?>">
                      <?php echo htmlspecialchars($a_status); ?>
                    </span>
                  </td>
                  <!-- PAN Status -->
                  <td class="py-4 px-6">
                    <?php
                    $p_badge = 'bg-gray-100 text-gray-700 border-gray-200';
                    if ($p_status === 'verified') $p_badge = 'bg-green-100 text-green-700 border-green-200';
                    if ($p_status === 'rejected') $p_badge = 'bg-red-100 text-red-700 border-red-200';
                    ?>
                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?php echo $p_badge; ?>">
                      <?php echo htmlspecialchars($p_status); ?>
                    </span>
                  </td>
                  <!-- Actions -->
                  <td class="py-4 px-6 text-center">
                    <a href="hr_applicant_detail.php?app_id=<?php echo $app['app_id']; ?>" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-semibold hover:bg-blue-100 transition inline-block">
                      View
                    </a>
                  </td>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php endif; /* end empty-state else */ ?>

        <!-- Pagination bar — only shown when there are results -->
        <?php if ($total_rows > 0): ?>
        <?php
          $filters = [
              'status'              => $status_filter,
              'verification_status' => $verification_filter,
              'title'               => $title_filter,
              'search'              => $search_query,
          ];
          $window     = 2;
          $page_start = max(1, $current_page - $window);
          $page_end   = min($total_pages, $current_page + $window);
        ?>
        <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
          <!-- Left: summary -->
          <p class="text-sm text-slate-500 order-2 sm:order-1">
            <?php echo $total_rows; ?> result<?php echo $total_rows !== 1 ? 's' : ''; ?> &mdash;
            page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
          </p>

          <!-- Right: page buttons -->
          <nav class="flex items-center gap-1 order-1 sm:order-2" aria-label="Pagination">

            <!-- Previous -->
            <?php if ($current_page > 1): ?>
              <a href="<?php echo htmlspecialchars(paginate_url($current_page - 1, $filters)); ?>"
                 class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-[16px]">chevron_left</span> Prev
              </a>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-sm text-slate-300 cursor-not-allowed">
                <span class="material-symbols-outlined text-[16px]">chevron_left</span> Prev
              </span>
            <?php endif; ?>

            <!-- First page + ellipsis -->
            <?php if ($page_start > 1): ?>
              <a href="<?php echo htmlspecialchars(paginate_url(1, $filters)); ?>"
                 class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">1</a>
              <?php if ($page_start > 2): ?>
                <span class="px-2 text-slate-400 text-sm">…</span>
              <?php endif; ?>
            <?php endif; ?>

            <!-- Page window -->
            <?php for ($p = $page_start; $p <= $page_end; $p++): ?>
              <?php if ($p === $current_page): ?>
                <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm"><?php echo $p; ?></span>
              <?php else: ?>
                <a href="<?php echo htmlspecialchars(paginate_url($p, $filters)); ?>"
                   class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors"><?php echo $p; ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <!-- Ellipsis + last page -->
            <?php if ($page_end < $total_pages): ?>
              <?php if ($page_end < $total_pages - 1): ?>
                <span class="px-2 text-slate-400 text-sm">…</span>
              <?php endif; ?>
              <a href="<?php echo htmlspecialchars(paginate_url($total_pages, $filters)); ?>"
                 class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors"><?php echo $total_pages; ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($current_page < $total_pages): ?>
              <a href="<?php echo htmlspecialchars(paginate_url($current_page + 1, $filters)); ?>"
                 class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                Next <span class="material-symbols-outlined text-[16px]">chevron_right</span>
              </a>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-sm text-slate-300 cursor-not-allowed">
                Next <span class="material-symbols-outlined text-[16px]">chevron_right</span>
              </span>
            <?php endif; ?>

          </nav>
        </div>
        <?php endif; /* end pagination if $total_rows > 0 */ ?>

      </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center" id="toast-icon-container">
      <span class="material-symbols-outlined text-[20px]" id="toast-icon">check_circle</span>
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-wider" id="toast-title">Success</p>
      <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-message">Status updated successfully</p>
    </div>
  </div>

  <script>
    // Add HOD approval button handler
    document.querySelectorAll('.send-hod-approval-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const appId = this.dataset.appId;
            const formData = new FormData();
            formData.append('application_id', appId);
            try {
                const response = await fetch('send_hod_approval.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast('success', 'Success', result.message);
                    // Optionally refresh to show updated status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', result.message);
                }
            } catch (e) {
                showToast('error', 'Error', 'Failed to send HOD approval request');
            }
        });
    });

    // Status update handler
    document.querySelectorAll('.status-update-select').forEach(select => {
      select.addEventListener('change', async function() {
        const appId = this.dataset.appId;
        const newStatus = this.value;
        
        if (!newStatus) return;
        
        // Confirm action
        if (!confirm(`Update application status to "${newStatus}"?`)) {
          this.value = '';
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('application_id', appId);
          formData.append('new_status', newStatus);
          formData.append('notes', 'Status updated by HR');
          
          const response = await fetch('update_application_status.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Error', result.message);
            this.value = '';
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to update status');
          this.value = '';
        }
      });
    });

    document.querySelectorAll('.verification-update-select').forEach(select => {
      select.addEventListener('change', async function() {
        const appId = this.dataset.appId;
        const newVerification = this.value;
        if (!newVerification) return;

        if (!confirm(`Update verification status to "${newVerification}"?`)) {
          this.value = '';
          return;
        }

        try {
          const formData = new FormData();
          formData.append('application_id', appId);
          formData.append('verification_status', newVerification);

          const response = await fetch('update_verification_status.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Error', result.message);
            this.value = '';
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to update verification status');
          this.value = '';
        }
      });
    });

    const bulkSelectAllTop = document.getElementById('bulk-select-all-top');
    const bulkSelectAll = document.getElementById('bulk-select-all');
    const bulkRows = Array.from(document.querySelectorAll('.bulk-select-row'));
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkApplyButton = document.getElementById('bulk-action-apply');
    const bulkSelectedCount = document.getElementById('bulk-selected-count');

    function updateBulkSelectionDisplay() {
      const selectedRows = bulkRows.filter(row => row.checked);
      const count = selectedRows.length;
      const enabled = count > 0 && bulkActionSelect.value !== '';
      bulkApplyButton.disabled = !enabled;
      bulkSelectedCount.textContent = count > 0 ? `${count} selected` : '';
      bulkSelectedCount.classList.toggle('hidden', count === 0);
      const allChecked = selectedRows.length === bulkRows.length && bulkRows.length > 0;
      if (bulkSelectAll) bulkSelectAll.checked = allChecked;
      if (bulkSelectAllTop) bulkSelectAllTop.checked = allChecked;
    }

    function setBulkSelection(checked) {
      bulkRows.forEach(row => row.checked = checked);
      updateBulkSelectionDisplay();
    }

    if (bulkSelectAll) {
      bulkSelectAll.addEventListener('change', function() {
        setBulkSelection(this.checked);
      });
    }

    if (bulkSelectAllTop) {
      bulkSelectAllTop.addEventListener('change', function() {
        setBulkSelection(this.checked);
      });
    }

    bulkRows.forEach(row => {
      row.addEventListener('change', updateBulkSelectionDisplay);
    });

    if (bulkActionSelect) {
      bulkActionSelect.addEventListener('change', updateBulkSelectionDisplay);
    }

    if (bulkApplyButton) {
      bulkApplyButton.addEventListener('click', async function() {
        const selectedIds = bulkRows.filter(row => row.checked).map(row => row.dataset.appId).filter(Boolean);
        const action = bulkActionSelect.value;
        if (selectedIds.length === 0) {
          showToast('error', 'No Selection', 'Select one or more applications before applying bulk action.');
          return;
        }
        if (!action) {
          showToast('error', 'Choose Action', 'Choose a bulk action to apply.');
          return;
        }

        const actionLabelMap = {
          move_to_test_completed: 'Move to Test Completed',
          move_to_hr_round: 'Move to HR Round',
          move_to_hod_approved: 'Move to HOD Approved',
          select_candidate: 'Select',
          reject: 'Reject',
          verification_pending: 'Verification Pending',
          verify: 'Verify',
          verification_rejected: 'Verification Rejected',
          delete: 'Delete'
        };
        const confirmText = `Are you sure you want to perform bulk action "${actionLabelMap[action] || action}" on ${selectedIds.length} application(s)?`;
        if (!confirm(confirmText)) {
          return;
        }

        try {
          const formData = new FormData();
          selectedIds.forEach(id => formData.append('application_ids[]', id));
          formData.append('action', action);

          const response = await fetch('bulk_update_applications.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (result.success) {
            showToast('success', 'Bulk update complete', result.message || 'Applications updated successfully.');
            setTimeout(() => location.reload(), 1300);
          } else {
            showToast('error', 'Bulk update failed', result.message || 'Unable to apply bulk action.');
          }
        } catch (error) {
          showToast('error', 'Error', 'Bulk action request failed.');
          console.error(error);
        }
      });
    }
    
    function showToast(type, title, message) {
      const toast = document.getElementById('toast');
      const toastIcon = document.getElementById('toast-icon');
      const toastIconContainer = document.getElementById('toast-icon-container');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      
      if (type === 'success') {
        toast.classList.remove('border-red-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600');
        toastTitle.classList.add('text-green-600');
      } else {
        toast.classList.remove('border-green-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 500);
      }, 3000);
    }
  // Reminder email handler
  document.querySelectorAll('.reminder-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const appId = this.dataset.appId;
      if (!appId) return;
      if (!confirm('Send reminder email to the applicant?')) return;

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        const response = await fetch('send_reminder_email.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          showToast('success', 'Email Sent', result.message);
        } else {
          showToast('error', 'Error', result.message || 'Failed to send email.');
        }
      } catch (e) {
        showToast('error', 'Error', 'Network error while sending reminder.');
      }
    });
  });

  // Single document verification handler
  document.querySelectorAll('.verify-single-doc-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      const docType = this.dataset.docType;
      
      let label = "all documents";
      if (docType === "aadhaar") label = "Aadhaar";
      if (docType === "pan") label = "PAN";

      if (!confirm(`Mark ${label} as Verified for this applicant?`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('verification_status', 'Verified');
        formData.append('verification_type', docType);

        const response = await fetch('update_verification_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Document Verified', result.message);
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Verification Failed', result.message || 'Failed to update verification status.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to submit verification request.');
        console.error(error);
      }
    });
  });

  // Send HOD approval handler
  document.querySelectorAll('.send-hod-approval-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      if (!confirm('Are you sure you want to send a HOD approval request email?')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('new_status', 'HOD Approval Pending');
        formData.append('notes', 'Initiated HOD approval flow via HR review button.');

        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Approval Sent', result.message || 'HOD approval request email sent successfully.');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast('error', 'Failed', result.message || 'Failed to send HOD approval.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to request HOD approval.');
        console.error(error);
      }
    });
  });

  // Direct Select Student handler
  document.querySelectorAll('.select-student-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      if (!confirm('Are you sure you want to select this student for the internship?')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('new_status', 'Selected');
        formData.append('notes', 'Candidate selected directly by HR.');

        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Student Selected', result.message || 'Student has been successfully selected!');
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Selection Failed', result.message || 'Failed to update candidate status.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to select student.');
        console.error(error);
      }
    });
  });
</script>
<?php print_resume_not_found_js(); ?>
<?php page_shell_end(); ?>
