<?php
session_start();
include "db.php";
include "status_utils.php";

// For demo purposes, set HR role (in production, check actual session)
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'HR';
    $_SESSION['user_id'] = 999; // Demo HR user
}

// Fetch all applications
$app_sql = "SELECT a.id as app_id, a.user_id, a.status, a.applied_date, a.education_status,
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   sp.full_name, sp.email, sp.college_name, sp.course
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            ORDER BY a.applied_date DESC";
$app_result = mysqli_query($conn, $app_sql);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>HR Applications Management - IMP</title>
  
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
  <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
    <div class="px-6 mb-8 flex items-center gap-3">
      <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
        <span class="material-symbols-outlined text-white">school</span>
      </div>
      <div>
        <h2 class="text-lg font-black tracking-tight text-blue-600 leading-tight">HR Panel</h2>
        <p class="text-[10px] uppercase tracking-widest text-gray-600 font-bold">Management Console</p>
      </div>
    </div>
    
    <nav class="flex-1 flex flex-col gap-1">
      <a href="hr_dashboard.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
        <span class="material-symbols-outlined">dashboard</span>
        <span>Dashboard</span>
      </a>
      <a href="hr_applications.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 cursor-pointer">
        <span class="material-symbols-outlined">assignment</span>
        <span>Applications</span>
      </a>
      <a href="#" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
        <span class="material-symbols-outlined">group</span>
        <span>Candidates</span>
      </a>
      <a href="#" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
        <span class="material-symbols-outlined">analytics</span>
        <span>Reports</span>
      </a>
    </nav>
    
    <div class="mt-auto border-t border-gray-200 pt-4 flex flex-col gap-1">
      <a href="#" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
        <span class="material-symbols-outlined">help</span>
        <span>Help Center</span>
      </a>
      <a href="index.html" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
        <span class="material-symbols-outlined">logout</span>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="pl-60 flex flex-col min-h-screen">
    
    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3">
      <div class="flex items-center gap-8">
        <span class="text-xl font-bold text-blue-600">IMP</span>
        <div class="relative w-80">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
          <input class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition-all text-sm" placeholder="Search applications..." type="text">
        </div>
      </div>
      <div class="flex items-center gap-4">
        <button class="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors">
          <span class="material-symbols-outlined">notifications</span>
        </button>
        <div class="h-8 w-[1px] bg-gray-200 mx-2"></div>
        <div class="flex items-center gap-3">
          <div class="text-right">
            <p class="font-semibold text-gray-900 leading-none">Sarah Jenkins</p>
            <p class="text-[10px] text-gray-500 mt-1 uppercase font-bold tracking-tight">HR Manager</p>
          </div>
          <img alt="User profile" class="w-10 h-10 rounded-full object-cover border-2 border-blue-100" src="https://ui-avatars.com/api/?name=Sarah+Jenkins&background=2563eb&color=fff">
        </div>
      </div>
    </header>

    <!-- Dashboard Body -->
    <div class="p-8 space-y-6 max-w-[1600px] mx-auto w-full">
      
      <!-- Page Header -->
      <div class="flex justify-between items-end">
        <div>
          <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Applications Management</h2>
          <p class="text-sm text-slate-500 mt-1">Review, update status, and manage all internship applications</p>
        </div>
        <div class="flex gap-2">
          <button class="flex items-center gap-2 bg-slate-100 text-slate-700 px-4 py-2 rounded-lg font-medium text-sm hover:bg-slate-200 transition-all">
            <span class="material-symbols-outlined text-[18px]">filter_list</span>
            Filters
          </button>
          <button class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium text-sm hover:bg-blue-700 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Export
          </button>
        </div>
      </div>

      <!-- Applications Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                <th class="py-4 px-6">Candidate</th>
                <th class="py-4 px-6">Internship</th>
                <th class="py-4 px-6">Applied Date</th>
                <th class="py-4 px-6">Education</th>
                <th class="py-4 px-6">Current Status</th>
                <th class="py-4 px-6">Update Status</th>
                <th class="py-4 px-6">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
              <?php while ($app = mysqli_fetch_assoc($app_result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-3">
                      <img class="w-10 h-10 rounded-full border border-slate-200" src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['full_name']); ?>&background=random" alt="<?php echo htmlspecialchars($app['full_name']); ?>">
                      <div>
                        <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($app['full_name']); ?></p>
                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['college_name']); ?></p>
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-6">
                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($app['title']); ?></p>
                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($app['duration']); ?> • <?php echo htmlspecialchars($app['mode']); ?></p>
                  </td>
                  <td class="py-4 px-6 text-slate-500 font-medium">
                    <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                  </td>
                  <td class="py-4 px-6">
                    <span class="px-2 py-1 <?php echo ($app['education_status'] === 'Pursuing') ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-purple-50 text-purple-700 border-purple-100'; ?> rounded text-[10px] font-bold border uppercase">
                      <?php echo htmlspecialchars($app['education_status']); ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide border uppercase <?php echo getStatusBadgeClass($app['status']); ?>">
                      <?php echo htmlspecialchars($app['status']); ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <select class="status-update-select w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none" data-app-id="<?php echo $app['app_id']; ?>" data-education="<?php echo htmlspecialchars($app['education_status']); ?>">
                      <option value="">-- Update Status --</option>
                      <option value="Applied">Applied</option>
                      <option value="Test Completed">Test Completed</option>
                      <option value="HR Round">HR Round</option>
                      <?php if ($app['education_status'] === 'Pursuing'): ?>
                      <option value="HOD Approved">HOD Approved</option>
                      <?php endif; ?>
                      <option value="Selected">Selected</option>
                      <option value="Rejected">Rejected</option>
                    </select>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-2">
                      <a href="view_application_status.php?app_id=<?php echo $app['app_id']; ?>" class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" title="View Timeline">
                        <span class="material-symbols-outlined text-[18px]">timeline</span>
                      </a>
                      <button class="p-2 text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors" title="View Details">
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center" id="toast-icon-container">
      <span class="material-symbols-outlined text-[20px]" id="toast-icon">check_circle</span>
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-wider" id="toast-title">Success</p>
      <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-message">Status updated successfully</p>
    </div>
  </div>

  <script>
    // Status update handler
    document.querySelectorAll('.status-update-select').forEach(select => {
      select.addEventListener('change', async function() {
        const appId = this.dataset.appId;
        const newStatus = this.value;
        const education = this.dataset.education;
        
        if (!newStatus) return;
        
        // Confirm action
        if (!confirm(`Update application status to "${newStatus}"?`)) {
          this.value = '';
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('application_id', appId);
          formData.append('new_status', newStatus);
          formData.append('notes', 'Status updated by HR');
          
          const response = await fetch('update_application_status.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Error', result.message);
            this.value = '';
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to update status');
          this.value = '';
        }
      });
    });
    
    function showToast(type, title, message) {
      const toast = document.getElementById('toast');
      const toastIcon = document.getElementById('toast-icon');
      const toastIconContainer = document.getElementById('toast-icon-container');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      
      if (type === 'success') {
        toast.classList.remove('border-red-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600');
        toastTitle.classList.add('text-green-600');
      } else {
        toast.classList.remove('border-green-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 500);
      }, 3000);
    }
  </script>

</body>
</html>
