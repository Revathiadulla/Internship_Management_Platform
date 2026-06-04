<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include 'db.php';

echo "=== SHOW CREATE TABLE dropout_requests ===\n";
$res = mysqli_query($conn, "SHOW CREATE TABLE dropout_requests");
if ($res && $row = mysqli_fetch_assoc($res)) {
    echo $row['Create Table'] . "\n";
} else {
    echo "dropout_requests table not found or query failed: " . mysqli_error($conn) . "\n";
}

echo "=== SHOW COLUMNS FROM dropout_requests ===\n";
$res2 = mysqli_query($conn, "SHOW COLUMNS FROM dropout_requests");
if ($res2) {
    while ($row = mysqli_fetch_assoc($res2)) {
        print_r($row);
    }
}
?>
