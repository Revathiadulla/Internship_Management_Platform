<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
// Require HR or Admin role
require_hr_or_admin();

require __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
ensure_module_schema($conn);

$error = '';
$success = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action']; // 'approve' or 'reject'

    if ($request_id > 0) {
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE hiring_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $request_id);
        if ($stmt->execute()) {
            $success = "Hiring request #$request_id has been successfully " . strtolower($status) . ".";
            
            // Get hiring request info first
            $q_info = mysqli_query($conn, "
                SELECT hr.*, cp.company_name
                FROM hiring_requests hr
                JOIN users u ON hr.company_id = u.id
                LEFT JOIN company_profiles cp ON u.id = cp.user_id
                WHERE hr.id = $request_id LIMIT 1
            ");
            if ($q_info && $req = mysqli_fetch_assoc($q_info)) {
                $req_company_id = $req['company_id'];
                $req_title = $req['title'];
                $req_company_name = $req['company_name'];
                
                // Log activity
                log_activity($conn, 'Hiring Request ' . $status, "HR updated hiring request #$request_id ($req_title) from $req_company_name to $status.");
                
                // Insert company notification
                $notif_title = "Hiring Request " . $status;
                $notif_msg = "Your hiring request for \"$req_title\" has been " . strtolower($status) . " by HR.";
                $notif_stmt = $conn->prepare("INSERT INTO company_notifications (company_id, type, title, message) VALUES (?, 'hiring_request', ?, ?)");
                if ($notif_stmt) {
                    $notif_stmt->bind_param("iss", $req_company_id, $notif_title, $notif_msg);
                    $notif_stmt->execute();
                }
                
                // If approved, we can automatically insert a job posting in the database!
                if ($status === 'Approved') {
                    $title = $req['title'] . ' (' . $req['company_name'] . ')';
                    $department = $req['department'];
                    $type = 'Internship';
                    $location = 'Remote / Hybrid';
                    $openings = $req['openings'];
                    $description = $req['description'];
                    $requirements = $req['requirements'];
                    $status_active = 'Active';
                    $deadline = date('Y-m-d', strtotime('+30 days'));

                    $ins = $conn->prepare("INSERT INTO job_postings (title, department, posting_type, location, openings, description, requirements, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param("ssssissss", $title, $department, $type, $location, $openings, $description, $requirements, $status_active, $deadline);
                    $ins->execute();
                }
            }
        } else {
            $error = 'Failed to update hiring request status.';
        }
    }
}

// Fetch hiring requests with company info
$hiring_requests = [];
$q_req = mysqli_query($conn, "
    SELECT hr.*, cp.company_name, cp.website, cp.company_size, u.full_name AS recruiter_name, u.email AS recruiter_email, u.phone AS recruiter_phone
    FROM hiring_requests hr
    JOIN users u ON hr.company_id = u.id
    LEFT JOIN company_profiles cp ON u.id = cp.user_id
    ORDER BY hr.created_at DESC
");
if ($q_req) {
    while ($row = mysqli_fetch_assoc($q_req)) {
        $hiring_requests[] = $row;
    }
}

// Custom page header wrapper
page_shell_start('hiring_requests', 'Corporate Hiring Requests', 'Manage talent demand requests and project postings from corporate partners.');

if ($error) {
    echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e($error) . '</div>';
}
if ($success) {
    echo '<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">' . e($success) . '</div>';
}
?>

<div class="rounded-lg border border-slate-200 bg-white overflow-hidden shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-lg font-bold text-slate-900 font-sans">Corporate Requests</h2>
        <p class="text-xs text-slate-500 font-sans mt-0.5">Reviews submitted by corporate accounts. Approvals automatically post job openings.</p>
    </div>
    
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 font-sans font-bold border-b border-slate-100">
            <tr>
                <th class="px-5 py-3">Company Details</th>
                <th class="px-5 py-3">Position Needed</th>
                <th class="px-5 py-3">Openings</th>
                <th class="px-5 py-3">Details / Skills</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-sans">
            <?php if (empty($hiring_requests)): ?>
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center py-4 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 mb-4 animate-pulse">
                                <span class="material-symbols-outlined text-3xl">assignment_late</span>
                            </div>
                            <h4 class="text-base font-bold text-slate-800 mb-1">No Hiring Requests Found</h4>
                            <p class="text-xs text-slate-500 max-w-sm">No corporate hiring requests have been submitted by partner companies yet.</p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($hiring_requests as $req): 
                $status_color = 'bg-slate-50 text-slate-600 border-slate-200';
                if ($req['status'] === 'Approved') {
                    $status_color = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                } elseif ($req['status'] === 'Rejected') {
                    $status_color = 'bg-red-50 text-red-700 border-red-200';
                }
            ?>
                <tr class="hover:bg-slate-50/50 transition-all">
                    <td class="px-5 py-4">
                        <div class="font-bold text-slate-900 leading-tight"><?php echo e($req['company_name'] ?: 'Corporate Partner'); ?></div>
                        <div class="text-[10px] text-slate-400 mt-1 font-medium">Recruiter: <?php echo e($req['recruiter_name']); ?></div>
                        <div class="text-[10px] text-slate-400 font-medium">Email: <?php echo e($req['recruiter_email']); ?></div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="font-semibold text-slate-800"><?php echo e($req['title']); ?></div>
                        <div class="mt-1 text-[10px] font-black uppercase tracking-wider text-blue-600"><?php echo e($req['department']); ?></div>
                    </td>
                    <td class="px-5 py-4 font-bold text-slate-700"><?php echo e($req['openings']); ?></td>
                    <td class="px-5 py-4 max-w-sm">
                        <p class="text-xs text-slate-600 line-clamp-2" title="<?php echo e($req['description']); ?>"><?php echo e($req['description'] ?: '—'); ?></p>
                        <?php if ($req['requirements']): ?>
                            <div class="text-[9px] text-slate-400 font-semibold mt-1">Skills: <?php echo e($req['requirements']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider <?php echo $status_color; ?>">
                            <?php echo e($req['status']); ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 text-right">
                        <?php if ($req['status'] === 'Pending'): ?>
                            <div class="flex gap-2 justify-end">
                                <form method="POST" action="hiring_requests.php" class="inline">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-700 transition-all shadow-sm">Approve</button>
                                </form>
                                <form method="POST" action="hiring_requests.php" class="inline">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="bg-white border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-50 transition-all">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-slate-400 italic font-semibold">Reviewed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php page_shell_end(); ?>
