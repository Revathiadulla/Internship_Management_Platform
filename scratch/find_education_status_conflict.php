<?php
include "db.php";

echo "--- Student Profiles ---\n";
$res = mysqli_query($conn, "SELECT user_id, student_type, education_status FROM student_profiles");
while ($row = mysqli_fetch_assoc($res)) {
    echo "User ID: {$row['user_id']} | Type: {$row['student_type']} | Edu Status: {$row['education_status']}\n";
}

echo "\n--- Internship Applications ---\n";
$res2 = mysqli_query($conn, "SELECT id, user_id, status, education_status FROM internship_applications");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "App ID: {$row['id']} | User ID: {$row['user_id']} | Status: {$row['status']} | Edu Status: {$row['education_status']}\n";
}
?>
