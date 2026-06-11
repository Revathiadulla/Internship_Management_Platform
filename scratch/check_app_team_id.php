<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT id, team_name, team_id, mentor_id FROM internship_applications WHERE id IN (40, 45)");
while ($row = mysqli_fetch_assoc($res)) {
    echo "App ID: {$row['id']}, Team Name: {$row['team_name']}, Team ID: " . var_export($row['team_id'], true) . ", Mentor ID: {$row['mentor_id']}\n";
}
