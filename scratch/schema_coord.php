<?php
require 'c:/xampp/htdocs/IMP/db.php';
$r = mysqli_query($conn, 'SHOW COLUMNS FROM coordinator_assignments');
if($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        echo $row['Field'] . ' | ' . $row['Type'] . "\n";
    }
} else {
    echo "Table does not exist or error: " . mysqli_error($conn);
}
