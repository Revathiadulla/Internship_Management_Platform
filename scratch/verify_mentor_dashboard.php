<?php
require_once __DIR__ . '/../db.php';

// Simulate Rajesh Kumar (ID 66)
$mentor_id = 66;

echo "=== VERIFYING COUNTS FOR MENTOR Rajesh Kumar (ID 66) ===\n";

// 1. Assigned Interns Count
$assigned_interns_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) AS cnt FROM (
        SELECT ptm.student_id 
        FROM project_teams t 
        JOIN project_team_members ptm ON ptm.project_team_id = t.id 
        WHERE t.mentor_id = ?
        
        UNION
        
        SELECT ia.user_id AS student_id 
        FROM internship_applications ia 
        WHERE ia.mentor_id = ? 
          AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
        
        UNION
        
        SELECT ma.student_id 
        FROM mentor_assignments ma 
        WHERE ma.mentor_id = ? AND ma.status = 'active'
    ) AS assigned
");
$assigned_interns_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$assigned_interns_stmt->execute();
$assigned_interns_row = $assigned_interns_stmt->get_result()->fetch_assoc();
$assigned_interns = intval($assigned_interns_row['cnt'] ?? 0);
$assigned_interns_stmt->close();
echo "Assigned Interns: $assigned_interns (Expected: 2)\n";

// 2. Active Projects Count
$projects_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT internship_id) AS cnt FROM (
        SELECT internship_id 
        FROM project_teams 
        WHERE mentor_id = ? AND internship_id > 0
        
        UNION
        
        SELECT COALESCE(assigned_project_id, internship_id) AS internship_id 
        FROM internship_applications 
        WHERE mentor_id = ? 
          AND status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
          AND COALESCE(assigned_project_id, internship_id) > 0
          
        UNION
        
        SELECT COALESCE(project_id, internship_id) AS internship_id 
        FROM mentor_assignments ma 
        WHERE ma.mentor_id = ? AND ma.status = 'active' AND COALESCE(project_id, internship_id) > 0
    ) AS proj
");
$projects_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$projects_stmt->execute();
$projects_row = $projects_stmt->get_result()->fetch_assoc();
$active_projects = intval($projects_row['cnt'] ?? 0);
$projects_stmt->close();
echo "Active Projects: $active_projects (Expected: 1)\n";

// 3. Pending Daily Logs Count
$logs_stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM daily_logs dl 
    WHERE LOWER(dl.status) IN ('submitted', 'pending_review') 
      AND dl.user_id IN (
          SELECT ptm.student_id 
          FROM project_teams t 
          JOIN project_team_members ptm ON ptm.project_team_id = t.id 
          WHERE t.mentor_id = ?
          
          UNION
          
          SELECT ia.user_id AS student_id 
          FROM internship_applications ia 
          WHERE ia.mentor_id = ? 
            AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
            
          UNION
          
          SELECT ma.student_id 
          FROM mentor_assignments ma 
          WHERE ma.mentor_id = ? AND ma.status = 'active'
      )
");
$logs_stmt->bind_param('iii', $mentor_id, $mentor_id, $mentor_id);
$logs_stmt->execute();
$logs_row = $logs_stmt->get_result()->fetch_assoc();
$pending_logs = intval($logs_row['cnt'] ?? 0);
$logs_stmt->close();
echo "Pending Daily Logs: $pending_logs\n";

// 4. Assigned Projects List
echo "\n=== ASSIGNED PROJECTS LIST ===\n";
$dashboard_projects = [];
$team_ids_added = [];
$team_names_added = [];

$team_sql = "SELECT t.id, t.team_name, t.status, t.internship_id, i.title AS project_title, i.project_type, i.project_subtype, i.technology_stack, i.duration, i.start_date, i.end_date, u.full_name AS mentor_name
             FROM project_teams t
             LEFT JOIN internships i ON t.internship_id = i.id
             LEFT JOIN users u ON t.mentor_id = u.id
             WHERE t.mentor_id = ?
             ORDER BY COALESCE(i.title, t.team_name) ASC, t.team_name ASC";
