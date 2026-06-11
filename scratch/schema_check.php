<?php
require_once __DIR__ . '/../db.php';
$tables = ['internships', 'job_postings', 'project_teams', 'project_team_members', 'team_members', 'mentor_assignments', 'daily_logs'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    try {
        $res = @mysqli_query($conn, "SHOW COLUMNS FROM $t");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                echo $row['Field'] . " (" . $row['Type'] . ")\n";
            }
        } else {
            echo "Table does not exist.\n";
        }
    } catch (Exception $e) {
        echo "Table does not exist or error.\n";
    }
    echo "\n";
}
?>
