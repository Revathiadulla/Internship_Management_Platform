<?php
require_once __DIR__ . '/../db.php';

// Set a dummy coordinator ID for testing. If there are no coordinators, we will search for one.
$coord_res = mysqli_query($conn, "SELECT id, email FROM users WHERE role = 'coordinator' LIMIT 1");
$coord = mysqli_fetch_assoc($coord_res);
if (!$coord) {
    echo "No coordinator user found in DB! Using dummy ID 0\n";
    $coordinator_id = 0;
} else {
    echo "Testing using coordinator: {$coord['email']} (ID: {$coord['id']})\n";
    $coordinator_id = intval($coord['id']);
}

$selected_subtype = ''; // test with empty subtype first

echo "\n--- RUNNING 8 COUNT QUERIES ---\n";

// 1. Created projects
$created_projects_count = 0;
$q_created = mysqli_query($conn, "SELECT COUNT(*) as c FROM internships WHERE coordinator_id = $coordinator_id AND is_deleted = 0" . (!empty($selected_subtype) ? " AND project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_created) {
    $row = mysqli_fetch_assoc($q_created);
    $created_projects_count = intval($row['c'] ?? 0);
    echo "Created Projects Count: $created_projects_count\n";
} else {
    echo "Error - Created Projects: " . mysqli_error($conn) . "\n";
}

// 2. Pending applications
$pending_applications_count = 0;
$q_pending_app = mysqli_query($conn, "
    SELECT COUNT(*) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.coordinator_id = $coordinator_id 
      AND a.status IN ('Applied', 'Pending', 'Verified', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approval Pending', 'Forwarded to HOD', 'HOD Approved')
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_pending_app) {
    $row = mysqli_fetch_assoc($q_pending_app);
    $pending_applications_count = intval($row['c'] ?? 0);
    echo "Pending Applications Count: $pending_applications_count\n";
} else {
    echo "Error - Pending Applications: " . mysqli_error($conn) . "\n";
}

// 3. Selected students
$selected_students_count = 0;
$q_sel_stud = mysqli_query($conn, "
    SELECT COUNT(*) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.coordinator_id = $coordinator_id 
      AND a.status = 'Selected'
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_sel_stud) {
    $row = mysqli_fetch_assoc($q_sel_stud);
    $selected_students_count = intval($row['c'] ?? 0);
    echo "Selected Students Count: $selected_students_count\n";
} else {
    echo "Error - Selected Students: " . mysqli_error($conn) . "\n";
}

// 4. Assigned teams
$assigned_teams_count = 0;
$q_teams = mysqli_query($conn, "
    SELECT COUNT(*) as c 
    FROM project_teams t
    JOIN internships i ON t.internship_id = i.id
    WHERE i.coordinator_id = $coordinator_id
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_teams) {
    $row = mysqli_fetch_assoc($q_teams);
    $assigned_teams_count = intval($row['c'] ?? 0);
    echo "Assigned Teams Count: $assigned_teams_count\n";
} else {
    echo "Error - Assigned Teams: " . mysqli_error($conn) . "\n";
}

// 5. Active projects
$active_projects_count = 0;
$q_act_proj = mysqli_query($conn, "
    SELECT COUNT(*) as c 
    FROM internships 
    WHERE coordinator_id = $coordinator_id 
      AND status = 'Active' 
      AND is_deleted = 0
" . (!empty($selected_subtype) ? " AND project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_act_proj) {
    $row = mysqli_fetch_assoc($q_act_proj);
    $active_projects_count = intval($row['c'] ?? 0);
    echo "Active Projects Count: $active_projects_count\n";
} else {
    echo "Error - Active Projects: " . mysqli_error($conn) . "\n";
}

// 6. Assigned mentors
$assigned_mentors_count = 0;
$q_mentors = mysqli_query($conn, "
    SELECT COUNT(DISTINCT t.mentor_id) as c 
    FROM project_teams t
    JOIN internships i ON t.internship_id = i.id
    WHERE i.coordinator_id = $coordinator_id
      AND t.mentor_id IS NOT NULL
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_mentors) {
    $row = mysqli_fetch_assoc($q_mentors);
    $assigned_mentors_count = intval($row['c'] ?? 0);
    echo "Assigned Mentors Count: $assigned_mentors_count\n";
} else {
    echo "Error - Assigned Mentors: " . mysqli_error($conn) . "\n";
}

// 7. Pending daily logs calculation
$active_interns = 0;
$q_active = mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.user_id) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status IN ('Started','Internship Started','Active Intern','Internship Active','Selected', 'Project Assigned')
      AND i.coordinator_id = $coordinator_id
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_active) {
    $row = mysqli_fetch_assoc($q_active);
    $active_interns = intval($row['c'] ?? 0);
}
$logged_today = 0;
$q_logged = mysqli_query($conn, "
    SELECT COUNT(DISTINCT d.user_id) as c 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.log_date = CURDATE() 
      AND i.coordinator_id = $coordinator_id
      AND i.is_deleted = 0
" . (!empty($selected_subtype) ? " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $selected_subtype) . "'" : ""));
if ($q_logged) {
    $row = mysqli_fetch_assoc($q_logged);
    $logged_today = intval($row['c'] ?? 0);
}
$pending_logs = max(0, $active_interns - $logged_today);
echo "Pending Logs Count: $pending_logs (Active: $active_interns, Logged Today: $logged_today)\n";


