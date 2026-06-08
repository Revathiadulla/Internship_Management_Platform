<?php
include __DIR__ . '/../db.php';

// Apply the fix: set DEFAULT 0 on question_bank_id
$check_qbid = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'question_bank_id'");
if ($check_qbid && mysqli_num_rows($check_qbid) > 0) {
    $qbid_info = mysqli_fetch_assoc($check_qbid);
    echo "BEFORE: question_bank_id Default = " . var_export($qbid_info['Default'], true) . "\n";
    
    if ($qbid_info['Default'] === null || $qbid_info['Default'] === '') {
        $result = mysqli_query($conn, "ALTER TABLE subtype_test_questions MODIFY COLUMN question_bank_id INT NOT NULL DEFAULT 0");
        echo "ALTER result: " . ($result ? "SUCCESS" : "FAILED: " . mysqli_error($conn)) . "\n";
    } else {
        echo "Already has default, no change needed.\n";
    }
    
    // Verify after
    $check_after = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'question_bank_id'");
    $after_info = mysqli_fetch_assoc($check_after);
    echo "AFTER: question_bank_id Default = " . var_export($after_info['Default'], true) . "\n";
} else {
    echo "question_bank_id column not found.\n";
}
