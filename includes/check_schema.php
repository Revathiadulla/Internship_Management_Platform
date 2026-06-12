<?php
include __DIR__ . '/db.php';
$tables = ['internships', 'internship_applications'];
foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $res = mysqli_query($conn, "DESCRIBE $t");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}
