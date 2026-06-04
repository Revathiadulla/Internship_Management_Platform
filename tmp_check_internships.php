<?php
require_once __DIR__ . '/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM internships");
if (!$res) {
    die('Query error: ' . mysqli_error($conn));
}
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>
