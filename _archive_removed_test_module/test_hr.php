<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$_SERVER['HTTP_HOST'] = 'localhost';

session_start();
// Mock user session so auth.php passes
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'hr';

ob_start();
try {
    include 'hr_applications.php';
} catch (Throwable $e) {
    echo "Fatal error caught: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
$out = ob_get_clean();

if (empty(trim(strip_tags($out)))) {
    echo "Output is completely empty/blank!";
    echo " Raw length: " . strlen($out);
} else {
    echo "Output generated: " . strlen($out) . " bytes.\n";
    // Let's print the first 500 chars to see what it is
    echo substr($out, 0, 500);
}
?>
