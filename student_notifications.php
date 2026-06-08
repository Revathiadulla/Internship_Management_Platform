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


// Seed realistic notifications if empty
$count_sql = "SELECT COUNT(*) as count FROM student_notifications WHERE user_id = '$user_id'";
$count_res = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_res);

if ($count_row['count'] == 0) {
    $v_status = isset($profile['verification_status']) ? $profile['verification_status'] : 'Pending';
    $v_msg = (strtolower($v_status) == 'verified' || strtolower($v_status) == 'approved') 
        ? "Your Aadhaar and PAN verification was successfully completed." 
        : "Your identity profile is currently pending Aadhaar validation.";
    
    $seed_sqls = [
        "INSERT INTO student_notifications (user_id, type, message, is_read, created_at) VALUES ('$user_id', 'Verification', '$v_msg', 0, NOW() - INTERVAL 1 HOUR)",
        "INSERT INTO student_notifications (user_id, type, message, is_read, created_at) VALUES ('$user_id', 'Reminder', 'Daily Log Reminder: Remember to submit your internship logbook entries regularly.', 0, NOW() - INTERVAL 4 HOUR)"
    ];

    // Seed based on active applications if any
    $app_check_sql = "SELECT i.title, a.status FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.user_id = '$user_id'";
    $app_check_res = mysqli_query($conn, $app_check_sql);
    while ($app_row = mysqli_fetch_assoc($app_check_res)) {
        $title = $app_row['title'];
        $status = $app_row['status'];
        $seed_sqls[] = "INSERT INTO student_notifications (user_id, type, message, is_read, created_at) VALUES ('$user_id', 'Application', 'Your application for $title was submitted successfully.', 1, NOW() - INTERVAL 1 DAY)";
        $seed_sqls[] = "INSERT INTO student_notifications (user_id, type, message, is_read, created_at) VALUES ('$user_id', 'Assessment', 'Assessment test is now available for $title.', 0, NOW() - INTERVAL 30 MINUTE)";
        if ($status == 'Shortlisted' || $status == 'Approved' || $status == 'Accepted') {
            $seed_sqls[] = "INSERT INTO student_notifications (user_id, type, message, is_read, created_at) VALUES ('$user_id', 'Selection', 'Congratulations! You have been shortlisted for the $title Internship.', 0, NOW() - INTERVAL 10 MINUTE)";
        }
    }

    foreach ($seed_sqls as $s_sql) {
        mysqli_query($conn, $s_sql);
    }
}

// Fetch unread notifications count for sidebar
$unread_sql = "
    SELECT COUNT(*) as count FROM (
        SELECT id, is_read FROM student_notifications WHERE user_id = '$user_id'
        UNION ALL
        SELECT id, is_read FROM notifications WHERE user_id = '$user_id'
    ) combined WHERE is_read = 0
";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? intval($unread_row['count']) : 0;

// Fetch all notifications for page display
$notifications_sql = "
    SELECT id, user_id, type, message, is_read, created_at, NULL AS title, NULL AS sender_name, link, 'student' AS source_table
    FROM student_notifications
    WHERE user_id = '$user_id'
    UNION ALL
    SELECT n.id, n.user_id, n.type, n.message, n.is_read, n.created_at, n.title, u.full_name AS sender_name, n.link, 'global' AS source_table
    FROM notifications n
    LEFT JOIN users u ON u.id = n.sender_id
    WHERE n.user_id = '$user_id'
    ORDER BY created_at DESC
