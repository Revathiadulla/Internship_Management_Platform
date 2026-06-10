<?php
include 'db.php';
$res = mysqli_query($conn, 'SELECT id, email, role, password, status, is_active FROM users');
while($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
