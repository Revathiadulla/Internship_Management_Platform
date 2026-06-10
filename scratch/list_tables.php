<?php
require 'c:/xampp/htdocs/IMP/db.php';
$r = mysqli_query($conn, 'SHOW TABLES');
while ($row = mysqli_fetch_row($r)) {
    echo $row[0] . "\n";
}
