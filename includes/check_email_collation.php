<?php
require __DIR__ . '/db.php';
$res = mysqli_query($conn, "SHOW FULL COLUMNS FROM users WHERE Field='email'");
$row = mysqli_fetch_assoc($res);
print_r($row);
?>
