<?php
require 'db.php';
$res = mysqli_query($conn, 'SELECT id, duration FROM internships LIMIT 5');
while($r = mysqli_fetch_assoc($res)) print_r($r);

echo "\nDaily Logs Columns:\n";
$res2 = mysqli_query($conn, 'DESCRIBE daily_logs');
if($res2) {
  while($r = mysqli_fetch_assoc($res2)) echo $r['Field'].", ";
} else {
  echo mysqli_error($conn);
}
?>
