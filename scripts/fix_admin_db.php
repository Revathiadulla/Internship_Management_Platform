<?php
$admin_files = [
    'dashboard.php', 'applications.php', 'coordinator_assignments.php',
    'daily_logs.php', 'dropout_requests.php', 'internships.php',
    'notifications.php', 'projects.php', 'project_categories.php',
    'received_notifications.php', 'reports.php', 'send_mail.php',
    'student_reports.php', 'talent_pool.php', 'users.php', 'user_approvals.php'
];

$admin_dir = __DIR__ . '/../admin/';

foreach ($admin_files as $file) {
    $path = $admin_dir . $file;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    $content = str_replace("require_once __DIR__ . '/../includes/db.php';", "require_once __DIR__ . '/../includes/db.php';", $content);
    file_put_contents($path, $content);
}

// And fix the db update in refactor_admin_paths.php
require_once __DIR__ . '/../includes/db.php';
$links_to_update = [
    'admin_dashboard.php' => 'admin/dashboard.php',
    'admin_users.php' => 'admin/users.php',
    'admin_user_approvals.php' => 'admin/user_approvals.php',
    'admin_internships.php' => 'admin/internships.php',
    'admin_applications.php' => 'admin/applications.php',
    'admin_projects.php' => 'admin/projects.php',
    'admin_project_categories.php' => 'admin/project_categories.php',
    'admin_coordinator_assignments.php' => 'admin/coordinator_assignments.php',
    'admin_daily_logs.php' => 'admin/daily_logs.php',
    'admin_dropout_requests.php' => 'admin/dropout_requests.php',
    'admin_reports.php' => 'admin/reports.php',
    'admin_received_notifications.php' => 'admin/received_notifications.php',
    'admin_notifications.php' => 'admin/notifications.php',
    'admin_send_mail.php' => 'admin/send_mail.php',
    'admin_student_reports.php' => 'admin/student_reports.php',
    'admin_talent_pool.php' => 'admin/talent_pool.php',
];

foreach ($links_to_update as $old => $new) {
    mysqli_query($conn, "UPDATE notifications SET link = REPLACE(link, '$old', '$new') WHERE link LIKE '%$old%'");
}
echo "Database notification links updated.\n";
