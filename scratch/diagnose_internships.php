<?php
require_once __DIR__ . '/../db.php';

echo "=== INTERNSHIPS ===\n";
$res = mysqli_query($conn, "SELECT id, title, project_type, project_subtype, technology_stack, duration, start_date, end_date FROM internships");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']}, Title: {$row['title']}, Type: {$row['project_type']}, Subtype: {$row['project_subtype']}, Stack: {$row['technology_stack']}\n";
}

echo "\n=== DESCRIBE internships ===\n";
$res2 = mysqli_query($conn, "DESCRIBE internships");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
