<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'mentor') {
    header("Location: login.php");
    exit();
}
include "db.php";

$mentor_id = $_SESSION['user_id'];
$mentor_name = $_SESSION['full_name'];

$success_msg = "";
$error_msg = "";

// Handle Quick Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $student_id = intval($_POST['student_id']);
    $comments = trim($_POST['comments']);
    $rating = intval($_POST['rating'] ?? 5);
    $feedback_title = trim($_POST['feedback_title'] ?: 'Weekly Progress Evaluation');

    if ($student_id <= 0 || empty($comments)) {
        $error_msg = "Please select a student and enter comments.";
    } else {
        $insert_sql = "INSERT INTO mentor_feedback (user_id, feedback_title, given_by, comments, rating) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "isssi", $student_id, $feedback_title, $mentor_name, $comments, $rating);
        
        if (mysqli_stmt_execute($stmt)) {
            // Also notify the student
            $notif_msg = "Your mentor " . htmlspecialchars($mentor_name) . " has submitted a new performance feedback: \"" . htmlspecialchars(mb_strimwidth($comments, 0, 50, '...')) . "\"";
            $notif_sql = "INSERT INTO student_notifications (user_id, type, message, is_read) VALUES (?, 'Mentor Feedback', ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($notif_stmt, "is", $student_id, $notif_msg);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $success_msg = "Feedback submitted successfully!";
        } else {
            $error_msg = "Error submitting feedback: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch assigned students
$students_sql = "
    SELECT u.id as student_id, u.full_name, u.email, u.phone, a.team_name, a.team_status,
           COALESCE(i.title, a.internship_name) as internship_title,
           (SELECT COUNT(*) FROM daily_logs WHERE user_id = u.id) as log_count
    FROM internship_applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN internships i ON a.internship_id = i.id
    WHERE a.mentor_id = ? AND a.team_name IS NOT NULL AND a.team_name != ''
    ORDER BY u.full_name ASC
";
$students_stmt = mysqli_prepare($conn, $students_sql);
mysqli_stmt_bind_param($students_stmt, "i", $mentor_id);
mysqli_stmt_execute($students_stmt);
$students_res = mysqli_stmt_get_result($students_stmt);
$students = [];
while ($row = mysqli_fetch_assoc($students_res)) {
    $students[] = $row;
}
mysqli_stmt_close($students_stmt);

// Fetch assigned squads summaries
$squads_sql = "
    SELECT DISTINCT a.team_name, a.team_status, i.title as project_title,
                    COUNT(DISTINCT a.user_id) as student_count
    FROM internship_applications a
    LEFT JOIN internships i ON a.internship_id = i.id
    WHERE a.mentor_id = ? AND a.team_name IS NOT NULL AND a.team_name != ''
    GROUP BY a.team_name, a.team_status, i.title
    ORDER BY a.team_name ASC
";
$squads_stmt = mysqli_prepare($conn, $squads_sql);
mysqli_stmt_bind_param($squads_stmt, "i", $mentor_id);
mysqli_stmt_execute($squads_stmt);
$squads_res = mysqli_stmt_get_result($squads_stmt);
$squads = [];
while ($row = mysqli_fetch_assoc($squads_res)) {
    $squads[] = $row;
}
mysqli_stmt_close($squads_stmt);

// Fetch recent daily logs from assigned students
$logs_sql = "
    SELECT d.id as log_id, d.log_date, d.status, d.time_spent, d.focus_level, u.full_name as student_name
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
    JOIN internship_applications a ON u.id = a.user_id
    WHERE a.mentor_id = ? AND a.team_name IS NOT NULL AND a.team_name != ''
    ORDER BY d.log_date DESC, d.created_at DESC LIMIT 5
";
$logs_stmt = mysqli_prepare($conn, $logs_sql);
mysqli_stmt_bind_param($logs_stmt, "i", $mentor_id);
mysqli_stmt_execute($logs_stmt);
$logs_res = mysqli_stmt_get_result($logs_stmt);
$recent_logs = [];
while ($row = mysqli_fetch_assoc($logs_res)) {
    $recent_logs[] = $row;
}
mysqli_stmt_close($logs_stmt);

// Fetch recent issues/blockers alerts from assigned students
$alerts_sql = "
    SELECT u.full_name, d.issues_faced, d.log_date
    FROM daily_logs d
    JOIN users u ON d.user_id = u.id
    JOIN internship_applications a ON u.id = a.user_id
    WHERE a.mentor_id = ? AND d.issues_faced IS NOT NULL AND d.issues_faced != ''
    ORDER BY d.log_date DESC LIMIT 3
";
$alerts_stmt = mysqli_prepare($conn, $alerts_sql);
mysqli_stmt_bind_param($alerts_stmt, "i", $mentor_id);
mysqli_stmt_execute($alerts_stmt);
$alerts_res = mysqli_stmt_get_result($alerts_stmt);
$alerts = [];
while ($row = mysqli_fetch_assoc($alerts_res)) {
    $alerts[] = $row;
}
mysqli_stmt_close($alerts_stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Mentor Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "on-surface-variant": "#434655",
                    "secondary-fixed-dim": "#c0c7d0",
                    "on-primary-container": "#eeefff",
                    "surface-container-low": "#f3f4f5",
                    "primary-fixed": "#dbe1ff",
                    "outline": "#737686",
                    "secondary": "#585f67",
                    "tertiary-container": "#bc4800",
                    "error": "#ba1a1a",
                    "primary-container": "#2563eb",
                    "surface": "#f8f9fa",
                    "on-primary-fixed-variant": "#003ea8",
                    "on-primary": "#ffffff",
                    "on-secondary-container": "#5e656d",
                    "on-background": "#191c1d",
                    "surface-container": "#edeeef",
                    "on-tertiary": "#ffffff",
                    "on-secondary": "#ffffff",
                    "secondary-fixed": "#dce3ec",
                    "on-tertiary-container": "#ffede6",
                    "on-tertiary-fixed-variant": "#7d2d00",
                    "outline-variant": "#c3c6d7",
                    "inverse-primary": "#b4c5ff",
                    "secondary-container": "#dce3ec",
                    "surface-dim": "#d9dadb",
                    "on-surface": "#191c1d",
                    "on-secondary-fixed-variant": "#40484f",
                    "inverse-surface": "#2e3132",
                    "on-error": "#ffffff",
                    "background": "#f8f9fa",
                    "primary": "#004ac6",
                    "tertiary": "#943700",
                    "tertiary-fixed": "#ffdbcd",
                    "surface-variant": "#e1e3e4",
                    "surface-container-highest": "#e1e3e4",
                    "on-tertiary-fixed": "#360f00",
                    "surface-container-lowest": "#ffffff",
                    "error-container": "#ffdad6",
                    "on-primary-fixed": "#00174b",
                    "surface-tint": "#0053db",
                    "primary-fixed-dim": "#b4c5ff",
                    "on-error-container": "#93000a",
                    "surface-bright": "#f8f9fa",
                    "inverse-on-surface": "#f0f1f2",
                    "on-secondary-fixed": "#151c23",
                    "surface-container-high": "#e7e8e9",
                    "tertiary-fixed-dim": "#ffb596"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "xl": "32px",
                    "lg": "24px",
                    "container-margin": "40px",
                    "md": "16px",
                    "sm": "8px",
                    "xs": "4px",
                    "gutter": "20px",
                    "unit": "4px"
            },
            "fontFamily": {
                    "body-lg": ["Inter"],
                    "label-md": ["Inter"],
                    "body-md": ["Inter"],
                    "h1": ["Inter"],
                    "label-sm": ["Inter"],
                    "h3": ["Inter"],
                    "h2": ["Inter"]
            },
            "fontSize": {
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-md": ["14px", {"lineHeight": "20px", "fontWeight": "500"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "h1": ["30px", {"lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                    "h3": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "h2": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}]
            }
          },
        },
      }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-background text-on-background">
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6">
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
    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Mentor Portal</p>
