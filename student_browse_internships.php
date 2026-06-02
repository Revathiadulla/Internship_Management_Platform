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

// Fetch all active internships from DB
$sql_internships = "SELECT * FROM internships WHERE status = 'Active'";
$result_internships = mysqli_query($conn, $sql_internships);
$db_internships = [];
while ($row = mysqli_fetch_assoc($result_internships)) {
    $db_internships[] = $row;
}
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
          <button id="profile-toggle" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50 transition-colors cursor-pointer text-left">
            <img alt="User profile" class="w-9 h-9 rounded-full border border-gray-200 shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
            <span class="hidden text-left lg:block">
              <span class="block text-sm font-bold text-slate-900"><?php echo htmlspecialchars($profile['full_name']); ?></span>
              <span class="block text-xs text-slate-500">Student</span>
            </span>
            <span class="material-symbols-outlined text-slate-400">expand_more</span>
          </button>
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
              <option value="development">Development</option>
              <option value="design">Design</option>
              <option value="marketing">Marketing</option>
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
              <option value="react">React.js</option>
              <option value="node">Node.js</option>
              <option value="python">Python</option>
              <option value="sql">SQL</option>
              <option value="figma">Figma</option>
              <option value="flutter">Flutter</option>
              <option value="seo">SEO</option>
              <option value="photoshop">Photoshop</option>
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
        <button class="filter-chip px-4 py-1.5 rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:border-blue-400 hover:text-blue-600" data-chip="all">All</button>
        <button class="filter-chip px-4 py-1.5 rounded-full border border-blue-200 bg-blue-50 text-xs font-semibold text-blue-700 hover:border-blue-400" data-chip="development">
          <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">code</span> Development</span>
        </button>
        <button class="filter-chip px-4 py-1.5 rounded-full border border-purple-200 bg-purple-50 text-xs font-semibold text-purple-700 hover:border-purple-400" data-chip="design">
          <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">palette</span> Design</span>
        </button>
        <button class="filter-chip px-4 py-1.5 rounded-full border border-orange-200 bg-orange-50 text-xs font-semibold text-orange-700 hover:border-orange-400" data-chip="marketing">
          <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">campaign</span> Marketing</span>
        </button>
      </div>

      <!-- ===== VERTICAL SECTIONS ===== -->
      <div id="all-sections" class="space-y-12 pb-12">

        <!-- ── DEVELOPMENT PROJECTS ── -->
        <section class="vertical-section" data-vertical="development">
          <div class="flex items-center gap-4 mb-6">
            <div class="section-header-bar border-blue-500 pl-4 flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600">code</span>
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-800">Development Projects</h2>
                <p class="text-xs text-slate-500 font-medium">Web, Mobile & Backend engineering roles</p>
              </div>
            </div>
            <div class="ml-auto flex items-center gap-2">
              <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-full border border-blue-100" id="dev-count">3 Openings</span>
            </div>
          </div>

          <!-- Sub-section: Web Development -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-blue-400 text-[18px]">web</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Web Development</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <!-- Static Card: Web Dev Intern -->
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="web development intern" data-duration="3 months" data-mode="remote"
                   data-skills="react.js html css tailwind javascript" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-blue-600">web</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Web Development Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[11px] font-semibold rounded-lg">React.js</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">HTML/CSS</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Tailwind</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">JavaScript</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Web+Development+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- Static Card: Frontend Engineer Intern -->
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="frontend engineer intern" data-duration="2 months" data-mode="hybrid"
                   data-skills="vue.js javascript css figma" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-indigo-600">layers</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Frontend Engineer Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[11px] font-semibold rounded-lg">Vue.js</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">JavaScript</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">CSS</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Figma</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Frontend+Engineer+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- DB-driven cards for Web Development -->
              <?php foreach ($db_internships as $row):
                $title_lower = strtolower($row['title']);
                if (strpos($title_lower, 'web') !== false || strpos($title_lower, 'frontend') !== false || strpos($title_lower, 'front-end') !== false):
                  $skills = explode(',', $row['skills']);
              ?>
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                   data-duration="<?php echo htmlspecialchars(strtolower($row['duration'])); ?>"
                   data-mode="<?php echo htmlspecialchars(strtolower($row['mode'])); ?>"
                   data-skills="<?php echo htmlspecialchars(strtolower($row['skills'])); ?>"
                   data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center font-bold text-blue-600 text-lg"><?php echo strtoupper(substr($row['title'],0,1)); ?></div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($row['duration']); ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($row['mode']); ?></span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <?php foreach ($skills as $skill): ?>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=<?php echo $row['id']; ?>&name=<?php echo urlencode($row['title']); ?>" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>

          <!-- Sub-section: Mobile Apps -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-blue-400 text-[18px]">smartphone</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Mobile Apps</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="mobile app developer intern" data-duration="3 months" data-mode="remote"
                   data-skills="flutter dart firebase android" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-cyan-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-cyan-600">smartphone</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Mobile App Developer Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-cyan-50 text-cyan-700 text-[11px] font-semibold rounded-lg">Flutter</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Dart</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Firebase</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Android</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Mobile+App+Developer+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="react native intern" data-duration="2 months" data-mode="hybrid"
                   data-skills="react native javascript expo ios android" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-sky-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-sky-600">phone_iphone</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">React Native Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-sky-50 text-sky-700 text-[11px] font-semibold rounded-lg">React Native</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">JavaScript</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Expo</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">iOS/Android</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=React+Native+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- DB mobile cards -->
              <?php foreach ($db_internships as $row):
                $title_lower = strtolower($row['title']);
                if (strpos($title_lower, 'mobile') !== false || strpos($title_lower, 'flutter') !== false || strpos($title_lower, 'android') !== false || strpos($title_lower, 'ios') !== false):
                  $skills = explode(',', $row['skills']);
              ?>
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                   data-duration="<?php echo htmlspecialchars(strtolower($row['duration'])); ?>"
                   data-mode="<?php echo htmlspecialchars(strtolower($row['mode'])); ?>"
                   data-skills="<?php echo htmlspecialchars(strtolower($row['skills'])); ?>"
                   data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-cyan-100 rounded-xl flex items-center justify-center font-bold text-cyan-600 text-lg"><?php echo strtoupper(substr($row['title'],0,1)); ?></div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($row['duration']); ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($row['mode']); ?></span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <?php foreach ($skills as $skill): ?>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=<?php echo $row['id']; ?>&name=<?php echo urlencode($row['title']); ?>" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>

          <!-- Sub-section: Backend Systems -->
          <div>
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-blue-400 text-[18px]">dns</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Backend Systems</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="backend developer intern" data-duration="3 months" data-mode="remote"
                   data-skills="node.js python sql mongodb rest api" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-emerald-600">dns</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Backend Developer Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[11px] font-semibold rounded-lg">Node.js</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Python</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">SQL</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">REST API</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Backend+Developer+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="database & api intern" data-duration="2 months" data-mode="online"
                   data-skills="python sql postgresql django rest api" data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-teal-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-teal-600">storage</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Database & API Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">public</span> Online</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-teal-50 text-teal-700 text-[11px] font-semibold rounded-lg">Python</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">SQL</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">PostgreSQL</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Django</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Database+%26+API+Intern" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- DB backend cards -->
              <?php foreach ($db_internships as $row):
                $title_lower = strtolower($row['title']);
                if (strpos($title_lower, 'backend') !== false || strpos($title_lower, 'back-end') !== false || strpos($title_lower, 'api') !== false || strpos($title_lower, 'database') !== false):
                  $skills = explode(',', $row['skills']);
              ?>
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                   data-duration="<?php echo htmlspecialchars(strtolower($row['duration'])); ?>"
                   data-mode="<?php echo htmlspecialchars(strtolower($row['mode'])); ?>"
                   data-skills="<?php echo htmlspecialchars(strtolower($row['skills'])); ?>"
                   data-vertical="development">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center font-bold text-emerald-600 text-lg"><?php echo strtoupper(substr($row['title'],0,1)); ?></div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($row['duration']); ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($row['mode']); ?></span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <?php foreach ($skills as $skill): ?>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=<?php echo $row['id']; ?>&name=<?php echo urlencode($row['title']); ?>" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>
        </section>
        <!-- END Development Section -->

        <!-- ── DESIGN PROJECTS ── -->
        <section class="vertical-section" data-vertical="design">
          <div class="flex items-center gap-4 mb-6">
            <div class="section-header-bar border-purple-500 pl-4 flex items-center gap-3">
              <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-600">palette</span>
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-800">Design Projects</h2>
                <p class="text-xs text-slate-500 font-medium">UI/UX, Graphic & Product design roles</p>
              </div>
            </div>
            <div class="ml-auto">
              <span class="px-3 py-1 bg-purple-50 text-purple-700 text-xs font-bold rounded-full border border-purple-100">3 Openings</span>
            </div>
          </div>

          <!-- Sub-section: UI/UX Design -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-purple-400 text-[18px]">design_services</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">UI/UX Design</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="ui/ux design intern" data-duration="3 months" data-mode="remote"
                   data-skills="figma adobe xd prototyping user research wireframing" data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-purple-600">design_services</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">UI/UX Design Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-purple-50 text-purple-700 text-[11px] font-semibold rounded-lg">Figma</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Adobe XD</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Prototyping</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">User Research</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=UI%2FUX+Design+Intern" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="product designer intern" data-duration="2 months" data-mode="hybrid"
                   data-skills="figma sketch user testing design systems accessibility" data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-violet-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-violet-600">widgets</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Product Designer Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-violet-50 text-violet-700 text-[11px] font-semibold rounded-lg">Figma</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Sketch</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Design Systems</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">User Testing</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Product+Designer+Intern" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- DB UI/UX cards -->
              <?php foreach ($db_internships as $row):
                $title_lower = strtolower($row['title']);
                if (strpos($title_lower, 'ui') !== false || strpos($title_lower, 'ux') !== false || strpos($title_lower, 'design') !== false):
                  $skills = explode(',', $row['skills']);
              ?>
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                   data-duration="<?php echo htmlspecialchars(strtolower($row['duration'])); ?>"
                   data-mode="<?php echo htmlspecialchars(strtolower($row['mode'])); ?>"
                   data-skills="<?php echo htmlspecialchars(strtolower($row['skills'])); ?>"
                   data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center font-bold text-purple-600 text-lg"><?php echo strtoupper(substr($row['title'],0,1)); ?></div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($row['duration']); ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($row['mode']); ?></span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <?php foreach ($skills as $skill): ?>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=<?php echo $row['id']; ?>&name=<?php echo urlencode($row['title']); ?>" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>

          <!-- Sub-section: Graphic Design -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-purple-400 text-[18px]">brush</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Graphic Design</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="graphic design intern" data-duration="2 months" data-mode="remote"
                   data-skills="photoshop illustrator indesign canva branding" data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-fuchsia-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-fuchsia-600">brush</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Graphic Design Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-fuchsia-50 text-fuchsia-700 text-[11px] font-semibold rounded-lg">Photoshop</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Illustrator</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">InDesign</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Canva</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Graphic+Design+Intern" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="visual branding intern" data-duration="3 months" data-mode="hybrid"
                   data-skills="illustrator canva brand identity typography motion graphics" data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-pink-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-pink-600">auto_awesome</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Visual Branding Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-pink-50 text-pink-700 text-[11px] font-semibold rounded-lg">Illustrator</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Canva</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Typography</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Motion Graphics</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Visual+Branding+Intern" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Sub-section: Product Design -->
          <div>
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-purple-400 text-[18px]">category</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Product Design</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="product design intern" data-duration="3 months" data-mode="remote"
                   data-skills="figma user research design thinking prototyping usability testing" data-vertical="design">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-indigo-600">category</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Product Design Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[11px] font-semibold rounded-lg">Figma</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">User Research</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Design Thinking</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Prototyping</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Product+Design+Intern" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
            </div>
          </div>
        </section>
        <!-- END Design Section -->

        <!-- ── MARKETING PROJECTS ── -->
        <section class="vertical-section" data-vertical="marketing">
          <div class="flex items-center gap-4 mb-6">
            <div class="section-header-bar border-orange-500 pl-4 flex items-center gap-3">
              <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-orange-600">campaign</span>
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-800">Marketing Projects</h2>
                <p class="text-xs text-slate-500 font-medium">SEO, Social Media & Content strategy roles</p>
              </div>
            </div>
            <div class="ml-auto">
              <span class="px-3 py-1 bg-orange-50 text-orange-700 text-xs font-bold rounded-full border border-orange-100">3 Openings</span>
            </div>
          </div>

          <!-- Sub-section: SEO Campaigns -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-orange-400 text-[18px]">travel_explore</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">SEO Campaigns</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="seo analyst intern" data-duration="3 months" data-mode="remote"
                   data-skills="seo google analytics keyword research ahrefs on-page seo" data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-orange-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-orange-600">travel_explore</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">SEO Analyst Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-orange-50 text-orange-700 text-[11px] font-semibold rounded-lg">SEO</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Google Analytics</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Ahrefs</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Keyword Research</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=SEO+Analyst+Intern" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="digital marketing intern" data-duration="2 months" data-mode="hybrid"
                   data-skills="seo sem google ads ppc analytics email marketing" data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-amber-600">ads_click</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Digital Marketing Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[11px] font-semibold rounded-lg">SEO/SEM</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Google Ads</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">PPC</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Analytics</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Digital+Marketing+Intern" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <!-- DB SEO/marketing cards -->
              <?php foreach ($db_internships as $row):
                $title_lower = strtolower($row['title']);
                if (strpos($title_lower, 'seo') !== false || strpos($title_lower, 'marketing') !== false || strpos($title_lower, 'digital') !== false):
                  $skills = explode(',', $row['skills']);
              ?>
              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                   data-duration="<?php echo htmlspecialchars(strtolower($row['duration'])); ?>"
                   data-mode="<?php echo htmlspecialchars(strtolower($row['mode'])); ?>"
                   data-skills="<?php echo htmlspecialchars(strtolower($row['skills'])); ?>"
                   data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-orange-100 rounded-xl flex items-center justify-center font-bold text-orange-600 text-lg"><?php echo strtoupper(substr($row['title'],0,1)); ?></div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> <?php echo htmlspecialchars($row['duration']); ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> <?php echo htmlspecialchars($row['mode']); ?></span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <?php foreach ($skills as $skill): ?>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=<?php echo $row['id']; ?>&name=<?php echo urlencode($row['title']); ?>" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>

          <!-- Sub-section: Social Media Strategy -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-orange-400 text-[18px]">share</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Social Media Strategy</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="social media intern" data-duration="2 months" data-mode="remote"
                   data-skills="instagram linkedin meta ads content creation canva analytics" data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-rose-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-rose-600">share</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Social Media Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-rose-50 text-rose-700 text-[11px] font-semibold rounded-lg">Instagram</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Meta Ads</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Canva</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Analytics</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Social+Media+Intern" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="social media strategist intern" data-duration="3 months" data-mode="hybrid"
                   data-skills="social media strategy content calendar community management hootsuite" data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-red-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-red-600">trending_up</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Social Media Strategist Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 3 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">apartment</span> Hybrid</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-red-50 text-red-700 text-[11px] font-semibold rounded-lg">Strategy</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Content Calendar</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Hootsuite</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Community Mgmt</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Social+Media+Strategist+Intern" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Sub-section: Content Marketing -->
          <div>
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-orange-400 text-[18px]">article</span>
              <h3 class="text-sm font-bold text-slate-600 uppercase tracking-wider">Content Marketing</h3>
              <div class="flex-1 h-px bg-slate-100 ml-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

              <div class="internship-card bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between shadow-sm"
                   data-title="content marketing intern" data-duration="2 months" data-mode="remote"
                   data-skills="copywriting blog writing content strategy wordpress seo writing" data-vertical="marketing">
                <div>
                  <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 bg-yellow-100 rounded-xl flex items-center justify-center">
                      <span class="material-symbols-outlined text-yellow-600">article</span>
                    </div>
                    <span class="px-2.5 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wider border border-green-100">● Active</span>
                  </div>
                  <h3 class="font-extrabold text-slate-800 text-base leading-snug mb-1">Content Marketing Intern</h3>
                  <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-4">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> 2 Months</span>
                    <span class="text-slate-300">•</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">laptop_mac</span> Remote</span>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mb-5">
                    <span class="px-2 py-0.5 bg-yellow-50 text-yellow-700 text-[11px] font-semibold rounded-lg">Copywriting</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">Blog Writing</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">WordPress</span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg">SEO Writing</span>
                  </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                  <span class="text-[11px] font-bold text-slate-400">Recently Posted</span>
                  <a href="internship_application_form.php?internship_id=0&name=Content+Marketing+Intern" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl hover:bg-orange-600 transition-colors shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply
                  </a>
                </div>
              </div>
            </div>
          </div>
        </section>
        <!-- END Marketing Section -->

        <!-- No Results Message -->
        <div id="no-results-msg" class="hidden py-20 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
          <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-slate-300 text-3xl">search_off</span>
          </div>
          <h3 class="font-bold text-slate-700 mb-1">No Internships Found</h3>
          <p class="text-slate-500 text-sm">Try adjusting your filters or search query.</p>
          <button onclick="document.getElementById('btn-clear-filters').click()" class="mt-4 px-5 py-2 bg-blue-50 text-blue-700 text-sm font-bold rounded-xl hover:bg-blue-100 transition-colors">Clear All Filters</button>
        </div>

      </div>
      <!-- END all-sections -->

    </main>
  </div>
  <!-- END Main Canvas -->

<script>
document.addEventListener('DOMContentLoaded', () => {

  // ── Profile dropdown ──
  const profileToggle = document.getElementById('profile-toggle');
  const profileDropdown = document.getElementById('profile-dropdown');
  if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('hidden'); });
    document.addEventListener('click', e => {
      if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.add('hidden');
    });
    profileDropdown.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        profileDropdown.classList.add('hidden');
      });
    });
  }

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
        const cardDur  = card.dataset.duration || '';
        const cardMode = card.dataset.mode     || '';
        const skills   = card.dataset.skills   || '';

        const matchQuery    = !query    || title.includes(query) || skills.includes(query) || cardDur.includes(query) || cardMode.includes(query);
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

    // Capitalise title for display
    const displayTitle = title.replace(/\b\w/g, c => c.toUpperCase());

    const url = 'internship_application_form.php'
      + '?internship_id=0'
      + '&name='     + encodeURIComponent(displayTitle)
      + '&duration=' + encodeURIComponent(duration)
      + '&mode='     + encodeURIComponent(mode)
      + '&skills='   + encodeURIComponent(skills);

    window.location.href = url;
  });
});
</script>
</body>
</html>
