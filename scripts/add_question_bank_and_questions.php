<?php
require_once __DIR__ . '/../db.php';
$test_id = 1;
$project_type = 'Development';
$project_subtype = 'Web Development';
$diff = 'Medium';
$bank_questions = [
    ['What does CSS stand for?', 'Cascading Style Sheets', 'Computer Style Sheets', 'Creative Style System', 'Cascading Simple Styles', 'A'],
    ['Which HTML tag is used for the largest heading?', '<h1>', '<h6>', '<heading>', '<top>', 'A'],
    ['Which JS method converts JSON string to object?', 'JSON.parse()', 'JSON.stringify()', 'parseJSON()', 'toObject()', 'A']
];

$insert_bank = mysqli_prepare($conn, "INSERT INTO question_bank (project_type, project_subtype, difficulty_level, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$insert_bank) { echo "ERROR preparing question_bank insert: " . mysqli_error($conn) . "\n"; exit(1); }
$added = 0;
$bank_ids = [];
foreach ($bank_questions as $q) {
    mysqli_stmt_bind_param($insert_bank, 'sssssssss', $project_type, $project_subtype, $diff, $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
    if (mysqli_stmt_execute($insert_bank)) {
        $bank_ids[] = mysqli_insert_id($conn);
        $added++;
    } else {
        echo "Failed to insert bank question: " . mysqli_error($conn) . "\n";
    }
}
mysqli_stmt_close($insert_bank);

if ($added === 0) { echo "No question_bank rows inserted.\n"; exit(1); }

$added_q = 0;
$insq = mysqli_prepare($conn, "INSERT INTO subtype_test_questions (subtype_test_id, question_bank_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if (!$insq) { echo "ERROR preparing subtype_test_questions insert: " . mysqli_error($conn) . "\n"; exit(1); }
foreach ($bank_ids as $idx => $bid) {
    $q = $bank_questions[$idx];
    mysqli_stmt_bind_param($insq, 'iissssss', $test_id, $bid, $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
    if (mysqli_stmt_execute($insq)) $added_q++;
}
mysqli_stmt_close($insq);

echo "Inserted $added bank rows and $added_q subtype_test_questions for test_id=$test_id\n";
