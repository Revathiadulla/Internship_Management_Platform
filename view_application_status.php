<?php
session_start();
include "db.php";
include "application_status_timeline.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

if ($app_id <= 0) {
    header("Location: student_applications.php");
    exit();
}

// Fetch application details
$app_sql = "SELECT a.*, 
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.id = $app_id AND a.user_id = $user_id
            LIMIT 1";
$app_result = mysqli_query($conn, $app_sql);

if (mysqli_num_rows($app_result) == 0) {
    header("Location: student_applications.php");
    exit();
}

$app = mysqli_fetch_assoc($app_result);

// Fetch student profile
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile_result = mysqli_query($conn, $profile_sql);
$profile = mysqli_fetch_assoc($profile_result);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Application Status - <?php echo htmlspecialchars($app['title']); ?></title>
  
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
      </a>
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
      <div class="flex items-center gap-4">
        <a href="student_applications.php" class="p-2 hover:bg-gray-50 rounded-lg transition-colors">
          <span class="material-symbols-outlined text-slate-600">arrow_back</span>
        </a>
        <div>
          <h1 class="text-lg font-bold text-slate-800">Application Status</h1>
          <p class="text-xs text-slate-500"><?php echo htmlspecialchars($app['title']); ?></p>
        </div>
      </div>
      
      <div class="flex items-center gap-6">
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
      <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- Internship Details Card -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?php echo htmlspecialchars($app['title']); ?></h2>
              <div class="flex flex-wrap gap-2 mt-3">
                <?php if (!empty($app['duration'])): ?>
                <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-lg border border-blue-100">
                  <span class="material-symbols-outlined text-[14px] align-middle mr-1">schedule</span>
                  <?php echo htmlspecialchars($app['duration']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($app['mode'])): ?>
                <span class="px-3 py-1 bg-purple-50 text-purple-700 text-xs font-semibold rounded-lg border border-purple-100">
                  <span class="material-symbols-outlined text-[14px] align-middle mr-1">location_on</span>
                  <?php echo htmlspecialchars($app['mode']); ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-right">
              <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Applied On</p>
              <p class="text-sm font-bold text-slate-700"><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></p>
            </div>
          </div>
        </div>
        
        <!-- Status Timeline -->
        <?php renderStatusTimeline($app_id, $conn); ?>
        
      </div>
    </main>
  </div>

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
