<?php
/**
 * Verify and clean up database after simulation run.
 */

include __DIR__ . '/../db.php';

echo "=== Verifying Database State After Simulated Run ===\n\n";

// Check application status and exam link
$app_res = mysqli_query($conn, "SELECT status, exam_link, exam_sent_date FROM internship_applications WHERE id = 46");
$updated_app_data = mysqli_fetch_assoc($app_res);

if ($updated_app_data['status'] === 'Exam Mail Sent') {
    echo "  [OK] Internship Application status updated to 'Exam Mail Sent'.\n";
} else {
    echo "  [FAIL] Internship Application status is: " . $updated_app_data['status'] . "\n";
}

if (!empty($updated_app_data['exam_link'])) {
    echo "  [OK] Exam link stored: " . $updated_app_data['exam_link'] . "\n";
} else {
    echo "  [FAIL] Exam link is empty.\n";
}

// Check history timeline
$hist_res = mysqli_query($conn, "SELECT * FROM application_status_history WHERE application_id = 46 ORDER BY id DESC LIMIT 1");
$hist_row = mysqli_fetch_assoc($hist_res);
if ($hist_row && $hist_row['new_status'] === 'Exam Mail Sent') {
    echo "  [OK] Application status history recorded successfully.\n";
    // Delete test history entry
    mysqli_query($conn, "DELETE FROM application_status_history WHERE id = " . $hist_row['id']);
    echo "  [CLEANUP] Deleted test status history entry.\n";
} else {
    echo "  [FAIL] Application status history not found.\n";
}

// Check student notifications
$notif_res = mysqli_query($conn, "SELECT * FROM student_notifications ORDER BY id DESC LIMIT 1");
$notif_row = mysqli_fetch_assoc($notif_res);
if ($notif_row && $notif_row['title'] === 'Exam Link Sent') {
    echo "  [OK] Student notification created successfully.\n";
    // Delete test notification entry
    mysqli_query($conn, "DELETE FROM student_notifications WHERE id = " . $notif_row['id']);
    echo "  [CLEANUP] Deleted test student notification.\n";
} else {
    echo "  [FAIL] Student notification was not created.\n";
}

// Check email logs
$log_res = mysqli_query($conn, "SELECT * FROM email_logs WHERE application_id = 46 ORDER BY id DESC LIMIT 1");
$log_row = mysqli_fetch_assoc($log_res);
if ($log_row) {
    echo "  [OK] Email log recorded successfully in email_logs.\n";
    echo "    Sender ID: " . $log_row['sender_id'] . "\n";
    echo "    Sender Role: " . $log_row['sender_role'] . "\n";
    echo "    From: " . $log_row['from_email'] . "\n";
    echo "    To: " . $log_row['to_email'] . "\n";
    echo "    Status: " . $log_row['status'] . "\n";
    echo "    Error: " . $log_row['error_message'] . "\n";
    // Delete test email log
    mysqli_query($conn, "DELETE FROM email_logs WHERE id = " . $log_row['id']);
    echo "  [CLEANUP] Deleted test email log.\n";
} else {
    echo "  [FAIL] Email log was not recorded.\n";
}

// Restore original status of candidate
mysqli_query($conn, "UPDATE internship_applications SET status = 'Shortlisted', exam_link = NULL, exam_sent_date = NULL WHERE id = 46");
echo "\n[CLEANUP] Restored Application 46 status to: Shortlisted\n";
