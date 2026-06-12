<?php
$admin_files = glob(__DIR__ . '/../admin/*.php');

foreach ($admin_files as $path) {
    $content = file_get_contents($path);
    // Replace incorrectly terminated includes like:
    // include_once __DIR__ . '/../includes/discontinuation_helpers.php";
    // with
    // include_once __DIR__ . '/../includes/discontinuation_helpers.php';
    
    $fixed_content = preg_replace("/(__DIR__\s*\.\s*\'\/[^\']+\.php)\";/", "$1';", $content);
    
    if ($content !== $fixed_content) {
        file_put_contents($path, $fixed_content);
        echo "Fixed syntax in: " . basename($path) . "\n";
    }
}
