<?php
include "db.php";
$r = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'applied_subtype'");
$row = mysqli_fetch_assoc($r);
if ($row) {
    echo "Column 'applied_subtype' exists!\n";
    print_r($row);
} else {
    echo "Column 'applied_subtype' does NOT exist!\n";
}
?>
