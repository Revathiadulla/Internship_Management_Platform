<?php
/**
 * Verify and clean up database after normal email simulation run.
 */

include __DIR__ . '/../db.php';

echo "=== Verifying Database State After Normal Email Simulated Run ===\n\n";

// Verify Application 46 status is still Shortlisted
$app46 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = 46"));
if ($app46['status'] === 'Shortlisted') {
    echo "  [OK] Application 46 status remained unchanged ('Shortlisted').\n";
} else {
    echo "  [FAIL] Application 46 status changed to: " . $app46['status'] . "\n";
}

// Verify Application 47 status is still HR Review
$app47 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM internship_applications WHERE id = 47"));
if ($app47['status'] === 'HR Review') {
    echo "  [OK] Application 47 status remained unchanged ('HR Review').\n";
} else {
    echo "  [FAIL] Application 47 status changed to: " . $app47['status'] . "\n";
}

// Verify separate email logs in email_logs
$logs_query = mysqli_query($conn, "SELECT * FROM email_logs WHERE subject = 'Test Normal Email Subject' ORDER BY id ASC");
$logged_count = mysqli_num_rows($logs_query);

if ($logged_count === 2) {
    echo "  [OK] Correctly found 2 separate email logs with subject 'Test Normal Email Subject'.\n";
    while ($log_row = mysqli_fetch_assoc($logs_query)) {
        echo "    Log ID: " . $log_row['id'] . "\n";
        echo "    Recipient: " . $log_row['to_email'] . "\n";
        echo "    Sender ID: " . $log_row['sender_id'] . "\n";
        echo "    Sender Role: " . $log_row['sender_role'] . "\n";
        echo "    Status: " . $log_row['status'] . "\n";
        
        // Delete log
        mysqli_query($conn, "DELETE FROM email_logs WHERE id = " . $log_row['id']);
        echo "    [CLEANUP] Deleted test log entry.\n";
    }
} else {
    echo "  [FAIL] Found " . $logged_count . " email logs, expected 2.\n";
    // Delete any matching logs to clean up
    mysqli_query($conn, "DELETE FROM email_logs WHERE subject = 'Test Normal Email Subject'");
}
