<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_role(['coordinator', 'admin']);
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$success_msg = "";
$error_msg = "";

// Generate CSRF token if not set
generate_csrf_token();

// Handle Mentor Assignment / Reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF security check failed.";
    } else {
        $student_id = intval($_POST['student_id']);
        $mentor_id = intval($_POST['mentor_id']);
        $application_id = intval($_POST['application_id']);
        $coordinator_id = current_user_id();

        // Verify mentor exists and has correct role
        $mentor_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'mentor' AND status = 'Active' LIMIT 1");
        $mentor_stmt->bind_param('i', $mentor_id);
        $mentor_stmt->execute();
        $mentor_row = $mentor_stmt->get_result()->fetch_assoc();

        // Verify student application exists and has active status
        $app_stmt = $conn->prepare("SELECT id, user_id, internship_name, full_name FROM internship_applications WHERE id = ? AND user_id = ? AND status IN ('Selected', 'Started', 'Active Intern', 'Internship Started') LIMIT 1");
        $app_stmt->bind_param('ii', $application_id, $student_id);
        $app_stmt->execute();
        $app_row = $app_stmt->get_result()->fetch_assoc();

        if (!$mentor_row) {
            $error_msg = "Selected mentor is invalid or inactive.";
        } elseif (!$app_row) {
            $error_msg = "Selected intern application is invalid or not in active state.";
        } else {
            // Check if this assignment already exists and is active
            $dup_stmt = $conn->prepare("SELECT id FROM mentor_assignments WHERE student_id = ? AND mentor_id = ? AND application_id = ? AND status = 'active' LIMIT 1");
            $dup_stmt->bind_param('iii', $student_id, $mentor_id, $application_id);
            $dup_stmt->execute();
            $dup_row = $dup_stmt->get_result()->fetch_assoc();

            if ($dup_row) {
                $error_msg = "Mentor is already assigned to this intern.";
            } else {
                // Begin transaction
                mysqli_begin_transaction($conn);
                try {
                    // 1. Deactivate old assignments
                    $deactivate_stmt = $conn->prepare("UPDATE mentor_assignments SET status = 'inactive' WHERE student_id = ? AND application_id = ? AND status = 'active'");
                    $deactivate_stmt->bind_param('ii', $student_id, $application_id);
                    if (!$deactivate_stmt->execute()) {
                        throw new Exception("Failed to deactivate old assignments: " . $deactivate_stmt->error);
                    }

                    // 2. Insert new assignment
                    $assign_stmt = $conn->prepare("INSERT INTO mentor_assignments (mentor_id, student_id, application_id, assigned_by, status) VALUES (?, ?, ?, ?, 'active')");
                    $assign_stmt->bind_param('iiii', $mentor_id, $student_id, $application_id, $coordinator_id);
                    if (!$assign_stmt->execute()) {
                        throw new Exception("Failed to insert new assignment: " . $assign_stmt->error);
                    }

                    // 3. Update application table mentor field
                    $update_app_stmt = $conn->prepare("UPDATE internship_applications SET mentor_id = ? WHERE id = ?");
                    $update_app_stmt->bind_param('ii', $mentor_id, $application_id);
                    if (!$update_app_stmt->execute()) {
                        throw new Exception("Failed to update application: " . $update_app_stmt->error);
                    }

                    // 4. Log in mentor activity logs
                    $details = "Coordinator assigned mentor " . $mentor_row['full_name'] . " to student " . $app_row['full_name'] . " for application #" . $application_id;
                    $log_stmt = $conn->prepare("INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details) VALUES (?, 'assignment', ?, NULL, ?)");
                    $log_stmt->bind_param('iis', $mentor_id, $student_id, $details);
                    if (!$log_stmt->execute()) {
                        throw new Exception("Failed to log activity: " . $log_stmt->error);
                    }

                    // 5. Notify Student
                    $student_msg = "Mentor " . $mentor_row['full_name'] . " has been assigned to supervise your internship \"" . $app_row['internship_name'] . "\".";
                    $notif_student_stmt = $conn->prepare("INSERT INTO student_notifications (user_id, title, type, message) VALUES (?, 'Mentor Assigned', 'mentor', ?)");
                    $notif_student_stmt->bind_param('is', $student_id, $student_msg);
                    if (!$notif_student_stmt->execute()) {
                        throw new Exception("Failed to notify student: " . $notif_student_stmt->error);
                    }

                    // 6. Notify Mentor
                    $mentor_msg = "You have been assigned to supervise student " . $app_row['full_name'] . " for their internship \"" . $app_row['internship_name'] . "\".";
                    $notif_mentor_stmt = $conn->prepare("INSERT INTO mentor_notifications (mentor_id, title, type, message) VALUES (?, 'New Intern Assigned', 'intern_assignment', ?)");
                    $notif_mentor_stmt->bind_param('is', $mentor_id, $mentor_msg);
                    if (!$notif_mentor_stmt->execute()) {
                        throw new Exception("Failed to notify mentor: " . $notif_mentor_stmt->error);
                    }

                    mysqli_commit($conn);
                    $success_msg = "Mentor assigned successfully and notifications sent.";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Database transaction failed: " . $e->getMessage();
                }
            }
        }
    }
}

