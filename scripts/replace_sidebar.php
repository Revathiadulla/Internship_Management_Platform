<?php
$files = [
    'admin_dashboard.php',
    'admin_users.php',
    'admin_internships.php',
    'admin_applications.php',
    'admin_projects.php',
    'admin_project_categories.php',
    'admin_coordinator_assignments.php',
    'admin_daily_logs.php',
    'admin_reports.php',
    'admin_notifications.php',
    'admin_talent_pool.php',
    'admin_user_approvals.php'
];

$replacement = "<?php include 'includes/admin_sidebar.php'; ?>";

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Regex to match <aside>...</aside> (non-greedy)
        // Since there might be a specific class structure or something, I'll match <aside> and all its contents till </aside>
        // Use the s modifier so . matches newlines
        $pattern = '/<aside[^>]*>.*?<\/aside>/is';
        
        if (preg_match($pattern, $content)) {
            $new_content = preg_replace($pattern, $replacement, $content, 1);
            file_put_contents($file, $new_content);
            echo "Replaced sidebar in $file\n";
        } else {
            echo "Warning: <aside> not found in $file\n";
        }
    } else {
        echo "Error: $file does not exist.\n";
    }
}
?>
