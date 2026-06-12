<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once __DIR__ . '/../includes/db.php';
// Notification helpers (notifyUser, createNotification, sendStudentNotification)
require_once __DIR__ . '/../includes/mail_helper.php';

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_assignment') {
        $project_type_id = intval($_POST['project_type_id'] ?? 0);
        $project_subtype_id = isset($_POST['project_subtype_id']) && $_POST['project_subtype_id'] !== '' ? intval($_POST['project_subtype_id']) : null;
        $coordinator_id = intval($_POST['coordinator_id'] ?? 0);
        $assigned_by = intval($_SESSION['user_id']);

        if ($project_type_id <= 0 || $coordinator_id <= 0) {
            $error_msg = 'Both Project Type and Coordinator are required.';
        } else {
            // Check if this specific exact assignment (coord + type + subtype) already exists
            $check_sql = "SELECT id FROM coordinator_assignments WHERE coordinator_id = $coordinator_id AND project_type_id = $project_type_id";
            if ($project_subtype_id) {
                $check_sql .= " AND project_subtype_id = $project_subtype_id";
            } else {
                $check_sql .= " AND project_subtype_id IS NULL";
            }
            $check_res = mysqli_query($conn, $check_sql);
            
            if ($check_res && mysqli_num_rows($check_res) > 0) {
                $error_msg = 'This exact assignment already exists for the coordinator.';
            } else {
                // Insert new assignment
                $stmt = $conn->prepare('INSERT INTO coordinator_assignments (coordinator_id, project_type_id, project_subtype_id, assigned_by) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iiii', $coordinator_id, $project_type_id, $project_subtype_id, $assigned_by);
                if ($stmt->execute()) {
                    $success_msg = 'Coordinator assigned successfully.';
                    
                    // Fetch category names for notification
                    $type_name = "Unknown";
                    $pt_res = mysqli_query($conn, "SELECT type_name FROM project_types WHERE id = $project_type_id LIMIT 1");
                    if ($pt_row = mysqli_fetch_assoc($pt_res)) $type_name = $pt_row['type_name'];
                    
                    $subtype_name = "All Subtypes";
                    if ($project_subtype_id) {
                        $ps_res = mysqli_query($conn, "SELECT subtype_name FROM project_subtypes WHERE id = $project_subtype_id LIMIT 1");
                        if ($ps_row = mysqli_fetch_assoc($ps_res)) $subtype_name = $ps_row['subtype_name'];
                    }

                    // Notification
                    $notif_title = "New Category Assigned";
                    $notif_msg = "Admin has assigned you to manage: $type_name -> $subtype_name.";
                    @mysqli_query($conn, "INSERT INTO notifications (user_id, role, title, message, type) VALUES ($coordinator_id, 'coordinator', '$notif_title', '$notif_msg', 'info')");

                    // Send Email
                    $c_res = mysqli_query($conn, "SELECT email, full_name FROM users WHERE id = $coordinator_id");
                    if ($c_row = mysqli_fetch_assoc($c_res)) {
                    $email_body = "Dear {$c_row['full_name']},\n\nYou have been assigned a new project category to manage:\n\nProject Type: $type_name\nSubtype: $subtype_name\n\nPlease log in to your dashboard to view the related internships, teams, and students.\n\nRegards,\nIMP Admin Team";
                    if (function_exists('notifyUser')) {
                      notifyUser($coordinator_id, 'coordinator', $c_row['email'], "IMP - New Project Category Assigned", $email_body, [
                        'event' => 'New Assignment',
                        'assigned_type' => $type_name,
                        'assigned_subtype' => $subtype_name,
                        'action_url' => 'http://localhost/IMP/login.php',
                        'action_label' => 'Log in to Dashboard'
                      ], 'coordinator_assignment');
                    } elseif (function_exists('createNotification')) {
                      // Fallback: create in-app notification when mail helper notifyUser is not available
                      createNotification($coordinator_id, 'coordinator', "IMP - New Project Category Assigned", $email_body, 'info', 'coordinator_assignment');
                    } else {
                      // Last-resort: ensure no fatal error — the DB notification was already inserted above.
                    }
                  }
                    
                    // Keep internships matching this project type/subtype in sync with the new coordinator_id!
                    if (!$project_subtype_id) {
                        $update_intern_stmt = $conn->prepare("UPDATE internships SET coordinator_id = ? WHERE project_type = ?");
                        $update_intern_stmt->bind_param('is', $coordinator_id, $type_name);
                        $update_intern_stmt->execute();
                        $update_intern_stmt->close();
                    } else {
                        $update_intern_stmt = $conn->prepare("UPDATE internships SET coordinator_id = ? WHERE project_type = ? AND project_subtype = ?");
                        $update_intern_stmt->bind_param('iss', $coordinator_id, $type_name, $subtype_name);
                        $update_intern_stmt->execute();
                        $update_intern_stmt->close();
                    }
                } else {
                    $error_msg = 'Failed to save assignment.';
                }
                $stmt->close();
            }
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

// Fetch all project subtypes
$project_subtypes = [];
$ps_res = mysqli_query($conn, 'SELECT * FROM project_subtypes WHERE status = "Active" ORDER BY subtype_name ASC');
while ($row = mysqli_fetch_assoc($ps_res)) {
    $project_subtypes[] = $row;
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
    SELECT ca.id, ca.coordinator_id, ca.project_type_id, ca.project_subtype_id,
           u.full_name as coordinator_name, 
           pt.type_name as project_type_name,
           ps.subtype_name
    FROM coordinator_assignments ca
    JOIN users u ON ca.coordinator_id = u.id
    LEFT JOIN project_types pt ON ca.project_type_id = pt.id
    LEFT JOIN project_subtypes ps ON ca.project_subtype_id = ps.id
    ORDER BY pt.type_name ASC, ps.subtype_name ASC
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
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

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
          <p class="mt-2 text-sm text-slate-500">Assign specific Project Categories and Subtypes to Coordinators so they manage only their cohorts.</p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[360px_1fr]">
          <!-- Form Panel -->
          <div class="border border-gray-200 rounded-3xl bg-slate-50 p-6">
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Create Assignment</h2>
            <form method="POST" action="coordinator_assignments.php" class="space-y-4">
              <input type="hidden" name="action" value="save_assignment">
              
              <div>
                <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Coordinator</label>
                <select name="coordinator_id" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                  <option value="">Select a Coordinator</option>
                  <?php foreach ($coordinators as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Project Category / Type</label>
                <select name="project_type_id" id="project_type_id" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10" onchange="filterSubtypes()">
                  <option value="">Select a Category</option>
                  <?php foreach ($project_types as $pt): ?>
                    <option value="<?php echo $pt['id']; ?>"><?php echo htmlspecialchars($pt['type_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Project Subtype (Optional)</label>
                <select name="project_subtype_id" id="project_subtype_id" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                  <option value="">All Subtypes in Category</option>
                  <!-- Subtypes populated via JS -->
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Leave empty to assign ALL subtypes under the selected category.</p>
              </div>

              <div class="flex justify-end pt-2">
                <button type="submit" class="w-full rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">Assign Category</button>
              </div>
            </form>
          </div>

          <!-- Assignments Table -->
          <div class="rounded-3xl border border-gray-200 bg-white p-6 overflow-x-auto">
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Category Assignments List</h2>
            <table class="min-w-full text-left text-sm text-slate-600">
              <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-[0.24em]">
                <tr>
                  <th class="px-4 py-3">Coordinator</th>
                  <th class="px-4 py-3">Category (Type)</th>
                  <th class="px-4 py-3">Project Subtype</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php foreach ($assignments as $a): ?>
                  <tr>
                    <td class="px-4 py-4 font-medium text-slate-700">
                      <?php echo htmlspecialchars($a['coordinator_name']); ?>
                    </td>
                    <td class="px-4 py-4 font-semibold text-slate-900">
                      <?php echo htmlspecialchars($a['project_type_name'] ?? 'Unknown'); ?>
                    </td>
                    <td class="px-4 py-4 font-medium text-slate-700">
                      <?php if($a['subtype_name']): ?>
                        <span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md text-xs"><?php echo htmlspecialchars($a['subtype_name']); ?></span>
                      <?php else: ?>
                        <span class="text-slate-400 italic">All Subtypes</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-right whitespace-nowrap">
                      <a href="coordinator_assignments.php?action=remove_assignment&id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Remove this assignment?');" class="text-red-600 hover:text-red-800 text-xs font-bold">Remove</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($assignments)): ?>
                  <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-slate-400">No coordinator assignments found. Assign a category to get started.</td>
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

<script>
const allSubtypes = <?php echo json_encode($project_subtypes); ?>;
function filterSubtypes() {
  const typeId = document.getElementById('project_type_id').value;
  const subtypeSelect = document.getElementById('project_subtype_id');
  
  // Clear current options
  subtypeSelect.innerHTML = '<option value="">All Subtypes in Category</option>';
  
  if (typeId) {
    const filtered = allSubtypes.filter(st => st.project_type_id == typeId);
    filtered.forEach(st => {
      const opt = document.createElement('option');
      opt.value = st.id;
      opt.textContent = st.subtype_name;
      subtypeSelect.appendChild(opt);
    });
  }
}
</script>
<script src="js/alerts.js"></script>
</body>
</html>
