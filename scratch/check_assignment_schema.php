<?php
include __DIR__ . '/../db.php';

function describe_table($conn, $table) {
    echo "=== Table: $table ===\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "  " . $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
        }
    } else {
        echo "  Table does not exist or error: " . mysqli_error($conn) . "\n";
    }
    echo "\n";
}

describe_table($conn, 'project_team_members');
describe_table($conn, 'project_teams');
describe_table($conn, 'internships');
