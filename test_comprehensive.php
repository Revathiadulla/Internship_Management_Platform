<?php
// Comprehensive test to check manual_message.php workflow
require_once 'db.php';

echo "=== COMPREHENSIVE FORM TEST ===\n\n";

// 1. Check users table structure
echo "1. Checking users table structure...\n";
$columns = mysqli_query($conn, "DESCRIBE users");
$col_names = [];
while ($col = mysqli_fetch_assoc($columns)) {
    $col_names[] = $col['Field'];
    if (in_array($col['Field'], ['id', 'email', 'role', 'full_name'])) {
        echo "   ✓ " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}

// 2. Check student count and valid IDs
echo "\n2. Checking students...\n";
$student_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE role = 'student'");
$cnt_row = mysqli_fetch_assoc($student_count);
echo "   Total students: " . $cnt_row['cnt'] . "\n";

$valid_students = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'student' AND full_name IS NOT NULL AND full_name != '' LIMIT 3");
echo "   Sample students with names:\n";
while ($s = mysqli_fetch_assoc($valid_students)) {
    echo "     - ID: " . $s['id'] . ", Name: " . $s['full_name'] . ", Email: " . $s['email'] . "\n";
}

// 3. Test form submission simulation
echo "\n3. Simulating form submission...\n";

// Get first valid student ID
$first_student = mysqli_query($conn, "SELECT id FROM users WHERE role = 'student' LIMIT 1");
$student_row = mysqli_fetch_assoc($first_student);
$test_student_id = intval($student_row['id']);

echo "   Using student ID: " . $test_student_id . "\n";

// Simulate POST data
$_POST['recipient_type'] = 'specific_student';
$_POST['recipient_id'] = $test_student_id;
$_POST['subject'] = 'Test Message';
$_POST['message'] = 'This is a test message.';
$_POST['send_notification'] = 'on';
$_POST['send_email'] = 'on';

$recipientId = intval(trim($_POST['recipient_id'] ?? '0'));
echo "   POST recipient_id parsed as: " . $recipientId . "\n";

if ($recipientId > 0) {
    $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'student'";
    $check_res = mysqli_query($conn, $check_sql);
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $found_user = mysqli_fetch_assoc($check_res);
        echo "   ✓ Student found in database!\n";
        echo "     - ID: " . $found_user['id'] . "\n";
        echo "     - Name: " . $found_user['full_name'] . "\n";
        echo "     - Email: " . $found_user['email'] . "\n";
        echo "     - Role: " . $found_user['role'] . "\n";
        echo "   ✓ Validation would PASS\n";
    } else {
        echo "   ✗ Student NOT found in database\n";
        echo "   ✗ Validation would FAIL\n";
    }
}

// 4. Check if there's an issue with the form field bindings
echo "\n4. Checking HTML form field names...\n";
echo "   Expected form fields:\n";
echo "     - recipient_type (from dropdown or hidden input)\n";
echo "     - recipient_id (from visible dropdown for selected type)\n";
echo "     - subject (from text input)\n";
echo "     - message (from textarea)\n";
echo "     - send_notification (from checkbox)\n";
echo "     - send_email (from checkbox)\n";

// 5. Check admin_send_notification.php Sent tab filtering
echo "\n5. Checking manual_messages table for testing...\n";
$manual_msg_check = mysqli_query($conn, "DESCRIBE manual_messages");
if ($manual_msg_check) {
    echo "   ✓ manual_messages table exists\n";
} else {
    echo "   ✗ manual_messages table does NOT exist\n";
}

echo "\n=== END TEST ===\n";
?>
