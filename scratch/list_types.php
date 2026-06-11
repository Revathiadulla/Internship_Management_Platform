<?php
require_once __DIR__ . '/../db.php';
echo "=== PROJECT TYPES ===\n";
$res1 = mysqli_query($conn, "SELECT id, type_name FROM project_types");
while ($row = mysqli_fetch_assoc($res1)) {
    echo "ID: {$row['id']} | Type: {$row['type_name']}\n";
}
echo "\n=== PROJECT SUBTYPES ===\n";
$res2 = mysqli_query($conn, "SELECT id, project_type_id, subtype_name, status FROM project_subtypes");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "ID: {$row['id']} | Type ID: {$row['project_type_id']} | Subtype: {$row['subtype_name']} | Status: {$row['status']}\n";
}
