<?php
function log_error($message) {
    $logFile = __DIR__ . '/profile_error.log';
    $date = date('Y-m-d H:i:s');
    $entry = "[$date] $message\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>
