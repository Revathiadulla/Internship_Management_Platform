<?php
require 'includes/db.php';
$q = mysqli_query($conn, "SELECT c.id, c.full_name, c.current_status, a.status, a.performance_score, a.certificate_status FROM candidates c LEFT JOIN internship_applications a ON c.latest_application_id = a.id");
while($r = mysqli_fetch_assoc($q)) print_r($r);
