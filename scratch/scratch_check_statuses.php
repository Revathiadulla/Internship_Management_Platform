<?php
include 'db.php';
$res = mysqli_query($conn, 'SELECT DISTINCT status FROM internship_applications');
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['status'] . "\n";
}
?>
