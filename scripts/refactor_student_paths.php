<?php
$dir = __DIR__ . '/../student/';
$files = glob($dir . '*.php');

$replacements = [
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
    'include "status_helper.php";' => "require_once __DIR__ . '/../status_helper.php';",
    'include __DIR__ . '/../includes/status_utils.php';' => "require_once __DIR__ . '/../includes/status_utils.php';",

    // Redirects to root pages
    'header("Location: login.php' => 'header("Location: ../login.php',
    "header('Location: login.php" => "header('Location: ../login.php",
    'header("Location: index.html' => 'header("Location: ../index.html',
    "header('Location: index.html" => "header('Location: ../index.html",
    'header("Location: logout.php' => 'header("Location: ../logout.php',
    "header('Location: logout.php" => "header('Location: ../logout.php",

    // Links to root pages
    'href="login.php"' => 'href="../login.php"',
    'href="logout.php"' => 'href="../logout.php"',
    'href="index.html"' => 'href="../index.html"',

    // Scripts and Assets
    'src="js/' => 'src="../js/',
    'href="css/' => 'href="../css/',
    'href="assets/' => 'href="../assets/',
    'src="uploads/' => 'src="../uploads/',
    'href="uploads/' => 'href="../uploads/',
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = strtr($content, $replacements);
    
    // Also handle dynamic redirect if there are any variables (not typically an issue with strtr if literal)
    
    if ($content !== $newContent) {
        file_put_contents($file, $newContent);
        echo "Updated: " . basename($file) . "\n";
    }
}
echo "Done.\n";
