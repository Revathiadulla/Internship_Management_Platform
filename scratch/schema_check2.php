<?php
require 'c:/xampp/htdocs/IMP/db.php';
$r = mysqli_query($conn, 'SHOW COLUMNS FROM internship_applications');
while ($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
