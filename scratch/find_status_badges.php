<?php
$dir = __DIR__ . '/..';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content === false) continue;
    $lines = explode("\n", $content);
    $basename = basename($file);
    foreach ($lines as $i => $line) {
        if (stripos($line, 'getStatusBadgeClass') !== false || stripos($line, 'Application Status') !== false) {
            echo "$basename Line " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
