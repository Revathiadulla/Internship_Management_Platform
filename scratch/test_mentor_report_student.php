<?php
require_once __DIR__ . '/../db.php';

// Mock Rajesh Kumar (ID 66) and student User ID 91 (App ID 40)
$mentor_id = 66;
$student_id = 91;
$app_id = 40;

echo "=== TESTING MENTOR REPORT STUDENT CHECK QUERY ===\n";

$check_sql = "SELECT 1 FROM (
                  SELECT 1 
                  FROM mentor_assignments ma 
                  WHERE ma.mentor_id = ? AND ma.application_id = ? AND ma.status = 'active'
                  
                  UNION ALL
                  
                  SELECT 1 
                  FROM project_teams t
                  JOIN project_team_members tm ON tm.project_team_id = t.id
                  JOIN internship_applications a ON a.user_id = tm.student_id
                  WHERE t.mentor_id = ? AND tm.student_id = ? AND a.id = ?
                  
                  UNION ALL
                  
                  SELECT 1
                  FROM internship_applications a
                  WHERE a.mentor_id = ? AND a.user_id = ? AND a.id = ?
                    AND a.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
              ) as assignments LIMIT 1";

$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    die('Database error: ' . $conn->error);
}
$check_stmt->bind_param('iiiiiiii', $mentor_id, $app_id, $mentor_id, $student_id, $app_id, $mentor_id, $student_id, $app_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo "Check query SUCCEEDED! Mentor is authorized to report student.\n";
} else {
    echo "Check query FAILED! Mentor is NOT authorized.\n";
}
$check_stmt->close();
