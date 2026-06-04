<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'db.php';

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_assignment') {
        $project_type_id = intval($_POST['project_type_id'] ?? 0);
        $coordinator_id = intval($_POST['coordinator_id'] ?? 0);
        $assigned_by = intval($_SESSION['user_id']);

        if ($project_type_id <= 0 || $coordinator_id <= 0) {
            $error_msg = 'Both Project Type and Coordinator are required.';
        } else {
            // Check if already assigned (to support insert or update)
            $check_stmt = $conn->prepare('SELECT id FROM coordinator_assignments WHERE project_type_id = ? LIMIT 1');
            $check_stmt->bind_param('i', $project_type_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($check_res && mysqli_num_rows($check_res) > 0) {
                // Update assignment
                $stmt = $conn->prepare('UPDATE coordinator_assignments SET coordinator_id = ?, assigned_by = ?, assigned_at = NOW() WHERE project_type_id = ?');
                $stmt->bind_param('iii', $coordinator_id, $assigned_by, $project_type_id);
                if ($stmt->execute()) {
                    $success_msg = 'Coordinator assignment updated successfully.';
                    
                    // Keep internships matching this project type in sync with the new coordinator_id!
                    $pt_res = mysqli_query($conn, "SELECT type_name FROM project_types WHERE id = $project_type_id LIMIT 1");
                    if ($pt_row = mysqli_fetch_assoc($pt_res)) {
                        $type_name = $pt_row['type_name'];
                        $update_intern_stmt = $conn->prepare("UPDATE internships SET coordinator_id = ? WHERE project_type = ?");
                        $update_intern_stmt->bind_param('is', $coordinator_id, $type_name);
                        $update_intern_stmt->execute();
                        $update_intern_stmt->close();
                    }
                } else {
                    $error_msg = 'Failed to update assignment.';
                }
                $stmt->close();
            } else {
                // Insert new assignment
                $stmt = $conn->prepare('INSERT INTO coordinator_assignments (coordinator_id, project_type_id, assigned_by) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $coordinator_id, $project_type_id, $assigned_by);
                if ($stmt->execute()) {
                    $success_msg = 'Coordinator assigned successfully.';
                    
                    // Sync internships
                    $pt_res = mysqli_query($conn, "SELECT type_name FROM project_types WHERE id = $project_type_id LIMIT 1");
                    if ($pt_row = mysqli_fetch_assoc($pt_res)) {
                        $type_name = $pt_row['type_name'];
                        $update_intern_stmt = $conn->prepare("UPDATE internships SET coordinator_id = ? WHERE project_type = ?");
                        $update_intern_stmt->bind_param('is', $coordinator_id, $type_name);
                        $update_intern_stmt->execute();
                        $update_intern_stmt->close();
                    }
                } else {
                    $error_msg = 'Failed to save assignment.';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}

// Handle GET actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'remove_assignment') {
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM coordinator_assignments WHERE id = ?');
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $success_msg = 'Assignment removed successfully.';
            } else {
                $error_msg = 'Failed to remove assignment.';
            }
            $stmt->close();
        }
    }
}

// Fetch all project types
$project_types = [];
$pt_res = mysqli_query($conn, 'SELECT * FROM project_types WHERE status = "Active" ORDER BY type_name ASC');
while ($row = mysqli_fetch_assoc($pt_res)) {
    $project_types[] = $row;
}

// Fetch all coordinators
$coordinators = [];
$c_res = mysqli_query($conn, 'SELECT id, full_name, email FROM users WHERE role = "coordinator" ORDER BY full_name ASC');
while ($row = mysqli_fetch_assoc($c_res)) {
    $coordinators[] = $row;
}

// Fetch assignments
$assignments = [];
$assign_res = mysqli_query($conn, '
    SELECT ca.id, ca.coordinator_id, ca.project_type_id, u.full_name as coordinator_name, pt.type_name as project_type_name
    FROM coordinator_assignments ca
    JOIN users u ON ca.coordinator_id = u.id
    JOIN project_types pt ON ca.project_type_id = pt.id
    ORDER BY pt.type_name ASC
');
while ($row = mysqli_fetch_assoc($assign_res)) {
    $assignments[] = $row;
}

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

// Fetch admin header details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coordinator Assignments</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 text-slate-800">
<div class="min-h-screen flex">
  <!-- Sidebar -->
  <?php include 'includes/admin_sidebar.php'; ?>

  <main class="flex-1 p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto space-y-6">
      <?php if ($success_msg): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
      <?php endif; ?>
      <?php if ($error_msg): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
      <?php endif; ?>

      <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Coordinator Assignments</h1>
          <p class="mt-2 text-sm text-slate-500">Assign specific Project Categories to Coordinators so they manage only their cohorts.</p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[360px_1fr]">
          <!-- Form Panel -->
          <div class="border border-gray-200 rounded-3xl bg-slate-50 p-6">
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Create / Edit Assignment</h2>
            <form method="POST" action="admin_coordinator_assignments.php" class="space-y-4">
              <input type="hidden" name="action" value="save_assignment">
              
              <div>
                <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Project Category / Type</label>
                <select name="project_type_id" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                  <option value="">Select a Category</option>
                  <?php foreach ($project_types as $pt): ?>
                    <option value="<?php echo $pt['id']; ?>"><?php echo htmlspecialchars($pt['type_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Coordinator</label>
                <select name="coordinator_id" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                  <option value="">Select a Coordinator</option>
                  <?php foreach ($coordinators as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="flex justify-end pt-2">
                <button type="submit" class="w-full rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">Save Assignment</button>
              </div>
            </form>
          </div>

          <!-- Assignments Table -->
          <div class="rounded-3xl border border-gray-200 bg-white p-6 overflow-x-auto">
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Category Assignments List</h2>
            <table class="min-w-full text-left text-sm text-slate-600">
              <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-[0.24em]">
                <tr>
                  <th class="px-4 py-3">Category (Project Type)</th>
                  <th class="px-4 py-3">Assigned Coordinator</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php foreach ($assignments as $a): ?>
                  <tr>
                    <td class="px-4 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($a['project_type_name']); ?></td>
                    <td class="px-4 py-4 font-medium text-slate-700"><?php echo htmlspecialchars($a['coordinator_name']); ?></td>
                    <td class="px-4 py-4 text-right whitespace-nowrap">
                      <a href="admin_coordinator_assignments.php?action=remove_assignment&id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Remove this assignment?');" class="text-red-600 hover:text-red-800 text-xs font-bold">Remove</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($assignments)): ?>
                  <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-slate-400">No coordinator assignments found. Assign a category to get started.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
<script src="js/alerts.js"></script>
</body>
</html>
