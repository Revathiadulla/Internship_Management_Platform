<?php
include 'db.php';
$r = mysqli_query($conn, "SELECT COUNT(*) as c FROM internship_applications WHERE status IN ('Exam Completed','Test Completed','Test Submitted','Test Passed','Test Failed')");
$row = mysqli_fetch_assoc($r);
echo 'Remaining exam status records in DB: ' . $row['c'] . PHP_EOL;
echo 'All done!' . PHP_EOL;
