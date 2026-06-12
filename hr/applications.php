<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_module_access('applications');
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/status_utils.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
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

$archived_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internship_applications WHERE is_deleted = 1"))['c'] ?? 0);
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

// Use new official statuses
$status_options       = ['Applied', 'HR Review', 'HOD Approval', 'Selected', 'Project Assignment', 'Active Intern', 'Completed', 'Rejected'];
$verification_options = ['Pending', 'Verified', 'Rejected'];
$where_clauses = [
    "a.is_deleted = 0",
    "a.status NOT IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Internship Completed', 'Certificate Issued', 'Archived')"
];

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
  // Match either the linked posting title / internship_name or the applied_subtype when searching
  $where_clauses[] = "(COALESCE(i.title, a.internship_name) LIKE '%$title_escaped%' OR a.applied_subtype LIKE '%$title_escaped%')";
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
                   -- Display applied_subtype before assignment; after assignment show the project/posting title
                   CASE WHEN COALESCE(a.assigned_project_id, 0) = 0 THEN COALESCE(NULLIF(a.applied_subtype, ''), '') ELSE COALESCE(i.title, a.internship_name) END as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.verification_status, a.hod_approval_status,
                   a.confirmation_letter_path, a.confirmation_letter_sent_at, a.confirmation_letter_sent,
                   sp.full_name, sp.email, sp.college_name, sp.course,
                   sp.resume_file, $resume_url_select,
                   sp.aadhaar_file, sp.pan_file,
                   a.aadhaar_verification_status, a.pan_verification_status,
                   a.aadhaar_status, a.pan_status, a.hod_status, a.final_status,
                   sp.student_type,
                   i.project_type, i.project_subtype,
                   a.applied_subtype,
                   a.preferred_domain, a.internship_name,
                   a.internship_duration
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
    return 'applications.php?' . http_build_query($params);
}

