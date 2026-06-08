<?php
include __DIR__ . '/../db.php';

echo "=== TEST 11 ===\n";
$res = mysqli_query($conn, "SELECT * FROM subtype_tests WHERE id = 11");
if ($res) {
    print_r(mysqli_fetch_assoc($res));
}

echo "=== QUESTIONS FOR TEST 11 ===\n";
$qres = mysqli_query($conn, "SELECT * FROM subtype_test_questions WHERE subtype_test_id = 11");
if ($qres) {
    echo "Found " . mysqli_num_rows($qres) . " questions:\n";
    while ($row = mysqli_fetch_assoc($qres)) {
        print_r($row);
    }
} else {
    echo "Query failed: " . mysqli_error($conn) . "\n";
}
