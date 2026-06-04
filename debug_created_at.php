<?php
require 'db.php';
$res = mysqli_query($conn, 'SELECT id, created_at, submission_date FROM internships LIMIT 5');
while ($r = mysqli_fetch_assoc($res)) {
    var_dump($r);
}
?>
