<?php
require_once 'email_helper.php';

$config = getSmtpConfig();

echo "<pre>";
print_r([
    'host' => $config['host'],
    'port' => $config['port'],
    'username' => $config['username'],
    'from_email' => $config['from_email']
]);
echo "</pre>";