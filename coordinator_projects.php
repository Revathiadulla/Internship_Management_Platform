<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Project Management - Coordinator</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #191c1d; }
                .material-symbols-outlined {
                        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                        vertical-align: middle;
                }
                aside {
                        transition: transform 0.3s ease-in-out;
                }
                main {
                        transition: margin-left 0.3s ease-in-out;
                        min-width: 0;
                        overflow-x: hidden;
                }
                @media (max-width: 767px) {
                        aside {
                                transform: translateX(-100%);
                        }
                        main {
                                margin-left: 0 !important;
                        }
                        body.sidebar-open aside {
                                transform: translateX(0);
                        }
                }
                @media (min-width: 768px) {
                        body.sidebar-closed aside {
                                transform: translateX(-100%);
                        }
                        body.sidebar-closed main {
                                margin-left: 0 !important;
                        }
                }
        </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- SideNavBar -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
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
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-1">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_dashboard.php">
                                <span class="material-symbols-outlined">dashboard</span>
                                <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_internships.php">
                                <span class="material-symbols-outlined">work</span>
                                <span>Postings</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_candidates.php">
                                <span class="material-symbols-outlined">group</span>
                                <span>Candidates</span>
                        </a>

                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_daily_logs.php">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span>Daily Logs Monitoring</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_reports.php">
                                <span class="material-symbols-outlined">analytics</span>
                                <span>Reports</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_teams.php">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <span>Team Management</span>
                        </a>
                </nav>
                <div class="mt-auto border-t border-gray-200 pt-4">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_help_center.php">
                                <span class="material-symbols-outlined">help</span>
                                <span>Help Center</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="logout.php">
                                <span class="material-symbols-outlined">logout</span>
                                <span>Logout</span>
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <?php
                $header_uid = $_SESSION['user_id'];
                $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
                $header_user = mysqli_fetch_assoc($header_res);
                $header_name = $header_user['full_name'] ?? 'Coordinator';
                $header_photo = $header_user['profile_photo'] ?? '';
                ?>
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Project Management</h2>
                        </div>
                        
                        <!-- Profile Dropdown Section -->
                        <div class="relative" id="profile-container">
                                <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                                        <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block">
                                                <?php echo htmlspecialchars($header_name); ?>
                                        </span>
                                        <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-500 transition-colors">
                                                <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                                                        <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=0D8ABC&color=fff" alt="Profile" class="w-full h-full object-cover">
                                                <?php endif; ?>
                                        </div>
                                        <span class="material-symbols-outlined text-gray-500 text-[18px] group-hover:text-blue-600 transition-colors">arrow_drop_down</span>
                                </button>
                                
                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                                        <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                <span>My Profile</span>
                                        </a>
                                        <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                <span>Settings</span>
                                        </a>
                                        <hr class="my-1 border-gray-100">
                                        <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                <span>Logout</span>
                                        </a>
                                </div>
                        </div>
                </header>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                        const profileBtn = document.getElementById('profile-menu-button');
                        const profileDropdown = document.getElementById('profile-dropdown');
                        
                        if (profileBtn && profileDropdown) {
                                profileBtn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        profileDropdown.classList.toggle('hidden');
                                });
                                
                                document.addEventListener('click', function(e) {
                                        if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                                                profileDropdown.classList.add('hidden');
                                        }
                                });
                        }
                });
                </script>


                <div class="flex-1 p-8 space-y-6">
                                <div class="bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center mt-12">
                                        <span class="material-symbols-outlined text-6xl text-gray-300 mb-4">assignment</span>
                                        <h3 class="text-xl font-bold text-gray-800 mb-2">Project Management Module</h3>
                                        <p class="text-gray-500">This module is currently integrated into the main Dashboard and Postings consoles.</p>
                                        <p class="text-sm text-gray-400 mt-2">Use the <a href="coordinator_dashboard.php" class="text-blue-600 hover:underline font-bold">Dashboard</a> or <a href="coordinator_internships.php" class="text-blue-600 hover:underline font-bold">Postings</a> page to manage projects.</p>
                                </div>
                        </div>
                </main>
                <script>
                        // Sidebar Toggle Handler
                        const toggleBtn = document.getElementById('sidebar-toggle');
                        if (toggleBtn) {
                            toggleBtn.addEventListener('click', () => {
                                if (window.innerWidth < 768) {
                                    document.body.classList.toggle('sidebar-open');
                                    document.body.classList.remove('sidebar-closed');
                                } else {
                                    document.body.classList.toggle('sidebar-closed');
                                    document.body.classList.remove('sidebar-open');
                                }
                            });
                        }
                </script>
</body>
</html>
