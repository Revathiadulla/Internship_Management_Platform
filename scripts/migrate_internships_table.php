<?php
require __DIR__ . '/includes/db.php';

$columns = [
    'start_date' => 'DATE NULL',
    'end_date' => 'DATE NULL',
    'description' => 'TEXT NULL',
    'admin_remarks' => 'TEXT NULL',
    'reviewed_by' => 'INT NULL',
    'reviewed_at' => 'DATETIME NULL'
];

foreach ($columns as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        $sql = "ALTER TABLE internships ADD COLUMN $col $def";
        if (mysqli_query($conn, $sql)) {
            echo "Added column $col\n";
        } else {
            echo "Error adding column $col: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Column $col already exists.\n";
    }
}
echo "Migration complete.\n";
?>