page_shell_start('applications', 'Applications', 'Review, update status, and manage all internship applications', '<a href="archived_applications.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all"><span class="material-symbols-outlined">archive</span> Archived Applications <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">' . $archived_count . '</span></a>');
?>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
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
            <a href="applications.php" class="w-full text-center border border-slate-200 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Reset</a>
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
                <?php echo $has_filters ? 'No applications found' : 'No applications yet'; ?>
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
          <a href="applications.php"
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
              <option value="send_email">Send Email</option>
              <option value="send_confirmation_letter">Send Confirmation Letter</option>
              <option value="archive">Archive selected</option>
            </select>
            <button id="bulk-action-apply" type="button" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Apply</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                <th class="py-4 px-6 w-12"><span class="sr-only">Select</span></th>
                <th class="py-4 px-6">Student Name</th>
                <th class="py-4 px-6">Internship Applied</th>
                <th class="py-4 px-6">Applied Date</th>
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
                  $raw_status = trim((string) ($app['status'] ?? ''));
                  $status_key = strtolower($raw_status);
                  $status_display = $status_key === 'exam_sent' ? 'Exam Sent' : $raw_status;
                  $application_status_label = in_array($status_key, ['selected', 'hr selected', 'hr_selected'], true) ? 'Selected' : $status_display;
                  $letter_sent = !empty($app['confirmation_letter_path']) || !empty($app['confirmation_letter_sent_at']) || (!empty($app['confirmation_letter_sent']) && (int) $app['confirmation_letter_sent'] === 1);
                  $show_confirmation_status = in_array($status_key, ['selected', 'hr selected', 'hr_selected', 'confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'], true);
                  $confirmation_letter_status_label = $letter_sent ? 'Sent' : 'Pending';
                  $bulk_exam_blocked = in_array($status_key, ['exam_sent', 'exam mail sent', 'test completed', 'test_completed'], true);
              ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-4 px-6">
                    <label class="inline-flex items-center text-slate-500">
                      <input type="checkbox"
                             data-app-id="<?php echo $app['app_id']; ?>"
                             data-status="<?php echo htmlspecialchars($raw_status); ?>"
                             data-student-name="<?php echo htmlspecialchars($app['full_name'] ?? '', ENT_QUOTES); ?>"
                             data-student-email="<?php echo htmlspecialchars($app['email'] ?? '', ENT_QUOTES); ?>"
                             data-bulk-disabled="<?php echo $bulk_exam_blocked ? '1' : '0'; ?>"
                             class="bulk-select-row h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                             <?php echo $bulk_exam_blocked ? 'disabled' : ''; ?> />
                      <span class="sr-only">Select application</span>
                    </label>
                  </td>
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
                  <td class="py-4 px-6">
                    <?php
 resolution:
                    // Resolve applied subtype
                    $forbidden_vals = ['awaiting selection', 'assigned project', 'team project', 'imp', 'internship management platform'];

                    $subtype = '';
                    if (!empty($app['applied_subtype']) && !in_array(strtolower(trim($app['applied_subtype'])), $forbidden_vals)) {
                        $subtype = trim($app['applied_subtype']);
                    }
                    if (empty($subtype) && !empty($app['preferred_domain']) && !in_array(strtolower(trim($app['preferred_domain'])), $forbidden_vals)) {
                        $subtype = trim($app['preferred_domain']);
                    }
                    if (empty($subtype) && !empty($app['internship_name']) && !in_array(strtolower(trim($app['internship_name'])), $forbidden_vals)) {
                        $subtype = trim($app['internship_name']);
                    }
                    if (empty($subtype) && !empty($app['project_subtype']) && !in_array(strtolower(trim($app['project_subtype'])), $forbidden_vals)) {
                        $subtype = trim($app['project_subtype']);
                    }

                    if (!empty($subtype)) {
                        $subtype = preg_replace('/(\s+Internship|\s+Intern|\s+Trainee)$/i', '', $subtype);
                    }

                    $proj_type = '';
                    if (!empty($app['project_type']) && !in_array(strtolower(trim($app['project_type'])), $forbidden_vals)) {
                        $proj_type = trim($app['project_type']);
                    }

                    $display_subtype = '';
                    if (!empty($proj_type) && !empty($subtype) && strtolower($proj_type) !== strtolower($subtype)) {
                        $display_subtype = $proj_type . ' - ' . $subtype;
                    } else {
                        $display_subtype = !empty($subtype) ? $subtype : (!empty($proj_type) ? $proj_type : 'General');
                    }
                    ?>
                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($display_subtype); ?></p>
                    <p class="text-xs text-slate-400">
                      <?php 
                      $disp_dur = !empty($app['duration']) ? trim($app['duration']) : (!empty($app['internship_duration']) ? trim($app['internship_duration']) : '');
                      $disp_mode = !empty($app['mode']) ? trim($app['mode']) : '';
                      
                      if ($disp_dur !== '' && $disp_mode !== '') {
                          echo htmlspecialchars($disp_dur . ' • ' . $disp_mode);
                      } else {
                          echo htmlspecialchars($disp_dur !== '' ? $disp_dur : ($disp_mode !== '' ? $disp_mode : 'As per requirement'));
                      }
                      ?>
                    </p>
                  </td>
                  <!-- Applied Date -->
                  <td class="py-4 px-6 text-slate-500 font-medium">
                    <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                  </td>
                  <!-- Current Status -->
                  <td class="py-4 px-6">
                    <div class="flex flex-col items-start gap-2">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getStatusBadgeClass($raw_status); ?>">
                        <?php echo htmlspecialchars(formatStatusLabel($raw_status)); ?>
                      </span>
                      <?php if ($show_confirmation_status): ?>
                      <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-700">
                        <span class="material-symbols-outlined text-[12px]">mail</span>
                        Confirmation Letter Status: <?php echo htmlspecialchars($confirmation_letter_status_label); ?>
                      </span>
                      <?php endif; ?>
                    </div>
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
                    <div class="flex items-center justify-center gap-2">
                      <a href="applicant_detail.php?app_id=<?php echo $app['app_id']; ?>" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-semibold hover:bg-blue-100 transition inline-block">
                        View
                      </a>
                      <button type="button" class="archive-app-btn px-3 py-1.5 bg-amber-50 text-amber-700 rounded-lg text-xs font-semibold hover:bg-amber-100 transition inline-block" data-app-id="<?php echo $app['app_id']; ?>" data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>" data-status="<?php echo htmlspecialchars($app['status'] ?? '', ENT_QUOTES); ?>">
                        Archive
                      </button>
                    </div>
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
  <div id="toast" class="fixed top-6 right-6 z-[60] bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
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
      const selectableRows = bulkRows.filter(row => !row.disabled);
      const selectedRows = selectableRows.filter(row => row.checked);
      const count = selectedRows.length;
      const enabled = count > 0 && bulkActionSelect.value !== '';
      bulkApplyButton.disabled = !enabled;
      bulkSelectedCount.textContent = count > 0 ? `${count} selected` : '';
      bulkSelectedCount.classList.toggle('hidden', count === 0);
      const allChecked = selectableRows.length > 0 && selectedRows.length === selectableRows.length;
      if (bulkSelectAll) bulkSelectAll.checked = allChecked;
      if (bulkSelectAllTop) bulkSelectAllTop.checked = allChecked;
    }

    function setBulkSelection(checked) {
      bulkRows.forEach(row => {
        if (!row.disabled) {
          row.checked = checked;
        }
      });
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
    updateBulkSelectionDisplay();

    if (bulkApplyButton) {
      bulkApplyButton.addEventListener('click', async function() {
        const selectedIds = bulkRows.filter(row => !row.disabled && row.checked).map(row => row.dataset.appId).filter(Boolean);
        const selectedRows = bulkRows.filter(row => !row.disabled && row.checked);
        const action = bulkActionSelect.value;

        if (selectedIds.length === 0) {
          alert('Please select at least one student.');
          showToast('error', 'No Selection', 'Please select at least one student.');
          return;
        }
        if (!action) {
          alert('Please select an action.');
          showToast('error', 'Choose Action', 'Please select an action.');
          return;
        }

        if (action === 'send_email') {
          openBulkExamComposeModal(selectedIds, selectedRows, { action: 'send_email' });
          return;
        }

        if (action === 'send_confirmation_letter') {
          const confirmText = `Are you sure you want to send confirmation letters to ${selectedIds.length} selected applicant(s)?`;
          if (!confirm(confirmText)) {
            return;
          }

          try {
            const formData = new FormData();
            selectedIds.forEach(id => formData.append('application_ids[]', id));
            formData.append('action', action);

            const response = await fetch('bulk_action.php', {
              method: 'POST',
              body: formData
            });

            const result = await response.json();
            if (result.success) {
              showToast('success', 'Confirmation letters sent', result.message || 'Confirmation letters processed successfully.');
              setTimeout(() => location.reload(), 1800);
            } else {
              showToast('error', 'Failed', result.message || 'Unable to send confirmation letters.');
            }
          } catch (error) {
            showToast('error', 'Error', 'Bulk confirmation letter request failed.');
            console.error(error);
          }
          return;
        }

        const actionLabelMap = {
          move_to_hod_approved: 'Move to HOD Approved',
          select_candidate: 'Select',
          reject: 'Reject',
          verification_pending: 'Verification Pending',
          verify: 'Verify',
          verification_rejected: 'Verification Rejected',
          delete: 'Delete',
          archive: 'Archive'
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
        toast.classList.remove('border-red-200', 'border-amber-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100', 'bg-amber-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600', 'text-amber-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600', 'text-amber-600');
        toastTitle.classList.add('text-green-600');
      } else if (type === 'warning') {
        toast.classList.remove('border-red-200', 'border-green-200');
        toast.classList.add('border-amber-200');
        toastIconContainer.classList.remove('bg-red-100', 'bg-green-100');
        toastIconContainer.classList.add('bg-amber-100');
        toastIcon.classList.remove('text-red-600', 'text-green-600');
        toastIcon.classList.add('text-amber-600');
        toastIcon.textContent = 'warning';
        toastTitle.classList.remove('text-red-600', 'text-green-600');
        toastTitle.classList.add('text-amber-600');
      } else {
        toast.classList.remove('border-green-200', 'border-amber-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100', 'bg-amber-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600', 'text-amber-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600', 'text-amber-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      toastMessage.classList.add('whitespace-pre-line');
      
      toast.classList.remove('hidden');
      toast.style.display = 'flex';
      toast.style.opacity = '1';
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
          toast.style.display = 'none';
          toastMessage.classList.remove('whitespace-pre-line');
        }, 500);
      }, 5000);
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

  document.querySelectorAll('.archive-app-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      const appName = this.dataset.name || 'this application';
      const status = (this.dataset.status || '').trim();
      const protectedStatuses = ['Applied', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approved', 'Selected', 'Project Assigned', 'Active Intern'];

      if (protectedStatuses.includes(status)) {
        showToast('warning', 'Archive Restricted', 'Only completed or closed applications can be archived.');
        return;
      }

      if (!confirm(`Archive ${appName}'s application? It will be moved to the archived list.`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('app_id', appId);

        const response = await fetch('archive_application.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Archived', result.message || 'Application archived successfully.');
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Archive Failed', result.message || 'Unable to archive application.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to archive application.');
        console.error(error);
      }
    });
  });

  function getBulkExamBaseUrl() {
    const pathname = window.location.pathname || '/';
    const parts = pathname.split('/').filter(Boolean);
    if (parts.length <= 1) {
      return window.location.origin;
    }
    const appRoot = '/' + parts.slice(0, -1).join('/');
    return window.location.origin + appRoot;
  }

  function openBulkExamComposeModal(selectedIds, selectedRows, options = {}) {
    const modal = document.getElementById('bulk-exam-modal');
    const form = document.getElementById('bulk-exam-form');
    const selectedCountEl = document.getElementById('bulk-exam-selected-count');
    const recipientsEl = document.getElementById('bulk-exam-recipients');
    const previewEl = document.getElementById('bulk-exam-link-preview');
    const previewWrapper = document.getElementById('bulk-exam-preview-wrapper');
    const appIdEl = document.getElementById('bulk-exam-app-id');
    const subjectInput = document.getElementById('bulk-exam-subject');
    const messageInput = document.getElementById('bulk-exam-message');
    const toInput = document.getElementById('bulk-exam-to');
    const ccInput = document.getElementById('bulk-exam-cc');
    const bccInput = document.getElementById('bulk-exam-bcc');
    const modalTitle = document.getElementById('bulk-exam-modal-title');
    const submitBtn = document.getElementById('bulk-exam-submit-btn');
    const singleRecipientNameInput = form.querySelector('input[name="single_recipient_name"]');
    const singleRecipientEmailInput = form.querySelector('input[name="single_recipient_email"]');

    if (!modal || !form || !selectedCountEl || !recipientsEl || !previewEl || !subjectInput || !messageInput) {
      return;
    }

    const selectedRowsData = selectedRows || bulkRows.filter(row => !row.disabled && row.checked);
    const singleRecipient = options.recipient || null;
    const action = options.action || 'send_email';
    const recipients = selectedRowsData
      .map(row => ({
        name: row.dataset.studentName || 'Student',
        email: row.dataset.studentEmail || ''
      }))
      .filter(item => item.email);

    selectedCountEl.textContent = `${selectedRowsData.length} student${selectedRowsData.length === 1 ? '' : 's'} selected`;
    recipientsEl.innerHTML = recipients.length ? recipients.map(item => `<li class="text-sm text-slate-600">${item.name} — ${item.email}</li>`).join('') : '<li class="text-sm text-slate-600">No recipients available.</li>';

    const firstId = (selectedIds || []).find(Boolean);
    const previewRecipient = singleRecipient || (recipients[0] || null);
    const previewUrl = firstId ? `${getBulkExamBaseUrl()}/application_status_timeline.php?application_id=${firstId}` : `${getBulkExamBaseUrl()}/application_status_timeline.php?application_id=APPLICATION_ID`;
    if (previewEl) previewEl.textContent = previewUrl;
    if (appIdEl) {
      appIdEl.textContent = firstId || '-';
    }
    if (singleRecipientNameInput) {
      singleRecipientNameInput.value = previewRecipient && previewRecipient.name ? previewRecipient.name : '';
    }
    if (singleRecipientEmailInput) {
      singleRecipientEmailInput.value = previewRecipient && previewRecipient.email ? previewRecipient.email : '';
    }

    if (previewRecipient && previewRecipient.email) {
      recipientsEl.innerHTML = `<li class="text-sm text-slate-600">${previewRecipient.name} — ${previewRecipient.email}</li>`;
    }

    if (toInput) toInput.value = recipients.map(item => item.email).filter(Boolean).join(', ');
    if (ccInput) ccInput.value = '';
    if (bccInput) bccInput.value = '';

    if (action === 'send_email') {
      if (subjectInput) subjectInput.value = 'Important Update from HR';
      if (messageInput) messageInput.value = [
        'Dear Student,',
        '',
        'This is an update from the HR team regarding your internship application.',
        '',
        'Please review the details and follow the next steps as communicated by the HR team.',
        '',
        'Regards,',
        'HR Team'
      ].join('\n');
      if (previewWrapper) previewWrapper.classList.add('hidden');
      if (modalTitle) modalTitle.textContent = 'Compose Email';
      if (submitBtn) submitBtn.textContent = 'Send Email';
    }

    form.dataset.action = action;
    form.querySelector('input[name="selected_count"]').value = selectedRowsData.length;
    form.querySelectorAll('input[name="application_ids[]"]').forEach(input => input.remove());
    (selectedIds || []).forEach(id => {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'application_ids[]';
      hidden.value = id;
      form.appendChild(hidden);
    });

    modal.classList.remove('hidden');
  }

  document.querySelectorAll('.send-exam-link-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const appId = this.dataset.appId;
      const studentName = this.dataset.studentName || 'Student';
      const studentEmail = this.dataset.studentEmail || '';
      const currentStatus = (this.dataset.status || '').trim().toLowerCase();
      const blockedStatuses = ['exam_sent', 'exam mail sent', 'test completed', 'test_completed', 'selected', 'rejected'];

      if (!appId || blockedStatuses.includes(currentStatus)) {
        return;
      }

      openBulkExamComposeModal([appId], [{
        dataset: {
          studentName,
          studentEmail
        }
      }], {
        recipient: { name: studentName, email: studentEmail }
      });
    });
  });

  // Bulk Exam Form submission handler
  const bulkExamForm = document.getElementById('bulk-exam-form');
  if (bulkExamForm) {
    bulkExamForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const selectedIds = Array.from(this.querySelectorAll('input[name="application_ids[]"]')).map(input => input.value).filter(Boolean);
      if (selectedIds.length === 0) {
        showToast('error', 'No Selection', 'Please select at least one student.');
        return;
      }

      const submitBtn = document.getElementById('bulk-exam-submit-btn');
      const action = this.dataset.action || 'send_email';
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
      
      try {
        const formData = new FormData(this);
        formData.set('action', action);
        
        const response = await fetch('bulk_action.php', {
          method: 'POST',
          body: formData
        });

        let result = {};
        const responseText = await response.text();
        try {
          result = responseText ? JSON.parse(responseText) : {};
        } catch (parseError) {
          console.error('Bulk action parse error', parseError, responseText);
          result = { success: false, title: 'Failed', message: 'No exam links were sent.\nReason: The server returned an invalid response.' };
        }

        const toastType = result.type || (result.success ? 'success' : 'error');
        const toastTitle = result.title || (result.success ? 'Success' : 'Failed');
        let toastMessage = result.message || 'Bulk exam request completed.';

        if (result.success) {
          closeBulkExamModal();
          showToast(toastType, toastTitle, toastMessage);
          setTimeout(() => location.reload(), 2200);
        } else {
          closeBulkExamModal();
          showToast(toastType, toastTitle, toastMessage);
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Email';
        }
      } catch (error) {
        console.error('Bulk action failed', error);
        showToast('error', 'Failed', 'Bulk action failed. Please check server error/logs.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Email';
      }
    });
  }
  
  window.closeBulkExamModal = function() {
    const modal = document.getElementById('bulk-exam-modal');
    const form = document.getElementById('bulk-exam-form');
    if (modal) modal.classList.add('hidden');
    if (form) {
      form.reset();
      form.querySelectorAll('input[name="application_ids[]"]').forEach(input => input.remove());
      const selectedCountInput = form.querySelector('input[name="selected_count"]');
      if (selectedCountInput) {
        selectedCountInput.value = '0';
      }
    }
  }
