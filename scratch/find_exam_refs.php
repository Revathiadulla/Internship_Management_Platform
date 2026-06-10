<?php
// Find all PHP files and show exact matches with line numbers
$dir = new RecursiveDirectoryIterator('.');
$iter = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($iter, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$patterns = ['Exam Completed', 'Test Completed', 'Exam completed', 'test completed'];
$excludes = ['vendor', '.git', 'scratch'];

foreach ($files as $file) {
    $path = $file[0];
    $skip = false;
    foreach ($excludes as $e) { if (strpos($path, $e) !== false) { $skip = true; break; } }
    if ($skip) continue;

    $lines = file($path);
    foreach ($lines as $lineNo => $line) {
        foreach ($patterns as $p) {
            if (strpos($line, $p) !== false) {
                echo $path . ':' . ($lineNo + 1) . ' => ' . trim($line) . PHP_EOL;
                break;
            }
        }
    }
}
