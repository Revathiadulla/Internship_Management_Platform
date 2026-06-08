<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;
include_once __DIR__ . "/includes/mail_helper.php";

$success_msg = "";
$error_msg = "";

// Fetch coordinators list for manual override selection
$coordinators_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'coordinator' ORDER BY full_name ASC");
$coordinators_list = [];
if ($coordinators_res) {
    while ($row = mysqli_fetch_assoc($coordinators_res)) {
        $coordinators_list[] = $row;
    }
}

// ── Workflow Oversight Actions ──
// Handle POST-based review actions (from modal form with admin_remarks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $action = trim($_POST['action']);
    $id = intval($_POST['id']);
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    $admin_id = intval($_SESSION['user_id']);

    if ($id > 0 && in_array($action, ['approve', 'reject', 'request_changes'])) {
        $new_status = match($action) {
            'approve'         => 'Approved',
            'reject'          => 'Rejected',
            'request_changes' => 'Changes Requested',
            default           => ''
        };
        $new_approval = $new_status;

        // Fetch current project_type and coordinator_id to auto-resolve if needed
        $i_check_res = mysqli_query($conn, "SELECT project_type, coordinator_id FROM internships WHERE id = $id");
        $i_row = mysqli_fetch_assoc($i_check_res);
        $project_type = $i_row['project_type'] ?? '';
        $current_coordinator_id = isset($i_row['coordinator_id']) ? intval($i_row['coordinator_id']) : 0;

        $override_coordinator_id = isset($_POST['override_coordinator_id']) ? intval($_POST['override_coordinator_id']) : 0;
        $resolved_coordinator_id = $current_coordinator_id;
        if ($override_coordinator_id > 0) {
            $resolved_coordinator_id = $override_coordinator_id;
        } elseif ($resolved_coordinator_id == 0 && !empty($project_type)) {
            // Auto-determine coordinator based on assignments table
            $auto_res = mysqli_query($conn, "
                SELECT ca.coordinator_id 
                FROM coordinator_assignments ca
                JOIN project_types pt ON ca.project_type_id = pt.id
                WHERE pt.type_name = '" . mysqli_real_escape_string($conn, $project_type) . "' AND ca.status = 'Active'
                LIMIT 1
            ");
            if ($auto_res && $auto_row = mysqli_fetch_assoc($auto_res)) {
                $resolved_coordinator_id = intval($auto_row['coordinator_id']);
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE internships SET status = ?, approval_status = ?, admin_remarks = ?, coordinator_id = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssii", $new_status, $new_approval, $admin_remarks, $resolved_coordinator_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Project posting status updated to: $new_status";
            } else {
                $error_msg = "Failed to update status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare approval query: " . mysqli_error($conn);
        }

        if (empty($error_msg)) {
            // Fetch coordinator's details to notify them
            $coord_res = mysqli_query($conn, "SELECT u.id as coord_user_id, u.email, u.full_name, i.title
                FROM internships i LEFT JOIN users u ON i.coordinator_id = u.id
                WHERE i.id = $id LIMIT 1");
            $coord_row = mysqli_fetch_assoc($coord_res);

            if ($coord_row && !empty($coord_row['coord_user_id'])) {
                $coord_uid = intval($coord_row['coord_user_id']);
                $c_title = 'Project Status Updated';
                $c_msg = "Your project posting '" . ($coord_row['title'] ?? 'Your Project') . "' has been " . $new_status . " by Admin." . (!empty($admin_remarks) ? " Remarks: $admin_remarks" : "");
                $c_type = $new_status === 'Approved' ? 'success' : ($new_status === 'Rejected' ? 'alert' : 'info');

                $link = "coordinator_internships.php?view=" . intval($id);
                $coord_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'coordinator', ?, ?, ?, ?)");
                if ($coord_stmt) {
                    $coord_stmt->bind_param("issss", $coord_uid, $c_title, $c_msg, $c_type, $link);
                    $coord_stmt->execute();
                    $coord_stmt->close();
                }
            }

            if ($coord_row && !empty($coord_row['email'])) {
                $coord_email = $coord_row['email'];
                $coord_name = $coord_row['full_name'] ?? 'Coordinator';
                $proj_title = $coord_row['title'] ?? 'Your Project';
                $action_label_str = $new_status;
                $notif_subject = "IMP – Project Review Decision: $action_label_str – $proj_title";
                $notif_message = "Dear $coord_name,\n\nYour project posting \"$proj_title\" has been reviewed by the Admin.\n\nDecision: $action_label_str" .
                    (!empty($admin_remarks) ? "\nAdmin Remarks: $admin_remarks" : "") .
                    "\n\nPlease log in to your Coordinator dashboard to view the updated status.";
                sendEmailNotification($coord_email, $notif_subject, $notif_message, [
                    'event'         => "Project Review: $action_label_str",
                    'project_title' => $proj_title,
                    'decision'      => $action_label_str,
                    'admin_remarks' => $admin_remarks ?: 'No remarks',
                    'action_url'    => 'http://localhost/IMP/coordinator_internships.php',
                    'action_label'  => 'View My Postings'
                ]);
            }
        }
    } elseif ($id > 0 && $action === 'delete') {
        if (!function_exists('tableExists')) {
            function tableExists($conn, $table) {
                $table = mysqli_real_escape_string($conn, $table);
                $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                return $result && mysqli_num_rows($result) > 0;
            }
        }
        if (!function_exists('columnExists')) {
            function columnExists($conn, $table, $column) {
                $table = mysqli_real_escape_string($conn, $table);
                $column = mysqli_real_escape_string($conn, $column);
                $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
                return $result && mysqli_num_rows($result) > 0;
            }
        }

        if (tableExists($conn, 'test_mappings') && columnExists($conn, 'test_mappings', 'internship_id')) {
            mysqli_query($conn, "DELETE FROM test_mappings WHERE internship_id = $id");
        }
        if (tableExists($conn, 'subtype_tests') && columnExists($conn, 'subtype_tests', 'internship_id')) {
            mysqli_query($conn, "DELETE FROM subtype_tests WHERE internship_id = $id");
        }
        if (tableExists($conn, 'subtype_test_questions') && columnExists($conn, 'subtype_test_questions', 'internship_id')) {
            mysqli_query($conn, "DELETE FROM subtype_test_questions WHERE internship_id = $id");
        }
        if (tableExists($conn, 'coordinator_assignments') && columnExists($conn, 'coordinator_assignments', 'internship_id')) {
            mysqli_query($conn, "DELETE FROM coordinator_assignments WHERE internship_id = $id");
        }
        
        $del_stmt = mysqli_prepare($conn, "DELETE FROM internships WHERE id = ?");
        if ($del_stmt) {
            mysqli_stmt_bind_param($del_stmt, "i", $id);
            if (mysqli_stmt_execute($del_stmt)) {
                $success_msg = "Internship deleted successfully.";
                $admin_id = intval($_SESSION['user_id']);
                $admin_link = "admin_internships.php";
                @mysqli_query($conn, "INSERT INTO notifications (user_id, role, title, message, type, link) VALUES ($admin_id, 'admin', 'Internship Deleted', 'Internship ID $id was permanently deleted.', 'info', '$admin_link')");
            } else {
                $error_msg = "Failed to delete internship: " . mysqli_error($conn);
            }
            mysqli_stmt_close($del_stmt);
        }
    }
}

// Handle POST-based Save Remarks action from Details Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_remarks' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    $admin_id = intval($_SESSION['user_id']);

    // Fetch coordinator info for notification
    $coord_res = mysqli_query($conn, "SELECT i.title, u.id as coord_id, u.email as coord_email FROM internships i LEFT JOIN users u ON i.coordinator_id = u.id WHERE i.id = $id");
    if ($coord_res && $coord_row = mysqli_fetch_assoc($coord_res)) {
        $update_stmt = mysqli_prepare($conn, "UPDATE internships SET admin_remarks = ? WHERE id = ?");
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "si", $admin_remarks, $id);
            if (mysqli_stmt_execute($update_stmt)) {
                $success_msg = "Remarks saved successfully.";
                
                // Send notifications if a coordinator exists
                if (!empty($coord_row['coord_id'])) {
                    $coord_id = intval($coord_row['coord_id']);
                    $proj_title = $coord_row['title'];
                    $notif_title = "Admin Remarks Added";
                    $notif_msg = "Admin has added remarks to your internship posting: $proj_title";
                    
                    $link_str = "coordinator_internships.php?view=" . intval($id);
                    @mysqli_query($conn, "INSERT INTO notifications (user_id, role, title, message, type, link) VALUES ($coord_id, 'coordinator', '" . mysqli_real_escape_string($conn, $notif_title) . "', '" . mysqli_real_escape_string($conn, $notif_msg) . "', 'info', '" . mysqli_real_escape_string($conn, $link_str) . "')");

                    if (!empty($coord_row['coord_email'])) {
                        require_once 'includes/mail_helper.php';
                        $notif_subject = "IMP – Admin Remarks Added: $proj_title";
                        sendEmailNotification($coord_row['coord_email'], $notif_subject, $notif_msg . "\n\nPlease review them in the coordinator dashboard.", [
                            'event'         => 'Admin Remarks Updated',
                            'project_title' => $proj_title,
                            'action_url'    => 'http://localhost/IMP/coordinator_internships.php',
                            'action_label'  => 'View Project Postings'
                        ]);
                    }
                }
            } else {
                $error_msg = "Failed to save remarks: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}

// Handle GET-based archive/simple actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    if ($id > 0) {
        if ($action === 'archive') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Archived', approval_status = 'Archived' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship archived successfully!";
            } else {
                $error_msg = "Failed to archive internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } elseif ($action === 'unarchive') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Approved', approval_status = 'Approved' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship unarchived and set to Approved successfully!";
            } else {
                $error_msg = "Failed to unarchive internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } elseif ($action === 'approve') {
            $stmt = mysqli_prepare($conn, "UPDATE internships SET status = 'Approved', approval_status = 'Approved' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Internship approved successfully!";
                
                // Fetch coordinator to notify
                $coord_res = mysqli_query($conn, "SELECT i.title, u.id as coord_id, u.email as coord_email FROM internships i LEFT JOIN users u ON i.coordinator_id = u.id WHERE i.id = $id");
                if ($coord_res && $coord_row = mysqli_fetch_assoc($coord_res)) {
                    if (!empty($coord_row['coord_id'])) {
                        $coord_id = intval($coord_row['coord_id']);
                        $proj_title = $coord_row['title'];
                        $notif_msg = "Your internship posting has been approved by Admin: $proj_title";
                        $link_str = "coordinator_internships.php?view=" . intval($id);
                        @mysqli_query($conn, "INSERT INTO notifications (user_id, role, title, message, type, link) VALUES ($coord_id, 'coordinator', 'Internship Approved', '" . mysqli_real_escape_string($conn, $notif_msg) . "', 'success', '" . mysqli_real_escape_string($conn, $link_str) . "')");

                        if (!empty($coord_row['coord_email'])) {
                            require_once 'includes/mail_helper.php';
                            sendEmailNotification($coord_row['coord_email'], "IMP – Internship Approved: $proj_title", $notif_msg, [
                                'event'         => 'Internship Approved',
                                'project_title' => $proj_title,
                                'action_url'    => 'http://localhost/IMP/coordinator_internships.php',
                                'action_label'  => 'View Project Postings'
                            ]);
                        }
                    }
                }
            } else {
                $error_msg = "Failed to approve internship. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'details' && isset($_GET['id'])) {
    $detail_id = intval($_GET['id']);
    $detail_stmt = mysqli_prepare($conn, "SELECT i.*, u.full_name as mentor_name, uc.full_name as coordinator_name FROM internships i LEFT JOIN users u ON i.assigned_mentor = u.id LEFT JOIN users uc ON i.coordinator_id = uc.id WHERE i.id = ? LIMIT 1");

    header('Content-Type: application/json');
    if ($detail_stmt) {
        mysqli_stmt_bind_param($detail_stmt, "i", $detail_id);
        mysqli_stmt_execute($detail_stmt);
        $detail_result = mysqli_stmt_get_result($detail_stmt);
        $detail_row = mysqli_fetch_assoc($detail_result);
        mysqli_stmt_close($detail_stmt);

        if ($detail_row) {
            echo json_encode(['success' => true, 'data' => $detail_row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Internship details not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to prepare details query.']);
    }
    exit();
}

// Calculate counters
$cnt_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Pending Approval' AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0;
$cnt_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE LOWER(status) IN ('approved', 'active', 'published') AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0;
$cnt_completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Completed' AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0;
$cnt_archived = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Archived' AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0;
$cnt_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE status = 'Rejected' AND is_deleted = 0 AND status != 'Inactive'"))['c'] ?? 0;

// ── Search & Filter Logic ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$mode_filter = isset($_GET['mode']) ? trim($_GET['mode']) : '';

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(title LIKE ? OR project_title LIKE ? OR technology_stack LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "sss";
}

if (!empty($status_filter)) {
    if (strtolower($status_filter) === 'active') {
        $where_clauses[] = "LOWER(i.status) IN ('approved', 'active', 'published')";
    } else {
        $where_clauses[] = "i.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

if (!empty($mode_filter)) {
    $where_clauses[] = "i.mode = ?";
    $params[] = $mode_filter;
    $types .= "s";
}

$where_clauses[] = "i.is_deleted = 0";
$where_clauses[] = "i.status != 'Inactive'";

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch all internships
$internships_sql = "
    SELECT i.*, u.full_name as mentor_name, uc.full_name as coordinator_name 
    FROM internships i
    LEFT JOIN users u ON i.assigned_mentor = u.id
    LEFT JOIN users uc ON i.coordinator_id = uc.id
    " . $where_sql . "
    GROUP BY i.id
    ORDER BY i.id DESC
";
$stmt = mysqli_prepare($conn, $internships_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$internships_res = mysqli_stmt_get_result($stmt);
$internships_list = [];
while ($row = mysqli_fetch_assoc($internships_res)) {
    $internships_list[] = $row;
}
mysqli_stmt_close($stmt);



// Fetch admin header details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Manage Internships – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#003ea8",
            "primary-hover": "#002a75",
            surface: "#f8f9fa",
            "surface-container": "#ffffff",
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          }
        }
      }
    }
    </script>
    <style>
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">
  <!-- Top Nav -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-8">
      <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
        <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="32" height="32" rx="8" fill="currentColor"/>
          <circle cx="16" cy="16" r="3" fill="white"/>
          <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
          <circle cx="16" cy="8" r="1.5" fill="white"/>
          <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
          <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
          <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
          <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
        </svg>
        <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
      </a>
      <div class="hidden md:flex gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest border-l border-gray-200 pl-6">
        Platform Administration
      </div>
    </div>
    
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-semibold text-slate-700">System Online</span>
      </div>
      
      <!-- Notifications Bell -->
      <a href="admin_received_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($admin_unread_count > 0): ?>
          <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $admin_unread_count; ?></span>
        <?php endif; ?>
      </a>

      <!-- Profile Button -->
      <div class="relative">
        <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
          <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
            <?php echo htmlspecialchars($header_name); ?> (Admin)
          </span>
          <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
            <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
              <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
            <?php else: ?>
              <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
        </button>
        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
          <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
          </a>
          <hr class="my-1 border-gray-100">
          <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Banners -->
        <?php if (!empty($success_msg)): ?>
          <div class="p-4 bg-green-50 border border-green-200 text-green-800 font-bold rounded-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
          <div class="p-4 bg-red-50 border border-red-200 text-red-800 font-bold rounded-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-red-600">error</span>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
          </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Internship Oversights</h1>
            <p class="text-gray-500 text-sm mt-1">Review coordinator-created postings and control publishing lifecycle</p>
          </div>
        </div>

        <!-- Dashboard Counters -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <!-- Pending Approval -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Pending Approval</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-amber-600"><?php echo $cnt_pending; ?></span>
              <span class="material-symbols-outlined text-amber-500 text-lg">hourglass_empty</span>
            </div>
          </div>
          <!-- Active -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Active</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-green-600"><?php echo $cnt_active; ?></span>
              <span class="material-symbols-outlined text-green-500 text-lg">play_arrow</span>
            </div>
          </div>
          <!-- Completed -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Completed</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-slate-700"><?php echo $cnt_completed; ?></span>
              <span class="material-symbols-outlined text-slate-500 text-lg">task_alt</span>
            </div>
          </div>
          <!-- Archived -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Archived</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-gray-600"><?php echo $cnt_archived; ?></span>
              <span class="material-symbols-outlined text-gray-500 text-lg">archive</span>
            </div>
          </div>
          <!-- Rejected -->
          <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Rejected</span>
            <div class="flex items-baseline justify-between mt-2">
              <span class="text-2xl font-black text-red-600"><?php echo $cnt_rejected; ?></span>
              <span class="material-symbols-outlined text-red-500 text-lg">cancel</span>
            </div>
          </div>
        </div>

        <!-- Filters Form -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
          <form method="GET" action="admin_internships.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, stacks..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none bg-gray-50">
            </div>
            
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Status:</label>
              <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none cursor-pointer">
                <option value="">All Statuses</option>
                <option value="Pending Approval" <?php if ($status_filter === 'Pending Approval') echo 'selected'; ?>>Pending Approval</option>
                <option value="Active" <?php if ($status_filter === 'Active') echo 'selected'; ?>>Active</option>
                <option value="Completed" <?php if ($status_filter === 'Completed') echo 'selected'; ?>>Completed</option>
                <option value="Archived" <?php if ($status_filter === 'Archived') echo 'selected'; ?>>Archived</option>
                <option value="Rejected" <?php if ($status_filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
              </select>
            </div>
            
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Mode:</label>
              <select name="mode" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none cursor-pointer">
                <option value="">All Modes</option>
                <option value="Remote" <?php if ($mode_filter === 'Remote') echo 'selected'; ?>>Remote</option>
                <option value="Hybrid" <?php if ($mode_filter === 'Hybrid') echo 'selected'; ?>>Hybrid</option>
                <option value="On-site" <?php if ($mode_filter === 'On-site') echo 'selected'; ?>>On-site</option>
              </select>
            </div>
            
            <div class="flex gap-2 justify-end">
              <button type="submit" class="bg-[#003ea8] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors cursor-pointer">Filter</button>
              <?php if (!empty($search) || !empty($status_filter) || !empty($mode_filter)): ?>
                <a href="admin_internships.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition-colors">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Postings Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($internships_list)): ?>
            <div class="col-span-full bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
              <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">work_off</span>
              <h3 class="text-base font-bold text-gray-800">No internship postings found</h3>
              <p class="text-xs text-gray-500 mt-1">Refine your query filters or create a new posting to get started.</p>
            </div>
          <?php else: ?>
            <?php foreach ($internships_list as $item): 
              $status_colors = [
                'pending approval' => 'bg-orange-50 text-orange-700 border-orange-200',
                'active' => 'bg-green-50 text-green-700 border-green-200',
                'approved' => 'bg-green-50 text-green-700 border-green-200',
                'published' => 'bg-green-50 text-green-700 border-green-200',
                'completed' => 'bg-slate-50 text-slate-700 border-slate-200',
                'archived' => 'bg-gray-50 text-gray-700 border-gray-200',
                'rejected' => 'bg-red-50 text-red-700 border-red-200'
              ];
              $status_cls = $status_colors[strtolower($item['status'])] ?? 'bg-slate-50 text-slate-700 border-slate-150';
            ?>
              <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow gap-4">
                <div class="space-y-3">
                  <div class="flex justify-between items-start">
                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_cls; ?>">
                      <?php echo htmlspecialchars($item['status']); ?>
                    </span>
                    <span class="text-xs text-gray-400 font-bold"><?php echo htmlspecialchars($item['mode']); ?></span>
                  </div>
                  
                  <div>
                    <h3 class="text-base font-bold text-gray-900 leading-snug"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($item['project_subtype'] ?: ($item['project_type'] ?: 'General Internship')); ?></p>
                  </div>
                  
                  <div class="space-y-1.5 text-xs text-gray-600">
                    <p><span class="font-bold text-gray-700">Created By:</span> <?php echo htmlspecialchars($item['coordinator_name'] ?: 'System/Admin'); ?></p>
                    <p><span class="font-bold text-gray-700">Submitted:</span> 
                      <?php 
                        $raw_date = !empty($item['created_at']) ? $item['created_at'] : (!empty($item['submission_date']) ? $item['submission_date'] : null);
                        if ($raw_date) {
                            echo date('M d, Y', strtotime($raw_date));
                        } else {
                            echo 'N/A';
                        }
                      ?>
                      <!-- DEBUG DUMP: <?php var_dump($raw_date); ?> -->
                    </p>
                    <p class="truncate"><span class="font-bold text-gray-700">Stack:</span> <?php echo htmlspecialchars($item['technology_stack'] ?: 'N/A'); ?></p>
                    <p><span class="font-bold text-gray-700">Duration:</span> <?php echo htmlspecialchars($item['duration']); ?></p>
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex flex-wrap gap-1.5 justify-end items-center">
                  <button type="button" data-id="<?php echo intval($item['id']); ?>" onclick="openDetailsModal(this)" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">View Details</button>
                  
                  <?php if (in_array(strtolower($item['status']), ['pending approval', 'changes requested', 'new'])): ?>
                    <a href="admin_internships.php?action=approve&id=<?php echo $item['id']; ?>" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold transition-colors">Approve</a>
                    <a href="admin_internships.php?action=archive&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure you want to archive this posting?')" class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs font-bold transition-colors">Archive</a>
                  <?php elseif (strtolower($item['status']) === 'archived'): ?>
                    <a href="admin_internships.php?action=unarchive&id=<?php echo $item['id']; ?>" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-colors">Unarchive</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- ── VIEW DETAILS MODAL ── -->
  <div id="details-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">info</span> Internship Details
        </h3>
        <button onclick="closeModal('details-modal')" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Internship Role Title</span>
          <p id="det-title" class="text-gray-900 font-extrabold text-sm"></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Category Type</span>
            <p id="det-project-type" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Subtype Domain</span>
            <p id="det-project-subtype" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Duration</span>
            <p id="det-duration" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Location Mode</span>
            <p id="det-mode" class="text-gray-900 font-bold"></p>
          </div>
        </div>



        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Start Date</span>
            <p id="det-start-date" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">End Date</span>
            <p id="det-end-date" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Technology Stack</span>
          <p id="det-tech-stack" class="text-gray-900 font-bold"></p>
        </div>



        <div class="grid grid-cols-2 gap-4">
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Created By (Coordinator)</span>
            <p id="det-coordinator" class="text-gray-900 font-bold"></p>
          </div>
          <div>
            <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Submission Date</span>
            <p id="det-submission-date" class="text-gray-900 font-bold"></p>
          </div>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Status</span>
          <p id="det-status" class="text-gray-900 font-bold"></p>
        </div>

        <div class="pt-4 border-t border-gray-100">
          <form method="POST" action="admin_internships.php">
            <input type="hidden" name="action" value="save_remarks">
            <input type="hidden" name="id" id="det-internship-id">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Admin Remarks</label>
            <textarea id="det-admin-remarks" name="admin_remarks" rows="3"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
              placeholder="Type remarks or requested changes for coordinator..."></textarea>
            <div class="flex justify-end items-center mt-2">
              <button type="submit" class="px-4 py-2 bg-[#003ea8] hover:bg-blue-800 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer">Save Remarks</button>
            </div>
          </form>
        </div>

        <div>
          <span class="text-[9px] text-gray-400 uppercase font-bold tracking-wider block">Project Description Details</span>
          <div id="det-description" class="bg-gray-50 border border-gray-150 p-3 rounded-lg text-gray-700 whitespace-pre-line font-medium"></div>
        </div>

        <div class="pt-3 border-t border-gray-100 flex justify-end">
          <button type="button" onclick="closeModal('details-modal')" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm cursor-pointer">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openDetailsModal(button) {
      const internshipId = button.dataset.id;
      if (!internshipId) {
        return;
      }
      fetchInternshipDetails(internshipId);
    }

    async function fetchInternshipDetails(id) {
      try {
        const response = await fetch(`admin_internships.php?ajax=details&id=${encodeURIComponent(id)}`);
        const payload = await response.json();
        if (!payload.success) {
          console.error(payload.message || 'Unable to load internship details');
          return;
        }
        const item = payload.data;

        document.getElementById('det-title').textContent = item.title || 'N/A';
        document.getElementById('det-project-type').textContent = item.project_type || 'N/A';
        document.getElementById('det-project-subtype').textContent = item.project_subtype || 'N/A';
        document.getElementById('det-duration').textContent = item.duration || 'N/A';
        document.getElementById('det-mode').textContent = item.mode || 'N/A';
        document.getElementById('det-start-date').textContent = item.start_date || 'N/A';
        document.getElementById('det-end-date').textContent = item.end_date || 'N/A';
        document.getElementById('det-tech-stack').textContent = item.technology_stack || 'N/A';
        document.getElementById('det-status').textContent = item.status || 'N/A';
        document.getElementById('det-coordinator').textContent = item.coordinator_name || 'System / Admin';
        if (item.created_at) {
            const dt = new Date(item.created_at);
            const options = { month: 'short', day: '2-digit', year: 'numeric' };
            document.getElementById('det-submission-date').textContent = dt.toLocaleDateString('en-US', options);
        } else {
            document.getElementById('det-submission-date').textContent = 'N/A';
        }
        
        document.getElementById('det-internship-id').value = item.id;
        document.getElementById('det-admin-remarks').value = item.admin_remarks || '';

        document.getElementById('det-description').textContent = item.description || 'No description details provided.';

        document.getElementById('details-modal').classList.remove('hidden');
      } catch (error) {
        console.error('Error loading internship details:', error);
      }
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.add('hidden');
    }

    function openReviewModal(id, title, coordinatorId = 0) {
      document.getElementById('review-internship-id').value = id;
      document.getElementById('review-title').textContent = title;
      document.getElementById('review-admin-remarks').value = '';
      const select = document.getElementById('review-override-coordinator');
      if (select) {
        select.value = coordinatorId ? coordinatorId : '';
      }
      document.getElementById('review-modal').classList.remove('hidden');
    }

    function confirmDelete(id) {
      document.getElementById('delete-internship-id').value = id;
      document.getElementById('delete-modal').classList.remove('hidden');
    }
  </script>

  <!-- ── DELETE CONFIRMATION MODAL ── -->
  <div id="delete-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
      <div class="p-6 text-center space-y-4">
        <span class="material-symbols-outlined text-red-500 text-5xl">warning</span>
        <h3 class="text-xl font-bold text-gray-900">Confirm Deletion</h3>
        <p class="text-sm text-gray-500">Are you sure you want to delete this internship? This action cannot be undone.</p>
        <form method="POST" action="admin_internships.php" class="flex gap-3 justify-center pt-2">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="delete-internship-id">
          <button type="button" onclick="closeModal('delete-modal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-bold transition-colors cursor-pointer">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-bold transition-colors cursor-pointer">Delete</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── REVIEW MODAL ── -->
  <div id="review-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">rate_review</span> Review Project Posting
        </h3>
        <button onclick="closeModal('review-modal')" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <form method="POST" action="admin_internships.php">
        <input type="hidden" name="id" id="review-internship-id">
        <div class="p-6 space-y-4">
          <p class="text-sm font-semibold text-gray-800">Project: <span id="review-title" class="text-blue-700"></span></p>
          <div>
            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Assign/Override Coordinator (Optional)</label>
            <select name="override_coordinator_id" id="review-override-coordinator"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-gray-50 cursor-pointer">
              <option value="">-- Keep Current / Auto-assign --</option>
              <?php foreach ($coordinators_list as $coord): ?>
                <option value="<?php echo $coord['id']; ?>"><?php echo htmlspecialchars($coord['full_name']); ?> (<?php echo htmlspecialchars($coord['email']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Admin Remarks (optional)</label>
            <textarea id="review-admin-remarks" name="admin_remarks" rows="3"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
              placeholder="Provide feedback or reason for your decision..."></textarea>
          </div>
          <div class="flex flex-col gap-2 pt-2 border-t border-gray-100">
            <button type="submit" name="action" value="approve"
              class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-sm transition-colors cursor-pointer flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-[18px]">check_circle</span> Approve &amp; Activate
            </button>
            <button type="submit" name="action" value="request_changes"
              class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-bold text-sm transition-colors cursor-pointer flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-[18px]">edit_note</span> Request Changes
            </button>
            <button type="submit" name="action" value="reject"
              onclick="return confirm('Are you sure you want to reject this posting?')"
              class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-sm transition-colors cursor-pointer flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-[18px]">cancel</span> Reject
            </button>
            <button type="button" onclick="closeModal('review-modal')"
              class="w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-bold text-sm transition-colors cursor-pointer">
              Cancel
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

</body>
</html>

