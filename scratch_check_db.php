<?php
require 'db.php';
$res = $conn->query("SHOW COLUMNS FROM internship_applications");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