echo "\n--- RUNNING RECENT ACTIVITY FEED QUERY ---\n";
$recent_activities = [];
$activity_sql = "
    SELECT * FROM (
        SELECT 
            'application' AS activity_type,
            a.id AS ref_id,
            a.full_name AS primary_name,
            i.title AS detail_name,
            a.status AS extra_info,
            a.applied_date AS activity_time
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.is_deleted = 0 AND (? = '' OR i.project_subtype = ?)

        UNION ALL

        SELECT 
            'team' AS activity_type,
            t.id AS ref_id,
            t.team_name AS primary_name,
            i.title AS detail_name,
            t.status AS extra_info,
            t.created_at AS activity_time
        FROM project_teams t
        JOIN internships i ON t.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.is_deleted = 0 AND (? = '' OR i.project_subtype = ?)

        UNION ALL

        SELECT 
            'assignment' AS activity_type,
            ptm.id AS ref_id,
            u.full_name AS primary_name,
            t.team_name AS detail_name,
            i.title AS extra_info,
            ptm.created_at AS activity_time
        FROM project_team_members ptm
        JOIN project_teams t ON ptm.project_team_id = t.id
        JOIN internships i ON t.internship_id = i.id
        JOIN users u ON ptm.student_id = u.id
        WHERE i.coordinator_id = ? AND i.is_deleted = 0 AND (? = '' OR i.project_subtype = ?)

        UNION ALL

        SELECT 
            'log' AS activity_type,
            d.id AS ref_id,
            u.full_name AS primary_name,
            d.tasks_completed AS detail_name,
            CAST(d.time_spent AS CHAR) AS extra_info,
            d.created_at AS activity_time
        FROM daily_logs d
        JOIN users u ON d.user_id = u.id
        JOIN internships i ON d.internship_id = i.id
        WHERE i.coordinator_id = ? AND i.is_deleted = 0 AND (? = '' OR i.project_subtype = ?)
    ) AS combined_activity
    ORDER BY activity_time DESC
    LIMIT 8
";
$activity_stmt = mysqli_prepare($conn, $activity_sql);
if ($activity_stmt) {
    mysqli_stmt_bind_param($activity_stmt, "ississississ", 
        $coordinator_id, $selected_subtype, $selected_subtype,
        $coordinator_id, $selected_subtype, $selected_subtype,
        $coordinator_id, $selected_subtype, $selected_subtype,
        $coordinator_id, $selected_subtype, $selected_subtype
    );
    mysqli_stmt_execute($activity_stmt);
    $activity_res = mysqli_stmt_get_result($activity_stmt);
    while ($act = mysqli_fetch_assoc($activity_res)) {
        $recent_activities[] = $act;
    }
    mysqli_stmt_close($activity_stmt);
    echo "Successfully retrieved " . count($recent_activities) . " activity items:\n";
    foreach ($recent_activities as $act) {
        echo "  - Type: {$act['activity_type']} | Primary: {$act['primary_name']} | Detail: " . substr($act['detail_name'], 0, 30) . " | Extra: {$act['extra_info']} | Time: {$act['activity_time']}\n";
    }
} else {
    echo "Error - Recent Activity Query: " . mysqli_error($conn) . "\n";
}
