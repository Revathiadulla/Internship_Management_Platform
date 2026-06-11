<?php
/**
 * End-to-end Simulation of HR Sending Normal Email in Bulk.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1; 
$_SESSION['role'] = 'hr';

// Setup mock POST data for normal email bulk dispatch
$_POST['action'] = 'send_email';
$_POST['selected_ids'] = [46, 47];
$_POST['subject'] = 'Test Normal Email Subject';
$_POST['message'] = "Dear student, this is a standard status update notification.";
$_POST['to'] = 'madhavimacha03@gmail.com, sample@gmail.com';
$_POST['cc'] = '';
$_POST['bcc'] = '';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/IMP/hr_bulk_action.php';

ob_start();
include __DIR__ . '/../hr_bulk_action.php';
$response_json = ob_get_clean();

echo "=== Simulating Bulk Send Normal Email ===\n\n";
echo "Response from hr_bulk_action.php:\n";
echo $response_json . "\n\n";
