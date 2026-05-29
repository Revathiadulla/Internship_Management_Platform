<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'hr') {
    header("Location: hr_dashboard.php");
    exit();
}
require_module_access('postings');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$page          = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit         = 10;
$offset        = ($page - 1) * $limit;

$where = "1=1";
$types = '';
$params = [];

if (in_array($status_filter, ['Active', 'Closed'], true)) {
    $where .= " AND jp.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}
if ($search !== '') {
    $term = "%$search%";
    $where .= " AND (jp.title LIKE ? OR jp.department LIKE ? OR jp.location LIKE ?)";
    $types .= 'sss';
    array_push($params, $term, $term, $term);
}

// 1. Get total count
$count_sql = "SELECT COUNT(*) as total FROM job_postings jp WHERE $where";
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
$sql = "SELECT jp.*,
        (SELECT COUNT(*) FROM internship_applications a
         WHERE a.job_posting_id = jp.id AND a.is_deleted = 0) AS applicant_count
        FROM job_postings jp
        WHERE $where
        ORDER BY jp.created_at DESC
        LIMIT ? OFFSET ?";
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

page_shell_start('postings', 'Postings', 'Create, edit, close, and track job or internship openings.', '<a href="add_posting.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm transition-all hover:shadow-md"><span class="material-symbols-outlined text-lg">add</span> Create posting</a>');
?>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="get" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <label class="text-sm font-semibold text-slate-700">Filter Status:</label>
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All statuses</option>
            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Closed" <?php echo $status_filter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
        </select>
        <?php if ($search !== ''): ?>
            <input type="hidden" name="search" value="<?php echo e($search); ?>">
        <?php endif; ?>
    </form>
    
    <form method="get" class="relative flex items-center w-full sm:w-72">
        <span class="material-symbols-outlined absolute left-3 text-slate-400">search</span>
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search job title, dept, location..." class="w-full rounded-lg border border-slate-200 pl-10 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
        <?php if ($status_filter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
        <?php endif; ?>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
            <tr>
                <th class="px-6 py-4">Posting</th>
                <th class="px-6 py-4">Type</th>
                <th class="px-6 py-4">Applicants</th>
                <th class="px-6 py-4">Deadline</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if ($result && $total_rows > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-900"><?php echo e($row['title']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo e($row['department'] ?: 'General'); ?> · <?php echo e($row['location'] ?: 'Not specified'); ?></div>
                        </td>
                        <td class="px-6 py-4 text-slate-600"><?php echo e($row['posting_type']); ?></td>
                        <td class="px-6 py-4"><a class="font-semibold text-blue-700 hover:text-blue-950 transition-colors hover:underline" href="hr_applications.php?job_posting_id=<?php echo (int) $row['id']; ?>"><?php echo (int) $row['applicant_count']; ?> applicants</a></td>
                        <td class="px-6 py-4 text-slate-600"><?php echo e(format_module_date($row['deadline'])); ?></td>
                        <td class="px-6 py-4"><?php echo status_badge($row['status']); ?></td>
                        <td class="px-6 py-4 text-right">
                            <a class="mr-3 font-semibold text-blue-700 hover:text-blue-900 transition-colors hover:underline" href="edit_posting.php?id=<?php echo (int) $row['id']; ?>">Edit</a>
                            <a class="font-semibold text-red-600 hover:text-red-800 transition-colors hover:underline" href="delete_posting.php?id=<?php echo (int) $row['id']; ?>" onclick="return confirm('Delete this posting?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 border-0">
                        <div class="flex flex-col items-center justify-center text-center border-0">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4 animate-pulse">
                                <span class="material-symbols-outlined text-3xl">work_off</span>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 mb-1">No Postings Found</h3>
                            <p class="text-xs text-slate-500 max-w-sm mb-6">We couldn't find any job or internship openings matching your search query or filters.</p>
                            <?php if ($search !== '' || $status_filter !== ''): ?>
                                <a href="postings.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-all hover:shadow-md">
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
                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
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
                    postings
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2.5 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 <?php echo $page === 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                        <span class="sr-only">Previous</span>
                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                    </a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2.5 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 <?php echo $page === $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">
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
