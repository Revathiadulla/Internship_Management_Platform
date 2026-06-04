<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch profile details
$sql_profile = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$result_profile = mysqli_query($conn, $sql_profile);
$profile = mysqli_fetch_assoc($result_profile);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}


// Fetch unread notifications count
$unread_sql = "SELECT COUNT(*) as count FROM student_notifications WHERE user_id = '$user_id' AND is_read = 0";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? $unread_row['count'] : 0;

// Fetch active started internship
$active_sql = "SELECT a.id as app_id FROM internship_applications a
               WHERE a.user_id = '$user_id' AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern')
               LIMIT 1";
$active_result = mysqli_query($conn, $active_sql);
$has_active = mysqli_num_rows($active_result) > 0;

// Fetch active project types
$pt_res = mysqli_query($conn, "SELECT * FROM project_types WHERE status = 'Active' ORDER BY type_name ASC");
$project_types = [];
while ($t = mysqli_fetch_assoc($pt_res)) {
    $project_types[] = $t;
}

// Fetch active project subtypes (only where subtype and type are active)
$ps_res = mysqli_query($conn, "SELECT s.*, t.type_name FROM project_subtypes s JOIN project_types t ON s.project_type_id = t.id WHERE s.status = 'Active' AND t.status = 'Active' ORDER BY s.subtype_name ASC");
$project_subtypes = [];
while ($s = mysqli_fetch_assoc($ps_res)) {
    $project_subtypes[] = $s;
}

