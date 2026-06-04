<?php
require_once __DIR__ . '/db.php';
$res = $conn->query('SHOW CREATE TABLE notifications');
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo 'Error: ' . $conn->error;
}
?>
