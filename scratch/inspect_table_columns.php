<?php
require_once __DIR__ . '/../db.php';

function describe_table($conn, $table) {
    echo "=== DESCRIBE $table ===\n";
    $res = mysqli_query($conn, "DESCRIBE $table");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Error or table does not exist: " . mysqli_error($conn) . "\n";
    }
    echo "\n";
}

describe_table($conn, 'project_teams');
describe_table($conn, 'project_team_members');
describe_table($conn, 'mentor_assignments');
