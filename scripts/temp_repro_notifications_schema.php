<?php
require __DIR__ . '/includes/db.php';
if (empty($conn)) {
    echo "no-conn\n";
    exit(1);
}
$res = mysqli_query($conn, 'SHOW COLUMNS FROM notifications');
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
