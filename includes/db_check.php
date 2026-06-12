<?php
$conn = mysqli_connect("localhost", "root", "", "imp_db");
if (!$conn) die(mysqli_connect_error());
function d($t, $c) {
    echo "--- $t ---\n";
    $r = mysqli_query($c, "DESCRIBE $t");
    if($r) {
        while($row = mysqli_fetch_assoc($r)) {
            echo $row["Field"]." | ".$row["Type"]."\n";
        }
    } else {
        echo "Table does not exist or error.\n";
    }
}
d("internship_applications", $conn);
d("student_profiles", $conn);
d("users", $conn);
?>
