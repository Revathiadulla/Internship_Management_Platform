<?php
require 'includes/db.php';
$q = mysqli_query($conn, "SELECT DISTINCT current_status FROM candidates");
while ($row = mysqli_fetch_assoc($q)) { echo $row['current_status'] . PHP_EOL; }
