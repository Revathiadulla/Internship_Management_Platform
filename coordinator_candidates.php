<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Candidates - Coordinator</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                body { font-family: 'Inter', sans-serif; }
                .material-symbols-outlined {
                        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                        vertical-align: middle;
                }
        </style>
</head>
<body class="bg-[#f8f9fa] text-gray-800">

        <!-- SideNavBar -->
        <aside class="fixed left-0 top-0 h-screen w-64 z-50 bg-white border-r border-gray-200 flex flex-col py-6 font-sans shadow-sm">
                <div class="px-6 mb-8">
                        <h1 class="text-xl font-bold tracking-tight text-blue-600 flex items-center gap-2"><span class="material-symbols-outlined">admin_panel_settings</span> Coordinator</h1>
                        <p class="text-xs text-gray-500 font-medium mt-1 uppercase tracking-wider">Management Console</p>
                </div>
                <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_dashboard.php">
                                <span class="material-symbols-outlined">dashboard</span>
                                <span class="text-sm font-medium">Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_internships.php">
                                <span class="material-symbols-outlined">school</span>
                                <span class="text-sm font-medium">Internship Mgt</span>
                        </a>
                        <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium transition-all shadow-sm"
                                href="coordinator_candidates.php">
                                <span class="material-symbols-outlined">groups</span>
                                <span class="text-sm font-medium">Candidates</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_projects.php">
                                <span class="material-symbols-outlined">assignment</span>
                                <span class="text-sm font-medium">Project Mgt</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_daily_logs.html">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span class="text-sm font-medium">Daily Logs</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_reports.php">
                                <span class="material-symbols-outlined">analytics</span>
                                <span class="text-sm font-medium">Reports</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="coordinator_teams.php">
                                <span class="material-symbols-outlined">diversity_3</span>
                                <span class="text-sm font-medium">Team Mgt</span>
                        </a>
                </nav>
                <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all"
                                href="#">
                                <span class="material-symbols-outlined">help</span>
                                <span class="text-sm font-medium">Help Center</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all"
                                href="index.html">
                                <span class="material-symbols-outlined">logout</span>
                                <span class="text-sm font-medium">Logout</span>
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-64 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-gray-800">Candidates</h2>
                        </div>
                </header>

                <div class="flex-1 p-8">
                        <div class="bg-white p-12 rounded-xl shadow-sm border border-gray-100 text-center mt-12">
                                <span class="material-symbols-outlined text-6xl text-gray-300 mb-4">groups</span>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Candidates Module</h3>
                                <p class="text-gray-500">This module is currently under development.</p>
                                <p class="text-sm text-gray-400 mt-2">Candidate list, profiles, and status tracking will be here.</p>
                        </div>
                </div>
        </main>
</body>
</html>
