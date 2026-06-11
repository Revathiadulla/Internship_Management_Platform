<?php
require_once __DIR__ . '/../db.php';
$tables = ['users', 'internship_applications', 'internships', 'project_teams', 'project_team_members', 'mentor_assignments', 'daily_logs', 'notifications', 'student_notifications'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    $res = mysqli_query($conn, "DESCRIBE `$t`");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "  {$row['Field']} - {$row['Type']} - Null: {$row['Null']} - Key: {$row['Key']}\n";
        }
    } else {
        echo "  [ERROR / DOES NOT EXIST]: " . mysqli_error($conn) . "\n";
    }
    echo "\n";
}
