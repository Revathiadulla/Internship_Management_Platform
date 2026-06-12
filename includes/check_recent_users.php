<?php
require __DIR__ . '/db.php';
$res = mysqli_query($conn, "SELECT id, email, role, status, is_active FROM users ORDER BY id DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
