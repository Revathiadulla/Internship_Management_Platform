<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE `users`");
while ($row = mysqli_fetch_assoc($res)) {
    echo "{$row['Field']} - {$row['Type']} - Null: {$row['Null']} - Key: {$row['Key']}\n";
}