// Helper function to resolve or create default internship record for an active subtype
function get_or_create_default_internship($conn, $project_type, $project_subtype, $sub_skills, $sub_mode, $sub_duration) {
    // Check if an active default internship already exists for this type and subtype
    $stmt = $conn->prepare("SELECT * FROM internships WHERE TRIM(LOWER(project_type)) = TRIM(LOWER(?)) AND TRIM(LOWER(project_subtype)) = TRIM(LOWER(?)) AND status IN ('Active', 'Approved') AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("ss", $project_type, $project_subtype);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return $row;
    }
    $stmt->close();

    // Not found, auto-create a default posting for the subtype
    $duration = !empty($sub_duration) ? $sub_duration : '3 Months';
    $mode = !empty($sub_mode) ? $sub_mode : 'Remote';
    $skills = !empty($sub_skills) ? $sub_skills : '';

    $title = $project_type . ' - ' . $project_subtype;
    $status = 'Active';
    $difficulty = 'Medium';
    $openings = 2;
    $coordinator_id = 3; // Default coordinator id
    $approval_status = 'Approved'; // Automatically approved to make it active and available

    $stmt_ins = $conn->prepare("INSERT INTO internships (title, duration, mode, skills, status, project_type, project_subtype, technology_stack, difficulty_level, openings, coordinator_id, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("sssssssssiis", $title, $duration, $mode, $skills, $status, $project_type, $project_subtype, $skills, $difficulty, $openings, $coordinator_id, $approval_status);
    $stmt_ins->execute();
    
    $new_id = $stmt_ins->insert_id;
    $stmt_ins->close();

    // Fetch the newly created record
    $stmt_sel = $conn->prepare("SELECT * FROM internships WHERE id = ? LIMIT 1");
    $stmt_sel->bind_param("i", $new_id);
    $stmt_sel->execute();
    $res_sel = $stmt_sel->get_result();
    $new_row = $res_sel->fetch_assoc();
    $stmt_sel->close();

    return $new_row;
}

// Collect unique skills from project subtypes for dynamic skills filtering
$unique_skills = [];
foreach ($project_subtypes as $sub) {
    if (!empty($sub['skills'])) {
        $skills_list = explode(',', $sub['skills']);
        foreach ($skills_list as $skill) {
            $trimmed = trim($skill);
            if ($trimmed !== '') {
                $unique_skills[strtolower($trimmed)] = $trimmed;
            }
        }
    }
}
ksort($unique_skills);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Available Internships - IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    .vertical-section { transition: opacity 0.25s ease, transform 0.25s ease; }
    .vertical-section.hidden-section { display: none; }
    .internship-card { transition: box-shadow 0.2s ease, transform 0.2s ease; }
    .internship-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.10); transform: translateY(-2px); }
    .internship-card.card-hidden { display: none; }
    .section-header-bar { border-left: 4px solid; }
    .filter-chip { transition: all 0.15s ease; }
    .filter-chip.active { background-color: #2563eb; color: #fff; border-color: #2563eb; }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#191c1d] font-sans antialiased">

  <!-- SideNavBar -->
  <aside class="fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col py-6 shadow-sm">
    <div class="px-6 mb-8">
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
      <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Student Portal</p>
    </div>
    <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
      <?php if ($has_active): ?>
      <a class="flex items-center gap-3 text-gray-600 rounded-lg px-4 py-3 font-medium transition-all hover:bg-gray-50 hover:text-blue-600" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#active-internship-card">
        <span class="material-symbols-outlined">badge</span><span class="text-sm font-medium">My Internship</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_daily_log.php">
        <span class="material-symbols-outlined">edit_note</span><span class="text-sm font-medium">Daily Logs</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#assigned-project-card">
        <span class="material-symbols-outlined">terminal</span><span class="text-sm font-medium">Project</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#mentor-feedback-card">
        <span class="material-symbols-outlined">reviews</span><span class="text-sm font-medium">Feedback</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span><span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?><span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span><?php endif; ?>
      </a>
      <?php else: ?>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_browse_internships.php">
        <span class="material-symbols-outlined">work</span><span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_applications.php">
        <span class="material-symbols-outlined">assignment</span><span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span><span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?><span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
    </nav>
    <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">help</span><span class="text-sm font-medium">Help Center</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="login.php">
        <span class="material-symbols-outlined">logout</span><span class="text-sm font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Canvas -->
  <div class="pl-64 flex flex-col min-h-screen">

    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
      <div class="flex items-center gap-4 flex-1">
        <span class="text-xs font-semibold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg">Student Workspace</span>
      </div>
      <div class="flex items-center gap-6">
        <a href="student_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative block">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($unread_count > 0): ?><span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
        </a>
        <div class="h-8 w-[1px] bg-gray-200"></div>
        <div class="relative">
          <div id="profile-toggle" class="flex items-center gap-3 cursor-pointer group select-none p-1 rounded-lg hover:bg-gray-50 transition-colors">
            <div class="text-right hidden md:block">
              <p class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($profile['full_name']); ?></p>
              <p class="text-xs text-gray-500">Student Account</p>
            </div>
            <img alt="User profile" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
          </div>
          <!-- Profile Dropdown -->
          <div id="profile-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-slate-50 flex items-center gap-4">
              <img alt="User profile" class="w-14 h-14 rounded-full border-2 border-white shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
              <div>
                <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($profile['full_name']); ?></h3>
                <p class="text-xs text-slate-500 mb-1"><?php echo $profile['email']; ?></p>
                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase">Student</span>
              </div>
            </div>
            <div class="p-5 space-y-4">
              <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-sm">
                <div class="text-slate-500 font-medium">Phone</div><div class="text-slate-800 truncate"><?php echo $profile['phone']; ?></div>
                <div class="text-slate-500 font-medium">College</div><div class="text-slate-800 truncate"><?php echo $profile['college_name']; ?></div>
                <div class="text-slate-500 font-medium">Course</div><div class="text-slate-800 truncate"><?php echo $profile['course']; ?></div>
                <div class="text-slate-500 font-medium">Skills</div><div class="text-slate-800 truncate"><?php echo $profile['skills']; ?></div>
              </div>
            </div>
            <div class="p-3 bg-gray-50 border-t border-gray-100 grid grid-cols-2 gap-2">
              <a href="student_dashboard.php" class="py-2 text-sm font-semibold text-slate-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors shadow-sm text-center">Edit Profile</a>
              <a href="login.php" class="py-2 text-sm font-semibold text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors shadow-sm text-center">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-8 max-w-7xl w-full mx-auto space-y-8">

      <!-- Page Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-800 tracking-tight">Available Internships</h1>
          <p class="text-slate-500 mt-1.5 text-sm">Browse internships organized by domain. Filter, search, and apply with one click.</p>
        </div>
        <a href="student_dashboard.php" class="flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline">
          <span class="material-symbols-outlined text-[18px]">keyboard_backspace</span> Back to Dashboard
        </a>
      </div>

      <!-- Search Bar -->
      <div class="relative w-full">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-[22px]">search</span>
        <input id="search-input" class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-all shadow-sm" placeholder="Search by title, skills, mode, or duration (e.g. React, Remote, 3 Months)..." type="text">
      </div>

      <!-- Filter Bar -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
          <!-- Domain Filter -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Domain / Vertical</label>
            <select id="filter-domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-blue-600 transition-colors cursor-pointer">
              <option value="">All Domains</option>
              <?php foreach ($project_types as $pt): ?>
                <option value="<?php echo htmlspecialchars(strtolower(trim($pt['type_name']))); ?>"><?php echo htmlspecialchars($pt['type_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Duration Filter -->
          <div class="flex-1 min-w-[130px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Duration</label>
            <select id="filter-duration" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-blue-600 transition-colors cursor-pointer">
              <option value="">All Durations</option>
              <option value="1 month">1 Month</option>
              <option value="2 months">2 Months</option>
              <option value="3 months">3 Months</option>
              <option value="6 months">6 Months</option>
            </select>
          </div>
          <!-- Mode Filter -->
          <div class="flex-1 min-w-[130px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Mode</label>
            <select id="filter-mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-blue-600 transition-colors cursor-pointer">
              <option value="">All Modes</option>
              <option value="remote">Remote</option>
              <option value="hybrid">Hybrid</option>
              <option value="online">Online</option>
              <option value="on-site">On-Site</option>
            </select>
          </div>
          <!-- Skills Filter -->
          <div class="flex-1 min-w-[150px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Skills</label>
            <select id="filter-skill" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-blue-600 transition-colors cursor-pointer">
              <option value="">All Skills</option>
              <?php foreach ($unique_skills as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Clear Button -->
          <div>
            <button id="btn-clear-filters" class="h-10 px-5 bg-slate-50 border border-slate-200 hover:border-red-200 hover:text-red-600 text-xs font-bold text-slate-500 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
              <span class="material-symbols-outlined text-[18px]">filter_alt_off</span> Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Domain Quick-Filter Chips -->
      <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Quick Filter:</span>
        <button class="filter-chip px-4 py-1.5 rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:border-blue-400 hover:text-blue-600 active" data-chip="all">All</button>
        <?php foreach ($project_types as $pt): 
            $slug = strtolower(trim($pt['type_name']));
            $icon = match($slug) {
                'development' => 'code',
                'design' => 'palette',
                'marketing' => 'campaign',
                default => 'category'
            };
            $bg_cls = match($slug) {
                'development' => 'bg-blue-50 text-blue-700 border-blue-200 hover:border-blue-400',
                'design' => 'bg-purple-50 text-purple-700 border-purple-200 hover:border-purple-400',
                'marketing' => 'bg-orange-50 text-orange-700 border-orange-200 hover:border-orange-400',
                default => 'bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-400'
            };
        ?>
            <button class="filter-chip px-4 py-1.5 rounded-full border <?php echo $bg_cls; ?> text-xs font-semibold" data-chip="<?php echo htmlspecialchars($slug); ?>">
              <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]"><?php echo $icon; ?></span> <?php echo htmlspecialchars($pt['type_name']); ?></span>
            </button>
        <?php endforeach; ?>
      </div>

      <!-- ===== VERTICAL SECTIONS ===== -->
      <div id="all-sections" class="space-y-12 pb-12">
        <?php 
        $has_any_internship_overall = false;
        foreach ($project_types as $pt): 
            $pt_slug = strtolower(trim($pt['type_name']));
            $pt_color_cls = match($pt_slug) {
                'development' => 'text-blue-600 border-blue-500 bg-blue-50/50',
                'design' => 'text-purple-600 border-purple-500 bg-purple-50/50',
                'marketing' => 'text-orange-600 border-orange-500 bg-orange-50/50',
                default => 'text-slate-600 border-slate-500 bg-slate-50/50'
            };
            
            // Get subtypes for this type
            $subtypes_for_type = array_filter($project_subtypes, function($sub) use ($pt) {
                return intval($sub['project_type_id']) === intval($pt['id']);
            });
            
            if (empty($subtypes_for_type)) continue;
            
            // Track if any active subtype gets rendered
            $has_any_subtype_here = false;
        ?>
            <!-- ── <?php echo strtoupper($pt['type_name']); ?> PROJECTS ── -->
            <section class="vertical-section" data-vertical="<?php echo htmlspecialchars($pt_slug); ?>">
              <!-- Project Type Section Header -->
              <div class="flex items-center gap-4 mb-6">
                <div class="section-header-bar border-l-4 pl-4 flex items-center gap-3 <?php echo explode(' ', $pt_color_cls)[1]; ?>">
                  <h2 class="text-xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-[24px] <?php echo explode(' ', $pt_color_cls)[0]; ?>"><?php echo $pt_slug === 'development' ? 'code' : ($pt_slug === 'design' ? 'palette' : ($pt_slug === 'marketing' ? 'campaign' : 'category')); ?></span>
                    <?php echo htmlspecialchars($pt['type_name']); ?> Projects
                  </h2>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php 
                foreach ($subtypes_for_type as $sub): 
                    $has_any_subtype_here = true;
                    $has_any_internship_overall = true;
                    
                    // Get or create the matching active default internship record in database
                    $row = get_or_create_default_internship($conn, $pt['type_name'], $sub['subtype_name'], $sub['skills'], $sub['mode'], $sub['duration']);
                    
                    $card_type = $pt['type_name'];
                    $card_subtype = $sub['subtype_name'];
                    $card_duration = !empty($sub['duration']) ? $sub['duration'] : ($row ? $row['duration'] : '3 Months');
                    $card_mode = !empty($sub['mode']) ? $sub['mode'] : ($row ? $row['mode'] : 'Remote');
                    $card_skills = !empty($sub['skills']) ? $sub['skills'] : ($row ? ($row['technology_stack'] ?: $row['skills']) : '');
                ?>
                    <div class="internship-card bg-white rounded-2xl border border-slate-200/85 p-6 flex flex-col justify-between shadow-sm"
                         data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                         data-type="<?php echo htmlspecialchars(strtolower($card_type)); ?>"
                         data-subtype="<?php echo htmlspecialchars(strtolower($card_subtype)); ?>"
                         data-duration="<?php echo htmlspecialchars(strtolower($card_duration)); ?>"
                         data-mode="<?php echo htmlspecialchars(strtolower($card_mode)); ?>"
                         data-skills="<?php echo htmlspecialchars(strtolower($card_skills)); ?>"
                         data-vertical="<?php echo htmlspecialchars($pt_slug); ?>">
                      <div>
                        <div class="flex items-start justify-between mb-4">
                          <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                        </div>
                        
                        <!-- Main Heading: Project Subtype (Inside Card) -->
                        <h3 class="font-extrabold text-slate-800 text-lg leading-snug mb-3"><?php echo htmlspecialchars($card_subtype); ?></h3>
                        
                        <!-- Duration · Mode -->
                        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                          <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($card_duration); ?></span>
                          <span class="text-slate-300">•</span>
                          <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($card_mode); ?></span>
                        </div>
                        
                        <!-- Skills Tags -->
                        <div class="flex flex-wrap gap-1.5 mb-4">
                          <?php 
                            $skills_arr = explode(',', $card_skills);
                            foreach ($skills_arr as $skill): if (trim($skill) !== ''):
                          ?>
                          <span class="px-2.5 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                          <?php endif; endforeach; ?>
                        </div>
                      </div>
                      
                      <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                        <a href="internship_application_form.php?internship_id=<?php echo (int)$row['id']; ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center gap-1">
                          <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                        </a>
                      </div>
                    </div>
                <?php endforeach; ?>
              </div>
            </section>
        <?php endforeach; ?>
        
        <?php if (!$has_any_internship_overall): ?>
            <div class="col-span-full py-16 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
                <p class="text-sm font-semibold">No active internship openings available currently.</p>
            </div>
        <?php endif; ?>
      </div>

      <!-- No Results Message -->
      <div id="no-results-msg" class="hidden py-20 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <span class="material-symbols-outlined text-slate-300 text-3xl">search_off</span>
        </div>
        <h3 class="font-bold text-slate-700 mb-1">No Internships Found</h3>
        <p class="text-slate-500 text-sm">Try adjusting your filters or search query.</p>
        <button onclick="document.getElementById('btn-clear-filters').click()" class="mt-4 px-5 py-2 bg-blue-50 text-blue-700 text-sm font-bold rounded-xl hover:bg-blue-100 transition-colors">Clear All Filters</button>
      </div>

    </main>
  </div>
  <!-- END Main Canvas -->

<script>
document.addEventListener('DOMContentLoaded', () => {

  // ── Profile dropdown ──
  const profileToggle = document.getElementById('profile-toggle');
  const profileDropdown = document.getElementById('profile-dropdown');
  profileToggle.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('hidden'); });
  document.addEventListener('click', e => {
    if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.add('hidden');
  });

  // ── Filter elements ──
  const searchInput    = document.getElementById('search-input');
  const filterDomain   = document.getElementById('filter-domain');
  const filterDuration = document.getElementById('filter-duration');
  const filterMode     = document.getElementById('filter-mode');
  const filterSkill    = document.getElementById('filter-skill');
  const btnClear       = document.getElementById('btn-clear-filters');
  const noResults      = document.getElementById('no-results-msg');
  const sections       = document.querySelectorAll('.vertical-section');
  const cards          = document.querySelectorAll('.internship-card');
  const chips          = document.querySelectorAll('.filter-chip');

  // ── Quick-filter chip sync with domain dropdown ──
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const val = chip.dataset.chip;
      filterDomain.value = val === 'all' ? '' : val;
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      applyFilters();
    });
  });

  // Sync chip highlight when dropdown changes
  filterDomain.addEventListener('change', () => {
    const val = filterDomain.value;
    chips.forEach(c => {
      c.classList.remove('active');
      if ((val === '' && c.dataset.chip === 'all') || c.dataset.chip === val) c.classList.add('active');
    });
    applyFilters();
  });

  // ── Core filter function ──
  function applyFilters() {
    const query    = searchInput.value.toLowerCase().trim();
    const domain   = filterDomain.value.toLowerCase();
    const duration = filterDuration.value.toLowerCase();
    const mode     = filterMode.value.toLowerCase();
    const skill    = filterSkill.value.toLowerCase();

    let totalVisible = 0;

    sections.forEach(section => {
      const sectionVertical = section.dataset.vertical;

      // Domain filter: hide entire section if it doesn't match
      if (domain && sectionVertical !== domain) {
        section.classList.add('hidden-section');
        return;
      }
      section.classList.remove('hidden-section');

      // Card-level filtering
      let sectionHasVisible = false;
      section.querySelectorAll('.internship-card').forEach(card => {
        const title    = card.dataset.title    || '';
        const pType    = card.dataset.type     || '';
        const pSubtype = card.dataset.subtype  || '';
        const cardDur  = card.dataset.duration || '';
        const cardMode = card.dataset.mode     || '';
        const skills   = card.dataset.skills   || '';

        const matchQuery    = !query    
          || title.includes(query) 
          || pType.includes(query) 
          || pSubtype.includes(query) 
          || skills.includes(query) 
          || cardDur.includes(query) 
          || cardMode.includes(query);
        const matchDuration = !duration || cardDur.includes(duration);
        const matchMode     = !mode     || cardMode.includes(mode);
        const matchSkill    = !skill    || skills.includes(skill);

        if (matchQuery && matchDuration && matchMode && matchSkill) {
          card.classList.remove('card-hidden');
          sectionHasVisible = true;
          totalVisible++;
        } else {
          card.classList.add('card-hidden');
        }
      });

      // Hide section if no cards visible after card-level filtering
      if (!sectionHasVisible) {
        section.classList.add('hidden-section');
      }
    });

    noResults.classList.toggle('hidden', totalVisible > 0);
  }

  // ── Event listeners ──
  searchInput.addEventListener('input', applyFilters);
  filterDuration.addEventListener('change', applyFilters);
  filterMode.addEventListener('change', applyFilters);
  filterSkill.addEventListener('change', applyFilters);

  btnClear.addEventListener('click', () => {
    searchInput.value = '';
    filterDomain.value = '';
    filterDuration.value = '';
    filterMode.value = '';
    filterSkill.value = '';
    chips.forEach(c => c.classList.remove('active'));
    document.querySelector('[data-chip="all"]').classList.add('active');
    applyFilters();
  });

  // Set "All" chip as default active
  document.querySelector('[data-chip="all"]').classList.add('active');

  // ── Easy Apply: build URL from card data attributes ──
  // This handles both static cards (internship_id=0) and DB cards (internship_id>0)
  document.addEventListener('click', e => {
    const btn = e.target.closest('a[href*="internship_application_form.php"]');
    if (!btn) return;

    const card = btn.closest('.internship-card');
    if (!card) return;

    // Read existing href to check if it already has a real internship_id
    const existingHref = btn.getAttribute('href');
    const idMatch = existingHref.match(/internship_id=(\d+)/);
    const internshipId = idMatch ? parseInt(idMatch[1]) : 0;

    // If it's a real DB record (id > 0), let the link work as-is
    if (internshipId > 0) return;

    // For static cards (id=0), build URL from card data attributes
    e.preventDefault();
    const title    = card.dataset.title    || '';
    const duration = card.dataset.duration || '';
    const mode     = card.dataset.mode     || '';
    const skills   = card.dataset.skills   || '';
    const type     = card.dataset.type     || '';
    const subtype  = card.dataset.subtype  || '';

    // Capitalise title for display
    const displayTitle = title.replace(/\b\w/g, c => c.toUpperCase());

    const url = 'internship_application_form.php'
      + '?internship_id=0'
      + '&name='     + encodeURIComponent(displayTitle)
      + '&duration=' + encodeURIComponent(duration)
      + '&mode='     + encodeURIComponent(mode)
      + '&skills='   + encodeURIComponent(skills)
      + '&project_type=' + encodeURIComponent(type)
      + '&project_subtype=' + encodeURIComponent(subtype);

    window.location.href = url;
  });
});
</script>
</body>
</html>
