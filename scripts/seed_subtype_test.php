<?php
require_once __DIR__ . '/../db.php';

$subtype = 'Web Development';
$difficulty = 'Medium';
// Check if already exists
$chk = mysqli_prepare($conn, "SELECT id FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ? LIMIT 1");
mysqli_stmt_bind_param($chk, 'ss', $subtype, $difficulty);
mysqli_stmt_execute($chk);
mysqli_stmt_store_result($chk);
if (mysqli_stmt_num_rows($chk) > 0) {
    echo "Subtype test already exists for $subtype / $difficulty\n";
    mysqli_stmt_close($chk);
    exit(0);
}
mysqli_stmt_close($chk);

$ins = mysqli_prepare($conn, "INSERT INTO subtype_tests (project_type, project_subtype, skills, difficulty_level, num_questions, status) VALUES (?, ?, ?, ?, ?, 'Active')");
$project_type = 'Development';
$skills = 'HTML,CSS,JavaScript';
$num_q = 3;
// bind with proper variables
mysqli_stmt_bind_param($ins, 'ssssi', $project_type, $subtype, $skills, $difficulty, $num_q);
if (!mysqli_stmt_execute($ins)) {
    echo "Failed to create subtype_test: " . mysqli_error($conn) . "\n";
    exit(1);
}
$test_id = mysqli_insert_id($conn);
mysqli_stmt_close($ins);

// Insert sample questions
$questions = [
    ['What does CSS stand for?', 'Cascading Style Sheets', 'Computer Style Sheets', 'Creative Style System', 'Cascading Simple Styles', 'A'],
    ['Which HTML tag is used for the largest heading?', '<h1>', '<h6>', '<heading>', '<top>', 'A'],
    ['Which JS method converts JSON string to object?', 'JSON.parse()', 'JSON.stringify()', 'parseJSON()', 'toObject()', 'A']
];
// Insert questions (set question_bank_id = 0 as this is a seeded test)
$insq = mysqli_prepare($conn, "INSERT INTO subtype_test_questions (subtype_test_id, question_bank_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$qb_id = 0;
foreach ($questions as $q) {
    mysqli_stmt_bind_param($insq, 'iissssss', $test_id, $qb_id, $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
    mysqli_stmt_execute($insq);
}
mysqli_stmt_close($insq);

echo "Seeded subtype_test id={$test_id} with " . count($questions) . " questions.\n";
