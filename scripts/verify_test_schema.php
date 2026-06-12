<?php
include __DIR__ . '/../includes/db.php';
$tables = ['subtype_tests', 'student_scores', 'internship_applications'];
foreach ($tables as $table) {
    echo "TABLE $table\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table");
    if (!$res) {
        echo "ERROR: " . mysqli_error($conn) . "\n\n";
        continue;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $default = $row['Default'] !== null ? ' DEFAULT ' . $row['Default'] : '';
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . $default . "\n";
    }
    echo "\n";
}
