<?php
include __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT a.id, a.status, sp.full_name, sp.email FROM internship_applications a LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE a.is_deleted = 0 ORDER BY a.id DESC LIMIT 15");
echo "=== Last 15 Applications ===\n";
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " | Status: " . $row['status'] . " | Name: " . $row['full_name'] . " | Email: " . $row['email'] . "\n";
}
