<?php
// Start session first, then set mock data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 66;
$_SESSION['role'] = 'mentor';
$_SESSION['full_name'] = 'Rajesh Kumar';

$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
try {
    include __DIR__ . '/../mentor_daily_logs.php';
} catch (Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
}
$out = ob_get_clean();

$has_errors = false;
if (preg_match('/(Fatal error|Warning|Notice)/i', $out, $matches)) {
    if (strpos($out, 'session_start(): Ignoring session_start()') === false) {
        $has_errors = true;
    }
}

if ($has_errors) {
    echo "Daily Logs rendering FAILED or had warnings:\n";
    echo substr($out, 0, 1000) . "\n";
} else {
    echo "Daily Logs simulation SUCCEEDED!\n";
    echo "Total Rows found: " . (isset($total_rows) ? $total_rows : 'none') . "\n";
    echo "Pending reviews count: " . (isset($total_pending) ? $total_pending : 'none') . "\n";
    echo "Assigned students count: " . (isset($assigned_students_count) ? $assigned_students_count : 'none') . "\n";
}
