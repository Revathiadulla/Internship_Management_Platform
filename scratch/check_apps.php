<?php
require 'db.php';
$res = mysqli_query($conn, "SELECT a.id as app_id, a.internship_id, a.status, a.internship_name, a.applied_subtype, i.id as i_id, i.coordinator_id, i.project_subtype, i.title FROM internship_applications a LEFT JOIN internships i ON a.internship_id = i.id");
if (!$res) die(mysqli_error($conn));
while($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
