<?php
require_once __DIR__ . '/../includes/db.php';

$tables = ['project_teams', 'project_team_members'];
$results = [];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    $results[$t] = $exists ? 'exists' : 'missing';
}

header('Content-Type: text/plain');
foreach ($results as $t => $status) {
    echo "$t: $status\n";
}

// Exit code: 0 when all present, 1 otherwise
foreach ($results as $s) {
    if ($s === 'missing') {
        exit(1);
    }
}
exit(0);
