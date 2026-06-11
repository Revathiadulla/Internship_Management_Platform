<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "DESCRIBE job_postings");
while ($row = mysqli_fetch_assoc($res)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

$res2 = mysqli_query($conn, "SELECT * FROM job_postings");
while ($row = mysqli_fetch_assoc($res2)) {
    print_r($row);
}
