<?php
require_once __DIR__ . '/../email_helper.php';

$parsed = normalizeEmailAddresses("first@example.com, second@example.com; third@example.com\nfourth@example.com");
$expected = ['first@example.com', 'second@example.com', 'third@example.com', 'fourth@example.com'];

if ($parsed !== $expected) {
    fwrite(STDERR, "Recipient parsing test failed. Expected: " . json_encode($expected) . " Got: " . json_encode($parsed) . PHP_EOL);
    exit(1);
}

echo "Mail helper recipient parsing tests passed" . PHP_EOL;