</script>

<!-- Bulk Exam Mail Modal -->
<div id="bulk-exam-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeBulkExamModal()"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-100">
      <form id="bulk-exam-form" enctype="multipart/form-data" class="p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-lg font-bold text-slate-800" id="bulk-exam-modal-title">Compose Email</h3>
          <button type="button" onclick="closeBulkExamModal()" class="text-slate-400 hover:text-slate-600 transition">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        
        <div class="space-y-4">
          <input type="hidden" name="action" value="send_email">
          <input type="hidden" name="selected_count" value="0">
          <input type="hidden" name="single_recipient_name" value="">
          <input type="hidden" name="single_recipient_email" value="">

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Recipients</p>
            <p id="bulk-exam-selected-count" class="mt-1 text-sm font-semibold text-slate-700">0 students selected</p>
            <p class="mt-2 text-sm text-slate-600"><span class="font-semibold">Application ID:</span> <span id="bulk-exam-app-id">-</span></p>
            <ul id="bulk-exam-recipients" class="mt-2 space-y-1 list-disc pl-5 text-sm text-slate-600"></ul>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
              <span class="mb-1 block">To</span>
              <input id="bulk-exam-to" type="text" name="to" class="w-full rounded-lg border border-slate-200 p-2.5 text-sm text-slate-700 bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition" required>
            </label>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
              <span class="mb-1 block">CC</span>
              <input id="bulk-exam-cc" type="text" name="cc" class="w-full rounded-lg border border-slate-200 p-2.5 text-sm text-slate-700 bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition">
            </label>
          </div>

          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
            <span class="mb-1 block">BCC</span>
            <input id="bulk-exam-bcc" type="text" name="bcc" class="w-full rounded-lg border border-slate-200 p-2.5 text-sm text-slate-700 bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition">
          </label>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Subject</label>
            <input id="bulk-exam-subject" type="text" name="subject" value="Internship Assessment Link" class="w-full rounded-lg border border-slate-200 p-2.5 text-sm text-slate-700 bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition" required>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Message</label>
            <textarea id="bulk-exam-message" name="message" rows="8" class="w-full rounded-lg border border-slate-200 p-2.5 text-sm text-slate-700 bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition" required></textarea>
          </div>

          <div id="bulk-exam-preview-wrapper">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Generated exam link preview</label>
            <div id="bulk-exam-link-preview" class="rounded-lg border border-blue-100 bg-blue-50 p-2.5 text-sm text-blue-700 break-all"></div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Attachment (optional)</label>
            <input type="file" name="attachment_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="w-full text-xs text-slate-700 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
          </div>
        </div>
        
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
          <button type="button" onclick="closeBulkExamModal()" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-semibold hover:bg-slate-50 transition">Cancel</button>
          <button type="submit" id="bulk-exam-submit-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition shadow-sm">Send</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php print_resume_not_found_js(); ?>
<?php page_shell_end(); ?>
