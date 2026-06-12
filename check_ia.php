<?php
require 'includes/db.php';
print_r(mysqli_fetch_assoc(mysqli_query($conn, "SHOW CREATE TABLE internship_applications")));
