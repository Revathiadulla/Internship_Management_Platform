<?php
// Script to automate refactoring of paths for the mentor module

$mentor_dir = __DIR__ . '/../mentor/';
$root_dir = __DIR__ . '/../';

// 1. Replacements for files INSIDE the mentor/ directory
$internal_replacements = [
    // Include paths
    "__DIR__ . '/includes/" => "__DIR__ . '/../includes/",
    '__DIR__ . "/includes/' => '__DIR__ . "/../includes/',
    "__DIR__ . '/uploads/" => "__DIR__ . '/../uploads/",
    '__DIR__ . "/uploads/' => '__DIR__ . "/../uploads/',
    "__DIR__ . '/db.php'" => "__DIR__ . '/../db.php'",
    "__DIR__ . '/email_helper.php'" => "__DIR__ . '/../email_helper.php'",
    "__DIR__ . '/notification_helpers.php'" => "__DIR__ . '/../notification_helpers.php'",
    "__DIR__ . '/status_helper.php'" => "__DIR__ . '/../status_helper.php'",
    "__DIR__ . '/status_utils.php'" => "__DIR__ . '/../status_utils.php'",

    // Plain includes
    'include __DIR__ . '/../includes/db.php';' => "require_once __DIR__ . '/../includes/db.php';",
    "include __DIR__ . '/../includes/db.php';" => "require_once __DIR__ . '/../includes/db.php';",
    'require __DIR__ . '/../includes/db.php';' => "require_once __DIR__ . '/../includes/db.php';",
    "require __DIR__ . '/../includes/db.php';" => "require_once __DIR__ . '/../includes/db.php';",

    // Redirects and links to root pages
    'header("Location: login.php' => 'header("Location: ../login.php',
    "header('Location: login.php" => "header('Location: ../login.php",
    'header("Location: index.html' => 'header("Location: ../index.html',
    "header('Location: index.html" => "header('Location: ../index.html",
    'header("Location: logout.php' => 'header("Location: ../logout.php',
    "header('Location: logout.php" => "header('Location: ../logout.php",
    'href="login.php"' => 'href="../login.php"',
    'href="logout.php"' => 'href="../logout.php"',
    'href="index.html"' => 'href="../index.html"',

    // Scripts and Assets
    'src="js/' => 'src="../js/',
    'href="css/' => 'href="../css/',
    'href="assets/' => 'href="../assets/',
    'src="uploads/' => 'src="../uploads/',
    'href="uploads/' => 'href="../uploads/',

    // Internal Mentor links (remove prefix, update names)
    'mentor_dashboard.php' => 'dashboard.php',
    'mentor_daily_logs.php' => 'daily_logs.php',
    'mentor_notifications.php' => 'notifications.php',
    'mentor_projects.php' => 'projects.php',
    'mentor_report_student.php' => 'feedback.php',
    'mentor_send_notification.php' => 'send_notification.php',
    'mentor_view_project.php' => 'view_project.php',
    'mentor_workspace.php' => 'students.php',
];

// 2. Replacements for files OUTSIDE the mentor/ directory (root files)
$external_replacements = [
    'mentor_dashboard.php' => 'mentor/dashboard.php',
    'mentor_daily_logs.php' => 'mentor/daily_logs.php',
    'mentor_notifications.php' => 'mentor/notifications.php',
    'mentor_projects.php' => 'mentor/projects.php',
    'mentor_report_student.php' => 'mentor/feedback.php',
    'mentor_send_notification.php' => 'mentor/send_notification.php',
    'mentor_view_project.php' => 'mentor/view_project.php',
    'mentor_workspace.php' => 'mentor/students.php',
];

// Update Mentor Files
$mentor_files = glob($mentor_dir . '*.php');
foreach ($mentor_files as $file) {
    $content = file_get_contents($file);
    $newContent = strtr($content, $internal_replacements);
    if ($content !== $newContent) {
        file_put_contents($file, $newContent);
        echo "Updated mentor file: " . basename($file) . "\n";
    }
}

// Update Root Files
$root_files = glob($root_dir . '*.php');
foreach ($root_files as $file) {
    if (strpos($file, 'refactor_') !== false) continue;
    $content = file_get_contents($file);
    $newContent = strtr($content, $external_replacements);
    if ($content !== $newContent) {
        file_put_contents($file, $newContent);
        echo "Updated root file: " . basename($file) . "\n";
    }
}

// Includes directory (like sidebars etc)
$includes_dir = __DIR__ . '/../includes/';
if (is_dir($includes_dir)) {
    $include_files = glob($includes_dir . '*.php');
    foreach ($include_files as $file) {
        $content = file_get_contents($file);
        $newContent = strtr($content, $external_replacements);
        if ($content !== $newContent) {
            file_put_contents($file, $newContent);
            echo "Updated include file: " . basename($file) . "\n";
        }
    }
}

echo "Done.\n";
