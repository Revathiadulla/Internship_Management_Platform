<?php
include 'db.php';
$res = mysqli_query($conn, 'SHOW TABLES');
while($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}
