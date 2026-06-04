<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT id, subtype_test_id, question_text FROM subtype_test_questions WHERE subtype_test_id=1");
if (!$res) { echo "ERROR: " . mysqli_error($conn) . "\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['id'] . ' | ' . substr($r['question_text'], 0, 120) . "\n";
}
