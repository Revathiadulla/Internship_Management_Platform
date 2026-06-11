<?php
// Start session first, then set mock data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 66;
$_SESSION['role'] = 'mentor';
$_SESSION['full_name'] = 'Rajesh Kumar';

$_GET['team_id'] = 3;
$_GET['team_name'] = 'alpha';

$_SERVER['REQUEST_METHOD'] = 'GET';

// We can run ob_start to capture any output or check if any errors/warnings are thrown
ob_start();
try {
    include __DIR__ . '/../mentor_workspace.php';
} catch (Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
}
$out = ob_get_clean();

// Check if any PHP warnings or errors occurred
// We exclude session_start notice because we intentionally started it in the test script.
$has_errors = false;
if (preg_match('/(Fatal error|Warning|Notice)/i', $out, $matches)) {
    // Check if it's just the session_start notice
    if (strpos($out, 'session_start(): Ignoring session_start()') === false) {
        $has_errors = true;
    }
}

if ($has_errors) {
    echo "Workspace rendering FAILED or had warnings:\n";
    echo substr($out, 0, 1000) . "\n";
} else {
    echo "Workspace simulation SUCCEEDED!\n";
    echo "Found team name: " . (isset($team['team_name']) ? $team['team_name'] : 'none') . "\n";
    echo "Found student count: " . count($students) . "\n";
    echo "Found logs count: " . count($logs) . "\n";
}
