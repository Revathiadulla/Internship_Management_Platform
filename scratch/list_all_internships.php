<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT id, title, is_deleted FROM internships");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']}, Title: {$row['title']}, Is Deleted: {$row['is_deleted']}\n";
}
