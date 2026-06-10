<?php
include 'db.php';

echo "=== users table structure ===\n";
$r = mysqli_query($conn, "SHOW COLUMNS FROM users");
while ($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Default:' . $row['Default'] . PHP_EOL;
}

echo "\n=== Sample users (students) ===\n";
$r2 = mysqli_query($conn, "SELECT id, email, role, status, is_active, password FROM users WHERE LOWER(role) = 'student' LIMIT 5");
while ($row = mysqli_fetch_assoc($r2)) {
    $pass_type = (strlen($row['password']) === 60 || substr($row['password'], 0, 4) === '$2y$') ? 'bcrypt' : 'plain';
    echo "ID:{$row['id']} | {$row['email']} | role:{$row['role']} | status:{$row['status']} | is_active:{$row['is_active']} | pass_type:$pass_type\n";
}

echo "\n=== All distinct roles in users table ===\n";
$r3 = mysqli_query($conn, "SELECT DISTINCT role FROM users");
while ($row = mysqli_fetch_assoc($r3)) { echo json_encode($row['role']) . PHP_EOL; }
