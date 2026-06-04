<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'db.php';

$success_msg = '';
$error_msg = '';

// Fix undefined tab warning - use safe fallback
$active_tab = $_GET['tab'] ?? 'types';
$allowed_tabs = ['types', 'subtypes'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'types';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_type') {
        $type_name = trim($_POST['type_name'] ?? '');
        $status = ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active';
        $edit_id = intval($_POST['edit_type_id'] ?? 0);

        if ($type_name === '') {
            $error_msg = 'Project Type name is required.';
        } else {
            $check_stmt = $conn->prepare('SELECT id FROM project_types WHERE LOWER(type_name) = LOWER(?)' . ($edit_id ? ' AND id != ?' : '') . ' LIMIT 1');
            if ($edit_id) {
                $check_stmt->bind_param('si', $type_name, $edit_id);
            } else {
                $check_stmt->bind_param('s', $type_name);
            }
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($check_res && mysqli_num_rows($check_res) > 0) {
                $error_msg = 'A project type with this name already exists.';
            } else {
                if ($edit_id) {
                    $stmt = $conn->prepare('UPDATE project_types SET type_name = ?, status = ? WHERE id = ?');
                    $stmt->bind_param('ssi', $type_name, $status, $edit_id);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = 'Project Type updated successfully.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO project_types (type_name, status) VALUES (?, ?)');
                    $stmt->bind_param('ss', $type_name, $status);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = 'Project Type added successfully.';
                }
            }
            $check_stmt->close();
        }
        $active_tab = 'types';
    }

    if ($_POST['action'] === 'save_subtype') {
        $project_type_id = intval($_POST['project_type_id'] ?? 0);
        $subtype_name = trim($_POST['subtype_name'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $mode = trim($_POST['mode'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $status = ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active';
        $edit_id = intval($_POST['edit_subtype_id'] ?? 0);

        if ($project_type_id <= 0 || $subtype_name === '') {
            $error_msg = 'Project Type and Subtype name are required.';
        } else {
            $type_stmt = $conn->prepare('SELECT id FROM project_types WHERE id = ? LIMIT 1');
            $type_stmt->bind_param('i', $project_type_id);
            $type_stmt->execute();
            $type_res = $type_stmt->get_result();
            if (!$type_res || mysqli_num_rows($type_res) === 0) {
                $error_msg = 'Selected Project Type does not exist.';
            } else {
                $check_stmt = $conn->prepare('SELECT id FROM project_subtypes WHERE project_type_id = ? AND LOWER(subtype_name) = LOWER(?)' . ($edit_id ? ' AND id != ?' : '') . ' LIMIT 1');
                if ($edit_id) {
                    $check_stmt->bind_param('isi', $project_type_id, $subtype_name, $edit_id);
                } else {
                    $check_stmt->bind_param('is', $project_type_id, $subtype_name);
                }
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $error_msg = 'This Subtype already exists for the selected Type.';
                } else {
                    if ($edit_id) {
                        $stmt = $conn->prepare('UPDATE project_subtypes SET project_type_id = ?, subtype_name = ?, skills = ?, mode = ?, duration = ?, status = ? WHERE id = ?');
                        $stmt->bind_param('isssssi', $project_type_id, $subtype_name, $skills, $mode, $duration, $status, $edit_id);
                        $stmt->execute();
                        $stmt->close();
                        $success_msg = 'Project Subtype updated successfully.';
                    } else {
                        $stmt = $conn->prepare('INSERT INTO project_subtypes (project_type_id, subtype_name, skills, mode, duration, status) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param('isssss', $project_type_id, $subtype_name, $skills, $mode, $duration, $status);
                        $stmt->execute();
                        $stmt->close();
                        $success_msg = 'Project Subtype added successfully.';
                    }
                }
                $check_stmt->close();
            }
            $type_stmt->close();
        }
        $active_tab = 'subtypes';
    }
}

// Handle GET actions for toggling and deleting
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'delete_type') {
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM project_subtypes WHERE project_type_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('DELETE FROM project_types WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Project Type and its Subtypes deleted successfully.';
        }
        $active_tab = 'types';
    }
    if ($_GET['action'] === 'toggle_type' && isset($_GET['status'])) {
        $status = ($_GET['status'] === 'Inactive') ? 'Inactive' : 'Active';
        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE project_types SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Project Type status updated successfully.';
        }
        $active_tab = 'types';
    }
    if ($_GET['action'] === 'delete_subtype') {
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM project_subtypes WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Project Subtype deleted successfully.';
        }
        $active_tab = 'subtypes';
    }
    if ($_GET['action'] === 'toggle_subtype' && isset($_GET['status'])) {
        $status = ($_GET['status'] === 'Inactive') ? 'Inactive' : 'Active';
        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE project_subtypes SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Project Subtype status updated successfully.';
        }
        $active_tab = 'subtypes';
    }
}

