<?php
require 'db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM internships");
echo "INTERNSHIPS COLUMNS:\n";
while($r = mysqli_fetch_assoc($res)) { echo $r['Field'] . "\n"; }

$res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
echo "\nAPPLICATIONS COLUMNS:\n";
while($r = mysqli_fetch_assoc($res)) { echo $r['Field'] . "\n"; }
