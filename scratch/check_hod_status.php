<?php
include 'db.php';
echo "=== Applications with pursuing student_type ===\n";
$r = mysqli_query($conn, "SELECT a.id, a.status, a.education_status, a.hod_approval_status, a.hod_status, sp.student_type
    FROM internship_applications a
    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
    WHERE sp.student_type = 'pursuing'
    ORDER BY a.id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($r)) {
    echo json_encode($row) . PHP_EOL;
}
