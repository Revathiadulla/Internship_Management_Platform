<?php
include __DIR__ . '/../db.php';

echo "=== ALL SUBTYPE TESTS & QUESTION COUNTS ===\n";
$res = mysqli_query($conn, "SELECT t.id, t.project_subtype, t.difficulty_level, t.num_questions, COUNT(q.id) as actual_question_count FROM subtype_tests t LEFT JOIN subtype_test_questions q ON t.id = q.subtype_test_id GROUP BY t.id ORDER BY t.id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
