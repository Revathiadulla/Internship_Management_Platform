<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "DESCRIBE mentor_assignments");
while ($row = mysqli_fetch_assoc($res)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
