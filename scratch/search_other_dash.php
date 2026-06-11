<?php
$files = ['admin_dashboard.php', 'mentor_dashboard.php', 'hr_dashboard.php', 'company_dashboard.php', 'hod_dashboard.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/../' . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (stripos($content, 'Recent Activity') !== false) {
            echo "Found 'Recent Activity' in $f\n";
            // Print a snippet around it
            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                if (stripos($line, 'Recent Activity') !== false) {
                    for ($j = max(0, $i - 5); $j < min(count($lines), $i + 15); $j++) {
                        echo "  " . ($j + 1) . ": " . $lines[$j] . "\n";
                    }
                    break;
                }
            }
        }
    }
}
