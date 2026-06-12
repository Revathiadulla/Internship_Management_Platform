<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include __DIR__ . '/includes/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>
