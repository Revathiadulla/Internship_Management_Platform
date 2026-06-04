<?php
/**
 * admin_student_reports.php
 * Admin interface to review mentor-submitted student reports.
 * 
 * Displays:
 * - All mentor reports with status "Reported by Mentor"
 * - Student profile and progress info
 * - Daily logs and performance data
 * - Mentor remarks
 * 
 * Admin actions:
 * - Keep Active
 * - Put On Hold
 * - Discontinue Internship
 * - Remove from Internship
 */

session_start();
include_once __DIR__ . '/includes/auth.php';
require_role('admin');
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/setup_discontinuation_schema.php';

$success_msg = "";
$error_msg = "";

// Handle admin decision submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $app_id = intval($_POST['application_id'] ?? 0);
    $decision = trim($_POST['decision'] ?? '');
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    $admin_id = intval($_SESSION['user_id']);

    $allowed_decisions = ['Keep Active', 'Put On Hold', 'Discontinue Internship', 'Remove from Internship'];

    if ($app_id <= 0 || empty($decision) || !in_array($decision, $allowed_decisions)) {
        $error_msg = "Invalid decision or application ID.";
    } else {
        // Map decision to internship_status
        $status_map = [
            'Keep Active' => 'Active',
            'Put On Hold' => 'On Hold',
            'Discontinue Internship' => 'Discontinued',
            'Remove from Internship' => 'Removed'
        ];
        $new_status = $status_map[$decision];

        // Fetch application details
        $app_sql = "SELECT a.id, a.user_id, a.internship_id, a.internship_name, 
                           COALESCE(i.title, a.internship_name) as internship_title,
                           a.internship_status, a.report_reason, a.mentor_remarks, a.reported_by,
                           u.full_name as student_name, u.email as student_email
                    FROM internship_applications a
                    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE a.id = ? LIMIT 1";
        $app_stmt = $conn->prepare($app_sql);
        if (!$app_stmt) {
            $error_msg = "Database error: " . $conn->error;
        } else {
            $app_stmt->bind_param('i', $app_id);
            $app_stmt->execute();
            $app_result = $app_stmt->get_result();

            if ($app_result->num_rows === 0) {
                $error_msg = "Application not found.";
            } else {
                $app = $app_result->fetch_assoc();
                $student_id = intval($app['user_id']);
                $old_status = $app['internship_status'];
                $student_name = $app['student_name'] ?? 'Student';
                $student_email = $app['student_email'] ?? '';

                // Begin transaction
                mysqli_begin_transaction($conn);

                try {
                    // 1. Update application with admin decision
                    $update_sql = "UPDATE internship_applications 
                                   SET internship_status = ?,
                                       admin_decision = ?,
                                       admin_remarks = ?,
                                       approved_by_admin = ?,
                                       approved_date = NOW()
                                   WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if (!$update_stmt) {
                        throw new Exception('Failed to prepare update: ' . $conn->error);
                    }
                    $update_stmt->bind_param('sssii', $new_status, $decision, $admin_remarks, $admin_id, $app_id);
                    if (!$update_stmt->execute()) {
                        throw new Exception('Failed to update: ' . $update_stmt->error);
                    }
                    $update_stmt->close();

                    // 2. Log to audit trail
                    $audit_sql = "INSERT INTO internship_status_history 
                                  (application_id, old_status, new_status, remarks, changed_by, changed_by_role, change_type)
                                  VALUES (?, ?, ?, ?, ?, 'admin', 'decision')";
                    $audit_stmt = $conn->prepare($audit_sql);
                    if (!$audit_stmt) {
                        throw new Exception('Failed to prepare audit: ' . $conn->error);
                    }
                    $audit_stmt->bind_param('isssi', $app_id, $old_status, $new_status, $admin_remarks, $admin_id);
                    if (!$audit_stmt->execute()) {
                        throw new Exception('Failed to log audit: ' . $audit_stmt->error);
                    }
                    $audit_stmt->close();

                    // 3. Get admin and mentor names for notifications
                    $admin_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $admin_id LIMIT 1");
                    $admin = mysqli_fetch_assoc($admin_res);
                    $admin_name = $admin['full_name'] ?? 'Admin';

                    $mentor_id = intval($app['reported_by'] ?? 0);
                    $mentor_name = '';
                    if ($mentor_id > 0) {
                        $mentor_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $mentor_id LIMIT 1");
                        $mentor = mysqli_fetch_assoc($mentor_res);
                        $mentor_name = $mentor['full_name'] ?? 'Mentor';
                    }

                    // 4. Notify student
                    $student_subject = "Internship Status Update from Administration";
                    $student_message = "Dear $student_name,\n\nYour internship status has been reviewed and updated to: $new_status.\n\nAdministrator Decision: $decision\n\nIf you have questions, please contact your coordinator or HR representative.\n\nBest regards,\nIMP Admin Team";
                    
                    sendStudentNotification($student_id, $student_name, $student_subject, $student_message, [
                        'event' => 'Internship Status Update',
                        'internship' => $app['internship_title'],
                        'new_status' => $new_status,
                        'decision' => $decision,
                        'action_url' => 'http://localhost/IMP/student_dashboard.php',
                        'action_label' => 'View Dashboard'
                    ]);

                    // 5. Create notifications
                    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
                                       VALUES ($student_id, 'Internship Status Changed', 'info', 
                                              'Your internship status has been updated to: $new_status')");

                    if ($mentor_id > 0) {
                        $mentor_notif = "Admin $admin_name reviewed your report for $student_name. Decision: $decision";
                        $m_link = "mentor_dashboard.php";
                        mysqli_query($conn, "INSERT INTO notifications (user_id, role, title, message, type, link)
                                           VALUES ($mentor_id, 'mentor', 'Report Decision', '$mentor_notif', 'info', '$m_link')");
                    }

                    mysqli_commit($conn);
                    $success_msg = "Admin decision saved successfully. Notifications sent to student and mentor.";

                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Error: " . $e->getMessage();
                }
            }
            $app_stmt->close();
        }
    }
}

