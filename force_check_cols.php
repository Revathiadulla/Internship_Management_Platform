<?php
require 'db.php';

$sql = "SHOW COLUMNS FROM internships";
$res = mysqli_query($conn, $sql);
$cols = [];
while ($row = mysqli_fetch_assoc($res)) {
    $cols[] = $row['Field'];
}

$added = 0;
if (!in_array('admin_remarks', $cols)) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN admin_remarks TEXT NULL");
    $added++;
    echo "Added admin_remarks\n";
}
if (!in_array('reviewed_by', $cols)) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN reviewed_by INT NULL");
    $added++;
    echo "Added reviewed_by\n";
}
if (!in_array('reviewed_at', $cols)) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN reviewed_at DATETIME NULL");
    $added++;
    echo "Added reviewed_at\n";
}

if ($added == 0) {
    echo "Columns already exist.\n";
} else {
    echo "Added missing columns.\n";
}
?>
