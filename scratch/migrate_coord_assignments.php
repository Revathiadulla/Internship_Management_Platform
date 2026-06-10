<?php
require 'c:/xampp/htdocs/IMP/db.php';

$sql = "ALTER TABLE coordinator_assignments ADD COLUMN project_subtype_id INT NULL AFTER project_type_id";
if (mysqli_query($conn, $sql)) {
    echo "Successfully added project_subtype_id column.\n";
} else {
    echo "Error or already exists: " . mysqli_error($conn) . "\n";
}
