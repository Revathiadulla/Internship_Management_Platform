<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT internship_id, COUNT(*) as cnt FROM internship_applications GROUP BY internship_id");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Internship ID: {$row['internship_id']}, Count: {$row['cnt']}\n";
}
