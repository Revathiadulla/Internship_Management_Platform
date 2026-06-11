<?php
/**
 * Test SMTP and Email Log schema/routing functionality.
 */

include __DIR__ . '/../db.php';
include __DIR__ . '/../email_helper.php';

echo "=== Running Email System Integration Checks ===\n\n";

// 1. Run Column Check
echo "1. Checking and updating email_logs columns...\n";
ensureEmailLogColumns();

$result = mysqli_query($conn, "SHOW COLUMNS FROM email_logs");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[$row['Field']] = $row['Type'];
}

$expected = [
    'from_email',
    'to_email',
    'body',
    'attachment_path',
    'application_id',
    'sender_id',
    'sender_role'
];

$missing = [];
foreach ($expected as $col) {
    if (isset($columns[$col])) {
        echo "  [OK] Column '$col' exists with type: " . $columns[$col] . "\n";
    } else {
        echo "  [FAIL] Column '$col' is missing!\n";
        $missing[] = $col;
    }
}

if (empty($missing)) {
    echo "Dynamic schema columns are all present in email_logs.\n";
} else {
    echo "Failed: missing columns: " . implode(', ', $missing) . "\n";
    exit(1);
}

// 2. Check isSmtpConfigured()
echo "\n2. Verifying SMTP configurations...\n";
if (isSmtpConfigured()) {
    echo "  [OK] SMTP settings are configured successfully.\n";
} else {
    echo "  [FAIL] SMTP settings are incomplete or missing.\n";
    exit(1);
}

// 3. Test sendEmail and logOutboundEmail directly
echo "\n3. Testing outbound email logging details...\n";

// Setup global mock for mail context
$GLOBALS['mail_context'] = [
    'sender_id' => 9999,
    'sender_role' => 'HR_TEST_MEMBER',
    'application_id' => 73,
];

// Let's log a mock email entry
logOutboundEmail([
    'sender_id' => 9999,
    'sender_role' => 'HR_TEST_MEMBER',
    'from_email' => 'imp.webportal2026@gmail.com',
    'to_email' => 'student_test@example.com',
    'subject' => 'Integration test subject',
    'body' => 'Integration test body description',
    'attachment_path' => 'uploads/test.pdf',
    'status' => 'sent',
    'application_id' => 73
]);

// Verify the log was written
$log_query = mysqli_query($conn, "SELECT * FROM email_logs WHERE sender_id = 9999 ORDER BY id DESC LIMIT 1");
if ($log_query && $log_row = mysqli_fetch_assoc($log_query)) {
    echo "  [OK] Successfully wrote email log record to database:\n";
    echo "    sender_id: " . $log_row['sender_id'] . "\n";
    echo "    sender_role: " . $log_row['sender_role'] . "\n";
    echo "    from_email: " . $log_row['from_email'] . "\n";
    echo "    to_email: " . $log_row['to_email'] . "\n";
    echo "    subject: " . $log_row['subject'] . "\n";
    echo "    body: " . $log_row['body'] . "\n";
    echo "    attachment_path: " . $log_row['attachment_path'] . "\n";
    echo "    application_id: " . $log_row['application_id'] . "\n";
    
    // Clean up mock log entry
    mysqli_query($conn, "DELETE FROM email_logs WHERE sender_id = 9999");
} else {
    echo "  [FAIL] Failed to log outbound email details to database.\n";
    exit(1);
}

echo "\n=== ALL CHECKS PASSED SUCCESSFULLY ===\n";
exit(0);
