<?php
require __DIR__ . '/includes/db.php';

$queries = [
    "ALTER TABLE users ADD COLUMN status ENUM('pending_approval','approved','rejected') DEFAULT 'approved'",
    "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL",
    "ALTER TABLE users ADD COLUMN approved_by INT NULL"
];

foreach ($queries as $q) {
    try {
        if (mysqli_query($conn, $q)) {
            echo "SUCCESS: $q\n";
        } else {
            echo "FAILED: $q\n";
        }
    } catch (Exception $e) {
        echo "SKIPPED (already exists?): " . $e->getMessage() . "\n";
    }
}
?>
