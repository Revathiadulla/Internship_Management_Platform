<?php
include 'db.php';
$res = mysqli_query($conn, 'SHOW COLUMNS FROM users');
while($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
