<?php
include 'db.php';
$sql = "SELECT * FROM internship_applications ORDER BY id DESC LIMIT 5";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    echo "Application ID: " . $row['id'] . "\n";
    foreach ($row as $col => $val) {
        if ($val !== null && $val !== '') {
            echo "  $col: $val\n";
        }
    }
}
?>
