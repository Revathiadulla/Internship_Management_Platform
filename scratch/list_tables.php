<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, 'SHOW TABLES');
while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];
    echo "TABLE: $table\n";
    if ($table === 'projects' || $table === 'job_postings' || $table === 'internships' || strpos($table, 'team') !== false || strpos($table, 'mentor') !== false) {
        $cols = mysqli_query($conn, "SHOW COLUMNS FROM $table");
        while ($c = mysqli_fetch_assoc($cols)) {
            echo "  - " . $c['Field'] . "\n";
        }
    }
}
?>
