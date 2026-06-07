<?php
// test_verification.php - End-to-end verification of HR verification and HOD approval workflow

// Include DB and helpers
define('INCLUDE_CHECK', true);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/workflow_helper.php';
require_once __DIR__ . '/includes/mail_helper.php';
// Ensure status_audit table exists
$conn->query("CREATE TABLE IF NOT EXISTS status_audit (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    entity VARCHAR(50),\n    entity_id INT,\n    old_status VARCHAR(50),\n    new_status VARCHAR(50),\n    extra TEXT NULL,\n    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB;");
function reset_test_data($conn) {
    $tables = ['users', 'student_profiles', 'internship_applications', 'notifications', 'status_audit'];
    // Clean up previous test records to avoid duplicates
    foreach ($tables as $t) {
        $conn->query("DELETE FROM $t");
    }
}

reset_test_data($conn);

// 1. Create test users
$hrEmail = 'test_hr@example.com';
$hodEmail = 'test_hod@example.com';
$studentEmail = 'test_student@example.com';

// Insert HR user
$conn->query("INSERT INTO users (full_name, email, role, password) VALUES ('Test HR', '$hrEmail', 'hr', '')");
$hrId = $conn->insert_id;
// Insert HOD user
$conn->query("INSERT INTO users (full_name, email, role, password) VALUES ('Test HOD', '$hodEmail', 'coordinator', '')");
$hodId = $conn->insert_id;
// Insert Student user
$conn->query("INSERT INTO users (full_name, email, role, password) VALUES ('Test Student', '$studentEmail', 'student', '')");
$studentId = $conn->insert_id;

// Insert student profile (pursuing)
$conn->query("INSERT INTO student_profiles (user_id, student_type, hod_name, hod_email) VALUES ($studentId, 'pursuing', 'Test HOD', '$hodEmail')");

// Insert internship application with verified docs and test score
$conn->query("INSERT INTO internship_applications (user_id, internship_name, status, test_score, aadhaar_status, pan_status, applied_subtype) VALUES ($studentId, 'Test Internship', 'HR Review', 85, 'verified', 'verified', 'Fullstack')");
$appId = $conn->insert_id;

echo "Setup complete. Application ID: $appId\n";

// 2. Simulate HR forwarding to HOD (logic from hr_forward_to_hod.php)
$appRes = $conn->query("SELECT a.*, sp.student_type FROM internship_applications a LEFT JOIN student_profiles sp ON a.user_id = sp.user_id WHERE a.id = $appId");
$app = $appRes->fetch_assoc();
if ($app['aadhaar_status'] !== 'verified' || $app['pan_status'] !== 'verified') {
    die('Docs not verified, cannot forward.');
}
if ($app['student_type'] !== 'pursuing') {
    die('Student not pursuing, cannot forward.');
}
// Generate token (plain)
$hodToken = bin2hex(random_bytes(32));
// Update application
$newStatus = 'Forwarded to HOD';
$newHodStatus = 'pending';
$newFinalStatus = 'pending';
$updateStmt = $conn->prepare('UPDATE internship_applications SET status = ?, hod_status = ?, final_status = ?, hod_token = ? WHERE id = ?');
$updateStmt->bind_param('ssssi', $newStatus, $newHodStatus, $newFinalStatus, $hodToken, $appId);
$updateStmt->execute();
$updateStmt->close();

// Notify student
add_notification($studentId, 'student', 'Application Forwarded to HOD', "Your application has been forwarded to HOD for approval.", 'info');

echo "HR forwarded to HOD. Token: $hodToken\n";

// 3. Simulate HOD approval (approve action)
$appRes = $conn->query("SELECT hod_token FROM internship_applications WHERE id = $appId");
$row = $appRes->fetch_assoc();
if ($row['hod_token'] !== $hodToken) {
    die('Token mismatch!');
}
// Perform approval update
$finalStatus = 'Selected';
$finalHodStatus = 'approved';
$finalFinalStatus = 'selected';
$approveStmt = $conn->prepare('UPDATE internship_applications SET status = ?, hod_status = ?, final_status = ?, hod_approved_at = NOW(), hod_token = NULL WHERE id = ?');
$approveStmt->bind_param('sssi', $finalStatus, $finalHodStatus, $finalFinalStatus, $appId);
$approveStmt->execute();
$approveStmt->close();

// Notify student of approval
add_notification($studentId, 'student', 'Application Approved by HOD', "Your application has been approved by the HOD.", 'info');

echo "HOD approved application.\n";

// 4. Verify final state
$finalRes = $conn->query("SELECT status, hod_status, final_status, hod_token FROM internship_applications WHERE id = $appId");
$final = $finalRes->fetch_assoc();
print_r($final);

// Verify notifications count for student
$notifRes = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $studentId");
$cnt = $notifRes->fetch_assoc();

echo "Student notifications count: {$cnt['cnt']}\n";
?>
