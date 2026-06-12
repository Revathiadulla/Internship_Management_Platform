<?php
require 'includes/db.php';
$q = mysqli_query($conn, "SELECT id, full_name, current_status FROM candidates");
while($row = mysqli_fetch_assoc($q)) {
    print_r($row);
}
