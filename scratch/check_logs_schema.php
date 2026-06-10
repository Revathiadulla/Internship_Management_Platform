<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query('SHOW CREATE TABLE student_notifications');
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Create Table'] . "\n";
} else {
    echo 'Error: ' . $conn->error . "\n";
}
?>
