<?php
require 'db.php';
$res = mysqli_query($conn, 'SELECT id, email, role, status, is_active, password FROM users WHERE LOWER(role) = "student" ORDER BY id ASC LIMIT 10');
while ($row = mysqli_fetch_assoc($res)) {
    $stored = $row['password'];
    $ok = false;
    if ($stored !== null && $stored !== '') {
        if (password_get_info($stored)['algo'] !== 0) {
            $ok = true;
        }
    }
    echo $row['id'] . '|' . $row['email'] . '|' . $row['role'] . '|' . $row['status'] . '|' . $row['is_active'] . '|' . ($ok ? 'hash_ok' : 'check_needed') . PHP_EOL;
}
