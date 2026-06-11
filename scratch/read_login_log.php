<?php
$log_file = __DIR__ . '/../uploads/login_debug.log';
if (file_exists($log_file)) {
    echo file_get_contents($log_file);
} else {
    echo "No debug log file found at: $log_file\n";
}
