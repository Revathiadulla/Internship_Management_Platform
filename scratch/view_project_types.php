<?php
require 'c:/xampp/htdocs/IMP/db.php';
$r = mysqli_query($conn, 'SELECT * FROM project_types');
while ($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}
echo "----------\n";
$r = mysqli_query($conn, 'SELECT * FROM project_subtypes');
while ($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}
