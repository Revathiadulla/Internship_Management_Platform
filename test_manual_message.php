<?php
// Test script to debug manual_message.php form submission
require_once 'db.php';
require_once 'includes/auth.php';

echo "=== Testing Manual Message Form ===\n\n";

// Check if students table exists and has data
$students_sql = "SELECT id, full_name, email, role FROM users WHERE role = 'student' ORDER BY full_name ASC LIMIT 10";
$students_res = mysqli_query($conn, $students_sql);

echo "Students in database:\n";
if ($students_res) {
    $count = 0;
    while ($row = mysqli_fetch_assoc($students_res)) {
        $count++;
        echo "  ID: " . $row['id'] . ", Name: " . $row['full_name'] . ", Email: " . $row['email'] . ", Role: " . $row['role'] . "\n";
    }
    if ($count === 0) {
        echo "  NO STUDENTS FOUND IN DATABASE!\n";
    }
} else {
    echo "  ERROR: " . mysqli_error($conn) . "\n";
}

echo "\n\nAdmins in database:\n";
$admins_sql = "SELECT id, full_name, email, role FROM users WHERE role = 'admin' ORDER BY full_name ASC LIMIT 10";
$admins_res = mysqli_query($conn, $admins_sql);

if ($admins_res) {
    $count = 0;
    while ($row = mysqli_fetch_assoc($admins_res)) {
        $count++;
        echo "  ID: " . $row['id'] . ", Name: " . $row['full_name'] . ", Email: " . $row['email'] . ", Role: " . $row['role'] . "\n";
    }
    if ($count === 0) {
        echo "  NO ADMINS FOUND IN DATABASE!\n";
    }
} else {
    echo "  ERROR: " . mysqli_error($conn) . "\n";
}

// Simulate a form submission
echo "\n\n=== Simulating Form Submission ===\n";

$_POST['recipient_type'] = 'specific_student';
$_POST['recipient_id'] = 1;  // Try student ID 1
$_POST['subject'] = 'Test Subject';
$_POST['message'] = 'Test Message';
$_POST['send_notification'] = 'on';
$_POST['send_email'] = 'on';

echo "POST data:\n";
echo "  recipient_type: " . ($_POST['recipient_type'] ?? 'MISSING') . "\n";
echo "  recipient_id: " . ($_POST['recipient_id'] ?? 'MISSING') . "\n";
echo "  subject: " . ($_POST['subject'] ?? 'MISSING') . "\n";

$recipientId = intval(trim($_POST['recipient_id'] ?? '0'));
echo "\nParsed recipient_id: " . $recipientId . "\n";

if ($recipientId > 0) {
    $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'student'";
    echo "Database check query: " . $check_sql . "\n";
    
    $check_res = mysqli_query($conn, $check_sql);
    if ($check_res) {
        $row = mysqli_fetch_assoc($check_res);
        if ($row) {
            echo "FOUND: ID=" . $row['id'] . ", Name=" . $row['full_name'] . ", Role=" . $row['role'] . "\n";
        } else {
            echo "NOT FOUND: Student ID $recipientId with role 'student'\n";
        }
    } else {
        echo "QUERY ERROR: " . mysqli_error($conn) . "\n";
    }
}

echo "\n=== END TEST ===\n";
mysqli_close($conn);
?>
