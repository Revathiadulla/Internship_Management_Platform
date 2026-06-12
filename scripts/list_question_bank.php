<?php
require_once __DIR__ . '/../includes/db.php';
$res = mysqli_query($conn, "SELECT id, skill, difficulty FROM question_bank ORDER BY id DESC LIMIT 10");
if (!$res) { echo "ERROR: " . mysqli_error($conn) . "\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['id'] . ' | ' . $r['skill'] . ' | ' . $r['difficulty'] . "\n";
}
