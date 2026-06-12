<?php
require __DIR__ . '/includes/db.php';
$email='veenasri.j27@gmail.com';
$password='student123';
$stmt=$conn->prepare('SELECT * FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->bind_param('s',$email);
$stmt->execute();
$result=$stmt->get_result();
if ($result && $result->num_rows > 0) {
    $user=$result->fetch_assoc();
    echo 'found|'.$user['id'].'|'.$user['role'].'|'.$user['status'].'|'.$user['is_active'].'|'.$user['password'].PHP_EOL;
    $stored=$user['password'];
    $ok=false;
    if ($stored !== null && $stored !== '') {
        if (password_get_info($stored)['algo'] !== 0) {
            $ok = password_verify($password, $stored);
        } elseif ($stored === $password) {
            $ok = true;
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
            $ok = hash_equals($stored, md5($password));
        } elseif (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
            $ok = hash_equals($stored, sha1($password));
        } elseif (preg_match('/^[a-f0-9]{64}$/i', $stored)) {
            $ok = hash_equals($stored, hash('sha256', $password));
        } elseif (preg_match('/^sha256\$(.+)$/', $stored, $m)) {
            $ok = hash_equals($m[1], hash('sha256', $password));
        }
    }
    echo 'ok=' . ($ok ? 'yes' : 'no') . PHP_EOL;
} else {
    echo 'not_found'.PHP_EOL;
}
