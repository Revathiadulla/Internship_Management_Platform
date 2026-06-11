<?php
$dir = __DIR__ . '/../';
$deprecated = ['relevant_skills', 'preferred_duration', 'reason_for_applying', 'assigned_project_id'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getRealPath();
    if (strpos($path, 'node_modules') !== false || strpos($path, '.git') !== false || strpos($path, 'vendor') !== false || strpos($path, 'scratch') !== false) {
        continue;
    }
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
        continue;
    }
    $content = file_get_contents($path);
    foreach ($deprecated as $dep) {
        if (strpos($content, $dep) !== false) {
            echo "Found '$dep' in " . basename($path) . "\n";
        }
    }
}
