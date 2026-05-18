<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Hiring Overview | Company Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1d4ed8",
                        secondary: "#64748b",
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
        body { background-color: #f8f9fa; color: #1e293b; }
        .sidebar-link { display: flex; items-center: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #64748b; }
        .sidebar-link:hover { background-color: #f1f5f9; color: #1d4ed8; }
        .sidebar-link.active { background-color: #1d4ed8; color: #ffffff; box-shadow: 0 4px 6px -1px rgb(29 78 216 / 0.1), 0 2px 4px -2px rgb(29 78 216 / 0.1); }
        .stat-card { background: white; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1); }
        .pipeline-step { position: relative; display: flex; flex-direction: column; align-items: center; flex: 1; }
        .pipeline-step::after { content: ''; position: absolute; top: 1.25rem; left: 50%; width: 100%; height: 2px; background-color: #e2e8f0; z-index: 0; }
        .pipeline-step:last-child::after { display: none; }
        .pipeline-dot { position: relative; z-index: 10; width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: white; border: 2px solid #e2e8f0; display: flex; items-center: center; justify-content: center; }
        .pipeline-active .pipeline-dot { border-color: #1d4ed8; color: #1d4ed8; background-color: #eff6ff; }
        .pipeline-completed .pipeline-dot { background-color: #1d4ed8; border-color: #1d4ed8; color: white; }
    </style>
</head>
<body class="min-h-screen flex font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col fixed h-screen z-50">
        <div class="flex items-center gap-3 mb-10 px-2">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                <span class="material-symbols-outlined">recruitment</span>
            </div>
            <div>
                <h1 class="text-xl font-black text-gray-900 tracking-tight leading-none">IMP</h1>
                <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mt-1">Recruitment Hub</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1">
            <a href="company_dashboard.html" class="sidebar-link active">
                <span class="material-symbols-outlined text-xl">dashboard</span>
                Hiring Overview
            </a>
            <a href="browse_talent_pool.html" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">person_search</span>
                Browse Talent Pool
            </a>
            <a href="#" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">verified</span>
                Verified Talent
            </a>
            <a href="#" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">handshake</span>
                Hiring Requests
            </a>
            <a href="#" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">analytics</span>
                Recruitment Stats
            </a>
            <a href="#" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">help</span>
                Support
            </a>
        </nav>

        <div class="mt-auto pt-6 border-t border-gray-100">
            <a href="index.html" class="sidebar-link text-red-600 hover:bg-red-50">
                <span class="material-symbols-outlined">logout</span>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Top Nav -->
            <header class="flex justify-between items-center bg-white p-4 rounded-2xl border border-gray-200 shadow-sm mb-8">
                <div class="flex items-center gap-4 px-2">
                    <span class="material-symbols-outlined text-gray-400">search</span>
                    <input type="text" placeholder="Search talent pool..." class="bg-transparent border-none text-sm focus:ring-0 w-80">
                </div>
                <div class="flex items-center gap-4">
                    <button class="relative p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-all">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                    <div class="h-8 w-px bg-gray-100"></div>
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden md:block">
                            <p class="text-xs font-bold text-gray-900 leading-none">Nexus Tech HR</p>
                            <p class="text-[10px] text-blue-600 font-bold mt-1">Enterprise Recruiter</p>
                        </div>
                        <img src="https://lh3.googleusercontent.com/a/ACg8ocL8C1A5Z1R4E7P2R9T6E6E6E6E6E6E6E6E6E6E6E6E=s96-c" class="w-10 h-10 rounded-full border border-gray-200" alt="Recruiter">
                    </div>
                </div>
            </header>

            <!-- Welcome Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Hiring Overview</h2>
                    <p class="text-gray-500 font-medium mt-1">Track your recruitment progress and certified internship graduates.</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='browse_talent_pool.html'" class="bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">
                        <span class="material-symbols-outlined text-lg">person_search</span> View Talent Pool
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Available Talent</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900">842</h3>
                        <span class="text-blue-600 text-[10px] font-bold bg-blue-50 px-2 py-1 rounded-lg">Certified</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Shortlisted</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900">18</h3>
                        <span class="text-amber-600 text-[10px] font-bold bg-amber-50 px-2 py-1 rounded-lg">Active</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Contacted</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900">12</h3>
                        <span class="text-indigo-600 text-[10px] font-bold bg-indigo-50 px-2 py-1 rounded-lg">Interviews</span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Hired</p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-gray-900">156</h3>
                        <span class="text-green-600 text-[10px] font-bold bg-green-50 px-2 py-1 rounded-lg">2024 Batch</span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Widgets -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Recruitment Pipeline (Left) -->
                <div class="lg:col-span-8 space-y-8">
                    <div class="bg-white p-8 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-bold text-gray-900 mb-10">Recruitment Pipeline</h3>
                        <div class="flex justify-between items-start">
                            <div class="pipeline-step pipeline-completed">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">fact_check</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Shortlisted</p>
                            </div>
                            <div class="pipeline-step pipeline-active">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">mail</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Contacted</p>
                            </div>
                            <div class="pipeline-step">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">forum</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Interview</p>
                            </div>
                            <div class="pipeline-step">
                                <div class="pipeline-dot"><span class="material-symbols-outlined text-lg">handshake</span></div>
                                <p class="text-[10px] font-bold text-gray-900 mt-3 uppercase tracking-tighter">Hired</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Talent Preview -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600">verified_user</span>
                            Recommended Talent Preview
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Preview Card -->
                            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden group">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-14 h-14 rounded-2xl overflow-hidden border border-gray-100">
                                                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuDyL_0hh2u--yxSMZOQdJiOkaGT3j7U9e0FfI0qcTd7sUFNQyL9PArBVQ7I2ledL3LgHA6A3LocxYUxiQnpU1XJgf0fea1nARRVS-7OkkJIIlVWKnpY9c7t3BJITcxADotIwUF2dd6jdeSyYfPwSguP-8AG5Vi5EVOat6m6oVVo_TrEuPAjKhhlI2rXYztnS9djBJuuIbYGiWsD1pPNRvOxTOzHCpqdn77lVCD0GtQWiHUuvw9L-br01BoH4Smzy5Nd3mq4NuVX1Q" alt="Talent" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-base">Alex Rivera</h4>
                                                <div class="flex items-center gap-1 mt-1">
                                                    <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">Certified</span>
                                                    <span class="text-xs font-black text-gray-900 ml-2">94%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-3 mb-6">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internship Project</p>
                                            <p class="text-sm font-bold text-gray-800">NextGen SaaS Dashboard UI/UX</p>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase">React</span>
                                            <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase">Tailwind</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 pt-4 border-t border-gray-50">
                                        <div class="flex gap-2">
                                            <button class="flex-1 bg-gray-900 text-white py-2 rounded-xl text-xs font-bold hover:bg-gray-800 transition-all shadow-md">Shortlist</button>
                                            <button class="flex-1 bg-white border border-gray-200 text-gray-700 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all">Contact Now</button>
                                        </div>
                                        <button class="w-full text-blue-600 py-1 rounded text-[10px] font-bold hover:underline">View Evaluations</button>
                                    </div>
                                </div>
                                <div class="px-6 py-2 bg-blue-50/30 text-[10px] font-bold text-blue-700 border-t border-blue-50">
                                    Mentor Eval: Industry Ready
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden group">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center border border-indigo-100 shadow-sm text-indigo-700 font-black text-lg">JD</div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-base">Jane Doe</h4>
                                                <div class="flex items-center gap-1 mt-1">
                                                    <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">Certified</span>
                                                    <span class="text-xs font-black text-gray-900 ml-2">98%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-3 mb-6">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internship Project</p>
                                            <p class="text-sm font-bold text-gray-800">Predictive NLP for Enterprise</p>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase">Python</span>
                                            <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase">BERT</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 pt-4 border-t border-gray-50">
                                        <div class="flex gap-2">
                                            <button class="flex-1 bg-gray-900 text-white py-2 rounded-xl text-xs font-bold hover:bg-gray-800 transition-all shadow-md">Shortlist</button>
                                            <button class="flex-1 bg-white border border-gray-200 text-gray-700 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all">Contact Now</button>
                                        </div>
                                        <button class="w-full text-blue-600 py-1 rounded text-[10px] font-bold hover:underline">View Evaluations</button>
                                    </div>
                                </div>
                                <div class="px-6 py-2 bg-blue-50/30 text-[10px] font-bold text-blue-700 border-t border-blue-50">
                                    Mentor Eval: Industry Ready
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar Widgets -->
                <div class="lg:col-span-4 space-y-6">
                    
                    <!-- Quick Actions Widget -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Recruitment Actions</h3>
                        <div class="space-y-2">
                            <button onclick="window.location.href='browse_talent_pool.html'" class="w-full text-left p-3 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-3 group border border-transparent hover:border-blue-100">
                                <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-600">person_search</span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900">Browse Talent Pool</p>
                                    <p class="text-[10px] text-gray-500">View 800+ certified interns</p>
                                </div>
                            </button>
                            <button class="w-full text-left p-3 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-3 group border border-transparent hover:border-blue-100">
                                <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-600">fact_check</span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900">Shortlisted Candidates</p>
                                    <p class="text-[10px] text-gray-500">18 profiles awaiting action</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Top Skills Widget -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Top Skills Available</h3>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-bold text-gray-700">
                                    <span>Web Development</span>
                                    <span>420</span>
                                </div>
                                <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-blue-600 h-full w-[85%] rounded-full"></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-bold text-gray-700">
                                    <span>AI / Machine Learning</span>
                                    <span>280</span>
                                </div>
                                <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-indigo-600 h-full w-[65%] rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Certifications -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">New Certifications</h3>
                            <span class="w-2 h-2 bg-red-500 rounded-full animate-ping"></span>
                        </div>
                        <div class="space-y-4">
                            <div class="flex gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-900 leading-tight">Sarah Chen <span class="font-medium text-gray-500">just earned certification in</span> UI/UX</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">5 mins ago</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- Footer -->
        <footer class="max-w-7xl mx-auto mt-16 pt-8 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400 font-medium tracking-tight">© 2024 InternshipHub Enterprise Portal. Talent verified via Internship Management Platform.</p>
        </footer>
    </main>

</body>
</html>
