<?php
$content = file_get_contents(__DIR__ . '/../coordinator_teams.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'Project Assigned') !== false || (stripos($line, 'status') !== false && stripos($line, 'update') !== false)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
