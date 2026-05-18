<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM applications WHERE user_id = '$user_id' ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
$profile = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
      <a href="index.html" class="flex items-center gap-2">
        <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-bold text-xl shadow-sm">I</div>
        <span class="text-xl font-bold text-slate-800 tracking-tight">IMP</span>
      </a>
      <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Student Portal</p>
    </div>
    
    <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">work</span>
        <span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">assignment</span>
        <span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold">2</span>
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
      <div class="flex items-center gap-4 flex-1">
        <div class="relative w-full max-w-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
          <input class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-600 focus:bg-white transition-colors" placeholder="Search internships or resources..." type="text">
        </div>
      </div>
      
      <div class="flex items-center gap-6">
        <button class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
          <span class="material-symbols-outlined">notifications</span>
          <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
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
                <a href="uploads/<?php echo htmlspecialchars($profile['resume_file']); ?>" target="_blank" class="w-full flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 transition-colors text-sm text-slate-700 group">
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-400 group-hover:text-red-500">picture_as_pdf</span>
                    <span class="truncate w-40 text-left font-medium"><?php echo basename($profile['resume_file']); ?></span>
                  </div>
                  <span class="text-blue-600 font-semibold text-xs">View</span>
                </a>
              </div>
            </div>

            <div class="p-3 bg-gray-50 border-t border-gray-100 grid grid-cols-2 gap-2">
              <button id="btn-edit-profile" class="py-2 text-sm font-semibold text-slate-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors shadow-sm">Edit Profile</button>
              <a href="login.php" class="py-2 text-sm font-semibold text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors shadow-sm text-center">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-8 max-w-7xl w-full mx-auto space-y-8">
      
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?> 👋</h1>
        <p class="text-slate-500 mt-2">Start applying for internships and track your application progress.</p>
      </div>

      <!-- Quick Status Row (3 Cards) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Verification Status -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-2">
                <div class="p-2 bg-yellow-50 rounded-lg">
                    <span class="material-symbols-outlined text-yellow-600">verified_user</span>
                </div>
                <h3 class="font-bold text-slate-800">Verification</h3>
            </div>
            <p class="text-sm text-slate-500 mb-4 flex-1">Profile submitted and pending verification.</p>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400">Aadhaar Validation</span>
                <span class="px-2.5 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full uppercase tracking-wider">Pending</span>
            </div>
        </div>

        <!-- Applied Count -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between group cursor-pointer hover:border-blue-200 hover:shadow-md transition-all">
            <div>
                <p class="text-sm text-slate-500 font-bold uppercase tracking-wider mb-1">Applied Internships</p>
                <p class="text-4xl font-black text-slate-800 group-hover:text-blue-600 transition-colors">0</p>
            </div>
            <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                <span class="material-symbols-outlined text-slate-400 text-3xl group-hover:text-blue-500">send</span>
            </div>
        </div>

        <!-- Shortlisted Count -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between group cursor-pointer hover:border-green-200 hover:shadow-md transition-all">
            <div>
                <p class="text-sm text-slate-500 font-bold uppercase tracking-wider mb-1">Shortlisted</p>
                <p class="text-4xl font-black text-slate-800 group-hover:text-green-600 transition-colors">0</p>
            </div>
            <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center group-hover:bg-green-50 transition-colors">
                <span class="material-symbols-outlined text-slate-400 text-3xl group-hover:text-green-500">star</span>
            </div>
        </div>

      </div>

      <!-- Main Columns -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Col: Available Internships -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">Available Internships</h2>
                <a href="#" class="text-sm font-semibold text-blue-600 hover:underline">View all</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Internship Card 1 -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition-shadow group flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center font-bold text-lg">T</div>
                            <span class="px-2 py-1 bg-green-50 text-green-700 text-[10px] font-bold rounded uppercase tracking-wider">New</span>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-600 transition-colors">Frontend Developer Intern</h3>
                        <p class="text-sm text-slate-500 mb-4">TechVision Inc. • Remote</p>
                        <div class="flex items-center gap-2 mb-5 flex-wrap">
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded">React.js</span>
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded">Tailwind</span>
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded">UI/UX</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                        <span class="text-xs font-bold text-slate-400">3 days ago</span>
                        <button class="px-4 py-1.5 bg-blue-50 text-blue-700 text-sm font-bold rounded-lg hover:bg-blue-100 transition-colors">Easy Apply</button>
                    </div>
                </div>

                <!-- Internship Card 2 -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition-shadow group flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center font-bold text-lg">D</div>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-600 transition-colors">Data Science Intern</h3>
                        <p class="text-sm text-slate-500 mb-4">DataCorp Analytics • Hybrid</p>
                        <div class="flex items-center gap-2 mb-5 flex-wrap">
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded">Python</span>
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded">SQL</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                        <span class="text-xs font-bold text-slate-400">1 week ago</span>
                        <button class="px-4 py-1.5 bg-blue-50 text-blue-700 text-sm font-bold rounded-lg hover:bg-blue-100 transition-colors">Easy Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Col: Application Pipeline -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">My Applications</h2>
                <span class="material-symbols-outlined text-slate-400">inbox</span>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col items-center justify-center h-64 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-slate-300 text-3xl">inbox</span>
                </div>
                <h3 class="font-bold text-slate-700 mb-1">No Applications Yet</h3>
                <p class="text-sm text-slate-500 max-w-[200px]">Browse available internships and start applying.</p>
                <button class="mt-6 px-5 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 shadow-sm transition-all">Browse Internships</button>
            </div>
        </div>

      </div>

    </main>
  </div>

  <!-- Edit Profile Modal Overlay -->
  <div id="edit-profile-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center p-4 overflow-y-auto">
    
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 sticky top-0 z-10">
            <h2 class="text-lg font-bold text-slate-800">Edit Profile Details</h2>
            <button id="btn-close-modal" class="p-1 rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Modal Body (Form) -->
        <div class="p-6 overflow-y-auto">
            <form id="edit-profile-form" class="space-y-6">
                
                <!-- Read Only Fields -->
                <div class="p-4 bg-yellow-50/50 border border-yellow-100 rounded-xl space-y-4">
                    <div class="flex items-start gap-2 text-yellow-800 text-xs font-medium mb-2">
                        <span class="material-symbols-outlined text-[16px]">info</span>
                        <p>Core identity details cannot be changed directly. Contact support if you need to update them.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo isset($profile['email']) ? htmlspecialchars($profile['email']) : ''; ?>" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-slate-500 text-sm cursor-not-allowed outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Aadhaar Number</label>
                            <input type="text" name="aadhaar_number" value="<?php echo isset($profile['aadhaar_number']) ? htmlspecialchars($profile['aadhaar_number']) : ''; ?>" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-slate-500 text-sm cursor-not-allowed outline-none font-mono">
                        </div>
                    </div>
                </div>

                <!-- Editable Fields -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                        <input type="tel" value="<?php echo $profile['phone']; ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">College / University</label>
                        <input type="text" value="<?php echo $profile['college_name']; ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Course / Degree</label>
                        <input type="text" value="<?php echo $profile['course']; ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Skills (Comma separated)</label>
                        <textarea rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all resize-none"><?php echo $profile['skills']; ?></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Update Resume</label>
                        <div class="flex items-center gap-4 p-4 border border-slate-200 rounded-xl bg-slate-50">
                            <div class="p-3 bg-red-100 text-red-600 rounded-lg">
                                <span class="material-symbols-outlined">picture_as_pdf</span>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <p class="text-sm font-semibold text-slate-800 truncate"><?php echo basename($profile['resume_file']); ?></p>
                                <p class="text-xs text-slate-500">Current file • 1.2 MB</p>
                            </div>
                            <button type="button" class="px-4 py-1.5 bg-white border border-slate-300 text-slate-700 text-sm font-bold rounded-lg hover:bg-slate-50 transition-colors shadow-sm">Replace</button>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-3 sticky bottom-0 z-10">
            <button id="btn-cancel-modal" class="px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">Cancel</button>
            <button type="button" class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">Save Changes</button>
        </div>
    </div>
  </div>

  <!-- UI Logic Script -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');
        const editProfileModal = document.getElementById('edit-profile-modal');
        const btnEditProfile = document.getElementById('btn-edit-profile');
        const btnCloseModal = document.getElementById('btn-close-modal');
        const btnCancelModal = document.getElementById('btn-cancel-modal');

        // Toggle Profile Dropdown
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

        // Open Modal
        btnEditProfile.addEventListener('click', () => {
            profileDropdown.classList.add('hidden'); // Close dropdown
            editProfileModal.classList.remove('hidden');
            editProfileModal.classList.add('flex');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        });

        // Close Modal logic
        const closeModal = () => {
            editProfileModal.classList.add('hidden');
            editProfileModal.classList.remove('flex');
            document.body.style.overflow = '';
        };

        btnCloseModal.addEventListener('click', closeModal);
        btnCancelModal.addEventListener('click', closeModal);

        // Close modal on backdrop click
        editProfileModal.addEventListener('click', (e) => {
            if (e.target === editProfileModal) {
                closeModal();
            }
        });
    });
  </script>

</body>
</html>