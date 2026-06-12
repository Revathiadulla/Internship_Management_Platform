<?php
require_once __DIR__ . '/../includes/db.php';
$test_id = 1;
$questions = [
    ['What does CSS stand for?', 'Cascading Style Sheets', 'Computer Style Sheets', 'Creative Style System', 'Cascading Simple Styles', 'A'],
    ['Which HTML tag is used for the largest heading?', '<h1>', '<h6>', '<heading>', '<top>', 'A'],
    ['Which JS method converts JSON string to object?', 'JSON.parse()', 'JSON.stringify()', 'parseJSON()', 'toObject()', 'A']
];
$insq = mysqli_prepare($conn, "INSERT INTO subtype_test_questions (subtype_test_id, question_text, option_a, option_b, option_c, option_d, correct_option, question_bank_id) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
$added = 0;
foreach ($questions as $q) {
    mysqli_stmt_bind_param($insq, 'issssss', $test_id, $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
    if (mysqli_stmt_execute($insq)) $added++;
}
mysqli_stmt_close($insq);
echo "Inserted $added questions for subtype_test_id=$test_id\n";