$team_stmt = $conn->prepare($team_sql);
if ($team_stmt) {
    $team_stmt->bind_param('i', $mentor_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    while ($row = $team_result->fetch_assoc()) {
        $team_id = intval($row['id']);
        $team_name = trim($row['team_name'] ?? '');
        
        $member_sql = "SELECT COUNT(*) AS student_count FROM project_team_members WHERE project_team_id = ?";
        $member_stmt = $conn->prepare($member_sql);
        $member_count = 0;
        if ($member_stmt) {
            $member_stmt->bind_param('i', $team_id);
            $member_stmt->execute();
            $member_result = $member_stmt->get_result();
            if ($member_row = $member_result->fetch_assoc()) {
                $member_count = intval($member_row['student_count'] ?? 0);
            }
            $member_stmt->close();
        }

        $dashboard_projects[] = [
            'team_id' => $team_id,
            'team_name' => $team_name ?: 'Project Team',
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_type' => $row['project_type'] ?: 'General',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'student_count' => $member_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
            'assigned_date' => $row['start_date'] ?: '—',
        ];
        
        if ($team_id > 0) {
            $team_ids_added[$team_id] = true;
        }
        if ($team_name !== '') {
            $team_names_added[strtolower($team_name)] = true;
        }
    }
    $team_stmt->close();
}

$app_teams_sql = "SELECT DISTINCT ia.team_name, ia.team_status, ia.internship_id, ia.internship_name, 
                          COALESCE(i.title, ia.internship_name) as project_title,
                          COALESCE(i.project_type, 'General') as project_type,
                          COALESCE(i.project_subtype, ia.applied_subtype, 'General') as project_subtype,
                          COALESCE(i.technology_stack, ia.tech_stack, '') as technology_stack,
                          COALESCE(i.duration, ia.internship_duration, '—') as duration,
                          i.start_date, i.end_date,
                          ia.team_id, u.full_name AS mentor_name, MIN(ia.applied_date) as assigned_date
                  FROM internship_applications ia
                  LEFT JOIN internships i ON ia.internship_id = i.id
                  LEFT JOIN users u ON ia.mentor_id = u.id
                  WHERE ia.mentor_id = ? 
                    AND ia.status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
                    AND ia.team_name IS NOT NULL AND ia.team_name != ''
                  GROUP BY ia.team_name, ia.internship_id";
$app_teams_stmt = $conn->prepare($app_teams_sql);
if ($app_teams_stmt) {
    $app_teams_stmt->bind_param('i', $mentor_id);
    $app_teams_stmt->execute();
    $app_teams_result = $app_teams_stmt->get_result();
    while ($row = $app_teams_result->fetch_assoc()) {
        $team_name = trim($row['team_name'] ?? '');
        $team_id = intval($row['team_id'] ?? 0);
        
        if ($team_id > 0 && isset($team_ids_added[$team_id])) {
            continue;
        }
        if (isset($team_names_added[strtolower($team_name)])) {
            continue;
        }

        $cnt_stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as cnt FROM internship_applications WHERE mentor_id = ? AND team_name = ? AND status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')");
        $student_count = 0;
        if ($cnt_stmt) {
            $cnt_stmt->bind_param('is', $mentor_id, $team_name);
            $cnt_stmt->execute();
            $student_count = intval($cnt_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $cnt_stmt->close();
        }

        $dashboard_projects[] = [
            'team_id' => $team_id > 0 ? $team_id : -1,
            'team_name' => $team_name,
            'project_title' => $row['project_title'] ?: 'Assigned Project',
            'project_type' => $row['project_type'] ?: 'General',
            'project_subtype' => $row['project_subtype'] ?: 'General',
            'student_count' => $student_count,
            'mentor_name' => $row['mentor_name'] ?: 'Mentor',
            'assigned_date' => $row['assigned_date'] ?: '—',
        ];
    }
    $app_teams_stmt->close();
}

foreach ($dashboard_projects as $proj) {
    echo "Team: {$proj['team_name']}, Project: {$proj['project_title']}, Type: {$proj['project_type']}, Subtype: {$proj['project_subtype']}, Students: {$proj['student_count']}, Assigned: {$proj['assigned_date']}\n";
}

echo "\nVerification Done.\n";
