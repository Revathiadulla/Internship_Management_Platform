<?php
require __DIR__ . '/includes/db.php';
echo "Daily Logs Statuses:\n";
$res = mysqli_query($conn, 'SELECT DISTINCT status FROM daily_logs');
if($res){ while($r = mysqli_fetch_assoc($res)) print_r($r); }

echo "\nInternship Statuses:\n";
$res2 = mysqli_query($conn, "SELECT DISTINCT internship_status FROM internship_applications");
if($res2){ while($r = mysqli_fetch_assoc($res2)) print_r($r); }
?>
