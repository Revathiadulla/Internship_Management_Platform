<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";
require_once __DIR__ . '/password_validation.php';

// Fetch admin notifications unread count for badge
$admin_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'admin' AND is_read = 0");
$admin_unread_row = mysqli_fetch_assoc($admin_unread_res);
$admin_unread_count = $admin_unread_row['count'] ?? 0;

$success_msg = "";
$error_msg = "";

// ── CRUD Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // ADD USER
        if ($action === 'add') {
            $name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $role = trim($_POST['role']);
            $password = trim($_POST['password']);

            if (empty($name) || empty($email) || empty($role) || empty($password)) {
                $error_msg = "Please fill in all required fields.";
            } else {
                $password_validation = validate_password_strength($password);
                if (!$password_validation['is_valid']) {
                    $error_msg = implode(' ', $password_validation['errors']);
                } else {
                // Check if email already exists
                $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $error_msg = "User with email '" . htmlspecialchars($email) . "' already exists.";
                } else {
                    mysqli_stmt_close($stmt);
                    $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_pw, $role);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_msg = "User '" . htmlspecialchars($name) . "' created successfully!";
                    } else {
                        $error_msg = "Failed to create user. Database error.";
                    }
                }
                mysqli_stmt_close($stmt);
                }
            }
        }

        // EDIT USER
        elseif ($action === 'edit') {
            $id = intval($_POST['user_id']);
            $name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $role = trim($_POST['role']);
            $status = trim($_POST['status'] ?? 'Active');
            $subscription_status = trim($_POST['subscription_status'] ?? 'Premium Plan');
            $password = trim($_POST['password'] ?? '');

            if (empty($name) || empty($email) || empty($role) || $id <= 0) {
                $error_msg = "Please fill in all required fields.";
            } else {
                if ($password !== '') {
                    $password_validation = validate_password_strength($password);
                    if (!$password_validation['is_valid']) {
                        $error_msg = implode(' ', $password_validation['errors']);
                    }
                }
                if ($error_msg === '') {
                // Check for email collision (excluding current user)
                $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
                mysqli_stmt_bind_param($stmt, "si", $email, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $error_msg = "Another user with email '" . htmlspecialchars($email) . "' already exists.";
                    mysqli_stmt_close($stmt);
                } else {
                    mysqli_stmt_close($stmt);
                    if (!empty($password)) {
                        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $hashed_pw, $role, $id);
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $role, $id);
                    }

                    if (mysqli_stmt_execute($stmt)) {
                        $success_msg = "User details updated successfully!";
                    } else {
                        $error_msg = "Failed to update user. Database error.";
                    }
                    mysqli_stmt_close($stmt);
                }
                }
            }
        }
    }
}

// SOFT DELETE USER (set status to Inactive)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'Inactive' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "User deactivated (soft deleted) successfully!";
        } else {
            $error_msg = "Failed to deactivate user. Database error.";
        }
        mysqli_stmt_close($stmt);
    }
}
// RESTORE USER (reactivate)
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'Active' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "User restored successfully!";
        } else {
            $error_msg = "Failed to restore user. Database error.";
        }
        mysqli_stmt_close($stmt);
    }
}

// TOGGLE USER STATUS
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = intval($_GET['id']);
    $new_status = $_GET['status'] === 'Inactive' ? 'Inactive' : 'Active';
    if ($id > 0) {
        $success_msg = "User status updated to '$new_status' successfully! (Simulated)";
    }
}

