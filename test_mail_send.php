<?php
require_once 'email_helper.php';

$debug = '';
$result = sendEmail(
    'your-personal-email@gmail.com',
    'Test Email from IMP',
    '<h3>This is a test email from Render</h3>',
    null,
    $debug
);

echo "<pre>";
var_dump($result);
echo "\n\nDEBUG:\n";
echo htmlspecialchars($debug);
echo "</pre>";