$edit_type_id = intval($_GET['edit_type_id'] ?? 0);
$edit_type = null;
if ($edit_type_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM project_types WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $edit_type_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_type = mysqli_fetch_assoc($res);
    $stmt->close();
    $active_tab = 'types';
}

$edit_subtype_id = intval($_GET['edit_subtype_id'] ?? 0);
$edit_subtype = null;
if ($edit_subtype_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM project_subtypes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $edit_subtype_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_subtype = mysqli_fetch_assoc($res);
    $stmt->close();
    $active_tab = 'subtypes';
}

$project_types = [];
$type_res = mysqli_query($conn, 'SELECT * FROM project_types ORDER BY type_name ASC');
while ($row = mysqli_fetch_assoc($type_res)) {
    $project_types[] = $row;
}

$project_subtypes = [];
$subtype_res = mysqli_query($conn, 'SELECT s.*, t.type_name FROM project_subtypes s JOIN project_types t ON s.project_type_id = t.id ORDER BY t.type_name ASC, s.subtype_name ASC');
while ($row = mysqli_fetch_assoc($subtype_res)) {
    $project_subtypes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Project Categories</title>
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
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-slate-900">Project Categories</h1>
            <p class="mt-2 text-sm text-slate-500">Admin-only management for approved internship category options.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a href="admin_project_categories.php?tab=types" class="px-4 py-2 rounded-full text-xs font-bold <?php echo $active_tab === 'types' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'; ?>">Manage Project Types</a>
            <a href="admin_project_categories.php?tab=subtypes" class="px-4 py-2 rounded-full text-xs font-bold <?php echo $active_tab === 'subtypes' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'; ?>">Manage Project Subtypes</a>
          </div>
        </div>

        <?php if ($active_tab === 'types'): ?>
          <div class="mt-8 grid gap-6 lg:grid-cols-[360px_1fr]">
            <div class="border border-gray-200 rounded-3xl bg-slate-50 p-6">
              <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Add / Edit Type</h2>
              <form method="POST" action="admin_project_categories.php?tab=types" class="space-y-4">
                <input type="hidden" name="action" value="save_type">
                <input type="hidden" name="edit_type_id" value="<?php echo $edit_type['id'] ?? ''; ?>">
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Project Type Name</label>
                  <input type="text" name="type_name" value="<?php echo htmlspecialchars($edit_type['type_name'] ?? ''); ?>" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Status</label>
                  <select name="status" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                    <option value="Active" <?php echo (($edit_type['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (($edit_type['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                  <?php if (!empty($edit_type)): ?>
                    <a href="admin_project_categories.php?tab=types" class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-gray-100">Cancel</a>
                  <?php endif; ?>
                  <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Save Type</button>
                </div>
              </form>
            </div>

            <div class="rounded-3xl border border-gray-200 bg-white p-6 overflow-x-auto">
              <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Project Types</h2>
              <table class="min-w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-[0.24em]">
                  <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($project_types as $type): ?>
                    <tr>
                      <td class="px-4 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($type['type_name']); ?></td>
                      <td class="px-4 py-4">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold <?php echo $type['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?php echo htmlspecialchars($type['status']); ?></span>
                      </td>
                      <td class="px-4 py-4 text-xs text-slate-500"><?php echo htmlspecialchars($type['created_at']); ?></td>
                      <td class="px-4 py-4 text-right space-x-2 whitespace-nowrap">
                        <a href="admin_project_categories.php?tab=types&edit_type_id=<?php echo (int)$type['id']; ?>" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Edit</a>
                        <a href="admin_project_categories.php?tab=types&action=toggle_type&id=<?php echo (int)$type['id']; ?>&status=<?php echo $type['status'] === 'Active' ? 'Inactive' : 'Active'; ?>" class="text-slate-700 hover:text-slate-900 text-xs font-semibold"><?php echo $type['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></a>
                        <a href="admin_project_categories.php?tab=types&action=delete_type&id=<?php echo (int)$type['id']; ?>" onclick="return confirm('Delete this type and all its subtypes?');" class="text-red-600 hover:text-red-800 text-xs font-semibold">Delete</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($project_types)): ?>
                    <tr>
                      <td colspan="4" class="px-4 py-8 text-center text-slate-400">No project types found. Add a type to get started.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php else: ?>
          <div class="mt-8 grid gap-6 lg:grid-cols-[360px_1fr]">
            <div class="border border-gray-200 rounded-3xl bg-slate-50 p-6">
              <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Add / Edit Subtype</h2>
              <form method="POST" action="admin_project_categories.php?tab=subtypes" class="space-y-4">
                <input type="hidden" name="action" value="save_subtype">
                <input type="hidden" name="edit_subtype_id" value="<?php echo $edit_subtype['id'] ?? ''; ?>">
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Project Type</label>
                  <select name="project_type_id" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                    <option value="">Select a project type</option>
                    <?php foreach ($project_types as $type): ?>
                      <option value="<?php echo (int)$type['id']; ?>" <?php echo (isset($edit_subtype['project_type_id']) && $edit_subtype['project_type_id'] == $type['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['type_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Subtype Name</label>
                  <input type="text" name="subtype_name" value="<?php echo htmlspecialchars($edit_subtype['subtype_name'] ?? ''); ?>" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Skills / Technology Stack</label>
                  <input type="text" name="skills" value="<?php echo htmlspecialchars($edit_subtype['skills'] ?? ''); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. HTML, CSS, JavaScript, PHP, MySQL">
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Mode</label>
                  <select name="mode" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                    <option value="Remote" <?php echo (($edit_subtype['mode'] ?? '') === 'Remote') ? 'selected' : ''; ?>>Remote</option>
                    <option value="Hybrid" <?php echo (($edit_subtype['mode'] ?? '') === 'Hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                    <option value="Offline" <?php echo (($edit_subtype['mode'] ?? '') === 'Offline') ? 'selected' : ''; ?>>Offline</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Duration</label>
                  <input list="durations" name="duration" value="<?php echo htmlspecialchars($edit_subtype['duration'] ?? ''); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. 3 Months">
                  <datalist id="durations">
                    <option value="1 Month">
                    <option value="2 Months">
                    <option value="3 Months">
                    <option value="6 Months">
                  </datalist>
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-[0.2em] text-slate-600 mb-2">Status</label>
                  <select name="status" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600/10">
                    <option value="Active" <?php echo (($edit_subtype['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (($edit_subtype['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                  <?php if (!empty($edit_subtype)): ?>
                    <a href="admin_project_categories.php?tab=subtypes" class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-gray-100">Cancel</a>
                  <?php endif; ?>
                  <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Save Subtype</button>
                </div>
              </form>
            </div>

            <div class="rounded-3xl border border-gray-200 bg-white p-6 overflow-x-auto">
              <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.2em] mb-4">Project Subtypes</h2>
              <table class="min-w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-[0.24em]">
                  <tr>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Subtype</th>
                    <th class="px-4 py-3">Skills</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($project_subtypes as $subtype): ?>
                    <tr>
                      <td class="px-4 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($subtype['type_name']); ?></td>
                      <td class="px-4 py-4"><?php echo htmlspecialchars($subtype['subtype_name']); ?></td>
                      <td class="px-4 py-4 text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($subtype['skills'] ?? ''); ?>"><?php echo htmlspecialchars($subtype['skills'] ?: '-'); ?></td>
                      <td class="px-4 py-4 text-xs"><?php echo htmlspecialchars($subtype['mode'] ?: '-'); ?></td>
                      <td class="px-4 py-4 text-xs"><?php echo htmlspecialchars($subtype['duration'] ?: '-'); ?></td>
                      <td class="px-4 py-4">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold <?php echo $subtype['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?php echo htmlspecialchars($subtype['status']); ?></span>
                      </td>
                      <td class="px-4 py-4 text-xs text-slate-500"><?php echo htmlspecialchars($subtype['created_at']); ?></td>
                      <td class="px-4 py-4 text-right space-x-2 whitespace-nowrap">
                        <a href="admin_project_categories.php?tab=subtypes&edit_subtype_id=<?php echo (int)$subtype['id']; ?>" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Edit</a>
                        <a href="admin_project_categories.php?tab=subtypes&action=toggle_subtype&id=<?php echo (int)$subtype['id']; ?>&status=<?php echo $subtype['status'] === 'Active' ? 'Inactive' : 'Active'; ?>" class="text-slate-700 hover:text-slate-900 text-xs font-semibold"><?php echo $subtype['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></a>
                        <a href="admin_project_categories.php?tab=subtypes&action=delete_subtype&id=<?php echo (int)$subtype['id']; ?>" onclick="return confirm('Delete this subtype?');" class="text-red-600 hover:text-red-800 text-xs font-semibold">Delete</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($project_subtypes)): ?>
                    <tr>
                      <td colspan="5" class="px-4 py-8 text-center text-slate-400">No project subtypes found. Add a subtype to get started.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="js/alerts.js"></script>
</body>
</html>
