<?php
require_once __DIR__ . '/../includes/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM question_bank");
if (!$res) { echo "ERROR: " . mysqli_error($conn) . "\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | ' . $r['Null'] . ' | ' . ($r['Default'] ?? 'NULL') . "\n";
}
