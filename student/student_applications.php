<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/status_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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
                   a.education_status,
                   a.applied_subtype,
                   i.project_type, i.project_subtype,
                   ss.score as ss_score,
                   ss.total_questions as ss_total_questions,
                   a.exam_link, a.exam_status, a.exam_name, a.exam_remarks,
                   a.exam_title, a.exam_instructions, a.exam_attachment, a.exam_date, a.exam_time,
                   a.confirmation_letter_path
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
               WHERE a.user_id = '$user_id' AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern' OR a.status = 'Internship Active') 
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
      <a href="../index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
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
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="../login.php">
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
              <a href="student_profile_form.php" class="py-2 text-sm font-semibold text-slate-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors shadow-sm text-center">Edit Profile</a>
              <a href="../login.php" class="py-2 text-sm font-semibold text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors shadow-sm text-center">Logout</a>
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
                    $current_status = $app['status'];
                    $status_info = getStatusBadge($current_status);
                ?>
                  <div class="p-6 hover:bg-slate-50/50 transition-all duration-200 group">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                      
                      <!-- Left: Internship Info -->
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-4">
                          <?php
                            $is_assigned = in_array($app['status'], ['Project Assigned', 'Internship Active', 'Started', 'Internship Started', 'Active Intern']);
                            $display_title = $is_assigned 
                                ? $app['title'] 
                                : (!empty($app['applied_subtype']) 
                                    ? $app['applied_subtype'] 
                                    : (!empty($app['project_subtype']) 
                                        ? $app['project_subtype'] 
                                        : $app['title']));
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
                            
                            <!-- Workflow Status Summary -->
                            <div class="mt-4 border-t border-slate-100 pt-3 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">

                              <?php
                                $is_pursuing = is_pursuing_student($app['education_status'] ?? '', $profile['student_type'] ?? '');
                              ?>
                              <?php if ($is_pursuing): ?>
                              <div>
                                <span class="text-slate-400 block mb-0.5">HOD Approval</span>
                                <?php if (in_array($app['status'], ['HOD Approved', 'Selected', 'Project Assigned', 'Internship Active'])): ?>
                                  <span class="text-emerald-700 font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span> Approved
                                  </span>
                                <?php elseif ($app['status'] === 'HOD Approval Pending'): ?>
                                  <span class="text-amber-700 font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">hourglass_empty</span> Pending
                                  </span>
                                <?php else: ?>
                                  <span class="text-slate-500 font-medium">Not Required</span>
                                <?php endif; ?>
                              </div>
                              <?php endif; ?>

                              <div>
                                <span class="text-slate-400 block mb-0.5">Confirmation Letter</span>
                                <?php if (!empty($app['confirmation_letter_path'])): ?>
                                  <span class="text-emerald-700 font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span> Sent
                                  </span>
                                <?php else: ?>
                                  <span class="text-slate-500 font-medium">Pending</span>
                                <?php endif; ?>
                              </div>

                              <div>
                                <span class="text-slate-400 block mb-0.5">Project Assignment</span>
                                <?php if (in_array($app['status'], ['Project Assigned', 'Internship Active'])): ?>
                                  <span class="text-emerald-700 font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span> Assigned
                                  </span>
                                <?php else: ?>
                                  <span class="text-slate-500 font-medium">Pending</span>
                                <?php endif; ?>
                              </div>
                            </div>


                          </div>
                        </div>
                      </div>
                      
                      <!-- Center: Current Status Only -->
                      <div class="flex-shrink-0">
                        <div class="text-center">
                          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Current Status</p>
                          <span class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold border-2 shadow-sm <?php echo $status_info['color']; ?>">
                            <span class="material-symbols-outlined text-[20px]"><?php echo $status_info['icon']; ?></span>
                            <?php echo htmlspecialchars($status_info['label']); ?>
                          </span>
                          <p class="text-xs text-slate-500 mt-2 font-medium">
                            Applied on <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                          </p>
                          
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
                        

                        
                        <?php if (!empty($app['confirmation_letter_path'])): ?>
                          <a href="<?php echo htmlspecialchars($app['confirmation_letter_path']); ?>" target="_blank" download
                             class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[18px]">download</span> 
                            Letter
                          </a>
                        <?php endif; ?>
                        
                        <?php if ($app['status'] === 'Project Assigned'): ?>
                          <a href="start_internship.php?app_id=<?php echo $app['app_id']; ?>" 
                             class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[18px]">rocket_launch</span> 
                            Start Internship
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

  <script>
    function closeResultModal() {
        // No-op (legacy compatibility)
    }
  </script>

  <!-- Search functionality -->
  <script src="../js/imp_search.js"></script>
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
