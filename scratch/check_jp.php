<?php
require 'db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM job_postings");
while($r = mysqli_fetch_assoc($res)) { echo $r['Field'] . "\n"; }
