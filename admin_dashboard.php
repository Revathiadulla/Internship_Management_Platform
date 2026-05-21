<!DOCTYPE html><html class="light" lang="en"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script id="tailwind-config">
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: "#1d4ed8",
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
</style>
</head>
<body class="min-h-screen flex flex-col font-sans">
  <!-- Top Nav -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
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
    <div class="flex items-center gap-4 text-sm font-medium">
      <div class="flex items-center gap-2 text-gray-600">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        System Online
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col gap-8 hidden md:flex overflow-y-auto">
      <div>
        <h2 class="text-[10px] font-bold text-gray-500 tracking-widest mb-4 uppercase">Main Menu</h2>
        <nav class="flex flex-col gap-1">
          <a href="#" class="flex items-center gap-3 bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium">
            <span class="material-symbols-outlined text-xl">dashboard</span>
            Dashboard
          </a>
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">group</span>
            Users
          </a>
          <a href="daily_logs_monitoring.html" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">work</span>
            Internship Oversight
          </a>
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">account_tree</span>
            Workflows
          </a>
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">person_search</span>
            Talent Pool
          </a>
          <a href="daily_logs_monitoring.html" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">bar_chart</span>
            Reports
          </a>
        </nav>
      </div>
      <div>
        <h2 class="text-[10px] font-bold text-gray-500 tracking-widest mb-4 uppercase">System</h2>
        <nav class="flex flex-col gap-1">
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">security</span>
            Security
          </a>
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">settings</span>
            Settings
          </a>
          <a href="#" class="flex items-center gap-3 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 text-sm font-medium">
            <span class="material-symbols-outlined text-xl">help</span>
            Help Center
          </a>
          <a href="index.html" class="flex items-center gap-3 text-red-600 px-4 py-2.5 rounded-lg hover:bg-red-50 text-sm font-medium mt-4">
            <span class="material-symbols-outlined text-xl">logout</span>
            Logout
          </a>
        </nav>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-500 text-sm mt-1">Complete system control and platform monitoring</p>
          </div>
          <div class="flex gap-3">
            <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:bg-gray-50 hover:shadow-md transition-all shadow-sm">
              <span class="material-symbols-outlined text-lg">person_add</span> Add Staff User
            </button>
            <button class="bg-[#003ea8] text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:bg-blue-800 hover:shadow-md transition-all shadow-sm">
              <span class="material-symbols-outlined text-lg">settings</span> Platform Controls
            </button>
          </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-default">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-blue-50 p-2.5 rounded-full text-blue-600">
                <span class="material-symbols-outlined">monitoring</span>
              </div>
              <span class="text-green-600 text-xs font-bold bg-green-50 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Internship Oversight</h3>
            <p class="text-2xl font-bold text-gray-900 mt-1">842</p>
            <button onclick="window.location.href='daily_logs_monitoring.html'" class="w-full mt-3 bg-blue-50 text-blue-600 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-blue-100 transition-colors flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-sm">visibility</span> View Daily Logs
            </button>
          </div>
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-default">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-indigo-50 p-2.5 rounded-full text-indigo-600">
                <span class="material-symbols-outlined">person_search</span>
              </div>
              <span class="text-blue-600 text-xs font-bold bg-blue-50 px-2 py-0.5 rounded-full">+12%</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Talent Pool Monitoring</h3>
            <p class="text-2xl font-bold text-gray-900 mt-1">3,420</p>
          </div>
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-default">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-orange-50 p-2.5 rounded-full text-orange-600">
                <span class="material-symbols-outlined">pending_actions</span>
              </div>
              <span class="text-orange-600 text-xs font-bold bg-orange-50 px-2 py-0.5 rounded-full">Awaiting</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">Pending Reviews</h3>
            <p class="text-2xl font-bold text-gray-900 mt-1">42</p>
          </div>
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-default">
            <div class="flex justify-between items-start mb-3">
              <div class="bg-purple-50 p-2.5 rounded-full text-purple-600">
                <span class="material-symbols-outlined">history_edu</span>
              </div>
              <span class="text-purple-600 text-xs font-bold bg-purple-50 px-2 py-0.5 rounded-full">Steady</span>
            </div>
            <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wide">System Activity Records</h3>
            <p class="text-2xl font-bold text-gray-900 mt-1">12.5k logs</p>
          </div>
        </div>

        <!-- Unified Workflow Management -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
          <h2 class="text-lg font-bold text-gray-900 mb-6">Unified Workflow Management</h2>
          <div class="flex justify-between items-center w-full px-2 text-center">
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">1</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Registration</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">2</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Verification</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">3</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Assessment</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">4</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Assignment</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">5</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Daily Logs</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">6</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Mentor Eval</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">7</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">HR Review</span>
            </div>
            <div class="h-0.5 bg-[#003ea8] flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-[#003ea8] text-white flex items-center justify-center font-bold text-xs mb-1 shadow-sm">8</div>
              <span class="text-[8px] text-[#003ea8] font-bold uppercase tracking-tight">Certification</span>
            </div>
            <div class="h-0.5 bg-gray-200 flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center font-bold text-xs mb-1 border border-gray-200">9</div>
              <span class="text-[8px] text-gray-400 font-bold uppercase tracking-tight">Talent Pool</span>
            </div>
            <div class="h-0.5 bg-gray-200 flex-1 mx-0.5"></div>
            <div class="flex flex-col items-center flex-1">
              <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center font-bold text-xs mb-1 border border-gray-200">10</div>
              <span class="text-[8px] text-gray-400 font-bold uppercase tracking-tight">Hiring</span>
            </div>
          </div>
        </div>

        <!-- Grid layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- User Management (Col span 2) -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2 hover:shadow-md transition-shadow">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center">
              <h2 class="text-lg font-bold text-gray-900">User Management</h2>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                <input type="text" placeholder="Search users..." class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#003ea8] focus:border-[#003ea8] outline-none w-64 bg-gray-50">
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                  <tr>
                    <th class="px-6 py-4">Name</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Email</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs border border-blue-100">JD</div>
                      <p class="font-medium text-gray-900">Jane Doe</p>
                    </td>
                    <td class="px-6 py-4"><span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider">Student</span></td>
                    <td class="px-6 py-4 text-gray-500">jane.doe@example.com</td>
                    <td class="px-6 py-4"><span class="flex items-center gap-1.5 text-green-600 font-medium text-xs"><div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Active</span></td>
                    <td class="px-6 py-4 text-right"><button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-sm">more_vert</span></button></td>
                  </tr>
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs border border-indigo-100">AK</div>
                      <p class="font-medium text-gray-900">Alex Kumar</p>
                    </td>
                    <td class="px-6 py-4"><span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider">Coordinator</span></td>
                    <td class="px-6 py-4 text-gray-500">a.kumar@university.edu</td>
                    <td class="px-6 py-4"><span class="flex items-center gap-1.5 text-green-600 font-medium text-xs"><div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Active</span></td>
                    <td class="px-6 py-4 text-right"><button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-sm">more_vert</span></button></td>
                  </tr>
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-xs border border-gray-200">NT</div>
                      <p class="font-medium text-gray-900">Nexus Tech HR</p>
                    </td>
                    <td class="px-6 py-4"><span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider">Company</span></td>
                    <td class="px-6 py-4 text-gray-500">recruiting@nexustech.io</td>
                    <td class="px-6 py-4"><span class="flex items-center gap-1.5 text-green-600 font-medium text-xs"><div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Verified</span></td>
                    <td class="px-6 py-4 text-right"><button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-sm">more_vert</span></button></td>
                  </tr>
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-orange-50 text-orange-700 flex items-center justify-center font-bold text-xs border border-orange-100">MR</div>
                      <p class="font-medium text-gray-900">Marcus Reed</p>
                    </td>
                    <td class="px-6 py-4"><span class="bg-orange-50 text-orange-700 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider">Mentor</span></td>
                    <td class="px-6 py-4 text-gray-500">m.reed@corp.com</td>
                    <td class="px-6 py-4"><span class="flex items-center gap-1.5 text-green-600 font-medium text-xs"><div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Active</span></td>
                    <td class="px-6 py-4 text-right"><button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-sm">more_vert</span></button></td>
                  </tr>
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-700 flex items-center justify-center font-bold text-xs border border-purple-100">SA</div>
                      <p class="font-medium text-gray-900">Sarah Adams</p>
                    </td>
                    <td class="px-6 py-4"><span class="bg-purple-50 text-purple-700 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider">HR</span></td>
                    <td class="px-6 py-4 text-gray-500">s.adams@corp.com</td>
                    <td class="px-6 py-4"><span class="flex items-center gap-1.5 text-green-600 font-medium text-xs"><div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Active</span></td>
                    <td class="px-6 py-4 text-right"><button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-sm">more_vert</span></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- System Activity -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col hover:shadow-md transition-shadow">
            <div class="p-5 border-b border-gray-100">
              <h2 class="text-lg font-bold text-gray-900">System Activity</h2>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-6">
              <div class="flex gap-4">
                <div class="bg-green-50 p-2 rounded-full text-green-600 h-fit border border-green-100">
                  <span class="material-symbols-outlined text-sm">person_add</span>
                </div>
                <div>
                  <p class="text-sm text-gray-900 font-medium">New student registered</p>
                  <p class="text-xs text-gray-500 mt-0.5">2 minutes ago</p>
                </div>
              </div>
              <div class="flex gap-4">
                <div class="bg-blue-50 p-2 rounded-full text-blue-600 h-fit border border-blue-100">
                  <span class="material-symbols-outlined text-sm">assignment</span>
                </div>
                <div>
                  <p class="text-sm text-gray-900 font-medium">Mentor submitted evaluation</p>
                  <p class="text-xs text-gray-500 mt-0.5">15 minutes ago</p>
                </div>
              </div>
              <div class="flex gap-4">
                <div class="bg-purple-50 p-2 rounded-full text-purple-600 h-fit border border-purple-100">
                  <span class="material-symbols-outlined text-sm">verified</span>
                </div>
                <div>
                  <p class="text-sm text-gray-900 font-medium">HR approved candidate</p>
                  <p class="text-xs text-gray-500 mt-0.5">1 hour ago</p>
                </div>
              </div>
              <div class="flex gap-4">
                <div class="bg-yellow-50 p-2 rounded-full text-yellow-600 h-fit border border-yellow-100">
                  <span class="material-symbols-outlined text-sm">workspace_premium</span>
                </div>
                <div>
                  <p class="text-sm text-gray-900 font-medium">Certificate generated</p>
                  <p class="text-xs text-gray-500 mt-0.5">3 hours ago</p>
                </div>
              </div>
            </div>
            <div class="p-4 border-t border-gray-100 flex flex-col gap-2 mt-auto">
              <button onclick="window.location.href='daily_logs_monitoring.html'" class="w-full bg-blue-50 text-blue-600 py-2 rounded-lg text-xs font-bold hover:bg-blue-100 transition-colors">
                View Daily Logs Report
              </button>
              <a href="#" class="text-[#003ea8] text-xs font-medium hover:underline text-center">View All Activity</a>
            </div>
          </div>
          
          <!-- Role Permissions Overview -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-6">
              <div class="bg-blue-50 p-2 rounded-lg text-blue-700">
                <span class="material-symbols-outlined text-xl">admin_panel_settings</span>
              </div>
              <h2 class="text-lg font-bold text-gray-900 leading-tight">Role Permissions<br>Overview</h2>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="font-bold text-gray-900 text-sm">Admin</span>
                <span class="bg-gray-50 text-gray-600 text-xs px-2.5 py-1 rounded font-medium border border-gray-100">Full System Control</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="font-bold text-gray-900 text-sm">Coordinator</span>
                <span class="bg-gray-50 text-gray-600 text-xs px-2.5 py-1 rounded font-medium border border-gray-100">Internship Operations</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="font-bold text-gray-900 text-sm">Mentor</span>
                <span class="bg-gray-50 text-gray-600 text-xs px-2.5 py-1 rounded font-medium border border-gray-100">Evaluation & Feedback</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="font-bold text-gray-900 text-sm">HR</span>
                <span class="bg-gray-50 text-gray-600 text-xs px-2.5 py-1 rounded font-medium border border-gray-100">Approval & Talent Pool</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="font-bold text-gray-900 text-sm">Company</span>
                <span class="bg-gray-50 text-gray-600 text-xs px-2.5 py-1 rounded font-medium border border-gray-100">Hiring Access</span>
              </div>
            </div>
          </div>

          <!-- Talent Pool Candidates (Blue Card) -->
          <div class="bg-[#003ea8] rounded-xl p-6 text-white flex flex-col justify-end relative overflow-hidden shadow-sm hover:shadow-lg hover:bg-blue-800 transition-all">
            <span class="material-symbols-outlined absolute right-4 bottom-4 text-blue-500 opacity-30 text-[80px]">badge</span>
            <div class="relative z-10 pt-16">
              <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-2">Talent Pool Candidates</p>
              <h3 class="text-4xl font-bold mb-1">1,240</h3>
              <p class="text-sm text-blue-100">Ready for placement</p>
            </div>
          </div>

          <!-- Successful Placements -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-end relative overflow-hidden hover:shadow-md transition-shadow">
            <span class="material-symbols-outlined absolute right-4 bottom-4 text-gray-100 text-[80px]">handshake</span>
            <div class="relative z-10 pt-16">
              <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-2">Successful Placements</p>
              <h3 class="text-4xl font-bold text-gray-900 mb-1">428</h3>
              <p class="text-sm text-green-600 font-medium">+ 16% conversion</p>
            </div>
          </div>

          <!-- Internship Batches (Col Span 2) -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 lg:col-span-2 hover:shadow-md transition-shadow">
            <div class="flex justify-between items-center mb-5">
              <h2 class="text-lg font-bold text-gray-900">Internship Batches</h2>
              <a href="#" class="text-[#003ea8] text-sm font-medium hover:underline">View History</a>
            </div>
            
            <div class="space-y-4">
              <!-- Batch 1 -->
              <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h3 class="text-gray-900 font-medium text-sm">Summer AI Research Batch '24</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Coordinator: Prof. Alan Turing</p>
                  </div>
                  <span class="bg-white border border-gray-200 px-2.5 py-1 rounded text-xs font-bold text-gray-700">120 Interns</span>
                </div>
                <div>
                  <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                    <div class="bg-[#003ea8] h-1.5 rounded-full" style="width: 75%"></div>
                  </div>
                  <div class="flex justify-between text-[11px] text-gray-500 font-medium">
                    <span>Batch Progress: 75%</span>
                    <span>Duration: 6 Months</span>
                  </div>
                </div>
              </div>

              <!-- Batch 2 -->
              <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h3 class="text-gray-900 font-medium text-sm">Frontend Engineering Intensive</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Coordinator: Sheryl Sandberg</p>
                  </div>
                  <span class="bg-white border border-gray-200 px-2.5 py-1 rounded text-xs font-bold text-gray-700">45 Interns</span>
                </div>
                <div>
                  <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                    <div class="bg-[#003ea8] h-1.5 rounded-full" style="width: 40%"></div>
                  </div>
                  <div class="flex justify-between text-[11px] text-gray-500 font-medium">
                    <span>Batch Progress: 40%</span>
                    <span>Duration: 3 Months</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Security & Compliance -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-6">
              <div class="bg-red-50 p-2 rounded-lg text-red-600">
                <span class="material-symbols-outlined text-xl">security</span>
              </div>
              <h2 class="text-lg font-bold text-gray-900">System Activity Monitoring</h2>
            </div>
            
            <div class="space-y-4 flex-1">
              <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                <span class="text-sm text-gray-600">Aadhaar Verifications</span>
                <span class="font-bold text-gray-900 text-sm">98.2%</span>
              </div>
              <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                <span class="text-sm text-gray-600">RBAC Health</span>
                <span class="font-bold text-green-600 text-sm">Compliant</span>
              </div>
              <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                <span class="text-sm text-gray-600">Missing Reports</span>
                <span class="font-bold text-red-600 text-sm">18 Missing</span>
              </div>
              <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                <span class="text-sm text-gray-600">Inactive Accounts</span>
                <span class="font-bold text-orange-600 text-sm">34 Inactive</span>
              </div>
              <div class="flex justify-between items-center pb-1">
                <span class="text-sm text-gray-600">Active Sessions</span>
                <span class="font-bold text-gray-900 text-sm">432</span>
              </div>
            </div>
            
            <button onclick="window.location.href='daily_logs_monitoring.html'" class="w-full mt-5 bg-red-50 text-red-600 text-sm font-medium py-2.5 rounded-lg hover:bg-red-100 transition-colors">
              View Reports
            </button>
          </div>

          <!-- Hiring Success Rate -->
          <div class="bg-[#003ea8] rounded-xl p-6 text-white flex flex-col justify-center relative overflow-hidden shadow-sm lg:col-span-3">
             <span class="material-symbols-outlined absolute right-8 top-1/2 -translate-y-1/2 text-blue-500 opacity-40 text-[100px]">trending_up</span>
             <div class="relative z-10 py-2">
               <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-1.5">Hiring Success Rate</p>
               <h3 class="text-4xl font-bold">68.4%</h3>
               <p class="text-sm text-blue-100 mt-1.5 font-medium">+4.2% from last month</p>
             </div>
          </div>

        </div>
        
      </div>
      
      <!-- Footer -->
      <footer class="max-w-6xl mx-auto mt-12 pt-6 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400 gap-4 mb-8">
        <p>© 2024 InternshipHub Enterprise Portal. All rights reserved.</p>
        <div class="flex gap-6 font-medium">
          <span class="text-gray-300">Internal Management System v2.4.0</span>
        </div>
      </footer>
    </main>
  </div>
</body>
</html>
