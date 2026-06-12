<?php
require 'includes/db.php';

$where = ["(
    a.status = 'Completed' OR 
    a.status = 'Internship Completed' OR 
    a.status = 'Certificate Issued' OR 
    a.performance_score = 100 OR 
    a.certificate_status = 'Issued' OR 
    c.current_status = 'completed'
)"];
$where_sql = implode(' AND ', $where);

$data_query = "
    SELECT c.full_name, c.current_status, a.status as app_status, a.performance_score, a.certificate_status 
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    WHERE $where_sql
";

$res = mysqli_query($conn, $data_query);
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
echo "Done";