// ── AJAX: GET PROFILE DETAIL ──
if (isset($_GET['action']) && $_GET['action'] === 'get_profile') {
    header('Content-Type: application/json');
    $uid = intval($_GET['id']);
    
    // Fetch basic user details
    $user_res = mysqli_query($conn, "SELECT id, full_name, email, role, status, phone, registered_date FROM users WHERE id = $uid LIMIT 1");
    if ($user_res && $user = mysqli_fetch_assoc($user_res)) {
        $user['status'] = $user['status'] ?: 'Active';
        $user['subscription_status'] = 'N/A';
        $user['phone'] = !empty($user['phone']) ? $user['phone'] : 'N/A';
        $user['registered_date'] = !empty($user['registered_date']) ? $user['registered_date'] : null;
        $role = strtolower($user['role']);
        $data = ['success' => true, 'user' => $user, 'role' => $role];
        
        if ($role === 'student') {
            // Fetch student profile details
            $prof_res = mysqli_query($conn, "SELECT * FROM student_profiles WHERE user_id = $uid LIMIT 1");
            $profile = mysqli_fetch_assoc($prof_res) ?: null;
            if ($profile) {
                $profile['resume_exists'] = check_resume_exists($profile);
            }
            $data['profile'] = $profile;
            
            // Fetch student applications and resolve the applied internship label
            $apps = [];
            $has_applied_subtype = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'applied_subtype'")) > 0;
            $has_project_subtype_id = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'project_subtype_id'")) > 0;
            $has_project_subtype = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'project_subtype'")) > 0;
            $has_internship_title = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'internship_title'")) > 0;
            $join_clause = "LEFT JOIN internships i ON a.internship_id = i.id";
            if ($has_project_subtype_id) {
                $join_clause = "LEFT JOIN project_subtypes ps ON a.project_subtype_id = ps.id LEFT JOIN internships i ON a.internship_id = i.id";
            }
            $applied_label_parts = [];
            if ($has_applied_subtype) {
                $applied_label_parts[] = "NULLIF(a.applied_subtype, '')";
            }
            if ($has_project_subtype_id) {
                $applied_label_parts[] = "NULLIF(ps.subtype_name, '')";
            }
            if ($has_project_subtype) {
                $applied_label_parts[] = "NULLIF(a.project_subtype, '')";
            }
            if ($has_internship_title) {
                $applied_label_parts[] = "NULLIF(a.internship_title, '')";
            }
            $applied_label_parts[] = "NULLIF(i.title, '')";
            $applied_label_expr = 'COALESCE(' . implode(', ', $applied_label_parts) . ", 'General Internship')";
            $apps_res = mysqli_query($conn, "SELECT a.*, $applied_label_expr AS applied_internship FROM internship_applications a $join_clause WHERE a.user_id = $uid ORDER BY a.id DESC");
            if ($apps_res) {
                while ($row = mysqli_fetch_assoc($apps_res)) $apps[] = $row;
            }
            $data['applications'] = $apps;
            
            // Fetch daily logs
            $logs = [];
            $logs_res = mysqli_query($conn, "SELECT * FROM daily_logs WHERE user_id = $uid ORDER BY log_date DESC LIMIT 10");
            if ($logs_res) {
                while ($row = mysqli_fetch_assoc($logs_res)) $logs[] = $row;
            }
            $data['logs'] = $logs;
        } elseif ($role === 'mentor') {
            // Fetch assigned internships/projects
            $projects = [];
            $proj_res = mysqli_query($conn, "SELECT i.*, (SELECT COUNT(*) FROM internship_applications a WHERE a.internship_id = i.id AND a.status IN ('Started','Internship Started','Active Intern')) as active_count FROM internships i WHERE i.assigned_mentor = $uid");
            if ($proj_res) {
                while ($row = mysqli_fetch_assoc($proj_res)) $projects[] = $row;
            }
            $data['projects'] = $projects;
            
            // Fetch students assigned
            $students = [];
            $stud_res = mysqli_query($conn, "SELECT a.*, u.full_name as student_name FROM internship_applications a JOIN users u ON a.user_id = u.id WHERE a.mentor_id = $uid");
            if ($stud_res) {
                while ($row = mysqli_fetch_assoc($stud_res)) $students[] = $row;
            }
            $data['students'] = $students;
            
            // Fetch mentor feedback
            $feedback = [];
            $fb_res = mysqli_query($conn, "SELECT mf.*, u.full_name as student_name FROM mentor_feedback mf JOIN users u ON mf.user_id = u.id WHERE mf.given_by = '" . mysqli_real_escape_string($conn, $user['full_name']) . "'");
            if ($fb_res) {
                while ($row = mysqli_fetch_assoc($fb_res)) $feedback[] = $row;
            }
            $data['feedback'] = $feedback;
        } elseif ($role === 'hr') {
            // Track application reviews and status changes made by this HR user
            $reviews = [];
            $rev_res = mysqli_query($conn, "SELECT h.*, u.full_name as student_name FROM application_status_history h JOIN internship_applications a ON h.application_id = a.id JOIN users u ON a.user_id = u.id WHERE h.updated_by_role = 'hr' AND h.updated_by_name = '" . mysqli_real_escape_string($conn, $user['full_name']) . "' ORDER BY h.created_at DESC LIMIT 15");
            if ($rev_res) {
                while ($row = mysqli_fetch_assoc($rev_res)) $reviews[] = $row;
            }
            $data['reviews'] = $reviews;
        } elseif ($role === 'company') {
            // Posted requirements
            $requirements = [];
            $req_res = mysqli_query($conn, "SELECT * FROM internships WHERE company_id = $uid ORDER BY id DESC");
            if ($req_res) {
                while ($row = mysqli_fetch_assoc($req_res)) $requirements[] = $row;
            }
            $data['requirements'] = $requirements;
            
            // Shortlisted students
            $shortlisted = [];
            $sl_res = mysqli_query($conn, "SELECT a.*, u.full_name as student_name, i.title as internship_title FROM internship_applications a JOIN users u ON a.user_id = u.id JOIN internships i ON a.internship_id = i.id WHERE a.status = 'Shortlisted' AND i.company_id = $uid");
            if ($sl_res) {
                if (mysqli_num_rows($sl_res) == 0) {
                    // Fallback to general shortlisted students if none are assigned specifically to company's internships
                    $sl_res = mysqli_query($conn, "SELECT a.*, u.full_name as student_name, COALESCE(i.title, 'General Internship') as internship_title FROM internship_applications a JOIN users u ON a.user_id = u.id LEFT JOIN internships i ON a.internship_id = i.id WHERE a.status = 'Shortlisted' LIMIT 15");
                }
                while ($row = mysqli_fetch_assoc($sl_res)) $shortlisted[] = $row;
            }
            $data['shortlisted'] = $shortlisted;
        } elseif ($role === 'coordinator') {
            // Assigned internships
            $internships = [];
            $int_res = mysqli_query($conn, "SELECT * FROM internships WHERE coordinator_id = $uid ORDER BY id DESC");
            if ($int_res) {
                while ($row = mysqli_fetch_assoc($int_res)) $internships[] = $row;
            }
            $data['internships'] = $internships;
            
            // Logs approved/reviewed by them
            $activities = [];
            $act_res = mysqli_query($conn, "SELECT h.*, u.full_name as student_name FROM application_status_history h JOIN internship_applications a ON h.application_id = a.id JOIN users u ON a.user_id = u.id WHERE h.updated_by_role = 'coordinator' AND h.updated_by_name = '" . mysqli_real_escape_string($conn, $user['full_name']) . "' ORDER BY h.created_at DESC LIMIT 15");
            if ($act_res) {
                while ($row = mysqli_fetch_assoc($act_res)) $activities[] = $row;
            }
            $data['activities'] = $activities;
        }
        
        echo json_encode($data);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    exit();
}

// ── Search & Filter Logic ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = ["role != 'admin'", "status != 'Inactive'"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_val = "%" . $search . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Total Count
$count_query = "SELECT COUNT(*) as c FROM users" . $where_sql;
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_stmt_get_result($count_stmt);
$total_rows = mysqli_fetch_assoc($count_res)['c'] ?? 0;
mysqli_stmt_close($count_stmt);

$total_pages = ceil($total_rows / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch users
$users_query = "SELECT id, full_name, email, phone, role, status, registered_date FROM users" . $where_sql . " ORDER BY id DESC LIMIT ? OFFSET ?";
$users_stmt = mysqli_prepare($conn, $users_query);

$bind_types = $types . "ii";
$bind_params = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($users_stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($users_stmt);
$users_res = mysqli_stmt_get_result($users_stmt);

$users_list = [];
while ($row = mysqli_fetch_assoc($users_res)) {
    $row['subscription_status'] = 'N/A';
    $row['phone'] = !empty($row['phone']) ? $row['phone'] : 'N/A';
    $users_list[] = $row;
}
mysqli_stmt_close($users_stmt);

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
    <title>Manage Users – IMP</title>
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
            <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
            <p class="text-gray-500 text-sm mt-1">Manage system logins, roles, and profiles</p>
          </div>
          <button onclick="openAddModal()" class="bg-[#003ea8] text-white px-4 py-2.5 rounded-lg flex items-center gap-2 text-sm font-semibold hover:bg-blue-800 hover:shadow-md transition-all shadow-sm cursor-pointer">
            <span class="material-symbols-outlined text-lg">person_add</span> Add New User
          </button>
        </div>

        <!-- Search & Filter Controls -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-4 items-center">
          <form method="GET" action="admin_users.php" class="w-full flex flex-col md:flex-row gap-4">
            <div class="relative flex-1">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users by name, email, phone..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none bg-gray-50">
            </div>
            
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider shrink-0">Role:</label>
              <select name="role" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-[#003ea8] outline-none cursor-pointer min-w-[120px]">
                <option value="">All Roles</option>
                <option value="student" <?php if ($role_filter === 'student') echo 'selected'; ?>>Student</option>
                <option value="coordinator" <?php if ($role_filter === 'coordinator') echo 'selected'; ?>>Coordinator</option>
                <option value="mentor" <?php if ($role_filter === 'mentor') echo 'selected'; ?>>Mentor</option>
                <option value="hr" <?php if ($role_filter === 'hr') echo 'selected'; ?>>HR</option>
                <option value="company" <?php if ($role_filter === 'company') echo 'selected'; ?>>Company</option>
              </select>
            </div>
            
            <div class="flex gap-2">
              <button type="submit" class="bg-[#003ea8] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors cursor-pointer">Filter</button>
              <?php if (!empty($search) || !empty($role_filter)): ?>
                <a href="admin_users.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition-colors">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                <tr>
                  <th class="px-6 py-4">Name</th>
                  <th class="px-6 py-4">Role</th>
                  <th class="px-6 py-4">Email</th>
                  <th class="px-6 py-4">Phone</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4">Created At</th>
                  <th class="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (empty($users_list)): ?>
                  <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-xs">No users found matching query.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users_list as $user): 
                    $role_colors = [
                      'student' => 'bg-blue-50 text-blue-700 border-blue-100',
                      'coordinator' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                      'mentor' => 'bg-purple-50 text-purple-700 border-purple-100',
                      'admin' => 'bg-red-50 text-red-700 border-red-100',
                      'hr' => 'bg-teal-50 text-teal-700 border-teal-100',
                      'company' => 'bg-orange-50 text-orange-700 border-orange-100'
                    ];
                    $role_cls = $role_colors[strtolower($user['role'])] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                  ?>
                    <tr class="hover:bg-gray-50/50">
                      <td class="px-6 py-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs border border-slate-200">
                          <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                        <div>
                          <p class="font-bold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                          <p class="text-[10px] text-gray-400">UID: #<?php echo $user['id']; ?></p>
                        </div>
                      </td>
                      <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border <?php echo $role_cls; ?>">
                          <?php echo htmlspecialchars($user['role']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($user['email']); ?></td>
                      <td class="px-6 py-4 text-gray-500 font-medium"><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                      <td class="px-6 py-4">
                        <?php if (strtolower($user['status'] ?? 'active') === 'inactive'): ?>
                          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-100 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactive
                          </span>
                        <?php else: ?>
                          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Active
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 text-gray-400 text-xs font-semibold"><?php echo (!empty($user['registered_date']) && strtotime($user['registered_date']) > 0) ? date('M d, Y', strtotime($user['registered_date'])) : 'Not Available'; ?></td>
                      <td class="px-6 py-4 text-right space-x-2">
                        <button onclick="viewUserProfile(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['role']); ?>')" class="text-blue-600 hover:text-blue-800 text-xs font-bold cursor-pointer" title="View Profile">Profile</button>
                        <button onclick='openEditModal(<?php echo json_encode($user); ?>)' class="text-gray-600 hover:text-gray-800 text-xs font-bold cursor-pointer">Edit</button>
                        
                        <?php if (strtolower($user['status'] ?? 'active') === 'inactive'): ?>
                          <a href="admin_users.php?action=restore&id=<?php echo $user['id']; ?>" class="text-emerald-600 hover:text-emerald-800 text-xs font-bold" onclick="return confirm('Restore this user?')">Restore</a>
                        <?php else: ?>
                          <a href="admin_users.php?action=delete&id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-800 text-xs font-bold" onclick="return confirm('Are you sure you want to deactivate this user?')">Delete</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-xs">
              <span class="text-gray-500 font-medium">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
              <div class="flex gap-1.5">
                <?php if ($page > 1): ?>
                  <a href="admin_users.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <a href="admin_users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="px-3 py-1.5 border rounded-lg font-bold transition-colors <?php echo $i === $page ? 'bg-[#003ea8] border-[#003ea8] text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                  <a href="admin_users.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 font-bold hover:bg-gray-50 transition-colors">Next</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- ── ADD USER MODAL ── -->
  <div id="add-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">person_add</span> Add New User
        </h3>
        <button onclick="closeModal('add-modal')" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <form method="POST" action="admin_users.php" class="p-6 space-y-4">
        <input type="hidden" name="action" value="add">
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Full Name *</label>
          <input type="text" name="full_name" required placeholder="Jane Doe" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Email Address *</label>
          <input type="email" name="email" required placeholder="jane.doe@example.com" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Phone Number</label>
            <input type="text" name="phone" placeholder="9876543210" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
          </div>
          
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Role *</label>
            <select name="role" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
              <option value="student">Student</option>
              <option value="coordinator">Coordinator</option>
              <option value="mentor">Mentor</option>
              <option value="hr">HR</option>
              <option value="company">Company</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Password *</label>
          <input type="password" name="password" required placeholder="••••••••" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
          <button type="button" onclick="closeModal('add-modal')" class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 cursor-pointer">Cancel</button>
          <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm cursor-pointer">Save User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── EDIT USER MODAL ── -->
  <div id="edit-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
          <span class="material-symbols-outlined">edit</span> Edit User
        </h3>
        <button onclick="closeModal('edit-modal')" class="text-white/80 hover:text-white cursor-pointer">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <form method="POST" action="admin_users.php" class="p-6 space-y-4">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="user_id" id="edit-user-id">
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Full Name *</label>
          <input type="text" name="full_name" id="edit-full-name" required placeholder="Jane Doe" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Email Address *</label>
          <input type="email" name="email" id="edit-email" required placeholder="jane.doe@example.com" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Phone Number</label>
            <input type="text" name="phone" id="edit-phone" placeholder="9876543210" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
          </div>
          
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Role *</label>
            <select name="role" id="edit-role" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
              <option value="student">Student</option>
              <option value="coordinator">Coordinator</option>
              <option value="mentor">Mentor</option>
              <option value="hr">HR</option>
              <option value="company">Company</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Status *</label>
            <select name="status" id="edit-status" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <div id="edit-subscription-container">
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Subscription Status</label>
            <select name="subscription_status" id="edit-subscription-status" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm outline-none cursor-pointer">
              <option value="Premium Plan">Premium Plan</option>
              <option value="Basic Plan">Basic Plan</option>
              <option value="Expired">Expired</option>
            </select>
          </div>
        </div>
        
        <div>
          <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Change Password (Leave blank to keep current)</label>
          <input type="password" name="password" placeholder="••••••••" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        
        <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
          <button type="button" onclick="closeModal('edit-modal')" class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 cursor-pointer">Cancel</button>
          <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm cursor-pointer">Update User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── USER PROFILE DETAIL MODAL ── -->
  <div id="profile-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between shrink-0">
        <h3 class="text-white font-bold flex items-center gap-2 text-base">
          <span class="material-symbols-outlined">badge</span>
          <span>User Profile Details</span>
          <span id="modal-role-badge" class="ml-2 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/20 text-white border border-white/30">STUDENT</span>
        </h3>
        <button onclick="closeModal('profile-modal')" class="text-white/80 hover:text-white cursor-pointer transition-colors">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      
      <!-- Body -->
      <div class="p-6 overflow-y-auto flex-1 space-y-6">
        <!-- Basic Info Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 p-5 rounded-xl border border-slate-100">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-extrabold text-xl border-2 border-white shadow-md uppercase font-sans" id="prof-avatar">
              US
            </div>
            <div>
              <h4 class="font-bold text-gray-900 text-lg" id="prof-name">User Name</h4>
              <p class="text-xs text-gray-505 flex items-center gap-1 mt-0.5 font-medium">
                <span class="material-symbols-outlined text-[16px] text-gray-400">mail</span>
                <span id="prof-email" class="text-gray-600">user@example.com</span>
              </p>
              <p class="text-xs text-gray-505 flex items-center gap-1 mt-0.5 font-medium">
                <span class="material-symbols-outlined text-[16px] text-gray-400">call</span>
                <span id="prof-phone" class="text-gray-600">Phone: N/A</span>
              </p>
            </div>
          </div>
          
          <div class="flex flex-col items-start sm:items-end gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Status:</span>
              <span id="prof-status-badge"></span>
            </div>
            <div class="flex items-center gap-2" id="prof-subscription-wrapper">
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Plan:</span>
              <span id="prof-subscription-badge" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-orange-50 text-orange-700 border border-orange-100">Premium Plan</span>
            </div>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200">
          <nav class="flex gap-4 -mb-px" id="profile-tabs-nav">
            <!-- Tabs will be rendered here dynamically -->
          </nav>
        </div>

        <!-- Tab contents wrapper -->
        <div id="profile-tabs-content" class="min-h-[150px]">
          <!-- Content will be populated dynamically -->
        </div>
      </div>
      
      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-100 flex justify-end shrink-0 bg-slate-50">
        <button onclick="closeModal('profile-modal')" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold cursor-pointer transition-colors shadow-sm">Close Profile</button>
      </div>
    </div>
  </div>

  <script>
    function openAddModal() {
      document.getElementById('add-modal').classList.remove('hidden');
    }
    
    function openEditModal(user) {
      document.getElementById('edit-user-id').value = user.id;
      document.getElementById('edit-full-name').value = user.full_name;
      document.getElementById('edit-email').value = user.email;
      document.getElementById('edit-phone').value = user.phone;
      document.getElementById('edit-role').value = user.role;
      document.getElementById('edit-status').value = user.status || 'Active';
      document.getElementById('edit-subscription-status').value = user.subscription_status || 'Premium Plan';
      
      if (user.role.toLowerCase() === 'company') {
        document.getElementById('edit-subscription-container').classList.remove('hidden');
      } else {
        document.getElementById('edit-subscription-container').classList.add('hidden');
      }
      
      document.getElementById('edit-modal').classList.remove('hidden');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      const editRole = document.getElementById('edit-role');
      if (editRole) {
        editRole.addEventListener('change', function() {
          if (this.value.toLowerCase() === 'company') {
            document.getElementById('edit-subscription-container').classList.remove('hidden');
          } else {
            document.getElementById('edit-subscription-container').classList.add('hidden');
          }
        });
      }
    });

    // ── Helper to escape HTML and prevent XSS ──
    function escapeHtml(str) {
      if (!str) return '';
      return str.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
    }

    // ── Student Info Renderer ──
    function renderStudentInfo(data) {
      const prof = data.profile || {};
      const baseUploadPath = 'view_doc.php?file=';
      
      function renderJsDocRow(title, url, icon, color, resumeExistsAttr = '') {
        if (!url || url === '#') {
          return '';
        }
        const u = url.trim();
        const isLegacyBroken = u.includes('/image/upload/') && /\.pdf$/i.test(u);
        const isLocalPath = !u.startsWith('http://') && !u.startsWith('https://') && !u.startsWith('resume_serve.php') && !u.startsWith('view_doc.php');
        const isProduction = !['localhost', '127.0.0.1'].includes(window.location.hostname);
        
        if (isLegacyBroken || (isLocalPath && isProduction)) {
          return `
            <div class="flex flex-col gap-1 bg-red-50 p-3 rounded-lg border border-red-100">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500">${icon}</span>
                <span class="font-semibold text-gray-800 text-xs">${title}</span>
              </div>
              <span class="text-[11px] text-red-600 font-semibold">Document unavailable. Please ask student to update/reupload document.</span>
            </div>
          `;
        }
        
        return `
          <div class="flex justify-between items-center bg-slate-50 p-3 rounded-lg border border-slate-100">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-${color}-500">${icon}</span>
              <span class="font-semibold text-gray-800 text-xs">${title}</span>
            </div>
            <a href="${u}" target="_blank" rel="noopener noreferrer" ${resumeExistsAttr} class="text-blue-600 hover:text-blue-800 text-xs font-bold">View File</a>
          </div>
        `;
      }
      
      let resumeHtml = '';
      if (prof.resume_file || prof.resume_url) {
        let rLink = '#';
        if (prof.resume_url && (prof.resume_url.startsWith('http://') || prof.resume_url.startsWith('https://'))) {
          rLink = prof.resume_url;
        } else if (prof.resume_file && (prof.resume_file.startsWith('http://') || prof.resume_file.startsWith('https://'))) {
          rLink = prof.resume_file;
        } else if (prof.resume_file) {
          rLink = 'resume_serve.php?file=' + encodeURIComponent(prof.resume_file) + '&mode=view';
        }
        const resumeExistsAttr = `data-resume-exists="${prof.resume_exists ? 'true' : 'false'}"`;
        resumeHtml = renderJsDocRow('Student Resume', rLink, 'picture_as_pdf', 'red', resumeExistsAttr);
      }
      
      let aadhaarHtml = '';
      if (prof.aadhaar_file) {
        let aLink = (prof.aadhaar_file.startsWith('http://') || prof.aadhaar_file.startsWith('https://'))
                    ? prof.aadhaar_file
                    : baseUploadPath + prof.aadhaar_file;
        aadhaarHtml = renderJsDocRow('Aadhaar Document', aLink, 'description', 'green');
      }
      
      let panHtml = '';
      if (prof.pan_file) {
        let pLink = (prof.pan_file.startsWith('http://') || prof.pan_file.startsWith('https://'))
                    ? prof.pan_file
                    : baseUploadPath + prof.pan_file;
        panHtml = renderJsDocRow('PAN Card Document', pLink, 'description', 'amber');
      }

      const docsSection = (resumeHtml || aadhaarHtml || panHtml) ? `
        <div class="pt-4 border-t border-gray-100 space-y-2">
          <h5 class="font-bold text-gray-400 uppercase text-[9px] tracking-wide mb-2">Uploaded Documents</h5>
          ${resumeHtml}
          ${aadhaarHtml}
          ${panHtml}
        </div>
      ` : `
        <div class="pt-4 border-t border-gray-100">
          <h5 class="font-bold text-gray-400 uppercase text-[9px] tracking-wide mb-2">Uploaded Documents</h5>
          <p class="text-xs text-gray-400">No documents uploaded.</p>
        </div>
      `;

      return `
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-semibold text-gray-700">
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">College / University</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.college_name) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Course / Department</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.course) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Year of Study</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.year_of_study) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Skills / Domain</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.skills) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Date of Birth</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.dob) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Gender</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.gender) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">Aadhaar Number</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.aadhaar_number) || "N/A"}</span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
              <span class="text-[9px] text-gray-400 uppercase block mb-0.5">PAN Number</span>
              <span class="text-gray-900 font-bold">${escapeHtml(prof.pan_number) || "N/A"}</span>
            </div>
          </div>
          ${docsSection}
        </div>
      `;
    }

    // ── Student Applications Renderer ──
    function renderStudentApps(data) {
      const apps = data.applications || [];
      if (apps.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No internship applications found.</div>`;
      }
      
      let rows = apps.map(app => {
        let statusCls = "bg-gray-50 text-gray-700 border-gray-100";
        const status = app.status.toLowerCase();
        if (status === 'applied') statusCls = "bg-blue-50 text-blue-700 border-blue-100";
        else if (status === 'shortlisted') statusCls = "bg-yellow-50 text-yellow-700 border-yellow-100";
        else if (status === 'rejected') statusCls = "bg-red-50 text-red-700 border-red-100";
        else if (['started', 'internship started', 'active intern', 'selected'].includes(status)) statusCls = "bg-green-50 text-green-700 border-green-100";
        
        let dateVal = app.applied_date ? new Date(app.applied_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'N/A';
        
        const internshipName = app.applied_internship || app.title || 'N/A';
        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(internshipName)}</td>
            <td class="px-4 py-3 text-gray-400 text-xs">${dateVal}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(app.status)}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Applied Internship</th>
                <th class="px-4 py-2.5">Applied Date</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Student Daily Logs Renderer ──
    function renderStudentLogs(data) {
      const logs = data.logs || [];
      if (logs.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No daily logs submitted.</div>`;
      }

      let rows = logs.map(log => {
        let statusCls = "bg-gray-50 text-gray-700 border-gray-100";
        const status = log.status.toLowerCase();
        if (status === 'approved') statusCls = "bg-green-50 text-green-700 border-green-100";
        else if (status === 'pending') statusCls = "bg-yellow-50 text-yellow-700 border-yellow-100";
        else if (status === 'rejected') statusCls = "bg-red-50 text-red-700 border-red-100";

        let logDate = log.log_date ? new Date(log.log_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'N/A';

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-semibold text-gray-900">${logDate}</td>
            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="${escapeHtml(log.activity_summary)}">${escapeHtml(log.activity_summary)}</td>
            <td class="px-4 py-3 font-semibold text-gray-700">${log.hours_worked} hrs</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(log.status)}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Log Date</th>
                <th class="px-4 py-2.5">Activity Summary</th>
                <th class="px-4 py-2.5">Hours</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Mentor Projects Renderer ──
    function renderMentorProjects(data) {
      const projects = data.projects || [];
      if (projects.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No assigned projects or internships.</div>`;
      }

      let rows = projects.map(proj => {
        let statusCls = "bg-green-50 text-green-700 border-green-100";
        if (proj.status && proj.status.toLowerCase() === 'inactive') statusCls = "bg-gray-50 text-gray-700 border-gray-100";

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(proj.title)}</td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(proj.location || 'N/A')}</td>
            <td class="px-4 py-3 text-gray-600 font-bold">${proj.active_count || 0} Students</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(proj.status || 'Active')}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Project Title</th>
                <th class="px-4 py-2.5">Location</th>
                <th class="px-4 py-2.5">Active Interns</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Mentor Assigned Students Renderer ──
    function renderMentorStudents(data) {
      const students = data.students || [];
      if (students.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No active students assigned to this mentor.</div>`;
      }

      let rows = students.map(stud => {
        let statusCls = "bg-gray-50 text-gray-700 border-gray-100";
        const status = stud.status.toLowerCase();
        if (['started', 'internship started', 'active intern', 'selected'].includes(status)) statusCls = "bg-green-50 text-green-700 border-green-100";
        else statusCls = "bg-blue-50 text-blue-700 border-blue-100";

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(stud.student_name)}</td>
            <td class="px-4 py-3 text-gray-400">Application ID: #${stud.id}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(stud.status)}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Student Name</th>
                <th class="px-4 py-2.5">Reference</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Mentor Feedback Renderer ──
    function renderMentorFeedback(data) {
      const feedback = data.feedback || [];
      if (feedback.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No feedback records found.</div>`;
      }

      let rows = feedback.map(fb => {
        let stars = '★'.repeat(fb.rating) + '☆'.repeat(5 - fb.rating);
        let fbDate = fb.created_at ? new Date(fb.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'N/A';

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(fb.student_name)}</td>
            <td class="px-4 py-3 text-amber-500 font-semibold tracking-wider text-sm">${stars}</td>
            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="${escapeHtml(fb.feedback_text)}">${escapeHtml(fb.feedback_text)}</td>
            <td class="px-4 py-3 text-gray-400">${fbDate}</td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Student Name</th>
                <th class="px-4 py-2.5">Rating</th>
                <th class="px-4 py-2.5">Feedback Comments</th>
                <th class="px-4 py-2.5">Submission Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── HR Reviews Renderer ──
    function renderHrReviews(data) {
      const reviews = data.reviews || [];
      if (reviews.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No application review activity logged.</div>`;
      }

      let rows = reviews.map(rev => {
        let statusCls = "bg-gray-50 text-gray-700 border-gray-100";
        const status = rev.status.toLowerCase();
        if (status === 'applied') statusCls = "bg-blue-50 text-blue-700 border-blue-100";
        else if (status === 'shortlisted') statusCls = "bg-yellow-50 text-yellow-700 border-yellow-100";
        else if (status === 'rejected') statusCls = "bg-red-50 text-red-700 border-red-100";
        else if (['started', 'internship started', 'active intern', 'selected'].includes(status)) statusCls = "bg-green-50 text-green-700 border-green-100";

        let changeDate = rev.created_at ? new Date(rev.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit'}) : 'N/A';

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(rev.student_name)}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(rev.status)}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="${escapeHtml(rev.comments)}">${escapeHtml(rev.comments || 'No comment added')}</td>
            <td class="px-4 py-3 text-gray-400 text-[10px]">${changeDate}</td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Student Name</th>
                <th class="px-4 py-2.5">Status Assigned</th>
                <th class="px-4 py-2.5">Review Comments</th>
                <th class="px-4 py-2.5">Log Timestamp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Company Requirements Renderer ──
    function renderCompanyReqs(data) {
      const requirements = data.requirements || [];
      if (requirements.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No internship requirements posted.</div>`;
      }

      let rows = requirements.map(req => {
        let statusCls = "bg-green-50 text-green-700 border-green-100";
        if (req.status && req.status.toLowerCase() === 'inactive') statusCls = "bg-gray-50 text-gray-700 border-gray-100";

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(req.title)}</td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(req.location || 'N/A')}</td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(req.duration || 'N/A')}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(req.status || 'Active')}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Requirement Title</th>
                <th class="px-4 py-2.5">Location</th>
                <th class="px-4 py-2.5">Duration</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Company Shortlisted Candidates Renderer ──
    function renderCompanyShortlist(data) {
      const shortlisted = data.shortlisted || [];
      if (shortlisted.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No shortlisted candidates at this moment.</div>`;
      }

      let rows = shortlisted.map(sl => {
        let dateVal = sl.applied_date ? new Date(sl.applied_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'N/A';

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(sl.student_name)}</td>
            <td class="px-4 py-3 text-gray-700">${escapeHtml(sl.internship_title)}</td>
            <td class="px-4 py-3 text-gray-400">${dateVal}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border bg-yellow-50 text-yellow-700 border-yellow-100">
                Shortlisted
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Candidate Name</th>
                <th class="px-4 py-2.5">Internship Position</th>
                <th class="px-4 py-2.5">Shortlist Date</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Coordinator Internships Renderer ──
    function renderCoordInternships(data) {
      const internships = data.internships || [];
      if (internships.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No internships assigned to this coordinator.</div>`;
      }

      let rows = internships.map(int => {
        let statusCls = "bg-green-50 text-green-700 border-green-100";
        if (int.status && int.status.toLowerCase() === 'inactive') statusCls = "bg-gray-50 text-gray-700 border-gray-100";

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(int.title)}</td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(int.duration || 'N/A')}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(int.status || 'Active')}
              </span>
            </td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Internship Program</th>
                <th class="px-4 py-2.5">Duration</th>
                <th class="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Coordinator Actions Log Renderer ──
    function renderCoordActivities(data) {
      const activities = data.activities || [];
      if (activities.length === 0) {
        return `<div class="py-8 text-center text-gray-400 text-xs">No coordinator activity logged.</div>`;
      }

      let rows = activities.map(act => {
        let statusCls = "bg-gray-50 text-gray-700 border-gray-100";
        const status = act.status.toLowerCase();
        if (status === 'applied') statusCls = "bg-blue-50 text-blue-700 border-blue-100";
        else if (status === 'shortlisted') statusCls = "bg-yellow-50 text-yellow-700 border-yellow-100";
        else if (status === 'rejected') statusCls = "bg-red-50 text-red-700 border-red-100";
        else if (['started', 'internship started', 'active intern', 'selected'].includes(status)) statusCls = "bg-green-50 text-green-700 border-green-100";

        let actDate = act.created_at ? new Date(act.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit'}) : 'N/A';

        return `
          <tr class="hover:bg-gray-50/50">
            <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(act.student_name)}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusCls}">
                ${escapeHtml(act.status)}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="${escapeHtml(act.comments)}">${escapeHtml(act.comments || 'No comment added')}</td>
            <td class="px-4 py-3 text-gray-400 text-[10px]">${actDate}</td>
          </tr>
        `;
      }).join('');

      return `
        <div class="overflow-x-auto border border-gray-200 rounded-xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[9px] tracking-wider border-b border-gray-100">
              <tr>
                <th class="px-4 py-2.5">Student Name</th>
                <th class="px-4 py-2.5">Action Status</th>
                <th class="px-4 py-2.5">Review/Feedback</th>
                <th class="px-4 py-2.5">Log Timestamp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              ${rows}
            </tbody>
          </table>
        </div>
      `;
    }

    // ── Main viewUserProfile Controller ──
    async function viewUserProfile(userId, role) {
      try {
        const response = await fetch(`admin_users.php?action=get_profile&id=${userId}`);
        const data = await response.json();
        if (data.success) {
          const user = data.user;
          role = role.toLowerCase();
          
          // Basic Info
          document.getElementById('prof-avatar').textContent = user.full_name.substring(0, 2).toUpperCase();
          document.getElementById('prof-name').textContent = user.full_name;
          document.getElementById('prof-email').textContent = user.email;
          document.getElementById('prof-phone').textContent = user.phone || "N/A";
          
          // Role badge
          const roleBadge = document.getElementById('modal-role-badge');
          roleBadge.textContent = role.toUpperCase();
          
          // Status badge
          const statusBadge = document.getElementById('prof-status-badge');
          if (user.status.toLowerCase() === 'inactive') {
            statusBadge.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-100 uppercase tracking-wider";
            statusBadge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactive`;
          } else {
            statusBadge.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 uppercase tracking-wider";
            statusBadge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Active`;
          }

          // Subscription plan (only for company)
          const subWrapper = document.getElementById('prof-subscription-wrapper');
          if (role === 'company') {
            subWrapper.classList.remove('hidden');
            document.getElementById('prof-subscription-badge').textContent = user.subscription_status || 'Premium Plan';
          } else {
            subWrapper.classList.add('hidden');
          }

          // Build Tabs based on role
          const tabsNav = document.getElementById('profile-tabs-nav');
          const tabsContent = document.getElementById('profile-tabs-content');
          tabsNav.innerHTML = '';
          tabsContent.innerHTML = '';

          let tabs = [];
          
          if (role === 'student') {
            tabs = [
              { id: 'student-info', label: 'Profile Info', render: renderStudentInfo },
              { id: 'student-apps', label: `Applications (${data.applications ? data.applications.length : 0})`, render: renderStudentApps },
              { id: 'student-logs', label: `Daily Logs (${data.logs ? data.logs.length : 0})`, render: renderStudentLogs }
            ];
          } else if (role === 'mentor') {
            tabs = [
              { id: 'mentor-projects', label: `Assigned Projects (${data.projects ? data.projects.length : 0})`, render: renderMentorProjects },
              { id: 'mentor-students', label: `Assigned Students (${data.students ? data.students.length : 0})`, render: renderMentorStudents },
              { id: 'mentor-feedback', label: `Feedback Given (${data.feedback ? data.feedback.length : 0})`, render: renderMentorFeedback }
            ];
          } else if (role === 'hr') {
            tabs = [
              { id: 'hr-reviews', label: `Review Activity (${data.reviews ? data.reviews.length : 0})`, render: renderHrReviews }
            ];
          } else if (role === 'company') {
            tabs = [
              { id: 'company-reqs', label: `Posted Requirements (${data.requirements ? data.requirements.length : 0})`, render: renderCompanyReqs },
              { id: 'company-shortlist', label: `Shortlisted Candidates (${data.shortlisted ? data.shortlisted.length : 0})`, render: renderCompanyShortlist }
            ];
          } else if (role === 'coordinator') {
            tabs = [
              { id: 'coord-internships', label: `Assigned Internships (${data.internships ? data.internships.length : 0})`, render: renderCoordInternships },
              { id: 'coord-activities', label: `Action Log (${data.activities ? data.activities.length : 0})`, render: renderCoordActivities }
            ];
          } else {
            // Admin or other
            tabs = [
              { id: 'admin-info', label: 'Info', render: () => `<div class="py-8 text-center text-gray-500 text-xs font-semibold">Administrator account does not have operational feedback, internship logs, or student records.</div>` }
            ];
          }

          // Render Tab buttons and contents
          tabs.forEach((tab, index) => {
            // Tab Button
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `py-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
              index === 0 ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`;
            btn.textContent = tab.label;
            btn.id = `tab-btn-${tab.id}`;
            btn.onclick = () => switchTab(tab.id, tabs);
            tabsNav.appendChild(btn);

            // Tab Content Div
            const div = document.createElement('div');
            div.id = `tab-content-${tab.id}`;
            div.className = index === 0 ? '' : 'hidden';
            div.innerHTML = tab.render(data);
            tabsContent.appendChild(div);
          });

          // Helper to switch tab
          function switchTab(targetId, allTabs) {
            allTabs.forEach(t => {
              const btn = document.getElementById(`tab-btn-${t.id}`);
              const content = document.getElementById(`tab-content-${t.id}`);
              if (t.id === targetId) {
                btn.className = 'py-3 px-4 text-xs font-bold border-b-2 border-blue-600 text-blue-600 transition-all cursor-pointer';
                content.classList.remove('hidden');
              } else {
                btn.className = 'py-3 px-4 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-all cursor-pointer';
                content.classList.add('hidden');
              }
            });
          }

          document.getElementById('profile-modal').classList.remove('hidden');
        } else {
          alert(data.message);
        }
      } catch (err) {
        console.error("AJAX Error:", err);
        alert("Failed to load user profile details.");
      }
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.add('hidden');
    }
  </script>
<?php print_resume_not_found_js(); ?>
</body>
</html>
