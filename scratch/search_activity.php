<?php
$content = file_get_contents(__DIR__ . '/../coordinator_dashboard.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'activity') !== false || stripos($line, 'recent') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
