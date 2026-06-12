<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_module_access('student_logs');
require_hr_or_admin();
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
ensure_module_schema($conn);

$current_user = current_user_id();

// Handle POST actions (AJAX reviews/remarks/bulk actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'bulk_mark_reviewed') {
        $log_ids = $_POST['log_ids'] ?? [];
        if (empty($log_ids) || !is_array($log_ids)) {
            echo json_encode(['success' => false, 'message' => 'No logs selected.']);
            exit();
        }
        $clean_ids = array_map('intval', $log_ids);
        $ids_str = implode(',', $clean_ids);
        $stmt = $conn->prepare("UPDATE daily_logs SET hr_review_status = 'Reviewed', hr_reviewed_by = ?, hr_reviewed_at = CURRENT_TIMESTAMP WHERE id IN ($ids_str)");
        $stmt->bind_param("i", $current_user);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => count($clean_ids) . ' logs marked as reviewed.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
        }
        $stmt->close();
        exit();
    }

    $log_id = intval($_POST['log_id'] ?? 0);
    if ($log_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Log ID.']);
        exit();
    }

    if ($action === 'mark_reviewed') {
        $stmt = $conn->prepare("UPDATE daily_logs SET hr_review_status = 'Reviewed', hr_reviewed_by = ?, hr_reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("ii", $current_user, $log_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Log marked as reviewed by HR.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'save_remarks') {
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $conn->prepare("UPDATE daily_logs SET hr_remarks = ?, hr_reviewed_by = ?, hr_reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("sii", $remarks, $current_user, $log_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'HR Remarks saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save remarks.']);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'get_log_details') {
        $stmt = $conn->prepare("
            SELECT dl.*, u.full_name, COALESCE(i.title, CONCAT('Internship #', dl.internship_id)) as posting_title, rev.full_name as reviewer_name
            FROM daily_logs dl
            JOIN users u ON dl.user_id = u.id
            LEFT JOIN internships i ON dl.internship_id = i.id
            LEFT JOIN users rev ON dl.hr_reviewed_by = rev.id
            WHERE dl.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $log_id);
        $stmt->execute();
        $log_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($log_data) {
            echo json_encode(['success' => true, 'log' => $log_data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Log details not found.']);
        }
        exit();
    }
}

// Filters & Search
$student_name = trim($_GET['student_name'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');
$review_status = trim($_GET['review_status'] ?? '');
$internship_id = isset($_GET['internship_id']) ? intval($_GET['internship_id']) : 0;

$where_clauses = ["1=1"];
if ($student_name !== '') {
    $student_escaped = mysqli_real_escape_string($conn, $student_name);
    $where_clauses[] = "u.full_name LIKE '%$student_escaped%'";
}
if ($from_date !== '') {
    $where_clauses[] = "dl.log_date >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
}
if ($to_date !== '') {
    $where_clauses[] = "dl.log_date <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
}
if ($review_status !== '') {
    $where_clauses[] = "dl.hr_review_status = '" . mysqli_real_escape_string($conn, $review_status) . "'";
}
if ($internship_id > 0) {
    $where_clauses[] = "dl.internship_id = $internship_id";
}
$where_sql = implode(' AND ', $where_clauses);

// Pagination
$per_page = 15;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) as total FROM daily_logs dl JOIN users u ON dl.user_id = u.id WHERE $where_sql";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = $count_result ? (int)mysqli_fetch_assoc($count_result)['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($current_page > $total_pages) $current_page = $total_pages;

// Main logs query (independent of postings module)
$logs_query = "
    SELECT dl.*, u.full_name, COALESCE(i.title, CONCAT('Internship #', dl.internship_id)) as posting_title, ap.id as app_id
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    LEFT JOIN internships i ON dl.internship_id = i.id
    LEFT JOIN internship_applications ap ON dl.user_id = ap.user_id AND ap.is_deleted = 0
    WHERE $where_sql
    GROUP BY dl.id
    ORDER BY dl.log_date DESC, dl.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$logs_result = mysqli_query($conn, $logs_query);

// Fetch internships list for the dropdown filter (independent of postings module)
$internships_res = mysqli_query($conn, "
    SELECT DISTINCT dl.internship_id, COALESCE(i.title, CONCAT('Internship #', dl.internship_id)) as title
    FROM daily_logs dl
    LEFT JOIN internships i ON dl.internship_id = i.id
    ORDER BY title ASC
");

// Summary Stats
function scalar_count_local($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return (int)($row['cnt'] ?? 0);
}
$stats = [
    'total_logs' => scalar_count_local($conn, "SELECT COUNT(*) as cnt FROM daily_logs"),
    'todays_logs' => scalar_count_local($conn, "SELECT COUNT(*) as cnt FROM daily_logs WHERE DATE(created_at) = CURDATE()"),
    'pending_review' => scalar_count_local($conn, "SELECT COUNT(*) as cnt FROM daily_logs WHERE hr_review_status = 'Pending'"),
    'reviewed_logs' => scalar_count_local($conn, "SELECT COUNT(*) as cnt FROM daily_logs WHERE hr_review_status = 'Reviewed'")
];

// Helper to preserve active query parameters on pagination
function log_paginate_url(int $page, array $filters): string {
    $params = array_filter($filters, fn($v) => $v !== '');
    $params['page'] = $page;
    return 'student_logs.php?' . http_build_query($params);
}
$active_filters = ['student_name' => $student_name, 'from_date' => $from_date, 'to_date' => $to_date, 'review_status' => $review_status, 'internship_id' => $internship_id];

page_shell_start('student_logs', 'Student Logs', 'Track, review, and comment on student daily log accomplishments.');
?>

<!-- Read-Only Banner Notice -->
<div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-600 flex items-center gap-2 shadow-sm">
    <span class="material-symbols-outlined text-[16px] text-slate-500">info</span>
    <span>Daily logs are read-only and immutable. HR can review accomplishments and leave feedback remarks, but cannot edit log contents, hours, or delete submissions.</span>
</div>

<div class="grid gap-5 lg:grid-cols-4 mb-6">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Logs</p>
        <p class="mt-3 text-3xl font-extrabold text-slate-900"><?php echo number_format($stats['total_logs']); ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Today's Logs</p>
        <p class="mt-3 text-3xl font-extrabold text-slate-900"><?php echo number_format($stats['todays_logs']); ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-amber-600">Pending Review</p>
        <p class="mt-3 text-3xl font-extrabold text-slate-900"><?php echo number_format($stats['pending_review']); ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-emerald-600">Reviewed Logs</p>
        <p class="mt-3 text-3xl font-extrabold text-slate-900"><?php echo number_format($stats['reviewed_logs']); ?></p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
    <form method="GET" action="student_logs.php" class="grid gap-4 xl:grid-cols-5 items-end">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Student Name</label>
            <input type="text" name="student_name" value="<?php echo htmlspecialchars($student_name); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500" placeholder="Search student name...">
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Internship</label>
            <select name="internship_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white outline-none focus:border-blue-500">
                <option value="">All Internships</option>
                <?php if ($internships_res): while ($i_row = mysqli_fetch_assoc($internships_res)): ?>
                    <option value="<?php echo $i_row['internship_id']; ?>" <?php echo $internship_id === intval($i_row['internship_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($i_row['title']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">From Log Date</label>
            <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500">
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">To Log Date</label>
            <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500">
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Review Status</label>
            <select name="review_status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white outline-none focus:border-blue-500">
                <option value="">All Logs</option>
                <option value="Pending" <?php echo $review_status === 'Pending' ? 'selected' : ''; ?>>Pending Review</option>
                <option value="Reviewed" <?php echo $review_status === 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
            </select>
        </div>
        <div class="xl:col-span-5 flex justify-end gap-2 mt-2">
            <a href="student_logs.php" class="border border-slate-200 rounded-lg px-6 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition flex items-center justify-center">Reset Filters</a>
            <button type="submit" class="bg-slate-900 text-white px-8 py-2 rounded-lg text-sm font-semibold hover:bg-black transition">Apply Filters</button>
        </div>
    </form>
</div>

<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="mb-4 hidden flex items-center justify-between bg-blue-50 border border-blue-200 rounded-xl p-4 transition-all">
    <div class="text-sm font-semibold text-blue-800">
        <span id="selected-count">0</span> log(s) selected for bulk review
    </div>
    <button onclick="bulkMarkReviewed()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2.5 px-5 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">done_all</span> Mark Selected as Reviewed
    </button>
</div>

<!-- Logs Table -->
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
            <tr>
                <th class="px-5 py-3 text-center w-12">
                    <input type="checkbox" id="select-all-logs" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                </th>
                <th class="px-5 py-3">Student</th>
                <th class="px-5 py-3">Internship</th>
                <th class="px-5 py-3">Log Date</th>
                <th class="px-5 py-3 text-center">Hours</th>
                <th class="px-5 py-3 text-center">Focus</th>
                <th class="px-5 py-3">Task Summary</th>
                <th class="px-5 py-3 text-center">Attach</th>
                <th class="px-5 py-3">HR Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if ($logs_result && mysqli_num_rows($logs_result) > 0): while ($log = mysqli_fetch_assoc($logs_result)): 
            $status_class = ($log['hr_review_status'] === 'Reviewed') 
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                : 'bg-amber-50 text-amber-700 border-amber-200';
            
            $focus_class = ($log['focus_level'] === 'High') ? 'text-green-600 bg-green-50' : (($log['focus_level'] === 'Low') ? 'text-red-600 bg-red-50' : 'text-blue-600 bg-blue-50');
        ?>
            <tr id="log-row-<?php echo $log['id']; ?>" class="hover:bg-slate-50/50 transition-colors">
                <td class="px-5 py-4 text-center">
                    <?php if ($log['hr_review_status'] !== 'Reviewed'): ?>
                        <input type="checkbox" name="selected_logs[]" value="<?php echo $log['id']; ?>" class="log-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <?php else: ?>
                        <input type="checkbox" disabled class="opacity-20 rounded border-slate-200 cursor-not-allowed">
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4">
                    <?php if (!empty($log['app_id'])): ?>
                        <a href="hr_applicant_detail.php?app_id=<?php echo $log['app_id']; ?>" class="font-semibold text-blue-700 hover:underline"><?php echo e($log['full_name']); ?></a>
                    <?php else: ?>
                        <span class="font-semibold text-slate-800"><?php echo e($log['full_name']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4 text-slate-600"><?php echo e($log['posting_title']); ?></td>
                <td class="px-5 py-4 text-slate-500 font-medium"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                <td class="px-5 py-4 text-center font-bold text-slate-700"><?php echo e($log['time_spent']); ?> hrs</td>
                <td class="px-5 py-4 text-center">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?php echo $focus_class; ?>"><?php echo e($log['focus_level']); ?></span>
                </td>
                <td class="px-5 py-4 text-slate-500 max-w-[200px] truncate" title="<?php echo e($log['tasks_completed']); ?>">
                    <?php echo e($log['tasks_completed']); ?>
                </td>
                <td class="px-5 py-4 text-center">
                    <?php if (!empty($log['attachment_path'])): ?>
                        <a href="<?php echo htmlspecialchars($log['attachment_path']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="Download Attachment">
                            <span class="material-symbols-outlined text-base">attachment</span>
                        </a>
                    <?php else: ?>
                        <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4">
                    <span id="badge-<?php echo $log['id']; ?>" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?php echo $status_class; ?>">
                        <?php echo e($log['hr_review_status'] ?: 'Pending'); ?>
                    </span>
                </td>
                <td class="px-5 py-4 text-right space-x-2">
                    <button onclick="viewLog(<?php echo $log['id']; ?>)" class="text-xs font-bold text-blue-600 hover:underline">View</button>
                    <?php if ($log['hr_review_status'] !== 'Reviewed'): ?>
                        <button id="btn-review-<?php echo $log['id']; ?>" onclick="markReviewed(<?php echo $log['id']; ?>)" class="text-xs font-bold text-emerald-600 hover:underline">Review</button>
                    <?php endif; ?>
                    <button onclick="editRemarks(<?php echo $log['id']; ?>)" class="text-xs font-bold text-slate-600 hover:underline">Remarks</button>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="10" class="px-5 py-10 text-center text-slate-500 border-0">No student daily logs found matching the filter criteria.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Links -->
<?php if ($total_pages > 1): ?>
    <div class="mt-6 flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4 rounded-lg shadow-sm border">
        <div class="text-xs text-slate-500">
            Showing logs <span class="font-bold text-slate-800"><?php echo $offset + 1; ?></span> to <span class="font-bold text-slate-800"><?php echo min($offset + $per_page, $total_rows); ?></span> of <span class="font-bold text-slate-800"><?php echo $total_rows; ?></span>
        </div>
        <div class="flex gap-1.5">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo log_paginate_url($current_page - 1, $active_filters); ?>" class="rounded border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo log_paginate_url($i, $active_filters); ?>" class="rounded border px-3 py-1.5 text-xs font-bold transition <?php echo $i === $current_page ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-slate-200 text-slate-600 hover:bg-slate-50'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo log_paginate_url($current_page + 1, $active_filters); ?>" class="rounded border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Log Details Modal (Read-Only Submission) -->
<div id="log-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="bg-white rounded-2xl max-w-lg w-full mx-4 shadow-2xl border border-slate-100 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-black text-gray-900 tracking-tight">Daily Log Details</h3>
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1 mt-1">
                    <span class="material-symbols-outlined text-[12px] font-black">lock</span> Read-Only Submission Record
                </div>
            </div>
            <button onclick="closeModal('log-modal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 font-bold uppercase">Student</span>
                    <p class="font-extrabold text-slate-900 text-sm mt-0.5" id="detail-student-name">-</p>
                </div>
                <div>
                    <span class="text-slate-400 font-bold uppercase">Internship</span>
                    <p class="font-extrabold text-slate-900 text-sm mt-0.5" id="detail-internship">-</p>
                </div>
                <div>
                    <span class="text-slate-400 font-bold uppercase">Log Date</span>
                    <p class="font-semibold text-slate-700 mt-0.5" id="detail-date">-</p>
                </div>
                <div>
                    <span class="text-slate-400 font-bold uppercase">Hours & Focus</span>
                    <p class="font-semibold text-slate-700 mt-0.5" id="detail-hours">-</p>
                </div>
            </div>
            
            <hr class="border-slate-100">
            
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase">Tasks Completed</span>
                <p class="text-sm text-slate-700 mt-1 leading-relaxed whitespace-pre-line bg-slate-50 p-3 rounded-lg border" id="detail-tasks">-</p>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase">Issues / Challenges</span>
                <p class="text-sm text-slate-700 mt-1 leading-relaxed whitespace-pre-line bg-slate-50 p-3 rounded-lg border" id="detail-issues">-</p>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase">Next Steps</span>
                <p class="text-sm text-slate-700 mt-1 leading-relaxed whitespace-pre-line bg-slate-50 p-3 rounded-lg border" id="detail-next">-</p>
            </div>
            
            <div id="reviewer-section" class="hidden text-xs bg-emerald-50 text-emerald-800 p-3.5 border border-emerald-150 rounded-xl">
                <p class="font-bold">Reviewed by HR:</p>
                <p class="mt-0.5 font-medium" id="detail-reviewer">-</p>
            </div>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
            <button onclick="closeModal('log-modal')" class="px-5 py-2.5 border border-slate-200 bg-white rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50">Close</button>
            <div id="modal-review-btn-container"></div>
        </div>
    </div>
</div>

<!-- Edit Remarks Modal -->
<div id="remarks-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="bg-white rounded-2xl max-w-md w-full mx-4 shadow-2xl border border-slate-100 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-black text-gray-900 tracking-tight">HR Remarks</h3>
            <button onclick="closeModal('remarks-modal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-xs text-slate-500 font-medium">Add internal remarks, flags, or review evaluations for this student log.</p>
            <input type="hidden" id="remarks-log-id">
            <textarea id="remarks-text" rows="5" class="w-full border border-slate-200 rounded-xl p-3.5 text-sm text-slate-800 outline-none focus:border-blue-500" placeholder="Type HR Remarks..."></textarea>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
            <button onclick="closeModal('remarks-modal')" class="px-5 py-2.5 border border-slate-200 bg-white rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button onclick="saveRemarks()" class="px-6 py-2.5 bg-slate-900 hover:bg-black rounded-xl text-xs font-bold text-white transition-all animate-pulse-once">Save Remarks</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="log-toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
    </div>
    <div>
        <p class="text-xs font-bold text-green-600 uppercase tracking-wider">Success</p>
        <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-msg">Operation completed</p>
    </div>
</div>

<script>
    function showToast(message) {
        const toast = document.getElementById('log-toast');
        const toastMsg = document.getElementById('toast-msg');
        toastMsg.textContent = message;
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

    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('opacity-100', 'scale-100');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('opacity-100', 'scale-100');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
    }

    async function viewLog(logId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_log_details');
            formData.append('log_id', logId);

            const res = await fetch('student_logs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                const log = data.log;
                document.getElementById('detail-student-name').textContent = log.full_name;
                document.getElementById('detail-internship').textContent = log.posting_title;
                document.getElementById('detail-date').textContent = new Date(log.log_date).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'});
                document.getElementById('detail-hours').textContent = `${log.time_spent} hrs (Focus: ${log.focus_level})`;
                document.getElementById('detail-tasks').textContent = log.tasks_completed;
                document.getElementById('detail-issues').textContent = log.issues_faced || 'None reported';
                document.getElementById('detail-next').textContent = log.next_plan || 'None reported';

                const revSec = document.getElementById('reviewer-section');
                if (log.hr_review_status === 'Reviewed') {
                    revSec.classList.remove('hidden');
                    const revDate = new Date(log.hr_reviewed_at).toLocaleString();
                    document.getElementById('detail-reviewer').textContent = `${log.reviewer_name || 'HR Recruiter'} on ${revDate}`;
                } else {
                    revSec.classList.add('hidden');
                }

                const btnContainer = document.getElementById('modal-review-btn-container');
                btnContainer.innerHTML = '';
                if (log.hr_review_status !== 'Reviewed') {
                    btnContainer.innerHTML = `<button onclick="markReviewed(${log.id}); closeModal('log-modal');" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all">Mark Reviewed</button>`;
                }

                openModal('log-modal');
            }
        } catch(e) {
            console.error(e);
        }
    }

    async function markReviewed(logId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_reviewed');
            formData.append('log_id', logId);

            const res = await fetch('student_logs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                // Update badge in UI
                const badge = document.getElementById(`badge-${logId}`);
                if (badge) {
                    badge.className = 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200';
                    badge.textContent = 'Reviewed';
                }
                const btn = document.getElementById(`btn-review-${logId}`);
                if (btn) btn.remove();
                
                // Disable checkbox
                const cb = document.querySelector(`.log-checkbox[value="${logId}"]`);
                if (cb) {
                    cb.checked = false;
                    cb.disabled = true;
                    cb.classList.add('opacity-20');
                    cb.classList.remove('log-checkbox');
                }
                updateBulkBar();
                showToast(data.message);
            }
        } catch(e) {
            console.error(e);
        }
    }

    async function editRemarks(logId) {
        document.getElementById('remarks-log-id').value = logId;
        document.getElementById('remarks-text').value = '';
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_log_details');
            formData.append('log_id', logId);

            const res = await fetch('student_logs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                document.getElementById('remarks-text').value = data.log.hr_remarks || '';
            }
        } catch(e) {
            console.error(e);
        }

        openModal('remarks-modal');
    }

    async function saveRemarks() {
        const logId = document.getElementById('remarks-log-id').value;
        const remarks = document.getElementById('remarks-text').value;

        try {
            const formData = new FormData();
            formData.append('action', 'save_remarks');
            formData.append('log_id', logId);
            formData.append('remarks', remarks);

            const res = await fetch('student_logs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                closeModal('remarks-modal');
                showToast(data.message);
            }
        } catch(e) {
            console.error(e);
        }
    }

    // Bulk Actions Javascript
    function updateBulkBar() {
        const totalChecked = document.querySelectorAll('.log-checkbox:checked').length;
        const bar = document.getElementById('bulk-actions-bar');
        const countSpan = document.getElementById('selected-count');
        if (totalChecked > 0) {
            countSpan.textContent = totalChecked;
            bar.classList.remove('hidden');
        } else {
            bar.classList.add('hidden');
        }
    }

    async function bulkMarkReviewed() {
        const checkboxes = document.querySelectorAll('.log-checkbox:checked');
        const logIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
        if (logIds.length === 0) return;
        
        if (!confirm(`Mark ${logIds.length} daily logs as reviewed?`)) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'bulk_mark_reviewed');
            logIds.forEach(id => formData.append('log_ids[]', id));
            
            const res = await fetch('student_logs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                logIds.forEach(id => {
                    // Update badge
                    const badge = document.getElementById(`badge-${id}`);
                    if (badge) {
                        badge.className = 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200';
                        badge.textContent = 'Reviewed';
                    }
                    // Remove review button
                    const btn = document.getElementById(`btn-review-${id}`);
                    if (btn) btn.remove();
                    // Disable checkbox
                    const cb = document.querySelector(`.log-checkbox[value="${id}"]`);
                    if (cb) {
                        cb.checked = false;
                        cb.disabled = true;
                        cb.classList.add('opacity-20');
                        cb.classList.remove('log-checkbox');
                    }
                });
                
                // Reset bulk bar
                document.getElementById('select-all-logs').checked = false;
                updateBulkBar();
                showToast(data.message);
            }
        } catch(e) {
            console.error(e);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-logs');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.log-checkbox').forEach(cb => {
                    cb.checked = this.checked;
                });
                updateBulkBar();
            });
        }
        
        // Use event delegation for checkboxes since list could be re-drawn or interactive
        document.querySelector('table').addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('log-checkbox')) {
                updateBulkBar();
            }
        });
    });
</script>

<?php page_shell_end(); ?>
