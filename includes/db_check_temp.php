<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "imp_db";
$port = 3306;

$conn = mysqli_connect($host, $user, $pass, $db, $port);
if (!$conn) {
    die("Local Connection failed: " . mysqli_connect_error());
}

$mentor_id = 66;
$student_id = 69;

echo "=== APPLICATIONS OF STUDENT 69 ===\n";
$res = mysqli_query($conn, "SELECT id, user_id, internship_id, status, internship_status FROM internship_applications WHERE user_id = $student_id");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}

// Function to test authorization query
function test_auth($conn, $mentor_id, $student_id, $app_id) {
    $check_sql = "SELECT 1 FROM (
                      SELECT 1 
                      FROM mentor_assignments ma 
                      WHERE ma.mentor_id = ? AND ma.application_id = ? AND ma.status = 'active'
                      
                      UNION ALL
                      
                      SELECT 1 
                      FROM project_teams t
                      JOIN project_team_members tm ON tm.project_team_id = t.id
                      JOIN internship_applications a ON tm.student_id = a.user_id AND t.internship_id = a.internship_id
                      WHERE t.mentor_id = ? AND tm.student_id = ? AND a.id = ?
                  ) as assignments LIMIT 1";
                  
    $stmt = $conn->prepare($check_sql);
    if (!$stmt) {
        echo "Prepare failed: " . $conn->error . "\n";
        return;
    }
    $stmt->bind_param('iiiii', $mentor_id, $app_id, $mentor_id, $student_id, $app_id);
    $stmt->execute();
    $res = $stmt->get_result();
    echo "Auth Check for Mentor $mentor_id, Student $student_id, App ID $app_id: " . ($res->num_rows > 0 ? "AUTHORIZED" : "NOT AUTHORIZED") . "\n";
    $stmt->close();
}

test_auth($conn, $mentor_id, $student_id, 31);
test_auth($conn, $mentor_id, $student_id, 32);

mysqli_close($conn);
?>
