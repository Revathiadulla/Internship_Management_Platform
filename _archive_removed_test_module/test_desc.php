<?php
require __DIR__ . '/includes/db.php';
$res = mysqli_query($conn, 'DESCRIBE internships');
if($res){
  while($r = mysqli_fetch_assoc($res)){
    echo $r['Field']."\n";
  }
} else {
  echo mysqli_error($conn);
}
?>
