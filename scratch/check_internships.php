<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT id, title, project_type, project_subtype, coordinator_id, status, is_deleted FROM internships");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']} | Title: {$row['title']} | Type: {$row['project_type']} | Subtype: {$row['project_subtype']} | Coord: {$row['coordinator_id']} | Status: {$row['status']} | Deleted: {$row['is_deleted']}\n";
}
