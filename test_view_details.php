<?php
/**
 * Test View Details Functionality
 * This page tests if the "View Details" button works correctly
 */

session_start();
include "db.php";

// For testing, we'll allow access without login
$test_mode = true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test View Details</title>
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
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Test "View Details" Functionality</h1>
            <p class="text-slate-600">Click on any "View Details" link below to test if it works correctly.</p>
        </div>

        <?php
        // Fetch all applications
        $apps_sql = "SELECT a.id, a.user_id, a.status, a.education_status, a.applied_date,
                           COALESCE(i.title, a.internship_name) as title,
                           sp.full_name
                    FROM internship_applications a
                    LEFT JOIN internships i ON a.internship_id = i.id
                    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                    ORDER BY a.applied_date DESC
                    LIMIT 10";
        $apps_result = mysqli_query($conn, $apps_sql);
        
        if (mysqli_num_rows($apps_result) > 0):
        ?>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 text-white">
                <h2 class="text-lg font-bold">Recent Applications</h2>
                <p class="text-sm text-blue-100">Click "View Details" to test the functionality</p>
            </div>
            
            <div class="divide-y divide-slate-100">
                <?php while ($app = mysqli_fetch_assoc($apps_result)): 
                    $status_colors = [
                        'Applied' => 'bg-slate-100 text-slate-700',
                        'Test Completed' => 'bg-purple-100 text-purple-700',
                        'HR Round' => 'bg-orange-100 text-orange-700',
                        'HOD Approved' => 'bg-cyan-100 text-cyan-700',
                        'Selected' => 'bg-emerald-100 text-emerald-700',
                        'Rejected' => 'bg-red-100 text-red-700'
                    ];
                    $status_class = $status_colors[$app['status']] ?? 'bg-slate-100 text-slate-700';
                ?>
                <div class="p-6 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-md">
                                    <?php echo strtoupper(substr($app['title'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-slate-500">
                                        <span>Student: <?php echo htmlspecialchars($app['full_name'] ?? 'N/A'); ?></span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span>Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span><?php echo htmlspecialchars($app['education_status']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <span class="px-3 py-1.5 rounded-lg text-xs font-bold <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($app['status']); ?>
                            </span>
                            
                            <a href="view_application_status.php?app_id=<?php echo $app['id']; ?>" 
                               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm"
                               target="_blank">
                                <span class="material-symbols-outlined text-[16px]">timeline</span>
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
            <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">info</span>
                Testing Instructions
            </h3>
            <ol class="space-y-2 text-sm text-blue-800">
                <li class="flex items-start gap-2">
                    <span class="font-bold">1.</span>
                    <span>Click any "View Details" button above (opens in new tab)</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">2.</span>
                    <span>The page should load without errors</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">3.</span>
                    <span>You should see a gradient header with the current status</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">4.</span>
                    <span>A vertical timeline should display with colored circles</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">5.</span>
                    <span>Application details should show at the bottom</span>
                </li>
            </ol>
        </div>

        <div class="mt-6 bg-emerald-50 border border-emerald-200 rounded-xl p-6">
            <h3 class="font-bold text-emerald-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">check_circle</span>
                Expected Result
            </h3>
            <div class="space-y-2 text-sm text-emerald-800">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>No SQL errors or fatal errors</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Page loads in under 1 second</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Timeline shows correct workflow (Pursuing = 5 stages, Passed Out = 4 stages)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Current stage is highlighted with pulsing animation</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Completed stages show checkmarks</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>History section shows all status changes (if any)</span>
                </div>
            </div>
        </div>

        <?php else: ?>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
            <span class="material-symbols-outlined text-6xl text-slate-300">inbox</span>
            <h3 class="text-xl font-bold text-slate-800 mt-4 mb-2">No Applications Found</h3>
            <p class="text-slate-600 mb-6">There are no applications in the database to test with.</p>
            
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 max-w-md mx-auto text-left">
                <h4 class="font-bold text-amber-900 mb-2">Create Test Data:</h4>
                <p class="text-sm text-amber-800 mb-3">Run this SQL query to create a test application:</p>
                <pre class="bg-amber-100 p-3 rounded text-xs overflow-x-auto"><code>INSERT INTO internship_applications 
(user_id, internship_id, internship_name, status, education_status, applied_date)
VALUES 
(1, 0, 'Test Internship', 'Applied', 'Pursuing', NOW());</code></pre>
            </div>
        </div>
        
        <?php endif; ?>

        <div class="mt-6 flex gap-3 justify-center">
            <a href="student_applications.php" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors">
                Go to Applications Page
            </a>
            <a href="test_status_flow.php" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg transition-colors">
                System Status Dashboard
            </a>
            <a href="status_system_index.html" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition-colors">
                Navigation Hub
            </a>
        </div>

    </div>
</body>
</html>
