<?php
require_once __DIR__ . '/../includes/db.php';
$res = mysqli_query($conn, "SELECT id, project_subtype, difficulty_level, status FROM subtype_tests ORDER BY id DESC LIMIT 50");
if (!$res) { echo "ERROR: " . mysqli_error($conn) . "\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['id'] . ' | ' . ($r['project_subtype'] ?? 'NULL') . ' | ' . ($r['difficulty_level'] ?? 'NULL') . ' | ' . ($r['status'] ?? 'NULL') . "\n";
}
