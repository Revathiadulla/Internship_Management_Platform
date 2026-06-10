<?php
$conn = mysqli_connect('localhost', 'root', '', 'imp_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
$r = mysqli_query($conn, "SELECT id, full_name, email, role, status FROM users WHERE role = 'student' LIMIT 10");
echo "=== Students in users table ===\n";
while ($row = mysqli_fetch_assoc($r)) {
    echo "ID: " . $row['id'] . " | Name: " . $row['full_name'] . " | Email: " . $row['email'] . " | Status: " . $row['status'] . "\n";
    // Check if profile exists
    $pr = mysqli_query($conn, "SELECT id FROM student_profiles WHERE user_id = " . $row['id']);
    if ($pr && mysqli_num_rows($pr) > 0) {
        echo "  -> Profile exists.\n";
    } else {
        echo "  -> Profile DOES NOT exist.\n";
    }
}
?>
