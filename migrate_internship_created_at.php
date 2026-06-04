<?php
require 'db.php';

// Check if created_at column exists in internships table
$check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'created_at'");
if (mysqli_num_rows($check) == 0) {
    // Add created_at column
    $sql = "ALTER TABLE internships ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (mysqli_query($conn, $sql)) {
        echo "Successfully added created_at column.\n";
    } else {
        echo "Error adding created_at column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "created_at column already exists.\n";
}

// Update existing NULL or empty rows using submission_date fallback if present
$update = "UPDATE internships SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'";
if (mysqli_query($conn, $update)) {
    echo "Successfully updated existing NULL created_at rows.\n";
} else {
    echo "Error updating existing rows: " . mysqli_error($conn) . "\n";
}

// Ensure the creation script does not use submission_date instead of created_at
echo "Migration finished.\n";
?>
