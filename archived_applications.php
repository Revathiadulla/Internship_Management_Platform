<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('applications');
include "db.php";
include "status_utils.php";
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

// Filter and search values
$status_options       = ['Applied', 'Test Completed', 'Interview Scheduled', 'HR Round', 'HOD Approved', 'Selected', 'Offer Sent', 'Onboarding Completed', 'Rejected'];
$verification_options = ['Pending', 'Verified', 'Rejected'];
$archived_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internship_applications WHERE is_deleted = 1"))['c'] ?? 0);
$status_filter       = isset($_GET['status'])              ? trim($_GET['status'])              : '';
$verification_filter = isset($_GET['verification_status']) ? trim($_GET['verification_status']) : '';
$title_filter        = isset($_GET['title'])               ? trim($_GET['title'])               : '';
$job_posting_filter  = isset($_GET['job_posting_id'])      ? intval($_GET['job_posting_id'])    : 0;
$search_query        = isset($_GET['search'])              ? trim($_GET['search'])              : '';

// Pagination
$per_page    = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;

// Build shared WHERE clause (used for both COUNT and data query)
$where_clauses = ["a.is_deleted = 1"];
if (in_array($status_filter, $status_options, true)) {
    $where_clauses[] = "a.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if (in_array($verification_filter, $verification_options, true)) {
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
$where_sql = implode(' AND ', $where_clauses);

// Total count for pagination (same filters, no LIMIT)
$count_sql    = "SELECT COUNT(*) as total
                 FROM internship_applications a
                 LEFT JOIN internships i      ON a.internship_id = i.id AND a.internship_id > 0
                 LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                 WHERE $where_sql";
$count_result = mysqli_query($conn, $count_sql);
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
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.verification_status,
                   sp.full_name, sp.email, sp.college_name, sp.course,
                   sp.resume_file, $resume_url_select
            FROM internship_applications a
            LEFT JOIN internships i       ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE $where_sql
            ORDER BY a.applied_date DESC
            LIMIT $per_page OFFSET $offset";
$app_result = mysqli_query($conn, $app_sql);

// Build query string helper — preserves all active filters when changing page
function paginate_url(int $page, array $filters): string {
    $params = array_filter($filters, fn($v) => $v !== '');
    $params['page'] = $page;
    return 'archived_applications.php?' . http_build_query($params);
}

page_shell_start('archived_applications', 'Archived Applications', 'View and restore deleted or archived internship applications', '<a href="hr_applications.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all"><span class="material-symbols-outlined">arrow_back</span> Back to Applications <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">' . $archived_count . '</span></a>');
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
            <a href="archived_applications.php" class="w-full text-center border border-slate-200 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Reset</a>
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
          $active_labels = [];
          if ($search_query)        $active_labels[] = '"' . htmlspecialchars($search_query) . '"';
          if ($status_filter)       $active_labels[] = 'status: ' . htmlspecialchars($status_filter);
          if ($verification_filter) $active_labels[] = 'verification: ' . htmlspecialchars($verification_filter);
          if ($title_filter)        $active_labels[] = 'title: "' . htmlspecialchars($title_filter) . '"';
        ?>
        <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
          <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-5">
            <span class="material-symbols-outlined text-[32px] text-slate-400">
              archive
            </span>
          </div>
          <h3 class="text-base font-bold text-slate-700 mb-1">
            No archived applications found
          </h3>
          <p class="text-sm text-slate-400 max-w-sm">
            <?php if ($has_filters): ?>
              No results for <?php echo implode(', ', $active_labels); ?>.
              Try adjusting your filters or search keywords.
            <?php else: ?>
              All deleted or archived candidate applications will appear here.
            <?php endif; ?>
          </p>
          <?php if ($has_filters): ?>
          <a href="archived_applications.php"
             class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
            Reset filters
          </a>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                <th class="py-4 px-6">Candidate</th>
                <th class="py-4 px-6">Internship</th>
                <th class="py-4 px-6">Applied Date</th>
                <th class="py-4 px-6">Education</th>
                <th class="py-4 px-6">Current Status</th>
                <th class="py-4 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
              <?php while ($app = mysqli_fetch_assoc($app_result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
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
                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($app['title']); ?></p>
                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['duration']); ?> • <?php echo htmlspecialchars($app['mode']); ?></p>
                  </td>
                  <td class="py-4 px-6 text-slate-500 font-medium">
                    <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                  </td>
                  <td class="py-4 px-6">
                    <span class="px-2 py-1 <?php echo ($app['education_status'] === 'Pursuing') ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-purple-50 text-purple-700 border-purple-100'; ?> rounded text-[10px] font-bold border uppercase">
                      <?php echo htmlspecialchars($app['education_status']); ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getStatusBadgeClass($app['status']); ?>">
                      <?php echo htmlspecialchars($app['status']); ?>
                    </span>
                    <div class="mt-2">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getVerificationBadgeClass($app['verification_status'] ?? 'Pending'); ?>">
                        <?php echo htmlspecialchars($app['verification_status'] ?: 'Pending'); ?>
                      </span>
                    </div>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button onclick="restoreApplication(<?php echo $app['app_id']; ?>, '<?php echo htmlspecialchars($app['full_name']); ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg transition-colors cursor-pointer" title="Restore Application">
                        <span class="material-symbols-outlined text-[15px]">restore</span> Restore
                      </button>
                    </div>
                  </td>
                </tr>
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
          <p class="text-sm text-slate-500 order-2 sm:order-1">
            <?php echo $total_rows; ?> result<?php echo $total_rows !== 1 ? 's' : ''; ?> &mdash;
            page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
          </p>

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

            <?php if ($page_start > 1): ?>
              <a href="<?php echo htmlspecialchars(paginate_url(1, $filters)); ?>"
                 class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">1</a>
              <?php if ($page_start > 2): ?>
                <span class="px-2 text-slate-400 text-sm">…</span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $page_start; $p <= $page_end; $p++): ?>
              <?php if ($p === $current_page): ?>
                <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm"><?php echo $p; ?></span>
              <?php else: ?>
                <a href="<?php echo htmlspecialchars(paginate_url($p, $filters)); ?>"
                   class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors"><?php echo $p; ?></a>
              <?php endif; ?>
            <?php endfor; ?>

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
        <?php endif; ?>

      </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center" id="toast-icon-container">
      <span class="material-symbols-outlined text-[20px]" id="toast-icon">check_circle</span>
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-wider" id="toast-title">Success</p>
      <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-message">Application restored successfully</p>
    </div>
  </div>

  <script>
    async function restoreApplication(appId, name) {
      if (!confirm(`Are you sure you want to restore the application for "${name}"?`)) {
        return;
      }
      
      try {
        const formData = new FormData();
        formData.append('app_id', appId);
        
        const response = await fetch('restore_application.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          showToast('success', 'Restored', result.message);
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast('error', 'Error', result.message);
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to restore application.');
      }
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
  </script>
<?php print_resume_not_found_js(); ?>
<?php page_shell_end(); ?>