// Pagination config
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total rows
$count_query = "
    SELECT COUNT(*) as total
    FROM internship_applications a
    WHERE a.status IN ('Selected', 'Started', 'Active Intern', 'Internship Started') AND a.is_deleted = 0
";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'] ?? 0;

// Fetch all active/selected students with their application and current mentor assignment
$students_query = "
    SELECT 
        a.id as application_id,
        a.user_id as student_id,
        a.full_name as student_name,
        a.email as student_email,
        a.phone as student_phone,
        a.internship_name,
        a.college_name,
        a.status as application_status,
        ma.mentor_id as assigned_mentor_id,
        u_mentor.full_name as mentor_name,
        u_mentor.email as mentor_email
    FROM internship_applications a
    LEFT JOIN mentor_assignments ma ON a.user_id = ma.student_id AND a.id = ma.application_id AND ma.status = 'active'
    LEFT JOIN users u_mentor ON ma.mentor_id = u_mentor.id
    WHERE a.status IN ('Selected', 'Started', 'Active Intern', 'Internship Started') AND a.is_deleted = 0
    ORDER BY a.applied_date DESC
    LIMIT $limit OFFSET $offset
";
$students_result = mysqli_query($conn, $students_query);

// Fetch all active mentors
$mentors_result = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'mentor' AND status = 'Active' ORDER BY full_name ASC");
$mentors = [];
while ($row = mysqli_fetch_assoc($mentors_result)) {
    $mentors[] = $row;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Team & Mentor Management - Coordinator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
</head>
<body class="bg-[#f8f9fa] text-gray-850">

    <!-- SideNavBar -->
    <aside class="fixed left-0 top-0 h-screen w-64 z-50 bg-white border-r border-gray-200 flex flex-col py-6 font-sans shadow-sm">
        <div class="px-6 mb-8">
            <h1 class="text-xl font-bold tracking-tight text-blue-600 flex items-center gap-2"><span class="material-symbols-outlined">admin_panel_settings</span> Coordinator</h1>
            <p class="text-xs text-gray-500 font-medium mt-1 uppercase tracking-wider">Management Console</p>
        </div>
        <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_internships.php">
                <span class="material-symbols-outlined">school</span>
                <span class="text-sm font-medium">Internship Mgt</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_candidates.php">
                <span class="material-symbols-outlined">groups</span>
                <span class="text-sm font-medium">Candidates</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_projects.php">
                <span class="material-symbols-outlined">assignment</span>
                <span class="text-sm font-medium">Project Mgt</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_daily_logs.html">
                <span class="material-symbols-outlined">monitoring</span>
                <span class="text-sm font-medium">Daily Logs</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="coordinator_reports.php">
                <span class="material-symbols-outlined">analytics</span>
                <span class="text-sm font-medium">Reports</span>
            </a>
            <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm"
                href="coordinator_teams.php">
                <span class="material-symbols-outlined">diversity_3</span>
                <span class="text-sm font-medium">Team Mgt</span>
            </a>
        </nav>
        <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                href="#">
                <span class="material-symbols-outlined">help</span>
                <span class="text-sm font-medium">Help Center</span>
            </a>
            <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all"
                href="logout.php">
                <span class="material-symbols-outlined">logout</span>
                <span class="text-sm font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="ml-64 flex flex-col min-h-screen">
        <!-- TopNavBar -->
        <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-4 font-sans antialiased text-sm">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-gray-800">Team & Mentor Management</h2>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500 font-medium">
                <span>Welcome, Coordinator</span>
            </div>
        </header>

        <div class="flex-grow p-8 space-y-6">
            <!-- Toast messages -->
            <?php if ($success_msg !== ''): ?>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error_msg !== ''): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Internship Supervision & Assignments</h1>
                    <p class="text-slate-500 text-sm mt-1">Assign active student interns to corporate mentors for logs review and evaluation.</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 shadow-sm text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Active Internships</p>
                    <p class="text-xl font-bold text-blue-600"><?php echo $total_rows; ?></p>
                </div>
            </div>

            <!-- Table of Active Students -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Student Intern</th>
                            <th class="px-6 py-4">Internship Detail</th>
                            <th class="px-6 py-4">College Name</th>
                            <th class="px-6 py-4">Assigned Mentor</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-150">
                        <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($student['student_email']); ?></div>
                                        <?php if (!empty($student['student_phone'])): ?>
                                            <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($student['student_phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($student['internship_name']); ?></div>
                                        <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 text-[10px] font-bold text-blue-700 px-2 py-0.5 mt-1 uppercase">
                                            <?php echo htmlspecialchars($student['application_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 text-xs">
                                        <?php echo htmlspecialchars($student['college_name'] ?: 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($student['assigned_mentor_id']): ?>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-800 flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-slate-500 text-sm">person</span>
                                                    <?php echo htmlspecialchars($student['mentor_name']); ?>
                                                </span>
                                                <span class="text-[11px] text-slate-500 pl-5"><?php echo htmlspecialchars($student['mentor_email']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-semibold px-2.5 py-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                No Mentor Assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick="openAssignModal(<?php echo $student['student_id']; ?>, <?php echo $student['application_id']; ?>, '<?php echo htmlspecialchars(addslashes($student['student_name'])); ?>', <?php echo $student['assigned_mentor_id'] ?: 'null'; ?>)" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5 inline-flex shadow-sm cursor-pointer">
                                            <span class="material-symbols-outlined text-sm">edit</span>
                                            <span>Assign / Reassign</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-4xl text-slate-300 block mb-2">person_off</span>
                                    <span>No active internships found. Active interns are selected through the recruitment funnel.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php
            $params = $_GET;
            echo module_pagination($total_rows, $limit, $page, 'coordinator_teams.php', $params);
            ?>
        </div>
    </main>

    <!-- Assign Mentor Modal -->
    <div id="assign-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-slate-800">Assign Mentor</h3>
                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form action="coordinator_teams.php" method="POST" class="p-6 space-y-4">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" id="modal-student-id" name="student_id">
                <input type="hidden" id="modal-application-id" name="application_id">

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Intern Student</label>
                    <input type="text" id="modal-student-name" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Select Mentor</label>
                    <select id="modal-mentor-id" name="mentor_id" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:border-blue-600 focus:ring-blue-600/10">
                        <option value="">-- Choose Corporate Mentor --</option>
                        <?php foreach ($mentors as $mentor): ?>
                            <option value="<?php echo $mentor['id']; ?>">
                                <?php echo htmlspecialchars($mentor['full_name']); ?> (<?php echo htmlspecialchars($mentor['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeAssignModal()" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-md">Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssignModal(studentId, applicationId, studentName, currentMentorId) {
            document.getElementById('modal-student-id').value = studentId;
            document.getElementById('modal-application-id').value = applicationId;
            document.getElementById('modal-student-name').value = studentName;
            
            const selectEl = document.getElementById('modal-mentor-id');
            selectEl.value = currentMentorId ? currentMentorId : "";
            
            document.getElementById('assign-modal').classList.remove('hidden');
        }

        function closeAssignModal() {
            document.getElementById('assign-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
