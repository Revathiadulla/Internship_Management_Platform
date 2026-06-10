<?php
$conn = mysqli_connect('localhost', 'root', '', 'imp_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
$r = mysqli_query($conn, 'SHOW COLUMNS FROM student_profiles');
while ($row = mysqli_fetch_assoc($r)) {
    echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . " | Default: " . ($row['Default'] === null ? 'NULL' : $row['Default']) . " | Extra: " . $row['Extra'] . "\n";
}
?>
