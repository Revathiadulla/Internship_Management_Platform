<?php
require_once __DIR__ . '/db.php';

$tables = [
    'email_logs' => ['sender_id','sender_role'],
    'email_notifications_log' => ['sender_id','sender_role']
];

foreach ($tables as $table => $cols) {
    echo "Checking table: $table\n";
    foreach ($cols as $col) {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $table) . "' AND COLUMN_NAME = '" . mysqli_real_escape_string($conn, $col) . "' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            echo "  - Column exists: $col\n";
        } else {
            echo "  - Column missing: $col\n";
        }
    }
}
