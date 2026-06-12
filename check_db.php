<?php
require 'includes/db.php';
$r = mysqli_query($conn, 'SHOW CREATE TABLE hiring_requests');
print_r(mysqli_fetch_assoc($r));
