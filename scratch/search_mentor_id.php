<?php
$files = ['../coordinator_dashboard.php', '../coordinator_analytics_api.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (strpos($content, 'mentor_id') !== false) {
            echo "Found 'mentor_id' in $f\n";
        }
    }
}
