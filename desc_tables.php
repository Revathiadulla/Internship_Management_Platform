<?php
require 'db.php';
function desc($table) {
    global $conn;
    echo "\n--- $table ---\n";
    $r = mysqli_query($conn, "DESCRIBE $table");
    if($r) {
        while($row = mysqli_fetch_assoc($r)) echo $row['Field'] . "\n";
    } else {
        echo mysqli_error($conn);
    }
}
desc('internship_applications');
desc('project_teams');
desc('internships');
desc('job_postings');
