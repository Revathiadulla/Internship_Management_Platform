<?php
require __DIR__ . '/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM users WHERE Field='password'");
$row = mysqli_fetch_assoc($res);
print_r($row);
?>
