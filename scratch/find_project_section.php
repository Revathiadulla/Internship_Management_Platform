<?php
$content = file_get_contents(__DIR__ . '/../student_dashboard.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'My Project') !== false || stripos($line, 'assigned_project') !== false || stripos($line, 'project_team') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