";
$notifications_result = mysqli_query($conn, $notifications_sql);
$total_notifications = mysqli_num_rows($notifications_result);

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
  <title>Notifications - IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span id="sidebar-badge" class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php else: ?>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_browse_internships.php">
        <span class="material-symbols-outlined">work</span>
        <span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_applications.php">
        <span class="material-symbols-outlined">assignment</span>
        <span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span id="sidebar-badge" class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
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
        <span class="text-xs font-semibold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg">Student Workspace</span>
      </div>
      
      <div class="flex items-center gap-6">
        <button class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($unread_count > 0): ?>
              <span id="nav-dot" class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          <?php endif; ?>
        </button>
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
                <a href="<?php echo htmlspecialchars(getDocumentViewUrl(get_resume_view_link($profile))); ?>" target="_blank" rel="noopener noreferrer" data-resume-exists="<?php echo check_resume_exists($profile) ? 'true' : 'false'; ?>" class="w-full flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors text-sm text-slate-700 group">
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-400 group-hover:text-red-500">picture_as_pdf</span>
                    <span class="truncate w-40 text-left font-medium"><?php echo !empty($profile['resume_original_name']) ? htmlspecialchars($profile['resume_original_name']) : (!empty($profile['resume_file']) ? basename($profile['resume_file']) : 'Resume Link'); ?></span>
                  </div>
                  <span class="text-blue-600 font-semibold text-xs">View</span>
                </a>
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
    <main class="flex-grow p-8 max-w-4xl w-full mx-auto space-y-8">
      
      <!-- Top Title Bar -->
      <div class="flex items-center justify-between border-b border-slate-100 pb-5">
          <div>
              <div class="flex items-center gap-2">
                  <h1 class="text-3xl font-black text-slate-800 tracking-tight">Notifications</h1>
                  <span id="unread-pill-count" class="bg-red-500 text-white text-xs font-extrabold px-2.5 py-0.5 rounded-full shadow-sm"><?php echo $unread_count; ?> New</span>
              </div>
              <p class="text-slate-500 mt-1 text-sm">Stay updated on your application status, assessment schedules, and validations.</p>
          </div>
          <div class="flex items-center gap-2">
              <a href="student_email_logs.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 border border-slate-200">
                  <span class="material-symbols-outlined text-[16px]">mail</span> Email Sandbox Logs
              </a>
              <?php if ($unread_count > 0): ?>
                  <button id="btn-mark-all-read" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-[16px]">done_all</span> Mark All as Read
                  </button>
              <?php endif; ?>
          </div>
      </div>

      <!-- Filter Categories -->
      <div class="flex flex-wrap gap-2">
          <button data-filter="all" class="filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm">All</button>
          <button data-filter="unread" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Unread</button>
          <button data-filter="application" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Applications</button>
          <button data-filter="assessment" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Tests/Assessments</button>
          <button data-filter="verification" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Verifications</button>
          <button data-filter="reminder" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Reminders</button>
          <button data-filter="mentor_message" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Mentor Messages</button>
      </div>

      <!-- Notification List -->
      <div id="notifications-container" class="space-y-4">
          <?php if ($total_notifications > 0): ?>
              <?php while($row = mysqli_fetch_assoc($notifications_result)): 
                  $type = $row['type'];
                  $is_read = $row['is_read'];
                  
                   // Style configurations based on type
                   $icon = 'notifications';
                   $bg_class = 'bg-blue-50 text-blue-600';
                   if (strtolower($type) == 'application') {
                       $icon = 'assignment';
                       $bg_class = 'bg-blue-50 text-blue-600';
                   } elseif (strtolower($type) == 'assessment') {
                       $icon = 'quiz';
                       $bg_class = 'bg-purple-50 text-purple-600';
                   } elseif (strtolower($type) == 'verification') {
                       $icon = 'verified_user';
                       $bg_class = 'bg-green-50 text-green-600';
                   } elseif (strtolower($type) == 'hr') {
                       $icon = 'person';
                       $bg_class = 'bg-orange-50 text-orange-600';
                   } elseif (strtolower($type) == 'hod') {
                       $icon = 'school';
                       $bg_class = 'bg-indigo-50 text-indigo-600';
                   } elseif (strtolower($type) == 'selection') {
                       $icon = 'stars';
                       $bg_class = 'bg-rose-50 text-rose-600';
                   } elseif (strtolower($type) == 'reminder') {
                       $icon = 'event_note';
                       $bg_class = 'bg-amber-50 text-amber-600';
                   } elseif (strtolower($type) == 'alert') {
                       $icon = 'warning';
                       $bg_class = 'bg-red-50 text-red-600';
                   } elseif (strtolower($type) == 'mentor_message') {
                       $icon = 'chat';
                       $bg_class = 'bg-indigo-50 text-indigo-600';
                   }

                   $display_type = $type;
                   if (strtolower($type) == 'mentor_message') {
                       $display_type = 'Mentor Message';
                   }
               ?>
                   <?php if (!empty($row['link'])): ?>
                   <a href="mark_notification_read.php?action=read_redirect&id=<?php echo $row['id']; ?>&source=<?php echo $row['source_table']; ?>&fallback=student_notifications.php" class="notification-card block bg-white rounded-2xl border <?php echo $is_read ? 'border-slate-100' : 'border-blue-100 shadow-sm'; ?> p-5 transition-all flex items-start gap-4 hover:border-blue-300"
                        data-id="<?php echo $row['id']; ?>"
                        data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                        data-source="<?php echo $row['source_table']; ?>"
                        data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                   <?php else: ?>
                   <div class="notification-card bg-white rounded-2xl border <?php echo $is_read ? 'border-slate-100' : 'border-blue-100 shadow-sm'; ?> p-5 transition-all flex items-start gap-4"
                        data-id="<?php echo $row['id']; ?>"
                        data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                        data-source="<?php echo $row['source_table']; ?>"
                        data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                   <?php endif; ?>
                       
                       <!-- Icon -->
                       <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo $bg_class; ?>">
                           <span class="material-symbols-outlined text-[20px]"><?php echo $icon; ?></span>
                       </div>
                       
                       <!-- Main Details -->
                       <div class="flex-grow min-w-0">
                           <div class="flex items-start justify-between gap-2">
                               <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><?php echo htmlspecialchars($display_type); ?> Notification</span>
                               <?php if (!$is_read): ?>
                                   <span class="new-dot w-2 h-2 bg-blue-600 rounded-full shrink-0 mt-1 shadow-sm"></span>
                               <?php endif; ?>
                           </div>
                           <?php if (!empty($row['title'])): ?>
                               <h4 class="font-bold text-slate-900 text-sm mt-1"><?php echo htmlspecialchars($row['title']); ?></h4>
                           <?php endif; ?>
                           <p class="<?php echo !empty($row['title']) ? 'text-slate-600 text-xs mt-1' : 'font-semibold text-slate-800 text-sm mt-1'; ?> leading-relaxed"><?php echo htmlspecialchars($row['message']); ?></p>
                           <?php if (!empty($row['sender_name'])): ?>
                               <p class="text-[11px] text-indigo-600 font-semibold mt-1.5">From: <?php echo htmlspecialchars($row['sender_name']); ?></p>
                           <?php endif; ?>
                           <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1 mt-3">
                               <span class="material-symbols-outlined text-[12px]">schedule</span> 
                               <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                           </span>
                       </div>

                       <?php if (!$is_read): ?>
                           <button class="btn-mark-single-read self-center text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50/50 hover:bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 transition-colors shrink-0 z-10 relative">
                               Mark Read
                           </button>
                       <?php endif; ?>
                   <?php if (!empty($row['link'])): ?>
                   </a>
                   <?php else: ?>
                   </div>
                   <?php endif; ?>
              <?php endwhile; ?>
          <?php else: ?>
              <div class="py-16 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
                  <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                      <span class="material-symbols-outlined text-slate-300 text-3xl">notifications_off</span>
                  </div>
                  <h3 class="font-bold text-slate-700 mb-1">No Notifications Yet</h3>
                  <p class="text-slate-500 text-sm">We'll alert you as soon as you receive updates regarding your applications!</p>
              </div>
          <?php endif; ?>
          
          <div id="no-filtered-notifs" class="hidden py-16 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
              <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                  <span class="material-symbols-outlined text-slate-300 text-3xl">filter_list_off</span>
              </div>
              <h3 class="font-bold text-slate-700 mb-1">No Notifications Found</h3>
              <p class="text-slate-500 text-sm font-medium">There are no notifications inside the selected category.</p>
          </div>
      </div>

    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Profile dropdown panel toggle
        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');

        profileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });

        // Filter Pills toggle logic
        const filterPills = document.querySelectorAll(".filter-pill");
        const cards = document.querySelectorAll(".notification-card");
        const noFilteredNotifs = document.getElementById("no-filtered-notifs");

        filterPills.forEach(pill => {
            pill.addEventListener("click", () => {
                // Set active pill design
                filterPills.forEach(p => {
                    p.className = "filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all";
                });
                pill.className = "filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm";

                const filter = pill.getAttribute("data-filter");
                let hasMatches = false;

                cards.forEach(card => {
                    const type = card.getAttribute("data-type") || "";
                    const read = card.getAttribute("data-read") || "";

                    let matches = false;
                    if (filter === "all") {
                        matches = true;
                    } else if (filter === "unread") {
                        matches = (read === "false");
                    } else {
                        matches = (type === filter);
                    }

                    if (matches) {
                        card.style.display = "flex";
                        hasMatches = true;
                    } else {
                        card.style.display = "none";
                    }
                });

                if (hasMatches) {
                    if (noFilteredNotifs) noFilteredNotifs.classList.add("hidden");
                } else {
                    if (noFilteredNotifs) noFilteredNotifs.classList.remove("hidden");
                }
            });
        });

        // Mark Single Notification as Read
        const singleReadButtons = document.querySelectorAll(".btn-mark-single-read");
        singleReadButtons.forEach(btn => {
            btn.addEventListener("click", async (e) => {
                const card = btn.closest(".notification-card");
                const notifId = card.getAttribute("data-id");

                const source = card.getAttribute("data-source") || "student";

                try {
                    const response = await fetch(`mark_notification_read.php?id=${notifId}&source=${source}`);
                    const data = await response.json();

                    if (data.success) {
                        // Soft fade new visual indicators
                        card.setAttribute("data-read", "true");
                        card.className = card.tagName.toLowerCase() === 'a' 
                            ? "notification-card block bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4 hover:border-blue-300"
                            : "notification-card bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4";
                        
                        const dot = card.querySelector(".new-dot");
                        if (dot) dot.remove();
                        btn.remove();

                        // Decrease badge counters smoothly
                        updateBadgeCounts();
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                }
            });
        });

        // Mark All Notifications as Read
        const markAllButton = document.getElementById("btn-mark-all-read");
        if (markAllButton) {
            markAllButton.addEventListener("click", async () => {
                try {
                    const response = await fetch("mark_notification_read.php?all=1");
                    const data = await response.json();

                    if (data.success) {
                        cards.forEach(card => {
                            card.setAttribute("data-read", "true");
                            card.className = card.tagName.toLowerCase() === 'a' 
                                ? "notification-card block bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4 hover:border-blue-300"
                                : "notification-card bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4";
                            const dot = card.querySelector(".new-dot");
                            if (dot) dot.remove();
                            const btn = card.querySelector(".btn-mark-single-read");
                            if (btn) btn.remove();
                        });

                        markAllButton.remove();
                        updateBadgeCounts(true);
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                }
            });
        }

        // Help count badge decrease locally
        function updateBadgeCounts(allRead = false) {
            const sideBadge = document.getElementById("sidebar-badge");
            const navDot = document.getElementById("nav-dot");
            const pillCount = document.getElementById("unread-pill-count");

            if (allRead) {
                if (sideBadge) sideBadge.remove();
                if (navDot) navDot.remove();
                if (pillCount) pillCount.textContent = "0 New";
            } else {
                let currentCount = parseInt(pillCount.textContent) || 0;
                if (currentCount > 0) {
                    currentCount--;
                }

                if (currentCount === 0) {
                    if (sideBadge) sideBadge.remove();
                    if (navDot) navDot.remove();
                    pillCount.textContent = "0 New";
                    if (markAllButton) markAllButton.remove();
                } else {
                    if (sideBadge) sideBadge.textContent = currentCount;
                    pillCount.textContent = `${currentCount} New`;
                }
            }
        }
    });
  </script>
<?php print_resume_not_found_js(); ?>
</body>
</html>