</div>
<nav class="flex-1 space-y-1">
<a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 font-sans text-sm font-medium duration-200 ease-in-out" href="mentor_dashboard.php">
<span class="material-symbols-outlined">dashboard</span>
                Dashboard
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="logout.php">
<span class="material-symbols-outlined">logout</span>
                Logout
            </a>
</nav>
<div class="mt-auto border-t border-gray-200 pt-4">
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined">help</span>
                Help Center
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="logout.php">
<span class="material-symbols-outlined">logout</span>
                Logout
            </a>
</div>
</aside>
<main class="ml-60 min-h-screen">
<!-- TopNavBar -->
<header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3">
<div class="flex items-center gap-4">
<a href="index.html" class="text-xl font-bold text-blue-600 font-sans hover:opacity-80 transition-opacity cursor-pointer block">IMP</a>
</div>
<div class="flex items-center gap-4">
<div class="h-8 w-8 rounded-full overflow-hidden border border-gray-200 cursor-pointer active:opacity-80">
<?php $mentor_avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($mentor_name) . "&background=2563eb&color=fff"; ?>
<img alt="User profile" src="<?php echo $mentor_avatar_url; ?>" class="">
</div>
</div>
</header>

<div class="p-xl space-y-lg">
    
    <?php if ($success_msg): ?>
        <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2">
            <span class="material-symbols-outlined text-green-500">check_circle</span>
            <span><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2">
            <span class="material-symbols-outlined text-red-500">error</span>
            <span><?php echo $error_msg; ?></span>
        </div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <section class="flex justify-between items-end">
    <div>
    <h1 class="font-h1 text-h1 text-on-surface">Mentor<span style="letter-spacing: -0.02em;" class="">&nbsp;Dashboard</span></h1>
    <p class="font-body-md text-body-md text-on-surface-variant">Welcome back, <?php echo htmlspecialchars($mentor_name); ?>. You have <?php echo count($students); ?> assigned students across your squads.</p>
    </div>
    </section>

    <!-- Main Bento Grid -->
    <div class="grid grid-cols-12 gap-gutter">
        <!-- Assigned Interns - High Density Cards -->
        <div class="col-span-12 lg:col-span-8 bg-surface-container-lowest rounded-xl shadow-sm p-lg">
            <div class="flex justify-between items-center mb-md">
            <h3 class="font-h3 text-h3 text-on-surface">Assigned Interns</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <?php if (empty($students)): ?>
                    <p class="text-sm text-gray-400 italic col-span-full py-8 text-center bg-gray-50 border border-dashed rounded-lg">No students have been assigned to you by the Coordinator yet.</p>
                <?php else: ?>
                    <?php foreach ($students as $st): 
                        $avatar = "https://ui-avatars.com/api/?name=" . urlencode($st['full_name']) . "&background=e0e7ff&color=4f46e5";
                        $log_progress = min(100, round(($st['log_count'] / 20) * 100)); // assume 20 logs is 100%
                        $st_status = $st['team_status'] ?: 'Active';
                        $status_class = $st_status === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700';
                    ?>
                        <div class="border border-outline-variant rounded-lg p-md hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between">
                                <div class="flex gap-md">
                                    <img class="w-12 h-12 rounded-full object-cover border" src="<?php echo $avatar; ?>" alt="Student avatar">
                                    <div>
                                        <h4 class="font-label-md text-label-md text-on-surface"><?php echo htmlspecialchars($st['full_name']); ?></h4>
                                        <p class="text-xs text-gray-500 font-semibold mt-0.5"><?php echo htmlspecialchars($st['internship_title'] ?: 'Intern'); ?></p>
                                        <p class="text-[10px] text-indigo-600 font-bold mt-1">Squad: <?php echo htmlspecialchars($st['team_name']); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded font-label-sm text-[9px] uppercase tracking-wider <?php echo $status_class; ?>"><?php echo htmlspecialchars($st_status); ?></span>
                            </div>
                            <div class="mt-md space-y-xs">
                                <div class="flex justify-between text-[11px] font-label-sm text-on-surface-variant">
                                    <span class="">Log Progression (<?php echo $st['log_count']; ?> logs)</span>
                                    <span class=""><?php echo $log_progress; ?>%</span>
                                </div>
                                <div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-primary h-full rounded-full" style="width: <?php echo $log_progress; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Squad Allocation summaries -->
        <div class="col-span-12 lg:col-span-4 bg-surface-container-lowest rounded-xl shadow-sm p-lg">
            <h3 class="font-h3 text-h3 text-on-surface mb-md">Assigned Squads</h3>
            <div class="space-y-md">
                <?php if (empty($squads)): ?>
                    <p class="text-xs text-gray-400 italic py-4 text-center">No active squads.</p>
                <?php else: ?>
                    <?php foreach ($squads as $sq): 
                        $status_bar = $sq['team_status'] === 'Completed' ? 'border-emerald-500' : 'border-primary';
                    ?>
                        <div class="flex items-center gap-md p-md bg-surface-container-low rounded-lg border-l-4 <?php echo $status_bar; ?>">
                            <div class="flex-1">
                                <p class="font-label-md text-label-md text-on-surface font-bold"><?php echo htmlspecialchars($sq['team_name']); ?></p>
                                <p class="text-[10px] text-gray-400 font-bold uppercase mt-0.5 truncate"><?php echo htmlspecialchars($sq['project_title']); ?></p>
                                <p class="text-[11px] text-on-surface-variant font-medium mt-1">Status: <?php echo htmlspecialchars($sq['team_status']); ?> • <?php echo $sq['student_count']; ?> Students</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Daily Log Review Section -->
        <div class="col-span-12 lg:col-span-7 bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
            <div class="p-lg flex justify-between items-center">
                <h3 class="font-h3 text-h3 text-on-surface">Recent Student Logs</h3>
            </div>
            <table class="w-full">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Intern</th>
                        <th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Date</th>
                        <th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Status</th>
                        <th class="text-right py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container text-xs">
                    <?php if (empty($recent_logs)): ?>
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-400 italic">No logs submitted yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): 
                            $status = htmlspecialchars($log['status'] ?: 'Submitted');
                            $badge_color = $status === 'Reviewed' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700';
                            $init = strtoupper(substr($log['student_name'], 0, 2));
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-md px-lg">
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-[10px]"><?php echo $init; ?></div>
                                        <span class="font-body-md text-body-md text-on-surface font-semibold"><?php echo htmlspecialchars($log['student_name']); ?></span>
                                    </div>
                                </td>
                                <td class="py-md px-lg text-on-surface-variant font-medium"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                                <td class="py-md px-lg">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $badge_color; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td class="py-md px-lg text-right">
                                    <!-- Coordinator handles full review, but mentor can view log list -->
                                    <span class="text-gray-400 italic">Log submitted</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Feedback & Alerts Section -->
        <div class="col-span-12 lg:col-span-5 flex flex-col gap-gutter">
            <!-- Mentor Notifications/Alerts -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-lg border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-h3 text-h3 text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-orange-500">notifications_active</span> Alerts & Issues</h3>
                </div>
                <div class="space-y-3">
                    <?php if (empty($alerts)): ?>
                        <p class="text-xs text-gray-400 italic py-2 text-center">No blocker alerts reported by students.</p>
                    <?php else: ?>
                        <?php foreach ($alerts as $alt): ?>
                            <div class="flex items-start gap-3 p-3 bg-red-50 rounded-lg border border-red-100">
                                <span class="material-symbols-outlined text-red-500 text-sm mt-0.5">error</span>
                                <div>
                                    <p class="text-xs font-bold text-red-800"><?php echo htmlspecialchars($alt['full_name']); ?> reported blocker</p>
                                    <p class="text-[11px] text-red-600 mt-0.5 italic">"<?php echo htmlspecialchars($alt['issues_faced']); ?>"</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feedback Submission -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-lg border border-gray-100">
                <div class="flex items-center gap-md mb-md">
                    <div class="p-2 bg-primary-container/10 text-primary rounded-lg">
                        <span class="material-symbols-outlined">rate_review</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface">Submit Intern Evaluation</h3>
                </div>
                <form method="POST" action="mentor_dashboard.php" class="space-y-md">
                    <input type="hidden" name="action" value="submit_feedback">
                    
                    <div>
                        <label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Select Intern</label>
                        <select name="student_id" required class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md py-2 px-3 text-xs bg-gray-50 border border-gray-200 outline-none cursor-pointer">
                            <option value="">Choose Student...</option>
                            <?php foreach ($students as $st): ?>
                                <option value="<?php echo $st['student_id']; ?>"><?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['team_name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Evaluation Title</label>
                        <input type="text" name="feedback_title" placeholder="e.g. Sprint 1 Evaluation" class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md py-2 px-3 text-xs bg-gray-50 border border-gray-200 outline-none">
                    </div>

                    <div>
                        <label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Rating (1-5 Stars)</label>
                        <select name="rating" required class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md py-2 px-3 text-xs bg-gray-50 border border-gray-200 outline-none cursor-pointer">
                            <option value="5">⭐⭐⭐⭐⭐ 5 (Excellent)</option>
                            <option value="4">⭐⭐⭐⭐ 4 (Good)</option>
                            <option value="3">⭐⭐⭐ 3 (Average)</option>
                            <option value="2">⭐⭐ 2 (Needs Improvement)</option>
                            <option value="1">⭐ 1 (Unsatisfactory)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Feedback Comments</label>
                        <textarea name="comments" required class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md p-md bg-gray-50 border border-gray-200 outline-none" placeholder="Share specific insights, progress review, or milestones..." rows="3"></textarea>
                    </div>

                    <button class="w-full bg-primary-container text-white py-2 mt-4 rounded-lg font-label-md text-label-md shadow-sm hover:brightness-110 transition-all font-bold text-xs cursor-pointer" type="submit">Submit Evaluation</button>
                </form>
            </div>
        </div>
    </div>
</div>
</main>
</body>
</html>