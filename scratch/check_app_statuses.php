<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT status, COUNT(*) as c FROM internship_applications GROUP BY status");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['status']} : {$r['c']}\n";
}
echo "\n=== All status history states ===\n";
$res2 = mysqli_query($conn, "SELECT DISTINCT new_status FROM application_status_history");
if ($res2) {
    while ($r = mysqli_fetch_assoc($res2)) {
        echo "{$r['new_status']}\n";
    }
}
