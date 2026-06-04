<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions");
if (!$res) { echo "ERROR: " . mysqli_error($conn) . "\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | ' . $r['Null'] . ' | ' . ($r['Default'] ?? 'NULL') . "\n";
}
