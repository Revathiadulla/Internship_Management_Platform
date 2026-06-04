<?php
session_start();
include "db.php";
include "questions_pool.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $sql);
$profile = mysqli_fetch_assoc($result);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}

// Fetch all applications
$app_sql = "SELECT a.id as app_id,
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.status, a.applied_date,
                   a.relevant_skills, a.preferred_duration,
                   a.test_status, a.test_score, a.test_answers, a.education_status, a.test_submitted_date,
                   i.project_type, i.project_subtype,
                   ss.score as ss_score,
                   ss.total_questions as ss_total_questions
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN student_scores ss ON a.id = ss.application_id
            WHERE a.user_id = '$user_id'
            ORDER BY a.applied_date DESC";
$app_result = mysqli_query($conn, $app_sql);
$app_count = mysqli_num_rows($app_result);

// Fetch unread notifications count
$unread_sql = "SELECT COUNT(*) as count FROM student_notifications WHERE user_id = '$user_id' AND is_read = 0";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? $unread_row['count'] : 0;

// Fetch active started internship (Started status)
$active_sql = "SELECT a.id as app_id 
               FROM internship_applications a 
               WHERE a.user_id = '$user_id' AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern') 
               LIMIT 1";
$active_result = mysqli_query($conn, $active_sql);
$has_active = mysqli_num_rows($active_result) > 0;
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>My Applications - IMP</title>
  
  <!-- CSS & Fonts -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
  
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
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
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#active-internship-card">
        <span class="material-symbols-outlined">badge</span>
        <span class="text-sm font-medium">My Internship</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_daily_log.php">
        <span class="material-symbols-outlined">edit_note</span>
        <span class="text-sm font-medium">Daily Logs</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#assigned-project-card">
        <span class="material-symbols-outlined">terminal</span>
        <span class="text-sm font-medium">Project</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#mentor-feedback-card">
        <span class="material-symbols-outlined">reviews</span>
        <span class="text-sm font-medium">Feedback</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php else: ?>
      <a class="flex items-center gap-3 text-gray-600 rounded-lg px-4 py-3 font-medium transition-all hover:bg-gray-50 hover:text-blue-600" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_browse_internships.php">
        <span class="material-symbols-outlined">work</span>
        <span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_applications.php">
        <span class="material-symbols-outlined">assignment</span>
        <span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
    </nav>
    
    <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">help</span>
        <span class="text-sm font-medium">Help Center</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="login.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Canvas -->
  <div class="pl-64 flex flex-col min-h-screen relative">
    
    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
      <div class="flex items-center gap-4 flex-1">
        <div class="relative w-full max-w-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
          <input id="app-search" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-600 focus:bg-white transition-colors" placeholder="Search by title, status, date..." type="text">
        </div>
      </div>
      
      <div class="flex items-center gap-6">
        <a href="student_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative block">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($unread_count > 0): ?>
              <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          <?php endif; ?>
        </a>
        <div class="h-8 w-[1px] bg-gray-200"></div>
        
        <!-- Profile Click Area -->
        <div class="relative">
          <div id="profile-toggle" class="flex items-center gap-3 cursor-pointer group select-none p-1 rounded-lg hover:bg-gray-50 transition-colors">
            <div class="text-right hidden md:block">
              <p class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($profile['full_name']); ?></p>
              <p class="text-xs text-gray-500">Student Account</p>
            </div>
            <img alt="User profile" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
          </div>

          <!-- Profile Dropdown Panel -->
          <div id="profile-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden transform origin-top-right transition-all">
            
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
                <div class="text-slate-500 font-medium">Verification</div>
                <div class="text-yellow-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">pending</span> Pending</div>
                
                <div class="text-slate-500 font-medium">Phone</div>
                <div class="text-slate-800 truncate"><?php echo $profile['phone']; ?></div>
                
                <div class="text-slate-500 font-medium">College</div>
                <div class="text-slate-800 truncate"><?php echo $profile['college_name']; ?></div>
                
                <div class="text-slate-500 font-medium">Course</div>
                <div class="text-slate-800 truncate"><?php echo $profile['course']; ?></div>
                
                <div class="text-slate-500 font-medium">Skills</div>
                <div class="text-slate-800 truncate"><?php echo $profile['skills']; ?></div>
              </div>
              
              <div class="pt-3 border-t border-gray-100">
                <a href="<?php echo get_resume_view_link($profile); ?>" target="_blank" data-resume-exists="<?php echo check_resume_exists($profile) ? 'true' : 'false'; ?>" class="w-full flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors text-sm text-slate-700 group">
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-400 group-hover:text-red-500">picture_as_pdf</span>
                    <span class="truncate w-40 text-left font-medium"><?php echo !empty($profile['resume_file']) ? basename($profile['resume_file']) : 'Resume Link'; ?></span>
                  </div>
                  <span class="text-blue-600 font-semibold text-xs">View</span>
                </a>
              </div>
            </div>

            <div class="p-3 bg-gray-50 border-t border-gray-100 grid grid-cols-2 gap-2">
              <a href="student_profile_form.php" class="py-2 text-sm font-semibold text-slate-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors shadow-sm text-center">Edit Profile</a>
              <a href="login.php" class="py-2 text-sm font-semibold text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors shadow-sm text-center">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8">
      
      <div class="max-w-5xl mx-auto">
        <?php if (isset($_GET['msg'])): ?>
          <div class="mb-6 p-4 text-sm font-bold text-green-800 rounded-lg bg-green-50 border border-green-300 shadow-sm">
            <?php echo htmlspecialchars($_GET['msg']); ?>
          </div>
        <?php endif; ?>
        <div class="flex items-center justify-between mb-8">
          <div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">My Applications</h1>
            <p class="text-sm text-slate-500 mt-1">Track the status of all your submitted internship applications.</p>
          </div>
          <a href="student_browse_internships.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow-sm transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px]">add</span> Apply for Internships
          </a>
        </div>

        <?php if ($app_count > 0): ?>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <div class="divide-y divide-slate-100">
                <?php while ($app = mysqli_fetch_assoc($app_result)): 
                    // Get status badge styling
                    $status_colors = [
                        'Applied' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'icon' => 'send'],
                        'Test Completed' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'icon' => 'quiz'],
                        'HR Round' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'icon' => 'manage_search'],
                        'HOD Approved' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200', 'icon' => 'verified'],
                        'Selected' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'icon' => 'check_circle'],
                        'Rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'icon' => 'cancel']
                    ];
                    
                    $current_status = $app['status'];
                    $status_style = $status_colors[$current_status] ?? $status_colors['Applied'];
                    
                    // Test deadline logic (48 hours from application)
                    $applied_time = strtotime($app['applied_date']);
                    $deadline_time = $applied_time + (48 * 60 * 60); // 48 hours
                    $current_time = time();
                    $time_remaining = $deadline_time - $current_time;
                    $is_deadline_expired = ($time_remaining <= 0);
                    
                    // Calculate hours and minutes remaining
                    $hours_left = floor($time_remaining / 3600);
                    $minutes_left = floor(($time_remaining % 3600) / 60);
                    
                    // Test status logic
                    $test_status = $app['test_status'] ?? 'Pending';
                    $test_score = $app['test_score'] ?? 0;
                    $test_submitted_date = $app['test_submitted_date'] ?? null;
                    
                    $raw_score = 0;
                    $total_qs = 30;
                    if ($app['ss_score'] !== null) {
                        $raw_score = intval($app['ss_score']);
                        $total_qs = intval($app['ss_total_questions'] ?: 30);
                    } else if (isset($app['test_score'])) {
                        $p = intval($app['test_score']);
                        if ($p > 30) {
                            $raw_score = intval(round(($p / 100) * 30));
                        } else {
                            $raw_score = $p;
                        }
                    }
                    
                    $show_start_test = ($current_status === 'Applied' && $test_status !== 'Completed' && !$is_deadline_expired);
                    $show_test_expired = ($current_status === 'Applied' && $test_status !== 'Completed' && $is_deadline_expired);
                    $show_view_result = ($test_status === 'Completed');
                ?>
                  <div class="p-6 hover:bg-slate-50/50 transition-all duration-200 group">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                      
                      <!-- Left: Internship Info -->
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-4">
                          <?php
                            $is_selected_or_approved = in_array($app['status'], ['Selected', 'Started', 'Internship Started', 'Active Intern']);
                            $display_title = $is_selected_or_approved 
                                ? $app['title'] 
                                : (!empty($app['project_type']) && !empty($app['project_subtype']) 
                                    ? $app['project_type'] . ' - ' . $app['project_subtype'] 
                                    : (!empty($app['project_subtype']) 
                                        ? $app['project_subtype'] 
                                        : (!empty($app['project_type']) 
                                            ? $app['project_type'] 
                                            : $app['title'])));
                          ?>
                          <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-xl shrink-0 shadow-md group-hover:shadow-lg transition-shadow">
                            <?php echo strtoupper(substr($display_title, 0, 1)); ?>
                          </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-900 text-lg mb-2 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($display_title); ?></h3>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                              <?php if (!empty($app['duration'])): ?>
                              <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">schedule</span>
                                <?php echo htmlspecialchars($app['duration']); ?>
                              </span>
                              <?php endif; ?>
                              <?php if (!empty($app['mode'])): ?>
                              <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">location_on</span>
                                <?php echo htmlspecialchars($app['mode']); ?>
                              </span>
                              <?php endif; ?>
                            </div>
                            
                            <!-- Test Deadline Warning (Only for Applied status with pending test) -->
                            <?php if ($current_status === 'Applied' && $test_status !== 'Completed'): ?>
                              <div class="mt-3 p-3 <?php echo $is_deadline_expired ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'; ?> border rounded-lg">
                                <div class="flex items-start gap-2">
                                  <span class="material-symbols-outlined text-[18px] <?php echo $is_deadline_expired ? 'text-red-600' : 'text-amber-600'; ?>">
                                    <?php echo $is_deadline_expired ? 'error' : 'schedule'; ?>
                                  </span>
                                  <div class="flex-1">
                                    <p class="text-xs font-bold <?php echo $is_deadline_expired ? 'text-red-700' : 'text-amber-700'; ?> mb-1">
                                      <?php echo $is_deadline_expired ? 'Test Deadline Expired' : 'Assessment Test Required'; ?>
                                    </p>
                                    <?php if ($is_deadline_expired): ?>
                                      <p class="text-xs text-red-600">The 48-hour test window has expired. Please contact HR.</p>
                                    <?php else: ?>
                                      <p class="text-xs text-amber-600 mb-1">Complete your test within 48 hours of application.</p>
                                      <p class="text-xs font-bold <?php echo ($hours_left < 6) ? 'text-red-600' : 'text-amber-700'; ?>">
                                        ⏱️ Time left: <?php echo $hours_left; ?>h <?php echo $minutes_left; ?>m
                                      </p>
                                      <p class="text-[10px] text-amber-500 mt-1">
                                        Deadline: <?php echo date('M d, Y \a\t g:i A', $deadline_time); ?>
                                      </p>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Center: Current Status Only -->
                      <div class="flex-shrink-0">
                        <div class="text-center">
                          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Current Status</p>
                          <span class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold border-2 shadow-sm <?php echo $status_style['bg'] . ' ' . $status_style['text'] . ' ' . $status_style['border']; ?>">
                            <span class="material-symbols-outlined text-[20px]"><?php echo $status_style['icon']; ?></span>
                            <?php echo htmlspecialchars($current_status); ?>
                          </span>
                          <p class="text-xs text-slate-500 mt-2 font-medium">
                            Applied on <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                          </p>
                          
                          <!-- Test Status Badge -->
                          <?php if ($current_status === 'Applied'): ?>
                            <div class="mt-3">
                              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Test Status</p>
                              <?php if ($test_status === 'Completed'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold">
                                  <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                  Completed
                                </span>
                              <?php elseif ($is_deadline_expired): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-700 border border-red-200 rounded-lg text-xs font-bold">
                                  <span class="material-symbols-outlined text-[14px]">cancel</span>
                                  Expired
                                </span>
                              <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-bold">
                                  <span class="material-symbols-outlined text-[14px]">pending</span>
                                  Pending
                                </span>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                          
                          <?php if ($current_status === 'Rejected'): ?>
                            <p class="text-xs text-red-600 mt-1 font-semibold">Not successful</p>
                          <?php endif; ?>
                        </div>
                      </div>
                      
                      <!-- Right: Actions -->
                      <div class="flex flex-col gap-2.5 lg:w-44">
                        <a href="view_application_status.php?app_id=<?php echo $app['app_id']; ?>" 
                           class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                          <span class="material-symbols-outlined text-[18px]">timeline</span> 
                          View Details
                        </a>
                        
                        <?php if ($show_start_test): ?>
                          <a href="student_test.php?application_id=<?php echo $app['app_id']; ?>" 
                             class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[18px]">quiz</span> 
                            Start Test
                          </a>
                        <?php elseif ($show_test_expired): ?>
                          <button disabled
                                  class="px-4 py-2.5 bg-slate-200 text-slate-500 text-sm font-bold rounded-lg cursor-not-allowed flex items-center justify-center gap-2 opacity-60">
                            <span class="material-symbols-outlined text-[18px]">block</span> 
                            Test Expired
                          </button>
                        <?php elseif ($show_view_result): ?>
                          <button onclick="openResultModal('<?php echo htmlspecialchars($display_title); ?>', <?php echo intval($raw_score); ?>, <?php echo intval($total_qs); ?>, '<?php echo htmlspecialchars($app['test_answers'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')" 
                                  class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[18px]">leaderboard</span> 
                            View Result
                          </button>
                        <?php endif; ?>
                        
                        <?php if ($app['status'] === 'Selected' || $app['status'] === 'HOD Approved'): ?>
                          <a href="start_internship.php?app_id=<?php echo $app['app_id']; ?>" 
                             class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[18px]">rocket_launch</span> 
                            Start Now
                          </a>
                        <?php endif; ?>
                      </div>
                      
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center max-w-xl mx-auto mt-8">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
              <span class="material-symbols-outlined text-slate-300 text-5xl">assignment_late</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">No Applications Found</h3>
            <p class="text-slate-500 text-sm max-w-sm mx-auto mb-6">You haven't submitted any internship applications yet. Browse the available listings and apply today!</p>
            <a href="student_browse_internships.php" class="inline-flex px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow-sm transition-all">
              Browse Available Internships
            </a>
          </div>
        <?php endif; ?>

      </div>

    </main>
  </div>

  <!-- Test Result Modal Overlay -->
  <div id="result-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl w-full max-w-2xl border border-slate-100 shadow-2xl overflow-hidden transform scale-95 transition-all duration-300" id="result-modal-content">
      
      <!-- Modal Header -->
      <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50/30 border-b border-slate-100 flex justify-between items-center">
        <div>
          <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600">Assessment Result</span>
          <h3 class="font-extrabold text-slate-800 text-lg leading-tight" id="result-modal-title">React.js Developer Internship</h3>
        </div>
        <button onclick="closeResultModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-200/50 transition-colors text-slate-400 hover:text-slate-700">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto" id="result-modal-body">
        <!-- Score Circular Progress or Graphics -->
        <div class="flex items-center gap-6 bg-slate-50 p-4 rounded-xl border border-slate-100">
          <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-extrabold text-2xl shrink-0" id="result-modal-score-circle">
            3/4
          </div>
          <div>
            <h4 class="font-bold text-slate-800 text-sm">Great effort!</h4>
            <p class="text-xs text-slate-500 mt-0.5" id="result-modal-score-comment">You scored 75% in this domain assessment. Your result has been logged and the hiring managers have been notified.</p>
          </div>
        </div>

        <!-- Questions & Answers breakdown -->
        <div class="space-y-4" id="result-modal-questions">
          <!-- Dynamic injection of Q&As -->
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
        <button onclick="closeResultModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-lg shadow-sm transition-all">
          Close Result
        </button>
      </div>

    </div>
  </div>

  <script>
    const domainQuestions = <?php echo json_encode($all_questions); ?>;

    function getDomainFromTitle(title) {
        const t = title.toLowerCase();
        if (t.includes('frontend') || t.includes('react') || t.includes('web')) return 'Frontend Development';
        if (t.includes('data') || t.includes('python') || t.includes('sql') || t.includes('science')) return 'Data Science';
        if (t.includes('ui') || t.includes('ux') || t.includes('design')) return 'UI/UX Design';
        if (t.includes('backend') || t.includes('node') || t.includes('php') || t.includes('database')) return 'Backend Development';
        return 'General Aptitude';
    }

    function openResultModal(title, score, totalQuestions, answersJsonStr) {
        const modal = document.getElementById("result-modal");
        const modalContent = document.getElementById("result-modal-content");
        const modalTitle = document.getElementById("result-modal-title");
        const scoreCircle = document.getElementById("result-modal-score-circle");
        const scoreComment = document.getElementById("result-modal-score-comment");
        const questionsContainer = document.getElementById("result-modal-questions");

        modalTitle.textContent = title;
        scoreCircle.textContent = score + "/" + totalQuestions;
        
        const percentage = Math.round((score / totalQuestions) * 100);
        let comment = `You scored ${score}/${totalQuestions} (${percentage}%). `;
        if (score === totalQuestions) comment += "Perfect score! Outstanding grasp of the subject matter. Hiring managers have been notified of your exceptional performance.";
        else if (percentage >= 80) comment += "Excellent job! You demonstrated strong foundational knowledge in this domain. Your response has been saved.";
        else if (percentage >= 60) comment += "Good attempt. You have solid basics but there is room for improvement. The review committee will review your profile shortly.";
        else comment += "Test completed. Your scores have been submitted. Focus on key core concepts to build your confidence.";

        scoreComment.textContent = comment;

        // Parse answers
        let answers = [];
        try {
            answers = JSON.parse(answersJsonStr);
        } catch(e) {
            console.error("Error parsing answers JSON", e);
        }

        const domain = getDomainFromTitle(title);
        const qList = domainQuestions[domain] || domainQuestions["General Aptitude"];

        questionsContainer.innerHTML = "";
        qList.forEach((q, idx) => {
            const studentAnsIdx = answers[idx] !== undefined ? parseInt(answers[idx]) : -1;
            const isCorrect = studentAnsIdx === q.correct;
            
            let choicesHtml = "";
            q.options.forEach((opt, optIdx) => {
                let bgClass = "border-slate-200 text-slate-600";
                let iconHtml = "";
                
                if (optIdx === q.correct) {
                    bgClass = "bg-emerald-50 border-emerald-400 text-emerald-800 font-semibold";
                    iconHtml = '<span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0">check_circle</span>';
                } else if (optIdx === studentAnsIdx && !isCorrect) {
                    bgClass = "bg-red-50 border-red-300 text-red-800 font-semibold";
                    iconHtml = '<span class="material-symbols-outlined text-[16px] text-red-600 shrink-0">cancel</span>';
                }

                choicesHtml += `
                    <div class="flex items-center justify-between px-3.5 py-2.5 rounded-lg border ${bgClass} text-xs transition-all">
                        <span>${opt}</span>
                        ${iconHtml}
                    </div>
                `;
            });

            const qBlock = document.createElement("div");
            qBlock.className = "space-y-2.5 p-4 rounded-xl border border-slate-100 bg-slate-50/30";
            qBlock.innerHTML = `
                <div class="flex items-start gap-2.5">
                    <span class="flex items-center justify-center w-5 h-5 rounded-full ${isCorrect ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'} text-[11px] font-bold shrink-0 mt-0.5">${idx + 1}</span>
                    <h5 class="font-bold text-slate-800 text-xs leading-snug">${q.q}</h5>
                </div>
                <div class="grid grid-cols-1 gap-2 pl-7.5">
                    ${choicesHtml}
                </div>
            `;
            questionsContainer.appendChild(qBlock);
        });

        // Open modal
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        setTimeout(() => {
            modalContent.classList.remove("scale-95");
            modalContent.classList.add("scale-100");
        }, 50);
    }

    function closeResultModal() {
        const modal = document.getElementById("result-modal");
        const modalContent = document.getElementById("result-modal-content");
        modalContent.classList.remove("scale-100");
        modalContent.classList.add("scale-95");
        setTimeout(() => {
            modal.classList.remove("flex");
            modal.classList.add("hidden");
        }, 150);
    }
  </script>

  <!-- Search functionality -->
  <script src="js/imp_search.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      ImpSearch.initTable({
        inputId: 'app-search',
        rowSelector: '#applications-table tbody tr',
        emptyMsg: 'No applications match your search.'
      });
    });
  </script>

</body>
</html>


  <!-- Profile Dropdown JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');

        // Toggle Profile Dropdown
        if (profileToggle && profileDropdown) {
            profileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });
        }
    });
  </script>
<?php print_resume_not_found_js(); ?>
