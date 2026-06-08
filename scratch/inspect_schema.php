<?php
include __DIR__ . '/../db.php';

echo "=== subtype_test_questions ===\n";
$r = mysqli_query($conn, 'SHOW COLUMNS FROM subtype_test_questions');
while($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Key:' . $row['Key'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . PHP_EOL;
}

echo "\n=== test_questions ===\n";
$r2 = mysqli_query($conn, 'SHOW COLUMNS FROM test_questions');
if ($r2) {
    while($row = mysqli_fetch_assoc($r2)) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Key:' . $row['Key'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . PHP_EOL;
    }
}

echo "\n=== question_bank ===\n";
$r3 = mysqli_query($conn, 'SHOW COLUMNS FROM question_bank');
if ($r3) {
    while($row = mysqli_fetch_assoc($r3)) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Key:' . $row['Key'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . PHP_EOL;
    }
}