// Fetch all reported students
$reports_sql = "SELECT a.id, a.user_id, a.internship_id, a.internship_name,
                       COALESCE(i.title, a.internship_name) as internship_title,
                       a.internship_status, a.report_reason, a.mentor_remarks, a.reported_by, a.reported_date,
                       a.admin_decision, a.admin_remarks, a.approved_by_admin, a.approved_date,
                       u.full_name as student_name, u.email as student_email,
                       m.full_name as mentor_name,
                       admin_u.full_name as admin_name
                FROM internship_applications a
                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN users m ON a.reported_by = m.id
                LEFT JOIN users admin_u ON a.approved_by_admin = admin_u.id
                WHERE a.internship_status IN ('Reported by Mentor', 'On Hold', 'Discontinued', 'Removed')
                ORDER BY a.reported_date DESC NULLS LAST, a.id DESC";

$reports_result = mysqli_query($conn, $reports_sql);
$reports = [];
if ($reports_result) {
    while ($row = mysqli_fetch_assoc($reports_result)) {
        $reports[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Reports - IMP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-slate-50">

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Student Reports</h1>
        <p class="text-slate-600">Review and manage mentor-submitted student reports</p>
    </div>

    <?php if ($success_msg): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg alert-success">
        <?php echo htmlspecialchars($success_msg); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg alert-danger">
        <?php echo htmlspecialchars($error_msg); ?>
    </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php if (empty($reports)): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <span class="material-symbols-outlined text-slate-300 text-6xl">inbox</span>
            <p class="text-slate-500 mt-2">No student reports at this time</p>
        </div>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <!-- Student Info -->
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Student</p>
                            <p class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($report['student_name']); ?></p>
                            <p class="text-sm text-slate-600"><?php echo htmlspecialchars($report['student_email']); ?></p>
                        </div>

                        <!-- Internship -->
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Internship</p>
                            <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($report['internship_title']); ?></p>
                            <p class="text-xs text-slate-600">ID: <?php echo $report['internship_id']; ?></p>
                        </div>

                        <!-- Status -->
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Status</p>
                            <p class="inline-block px-3 py-1 rounded-full text-xs font-bold
                                <?php 
                                $status_colors = [
                                    'Reported by Mentor' => 'bg-yellow-100 text-yellow-800',
                                    'On Hold' => 'bg-orange-100 text-orange-800',
                                    'Discontinued' => 'bg-red-100 text-red-800',
                                    'Removed' => 'bg-slate-100 text-slate-800',
                                    'Active' => 'bg-green-100 text-green-800'
                                ];
                                echo $status_colors[$report['internship_status']] ?? 'bg-slate-100 text-slate-800';
                                ?>">
                                <?php echo htmlspecialchars($report['internship_status']); ?>
                            </p>
                        </div>

                        <!-- Report Date -->
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Reported</p>
                            <p class="text-sm font-bold text-slate-900"><?php echo date('M d, Y', strtotime($report['reported_date'])); ?></p>
                            <p class="text-xs text-slate-600"><?php echo htmlspecialchars($report['mentor_name']); ?></p>
                        </div>
                    </div>

                    <!-- Report Details -->
                    <div class="border-t pt-6 space-y-4">
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-2">Report Reason</p>
                            <p class="text-sm font-bold text-red-700"><?php echo htmlspecialchars($report['report_reason']); ?></p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-2">Mentor Remarks</p>
                            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded"><?php echo htmlspecialchars($report['mentor_remarks']); ?></p>
                        </div>

                        <?php if ($report['internship_status'] !== 'Reported by Mentor'): ?>
                        <div>
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-2">Admin Decision</p>
                            <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($report['admin_decision']); ?></p>
                            <p class="text-xs text-slate-600">By <?php echo htmlspecialchars($report['admin_name']); ?> on <?php echo date('M d, Y', strtotime($report['approved_date'])); ?></p>
                            <?php if ($report['admin_remarks']): ?>
                            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded mt-2"><?php echo htmlspecialchars($report['admin_remarks']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="flex gap-2">
                            <button onclick="openDecisionModal(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['student_name']); ?>')" 
                                    class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                                Take Action
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Decision Modal -->
<div id="decisionModal" class="modal items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="bg-slate-900 text-white p-6 flex items-center justify-between">
            <h2 class="text-xl font-bold">Take Action on Report</h2>
            <button onclick="closeDecisionModal()" class="text-slate-300 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="decision">
            <input type="hidden" name="application_id" id="modalAppId" value="">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Student Name</label>
                <p class="text-sm font-bold text-slate-900" id="modalStudentName"></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Decision</label>
                <select name="decision" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">-- Select Decision --</option>
                    <option value="Keep Active">Keep Active</option>
                    <option value="Put On Hold">Put On Hold</option>
                    <option value="Discontinue Internship">Discontinue Internship</option>
                    <option value="Remove from Internship">Remove from Internship</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Admin Remarks</label>
                <textarea name="admin_remarks" rows="3" placeholder="Enter your decision remarks..." 
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                    Submit Decision
                </button>
                <button type="button" onclick="closeDecisionModal()" class="flex-1 px-4 py-2 bg-slate-200 text-slate-800 font-semibold rounded-lg hover:bg-slate-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDecisionModal(appId, studentName) {
    document.getElementById('modalAppId').value = appId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('decisionModal').classList.add('show');
}

function closeDecisionModal() {
    document.getElementById('decisionModal').classList.remove('show');
}
</script>

<script src="js/alerts.js"></script>
</body>
</html>
