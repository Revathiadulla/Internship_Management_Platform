<?php
require __DIR__ . '/includes/db.php';

// Add created_at column if it doesn't exist
$sql = "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
try {
    if (mysqli_query($conn, $sql)) {
        echo "SUCCESS: Added created_at column\n";
    }
} catch (Exception $e) {
    echo "SKIPPED created_at: " . $e->getMessage() . "\n";
}

// Ensure status column exists
$sql_status = "ALTER TABLE users ADD COLUMN status ENUM('pending_approval','approved','rejected') DEFAULT 'approved'";
try {
    if (mysqli_query($conn, $sql_status)) {
        echo "SUCCESS: Added status column\n";
    }
} catch (Exception $e) {
    echo "SKIPPED status: " . $e->getMessage() . "\n";
}

// Update existing users
$sql2 = "UPDATE users SET status = 'approved' WHERE role IN ('student', 'admin', 'company') AND status IS NULL";
if (mysqli_query($conn, $sql2)) {
    echo "Updated students/admin/company to approved.\n";
}

$sql3 = "UPDATE users SET status = 'pending_approval' WHERE role IN ('hr', 'mentor', 'coordinator') AND status IS NULL";
if (mysqli_query($conn, $sql3)) {
    echo "Updated hr/mentor/coordinator to pending_approval.\n";
}

echo "Migration complete.\n";
?>
