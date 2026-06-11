<?php
/**
 * Safe simulation of missing SMTP configuration error path.
 * Executes target in a subprocess to protect the host script.
 */

$config_file = __DIR__ . '/../config/email_config.php';
$backup_file = __DIR__ . '/../config/email_config.php.bak';

// 1. Back up
copy($config_file, $backup_file);

try {
    // 2. Break config
    file_put_contents($config_file, "<?php\n// Broken config for testing error path\n");

    // 3. Prepare temporary script to execute hr_bulk_action.php with mock session and POST data
    $temp_runner = __DIR__ . '/temp_smtp_test_runner.php';
    $runner_code = <<<'PHP'
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1; 
$_SESSION['role'] = 'hr';

$_POST['action'] = 'send_email';
$_POST['selected_ids'] = [46];
$_POST['subject'] = 'SMTP Error Test';
$_POST['message'] = 'Testing...';
$_POST['to'] = 'madhavimacha03@gmail.com';
$_POST['cc'] = '';
$_POST['bcc'] = '';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/IMP/hr_bulk_action.php';

include __DIR__ . '/../hr_bulk_action.php';
PHP;

    file_put_contents($temp_runner, $runner_code);

    // 4. Run subprocess
    $output = [];
    $retval = 0;
    exec("C:\\xampp\\php\\php.exe " . escapeshellarg($temp_runner), $output, $retval);
    $response_json = implode("\n", $output);

    // Clean up temporary runner
    @unlink($temp_runner);

    echo "=== Simulating SMTP Configuration Missing Error ===\n\n";
    echo "Response from hr_bulk_action.php:\n";
    echo $response_json . "\n\n";

    // Verify response
    $response = json_decode($response_json, true);
    if ($response && $response['success'] === false && $response['title'] === 'SMTP Configuration Missing') {
        echo "  [OK] Successfully returned SMTP configuration missing error JSON!\n";
    } else {
        echo "  [FAIL] SMTP configuration missing error was not returned or structure is incorrect.\n";
        exit(1);
    }

} finally {
    // 5. Always restore original config
    if (file_exists($backup_file)) {
        copy($backup_file, $config_file);
        unlink($backup_file);
        echo "  [INFO] SMTP config restored.\n";
    }
}
