<?php
require_once __DIR__ . '/includes/db.php';

$errors = [];
$actions = [];

function runSql(mysqli $conn, string $sql, string $label, array &$actions, array &$errors): void {
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        $errors[] = "$label failed: " . mysqli_error($conn);
        return;
    }
    $actions[] = "$label OK";
}

$cols = [
    'internship_applications' => ['test_status', 'test_score', 'test_result', 'test_answers', 'test_submitted_date', 'test_completed_at', 'test_attempts', 'max_attempts']
];

foreach ($cols as $table => $columns) {
    foreach ($columns as $column) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check && mysqli_num_rows($check) > 0) {
            runSql($conn, "ALTER TABLE `$table` DROP COLUMN `$column`", "Dropped column $table.$column", $actions, $errors);
        } else {
            $actions[] = "Column $table.$column already absent";
        }
    }
}

$tables = ['test_attempt_history', 'subtype_test_questions', 'subtype_tests', 'test_questions'];
foreach ($tables as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if ($check && mysqli_num_rows($check) > 0) {
        runSql($conn, "DROP TABLE IF EXISTS `$table`", "Dropped table $table", $actions, $errors);
    } else {
        $actions[] = "Table $table already absent";
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Test module cleanup complete.\n";
echo "Actions:\n";
foreach ($actions as $action) {
    echo "- $action\n";
}
if ($errors) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
