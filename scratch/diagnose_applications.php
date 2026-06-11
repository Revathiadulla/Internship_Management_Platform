<?php
require_once __DIR__ . '/../db.php';

echo "=== APPLICATIONS ===\n";
$res2 = mysqli_query($conn, "SELECT id, user_id, status, internship_id, internship_name, mentor_id, team_name, team_status FROM internship_applications WHERE mentor_id IS NOT NULL OR status = 'Project Assigned'");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "App ID: {$row['id']}, User ID: {$row['user_id']}, Status: {$row['status']}, Intern ID: {$row['internship_id']}, Intern Name: {$row['internship_name']}, Team Name: {$row['team_name']}, Mentor ID: {$row['mentor_id']}, Team Status: {$row['team_status']}\n";
}
