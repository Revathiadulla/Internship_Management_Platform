<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_hr_or_admin();
include 'db.php';
include 'status_utils.php';

// Ensure verification_status and soft delete columns exist
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'verification_status'");
if ($col_check && mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN verification_status VARCHAR(20) DEFAULT 'Pending'");
}
$delete_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'is_deleted'");
if ($delete_col_check && mysqli_num_rows($delete_col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
}
$deleted_at_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_at'");
if ($deleted_at_check && mysqli_num_rows($deleted_at_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_deleted");
}
$deleted_by_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'deleted_by'");
if ($deleted_by_check && mysqli_num_rows($deleted_by_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN deleted_by VARCHAR(100) DEFAULT NULL AFTER deleted_at");
}

// Filter and search values
$status_options       = ['Applied', 'Assessment', 'HR Review', 'Interview', 'Approved', 'Rejected', 'Internship Started'];
$verification_options = ['Pending', 'Verified', 'Rejected'];
$status_filter       = isset($_GET['status'])              ? trim($_GET['status'])              : '';
$verification_filter = isset($_GET['verification_status']) ? trim($_GET['verification_status']) : '';
$title_filter        = isset($_GET['title'])               ? trim($_GET['title'])               : '';
$search_query        = isset($_GET['search'])              ? trim($_GET['search'])              : '';

// Pagination
$per_page     = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;

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
if ($search_query !== '') {
    $search_escaped  = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(COALESCE(u.full_name, sp.full_name) LIKE '%$search_escaped%' OR COALESCE(u.email, sp.email) LIKE '%$search_escaped%')";
}
$where_sql = implode(' AND ', $where_clauses);

$count_sql   = "SELECT COUNT(*) as total
                FROM internship_applications a
                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                WHERE $where_sql";
$count_result = mysqli_query($conn, $count_sql);
$total_rows  = (int) mysqli_fetch_assoc($count_result)['total'];
$total_pages = max(1, (int) ceil($total_rows / $per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $per_page;

$app_sql = "SELECT a.id as app_id, a.user_id, a.status, a.applied_date, a.education_status,
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.verification_status,
                   COALESCE(u.full_name, sp.full_name) as full_name,
                   COALESCE(u.email, sp.email) as email,
                   sp.college_name, sp.course,
                   sp.resume_file,
                   a.deleted_at
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE $where_sql
            ORDER BY a.applied_date DESC
            LIMIT $per_page OFFSET $offset";
$app_result = mysqli_query($conn, $app_sql);

function paginate_url(int $page, array $filters): string {
    $params = array_filter($filters, fn($v) => $v !== '');
    $params['page'] = $page;
    return 'archived_applications.php?' . http_build_query($params);
}

include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

page_shell_start(
    'archived_applications',
    'Archived Applications',
    'Restore accidentally deleted applications back to the active pool.',
    '<a href="workflows.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all"><span class="material-symbols-outlined">account_tree</span> Active Workflows</a>'
);
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
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <?php if ($total_rows === 0): ?>
          <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-5">
              <span class="material-symbols-outlined text-[32px] text-slate-400">archive</span>
            </div>
            <h3 class="text-base font-bold text-slate-700 mb-1">No archived applications yet</h3>
            <p class="text-sm text-slate-400 max-w-sm">Deleted applications will appear here once HR archives them. Use restore to recover any application.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                  <th class="py-4 px-6">Candidate</th>
                  <th class="py-4 px-6">Internship</th>
                  <th class="py-4 px-6">Deleted On</th>
                  <th class="py-4 px-6">Status</th>
                  <th class="py-4 px-6">Verification</th>
                  <th class="py-4 px-6">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                <?php while ($app = mysqli_fetch_assoc($app_result)): ?>
                  <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="py-4 px-6">
                      <div class="flex items-center gap-3">
                        <img class="w-10 h-10 rounded-full border border-slate-200" src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['full_name']); ?>&background=random" alt="<?php echo htmlspecialchars($app['full_name']); ?>">
                        <div>
                          <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($app['full_name'] ?: 'Unknown Candidate'); ?></p>
                          <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['college_name'] ?: 'Unknown college'); ?></p>
                        </div>
                      </div>
                    </td>
                    <td class="py-4 px-6">
                      <p class="font-medium text-slate-800"><?php echo htmlspecialchars($app['title']); ?></p>
                      <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['duration']); ?> • <?php echo htmlspecialchars($app['mode']); ?></p>
                    </td>
                    <td class="py-4 px-6 text-slate-500 font-medium">
                      <span class="text-sm text-slate-400"><?php echo $app['deleted_at'] ? htmlspecialchars(date('M d, Y', strtotime($app['deleted_at']))) : 'Archived'; ?></span>
                    </td>
                    <td class="py-4 px-6">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getStatusBadgeClass($app['status']); ?>">
                        <?php echo htmlspecialchars($app['status']); ?>
                      </span>
                    </td>
                    <td class="py-4 px-6">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getVerificationBadgeClass($app['verification_status'] ?? 'Pending'); ?>">
                        <?php echo htmlspecialchars($app['verification_status'] ?: 'Pending'); ?>
                      </span>
                    </td>
                    <td class="py-4 px-6">
                      <button type="button" class="restore-application inline-flex items-center gap-2 bg-emerald-600 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-emerald-700 transition" data-app-id="<?php echo $app['app_id']; ?>">
                        <span class="material-symbols-outlined text-[18px]">restore</span>
                        Restore
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
              <?php echo $total_rows; ?> archived application<?php echo $total_rows !== 1 ? 's' : ''; ?>.
            </p>
            <nav class="flex items-center gap-1" aria-label="Pagination">
              <?php
                $filters = [
                    'status'              => $status_filter,
                    'verification_status' => $verification_filter,
                    'title'               => $title_filter,
                    'search'              => $search_query,
                ];
                $window = 2;
                $page_start = max(1, $current_page - $window);
                $page_end   = min($total_pages, $current_page + $window);
              ?>
              <?php if ($current_page > 1): ?>
                <a href="<?php echo htmlspecialchars(paginate_url($current_page - 1, $filters)); ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                  <span class="material-symbols-outlined text-[16px]">chevron_left</span> Prev
                </a>
              <?php endif; ?>
              <?php for ($p = $page_start; $p <= $page_end; $p++): ?>
                <?php if ($p === $current_page): ?>
                  <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm"><?php echo $p; ?></span>
                <?php else: ?>
                  <a href="<?php echo htmlspecialchars(paginate_url($p, $filters)); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors"><?php echo $p; ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars(paginate_url($current_page + 1, $filters)); ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                  Next <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>

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
    document.querySelectorAll('.restore-application').forEach(button => {
      button.addEventListener('click', async function() {
        const appId = this.dataset.appId;
        if (!confirm('Restore this application?')) {
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
            setTimeout(() => location.reload(), 1200);
          } else {
            showToast('error', 'Error', result.message);
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to restore application');
          console.error(error);
        }
      });
    });

    function showToast(type, title, message) {
      const toast = document.getElementById('toast');
      const toastIcon = document.getElementById('toast-icon');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      toast.classList.remove('hidden');
      toastTitle.textContent = title;
      toastMessage.textContent = message;

      if (type === 'success') {
        toastIcon.textContent = 'check_circle';
        toast.classList.add('border-green-200');
      } else {
        toastIcon.textContent = 'error';
        toast.classList.add('border-red-200');
      }
      toast.classList.remove('translate-x-[400px]');
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => toast.classList.add('hidden'), 500);
      }, 3000);
    }
  </script>
<?php page_shell_end(); ?>
