<?php
$admin_files = [
    'dashboard.php', 'applications.php', 'coordinator_assignments.php',
    'daily_logs.php', 'dropout_requests.php', 'internships.php',
    'notifications.php', 'projects.php', 'project_categories.php',
    'received_notifications.php', 'reports.php', 'send_mail.php',
    'student_reports.php', 'talent_pool.php', 'users.php', 'user_approvals.php'
];

$admin_dir = __DIR__ . '/../admin/';
$root_dir = __DIR__ . '/../';

foreach ($admin_files as $file) {
    $path = $admin_dir . $file;
    if (!file_exists($path)) {
        echo "Missing file: $path\n";
        continue;
    }
    
    $content = file_get_contents($path);
    
    // 1. Fix includes
    $content = preg_replace("/include\s+['\"]db\.php['\"];/", "require_once __DIR__ . '/../includes/db.php';", $content);
    $content = preg_replace("/include_once\s+['\"]db\.php['\"];/", "require_once __DIR__ . '/../includes/db.php';", $content);
    $content = preg_replace("/require_once\s+['\"]db\.php['\"];/", "require_once __DIR__ . '/../includes/db.php';", $content);
    $content = preg_replace("/include\s+['\"]includes\//", "include __DIR__ . '/../includes/", $content);
    $content = preg_replace("/include_once\s+['\"]includes\//", "include_once __DIR__ . '/../includes/", $content);
    $content = preg_replace("/include_once\s+__DIR__\s*\.\s*['\"]\/?includes\//", "include_once __DIR__ . '/../includes/", $content);
    $content = preg_replace("/include\s+__DIR__\s*\.\s*['\"]\/?includes\//", "include __DIR__ . '/../includes/", $content);

    // 2. Fix inner links pointing to admin_*.php
    // When inside admin/, they should just point to the mapped names.
    // e.g. admin_dashboard.php -> dashboard.php
    $content = str_replace('admin_dashboard.php', 'dashboard.php', $content);
    $content = str_replace('admin_users.php', 'users.php', $content);
    $content = str_replace('admin_user_approvals.php', 'user_approvals.php', $content);
    $content = str_replace('admin_internships.php', 'internships.php', $content);
    $content = str_replace('admin_applications.php', 'applications.php', $content);
    $content = str_replace('admin_projects.php', 'projects.php', $content);
    $content = str_replace('admin_project_categories.php', 'project_categories.php', $content);
    $content = str_replace('admin_coordinator_assignments.php', 'coordinator_assignments.php', $content);
    $content = str_replace('admin_daily_logs.php', 'daily_logs.php', $content);
    $content = str_replace('admin_dropout_requests.php', 'dropout_requests.php', $content);
    $content = str_replace('admin_reports.php', 'reports.php', $content);
    $content = str_replace('admin_received_notifications.php', 'received_notifications.php', $content);
    $content = str_replace('admin_notifications.php', 'notifications.php', $content);
    $content = str_replace('admin_send_mail.php', 'send_mail.php', $content);
    $content = str_replace('admin_student_reports.php', 'student_reports.php', $content);
    $content = str_replace('admin_talent_pool.php', 'talent_pool.php', $content);

    // 3. Fix root links (login, logout, mark_notification_read, etc.)
    $content = str_replace('"login.php', '"../login.php', $content);
    $content = str_replace("'login.php", "'../login.php", $content);
    $content = str_replace('"logout.php', '"../logout.php', $content);
    $content = str_replace("'logout.php", "'../logout.php", $content);
    $content = str_replace('"mark_notification_read.php', '"../mark_notification_read.php', $content);
    $content = str_replace("'mark_notification_read.php", "'../mark_notification_read.php", $content);
    $content = str_replace('"get_notification_count.php', '"../get_notification_count.php', $content);
    $content = str_replace("'get_notification_count.php", "'../get_notification_count.php", $content);
    
    // Fix action endpoints for forms
    $content = str_replace('action="student_reports.php"', 'action="student_reports.php"', $content); // No change needed if self
    
    file_put_contents($path, $content);
    echo "Updated: admin/$file\n";
}

// 4. Fix includes/admin_sidebar.php
$sidebar_path = __DIR__ . '/../includes/admin_sidebar.php';
$sidebar_content = file_get_contents($sidebar_path);
$sidebar_content = str_replace('admin_dashboard.php', '$a_prefix . \'dashboard.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_users.php', '$a_prefix . \'users.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_user_approvals.php', '$a_prefix . \'user_approvals.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_internships.php', '$a_prefix . \'internships.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_applications.php', '$a_prefix . \'applications.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_projects.php', '$a_prefix . \'projects.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_project_categories.php', '$a_prefix . \'project_categories.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_coordinator_assignments.php', '$a_prefix . \'coordinator_assignments.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_daily_logs.php', '$a_prefix . \'daily_logs.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_dropout_requests.php', '$a_prefix . \'dropout_requests.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_reports.php', '$a_prefix . \'reports.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_received_notifications.php', '$a_prefix . \'received_notifications.php\'', $sidebar_content);
$sidebar_content = str_replace('admin_talent_pool.php', '$a_prefix . \'talent_pool.php\'', $sidebar_content);
$sidebar_content = str_replace('confirmation_letter_template.php', '$r_prefix . \'confirmation_letter_template.php\'', $sidebar_content);
$sidebar_content = str_replace('certificate_template.php', '$r_prefix . \'certificate_template.php\'', $sidebar_content);

// inject prefix logic
$prefix_logic = <<<PHP
\$in_admin = (strpos(\$_SERVER['SCRIPT_NAME'], '/admin/') !== false);
\$a_prefix = \$in_admin ? '' : 'admin/';
\$r_prefix = \$in_admin ? '../' : '';
PHP;

$sidebar_content = preg_replace('/\$menu_items\s*=\s*\[/', $prefix_logic . "\n\$menu_items = [", $sidebar_content);
$sidebar_content = str_replace('"logout.php"', '<?php echo $r_prefix; ?>logout.php', $sidebar_content);
file_put_contents($sidebar_path, $sidebar_content);
echo "Updated includes/admin_sidebar.php\n";

// 5. Update global includes/hr_module_helpers.php
$hr_helpers = __DIR__ . '/../includes/hr_module_helpers.php';
$hr_content = file_get_contents($hr_helpers);
$hr_content = str_replace("admin_received_notifications.php", "received_notifications.php", $hr_content); // since hr_sidebar uses $a_prefix
file_put_contents($hr_helpers, $hr_content);
echo "Updated includes/hr_module_helpers.php\n";

// 6. DB update for notifications links
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
