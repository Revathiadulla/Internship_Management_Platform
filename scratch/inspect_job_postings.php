<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT * FROM job_postings WHERE id = 12");
if ($row = mysqli_fetch_assoc($res)) {
    echo "Found job posting 12:\n";
    print_r($row);
} else {
    echo "Job posting 12 NOT found in job_postings table!\n";
}

$res2 = mysqli_query($conn, "SELECT id, title, company_id FROM job_postings");
echo "\nAll job postings:\n";
while ($row = mysqli_fetch_assoc($res2)) {
    echo "ID: {$row['id']}, Title: {$row['title']}\n";
}
