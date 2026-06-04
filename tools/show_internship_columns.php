<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../db.php';
$tables = ['internship_applications', 'internships'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM $table");
    if (!$result) {
        echo "ERROR: could not inspect $table\n";
        continue;
    }
    echo "TABLE: $table\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . '\n';
    }
    echo "\n";
}
