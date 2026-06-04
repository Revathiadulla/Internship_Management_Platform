<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('candidates');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);
sync_candidates_from_applications($conn);

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = "1=1";
$types = '';
$params = [];

if ($search !== '') {
    $term = "%$search%";
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR skills LIKE ? OR college LIKE ?)";
    $types .= 'ssss';
    array_push($params, $term, $term, $term, $term);
}
if ($status !== '') {
    $where .= " AND current_status = ?";
    $types .= 's';
    $params[] = $status;
}

// 1. Get total count
$count_sql = "SELECT COUNT(*) as total FROM candidates WHERE $where";
$c_stmt = $conn->prepare($count_sql);
if ($c_stmt) {
    if ($types !== '') {
        $c_stmt->bind_param($types, ...$params);
    }
    $c_stmt->execute();
    $total_rows = $c_stmt->get_result()->fetch_assoc()['total'];
    $c_stmt->close();
} else {
    $total_rows = 0;
}

$total_pages = ceil($total_rows / $limit);
$total_pages = max(1, $total_pages);
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// 2. Fetch paginated records
$sql = "SELECT * FROM candidates WHERE $where ORDER BY updated_at DESC LIMIT ? OFFSET ?";
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types_limit, ...$params_limit);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

$statuses = mysqli_query($conn, "SELECT DISTINCT current_status FROM candidates WHERE current_status IS NOT NULL AND current_status <> '' ORDER BY current_status");

page_shell_start('candidates', 'Candidates', 'Central applicant database with resume, skills, college, and status tracking.');
?>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="get" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <label class="text-sm font-semibold text-slate-700">Filter Status:</label>
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All statuses</option>
            <?php if ($statuses): while ($s = mysqli_fetch_assoc($statuses)): ?>
                <option value="<?php echo e($s['current_status']); ?>" <?php echo $status === $s['current_status'] ? 'selected' : ''; ?>><?php echo e($s['current_status']); ?></option>
            <?php endwhile; endif; ?>
        </select>
        <?php if ($search !== ''): ?>
            <input type="hidden" name="search" value="<?php echo e($search); ?>">
        <?php endif; ?>
    </form>
    
    <form method="get" class="relative flex items-center w-full sm:w-72">
        <span class="material-symbols-outlined absolute left-3 text-slate-400">search</span>
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search name, skills, college..." class="w-full rounded-lg border border-slate-200 pl-10 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?php echo e($status); ?>">
        <?php endif; ?>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
            <tr>
                <th class="px-6 py-4">Candidate</th>
                <th class="px-6 py-4">College</th>
                <th class="px-6 py-4">Skills</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Profile</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if ($result && $total_rows > 0): while ($row = mysqli_fetch_assoc($result)): ?>
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-semibold text-slate-900"><?php echo e($row['full_name']); ?></div>
                    <div class="text-xs text-slate-500"><?php echo e($row['email']); ?> <?php echo $row['phone'] ? '· ' . e($row['phone']) : ''; ?></div>
                </td>
                <td class="px-6 py-4 text-slate-600"><?php echo e($row['college'] ?: 'Not added'); ?></td>
                <td class="px-6 py-4 text-slate-600">
                    <div class="flex flex-wrap gap-1">
                        <?php 
                        $skills_arr = array_filter(array_map('trim', explode(',', $row['skills'] ?? '')));
                        if (empty($skills_arr)): 
                            echo '<span class="text-slate-400 text-xs">Not added</span>';
                        else:
                            $count = 0;
                            foreach ($skills_arr as $sk): 
                                if ($count < 3):
                                    echo '<span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">' . e($sk) . '</span>';
                                else:
                                    echo '<span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-400">+' . (count($skills_arr) - 3) . ' more</span>';
                                    break;
                                endif;
                                $count++;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </td>
                <td class="px-6 py-4"><?php echo status_badge($row['current_status'] ?: 'Applied'); ?></td>
                <td class="px-6 py-4 text-right">
                    <a class="inline-flex items-center gap-1 font-semibold text-blue-700 hover:text-blue-900 transition-colors hover:underline" href="hr_applicant_detail.php?app_id=<?php echo (int) $row['latest_application_id']; ?>">
                        <span class="material-symbols-outlined text-[16px]">visibility</span> View
                    </a>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr>
                <td colspan="5" class="px-6 py-12">
                    <div class="flex flex-col items-center justify-center text-center">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4 animate-pulse">
                            <span class="material-symbols-outlined text-3xl">group_off</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1">No Candidates Found</h3>
                        <p class="text-xs text-slate-500 max-w-sm mb-6">We couldn't find any candidate records matching your search query or filters.</p>
                        <?php if ($search !== '' || $status !== ''): ?>
                            <a href="candidates.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-all hover:shadow-md">
                                <span class="material-symbols-outlined text-xs">restart_alt</span> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination Footer -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4">
        <div class="flex flex-1 justify-between sm:hidden">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-700">
                    Showing
                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                    to
                    <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span>
                    of
                    <span class="font-medium"><?php echo $total_rows; ?></span>
                    candidates
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2.5 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 <?php echo $page === 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                        <span class="sr-only">Previous</span>
                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                    </a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2.5 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 <?php echo $page === $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">
                        <span class="sr-only">Next</span>
                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                    </a>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php page_shell_end(); ?>
