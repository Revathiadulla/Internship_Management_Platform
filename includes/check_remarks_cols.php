<?php
require __DIR__ . '/db.php';

$cols = [
    'admin_remarks' => 'TEXT NULL',
    'reviewed_by' => 'INT NULL',
    'reviewed_at' => 'DATETIME NULL'
];

foreach ($cols as $col => $def) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE '$col'");
    if (mysqli_num_rows($res) == 0) {
        mysqli_query($conn, "ALTER TABLE internships ADD COLUMN $col $def");
        echo "Added $col\n";
    } else {
        echo "$col already exists\n";
    }
}
echo "Done.\n";
?>
