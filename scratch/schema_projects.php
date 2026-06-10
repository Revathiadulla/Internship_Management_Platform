<?php
require 'c:/xampp/htdocs/IMP/db.php';
$tables = ['project_types', 'project_subtypes'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $r = mysqli_query($conn, "SHOW COLUMNS FROM $table");
    if($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            echo $row['Field'] . ' | ' . $row['Type'] . "\n";
        }
    } else {
        echo "Table does not exist or error: " . mysqli_error($conn) . "\n";
    }
}
