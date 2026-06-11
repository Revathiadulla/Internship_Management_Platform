<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT * FROM internships WHERE id = 12");
if ($row = mysqli_fetch_assoc($res)) {
    echo "Found internship 12:\n";
    print_r($row);
} else {
    echo "Internship 12 NOT found in internships table!\n";
}
