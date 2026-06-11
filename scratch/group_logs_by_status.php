<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT status, is_reviewed, COUNT(*) as cnt FROM daily_logs GROUP BY status, is_reviewed");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Status: " . var_export($row['status'], true) . ", Is Reviewed: {$row['is_reviewed']}, Count: {$row['cnt']}\n";
}
