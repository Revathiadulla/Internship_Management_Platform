<?php
require 'c:/xampp/htdocs/IMP/db.php';
$r = mysqli_query($conn, 'SHOW COLUMNS FROM users');
while ($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | DEFAULT: ' . $row['Default'] . "\n";
}
