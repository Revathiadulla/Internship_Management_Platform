<?php
require_once __DIR__ . '/../db.php';

echo "=== MENTORS IN SYSTEM ===\n";
$res = mysqli_query($conn, "SELECT id, full_name, email, role FROM users WHERE LOWER(role) = 'mentor'");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']}, Name: {$row['full_name']}, Email: {$row['email']}, Role: {$row['role']}\n";
}

echo "\n=== APPLICATIONS WITH MENTOR OR PROJECT STATUS ===\n";
$res2 = mysqli_query($conn, "SELECT id, user_id, status, internship_id, internship_name, mentor_id, team_name FROM internship_applications WHERE mentor_id IS NOT NULL OR status = 'Project Assigned'");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "App ID: {$row['id']}, User ID: {$row['user_id']}, Status: {$row['status']}, Intern Name: {$row['internship_name']}, Team Name: {$row['team_name']}, Mentor ID: {$row['mentor_id']}\n";
}

echo "\n=== PROJECT TEAMS ===\n";
$res3 = mysqli_query($conn, "SELECT * FROM project_teams");
while ($row = mysqli_fetch_assoc($res3)) {
    echo "ID: {$row['id']}, Name: {$row['team_name']}, Mentor ID: {$row['mentor_id']}, Internship ID: {$row['internship_id']}, Status: {$row['status']}\n";
}

echo "\n=== PROJECT TEAM MEMBERS ===\n";
$res4 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM project_team_members"));
echo "Total project team members: {$res4['cnt']}\n";

echo "\n=== MENTOR ASSIGNMENTS ===\n";
$res5 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM mentor_assignments"));
echo "Total mentor assignments: {$res5['cnt']}\n";

echo "\n=== DESCRIBE project_teams ===\n";
$res6 = mysqli_query($conn, "DESCRIBE project_teams");
while ($row = mysqli_fetch_assoc($res6)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== DESCRIBE project_team_members ===\n";
$res7 = mysqli_query($conn, "DESCRIBE project_team_members");
while ($row = mysqli_fetch_assoc($res7)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== DESCRIBE internship_applications ===\n";
$res8 = mysqli_query($conn, "DESCRIBE internship_applications");
while ($row = mysqli_fetch_assoc($res8)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\nDone diagnostics.\n";
