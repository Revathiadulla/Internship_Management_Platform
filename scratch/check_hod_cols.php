<?php
include 'db.php';
echo "=== Sample internship_applications status values ===\n";
$r = mysqli_query($conn, "SELECT DISTINCT status FROM internship_applications LIMIT 30");
while($row = mysqli_fetch_assoc($r)) { echo json_encode($row['status']) . "\n"; }

echo "\n=== Check HOD data in a sample pursuing application ===\n";
$r2 = mysqli_query($conn, "SELECT a.id, a.status, a.education_status, sp.student_type, a.hod_name, a.hod_email, sp.hod_name AS sp_hod_name, sp.hod_email AS sp_hod_email FROM internship_applications a LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE sp.student_type = 'pursuing' LIMIT 5");
while($row = mysqli_fetch_assoc($r2)) { echo json_encode($row) . "\n"; }
