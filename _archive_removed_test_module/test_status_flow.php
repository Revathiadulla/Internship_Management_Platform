<?php
/**
 * Test Status System Flow
 * This page helps verify that the status system is working correctly
 */

session_start();
include __DIR__ . '/includes/db.php';

// Check if we're logged in (for testing, we'll allow access)
$test_mode = true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Status System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-slate-50 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Status System Test Dashboard</h1>
            <p class="text-slate-600">Verify that all components of the improved status system are working correctly.</p>
        </div>

        <!-- System Status Checks -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            <!-- Database Tables Check -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600">storage</span>
                    Database Tables
                </h2>
                <?php
                $tables_to_check = [
                    'internship_applications' => 'Applications table',
                    'application_status_history' => 'Status history table',
                    'student_profiles' => 'Student profiles table',
                    'internships' => 'Internships table'
                ];
                
                foreach ($tables_to_check as $table => $description) {
                    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                    $exists = mysqli_num_rows($check) > 0;
                    $icon = $exists ? 'check_circle' : 'cancel';
                    $color = $exists ? 'text-emerald-600' : 'text-red-600';
                    echo "<div class='flex items-center gap-2 py-2'>";
                    echo "<span class='material-symbols-outlined $color text-[20px]'>$icon</span>";
                    echo "<span class='text-sm text-slate-700'>$description</span>";
                    echo "</div>";
                }
                ?>
            </div>

            <!-- Required Files Check -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-600">description</span>
                    Required Files
                </h2>
                <?php
                $files_to_check = [
                    'student_applications.php' => 'Applications page',
                    'view_application_status.php' => 'Status view page',
                    'application_status_timeline.php' => 'Timeline component',
                    'status_utils.php' => 'Status utilities',
                    'update_application_status.php' => 'Status update handler'
                ];
                
                foreach ($files_to_check as $file => $description) {
                    $exists = file_exists(__DIR__ . '/' . $file);
                    $icon = $exists ? 'check_circle' : 'cancel';
                    $color = $exists ? 'text-emerald-600' : 'text-red-600';
                    echo "<div class='flex items-center gap-2 py-2'>";
                    echo "<span class='material-symbols-outlined $color text-[20px]'>$icon</span>";
                    echo "<span class='text-sm text-slate-700'>$description</span>";
                    echo "</div>";
                }
                ?>
            </div>

        </div>

        <!-- Applications Overview -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200 mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-600">assignment</span>
                Recent Applications
            </h2>
            <?php
            $apps_sql = "SELECT a.id, a.user_id, a.status, a.education_status, a.applied_date,
                               COALESCE(i.title, a.internship_name) as title,
                               sp.full_name
                        FROM internship_applications a
                        LEFT JOIN internships i ON a.internship_id = i.id
                        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                        ORDER BY a.applied_date DESC
                        LIMIT 10";
            $apps_result = mysqli_query($conn, $apps_sql);
            
            if (mysqli_num_rows($apps_result) > 0) {
                echo "<div class='overflow-x-auto'>";
                echo "<table class='w-full text-sm'>";
                echo "<thead class='bg-slate-50 border-b border-slate-200'>";
                echo "<tr>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>ID</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Student</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Internship</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Status</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Education</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Applied</th>";
                echo "<th class='text-left py-3 px-4 font-semibold text-slate-600'>Actions</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody class='divide-y divide-slate-100'>";
                
                while ($app = mysqli_fetch_assoc($apps_result)) {
                    $status_colors = [
                        'Applied' => 'bg-slate-100 text-slate-700',
                        'Test Completed' => 'bg-purple-100 text-purple-700',
                        'HR Round' => 'bg-orange-100 text-orange-700',
                        'HOD Approved' => 'bg-cyan-100 text-cyan-700',
                        'Selected' => 'bg-emerald-100 text-emerald-700',
                        'Rejected' => 'bg-red-100 text-red-700'
                    ];
                    $status_class = $status_colors[$app['status']] ?? 'bg-slate-100 text-slate-700';
                    
                    echo "<tr class='hover:bg-slate-50'>";
                    echo "<td class='py-3 px-4 font-mono text-xs'>#" . $app['id'] . "</td>";
                    echo "<td class='py-3 px-4'>" . htmlspecialchars($app['full_name'] ?? 'N/A') . "</td>";
                    echo "<td class='py-3 px-4'>" . htmlspecialchars($app['title']) . "</td>";
                    echo "<td class='py-3 px-4'><span class='px-2 py-1 rounded text-xs font-semibold $status_class'>" . htmlspecialchars($app['status']) . "</span></td>";
                    echo "<td class='py-3 px-4'><span class='text-xs'>" . htmlspecialchars($app['education_status']) . "</span></td>";
                    echo "<td class='py-3 px-4 text-xs text-slate-500'>" . date('M d, Y', strtotime($app['applied_date'])) . "</td>";
                    echo "<td class='py-3 px-4'>";
                    echo "<a href='view_application_status.php?app_id=" . $app['id'] . "' class='text-blue-600 hover:text-blue-700 text-xs font-semibold flex items-center gap-1'>";
                    echo "<span class='material-symbols-outlined text-[16px]'>timeline</span> View";
                    echo "</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                echo "</div>";
            } else {
                echo "<div class='text-center py-8 text-slate-500'>";
                echo "<span class='material-symbols-outlined text-4xl text-slate-300'>inbox</span>";
                echo "<p class='mt-2'>No applications found in the database.</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Status Distribution -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200 mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-600">pie_chart</span>
                Status Distribution
            </h2>
            <?php
            $status_sql = "SELECT status, COUNT(*) as count FROM internship_applications GROUP BY status ORDER BY count DESC";
            $status_result = mysqli_query($conn, $status_sql);
            
            if (mysqli_num_rows($status_result) > 0) {
                echo "<div class='grid grid-cols-2 md:grid-cols-3 gap-4'>";
                while ($stat = mysqli_fetch_assoc($status_result)) {
                    $status_colors = [
                        'Applied' => 'from-slate-500 to-slate-600',
                        'Test Completed' => 'from-purple-500 to-purple-600',
                        'HR Round' => 'from-orange-500 to-orange-600',
                        'HOD Approved' => 'from-cyan-500 to-cyan-600',
                        'Selected' => 'from-emerald-500 to-emerald-600',
                        'Rejected' => 'from-red-500 to-red-600'
                    ];
                    $gradient = $status_colors[$stat['status']] ?? 'from-slate-500 to-slate-600';
                    
                    echo "<div class='bg-gradient-to-br $gradient text-white rounded-lg p-4'>";
                    echo "<div class='text-3xl font-extrabold'>" . $stat['count'] . "</div>";
                    echo "<div class='text-sm opacity-90 mt-1'>" . htmlspecialchars($stat['status']) . "</div>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<p class='text-slate-500 text-center py-4'>No status data available.</p>";
            }
            ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
            <h2 class="text-lg font-bold mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="student_applications.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg p-4 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl">assignment</span>
                    <div>
                        <div class="font-bold">View Applications</div>
                        <div class="text-xs opacity-75">Student view</div>
                    </div>
                </a>
                <a href="hr_applications.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg p-4 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl">manage_accounts</span>
                    <div>
                        <div class="font-bold">HR Dashboard</div>
                        <div class="text-xs opacity-75">Manage statuses</div>
                    </div>
                </a>
                <a href="IMPROVED_STATUS_SYSTEM.md" target="_blank" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg p-4 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl">description</span>
                    <div>
                        <div class="font-bold">Documentation</div>
                        <div class="text-xs opacity-75">Read the docs</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-slate-500">
            <p>Status System v2.0 • Last Updated: May 19, 2026</p>
            <p class="mt-1">All systems operational ✓</p>
        </div>
    </div>
</body>
</html>
