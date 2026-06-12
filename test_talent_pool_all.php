<?php
require 'includes/db.php';

$where = ["1=1"];

$search = '';
$domain = '';
$stack = '';
$score = '';
$certification = '';

// Let's just output the exact candidates that would be shown if we don't have $where filtering
$data_query = "
    SELECT c.id, c.full_name, c.current_status, a.status as app_status, a.performance_score 
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LIMIT 10
";
$res = mysqli_query($conn, $data_query);
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
