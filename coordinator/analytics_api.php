<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$coordinator_id = intval($_SESSION['user_id']);

// Fetch coordinator's assigned subtypes dynamically
$assigned_subtypes = [];
$sub_stmt = mysqli_prepare($conn, "
    SELECT DISTINCT ps.subtype_name 
    FROM project_subtypes ps 
    JOIN coordinator_assignments ca ON ps.project_type_id = ca.project_type_id 
    WHERE ca.coordinator_id = ? AND ps.status = 'Active'
    ORDER BY ps.subtype_name ASC
");
if ($sub_stmt) {
    mysqli_stmt_bind_param($sub_stmt, "i", $coordinator_id);
    mysqli_stmt_execute($sub_stmt);
    $sub_res = mysqli_stmt_get_result($sub_stmt);
    while ($row = mysqli_fetch_assoc($sub_res)) {
        $assigned_subtypes[] = $row['subtype_name'];
    }
    mysqli_stmt_close($sub_stmt);
}

$subtype = isset($_GET['subtype']) ? trim($_GET['subtype']) : '';
if (empty($subtype) && !empty($assigned_subtypes)) {
    $subtype = $assigned_subtypes[0];
}

$internship_id = isset($_GET['internship_id']) ? intval($_GET['internship_id']) : 0;

// Build condition for internships
$internship_cond = "i.coordinator_id = $coordinator_id";
if ($internship_id > 0) {
    $internship_cond .= " AND i.id = $internship_id";
} else {
    $internship_cond .= " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $subtype) . "'";
}

$team_filter_cond = "(t.created_by = $coordinator_id OR i.coordinator_id = $coordinator_id)";
if ($internship_id > 0) {
    $team_filter_cond .= " AND i.id = $internship_id";
} else {
    $team_filter_cond .= " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $subtype) . "'";
}

// Get user_ids assigned to internships of the selected group
$intern_ids = [];
$uid_res = mysqli_query($conn,
    "SELECT DISTINCT a.user_id FROM internship_applications a
     JOIN internships i ON a.internship_id = i.id
     WHERE $internship_cond
       AND a.status IN ('Started','Internship Started','Active Intern','Selected')");
while ($r = mysqli_fetch_assoc($uid_res)) {
    $intern_ids[] = intval($r['user_id']);
}

$log_where = "";
if (!empty($intern_ids)) {
    $ids_str  = implode(',', $intern_ids);
    $log_where = "AND d.user_id IN ($ids_str)";
} else {
    $log_where = "AND 1=0";
}

if (!function_exists('executeSafeCountQuery')) {
    function executeSafeCountQuery($conn, $query, $metric_name) {
        $res = mysqli_query($conn, $query);
        if (!$res) {
            error_log("IMP Coordinator Analytics API Error ($metric_name): " . mysqli_error($conn));
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        return intval($row['c'] ?? 0);
    }
}

// 8 counts
$created_projects = executeSafeCountQuery($conn, "SELECT COUNT(*) as c FROM internships i WHERE $internship_cond AND i.is_deleted = 0", 'Created Projects');

$pending_applications = executeSafeCountQuery($conn, "
    SELECT COUNT(*) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE $internship_cond 
      AND a.status IN ('Applied', 'Pending', 'Verified', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approval Pending', 'Forwarded to HOD', 'HOD Approved')
      AND i.is_deleted = 0
", 'Pending Applications');

$selected_students = executeSafeCountQuery($conn, "
    SELECT COUNT(DISTINCT ptm.student_id) as c 
    FROM project_team_members ptm
    JOIN project_teams t ON ptm.project_team_id = t.id
    LEFT JOIN internships i ON t.internship_id = i.id
    WHERE $team_filter_cond AND i.is_deleted = 0
", 'Selected Students');

$assigned_teams = executeSafeCountQuery($conn, "
    SELECT COUNT(*) as c 
    FROM project_teams t
    LEFT JOIN internships i ON t.internship_id = i.id
    WHERE $team_filter_cond AND i.is_deleted = 0
", 'Assigned Teams');

$active_projects = executeSafeCountQuery($conn, "
    SELECT COUNT(*) as c 
    FROM internships i
    WHERE $internship_cond 
      AND i.status IN ('Active', 'Approved', 'Admin-Approved') 
      AND i.is_deleted = 0
", 'Active Projects');

$assigned_mentors = executeSafeCountQuery($conn, "
    SELECT COUNT(DISTINCT t.mentor_id) as c 
    FROM project_teams t
    LEFT JOIN internships i ON t.internship_id = i.id
    WHERE $team_filter_cond AND t.mentor_id IS NOT NULL AND i.is_deleted = 0
", 'Assigned Mentors');

$total_interns = executeSafeCountQuery($conn, "
    SELECT COUNT(DISTINCT a.user_id) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE $internship_cond AND i.is_deleted = 0
", 'Total Interns');

$active_interns = executeSafeCountQuery($conn, "
    SELECT COUNT(DISTINCT a.user_id) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status IN ('Started','Internship Started','Active Intern','Internship Active','Selected', 'Project Assigned')
      AND $internship_cond AND i.is_deleted = 0
", 'Active Interns');

$total_logs = executeSafeCountQuery($conn, "
    SELECT COUNT(*) as c 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE $internship_cond AND i.is_deleted = 0
", 'Total Logs');

$logged_today = executeSafeCountQuery($conn, "
    SELECT COUNT(DISTINCT d.user_id) as c 
    FROM daily_logs d
    JOIN internships i ON d.internship_id = i.id
    WHERE d.log_date = CURDATE() AND $internship_cond AND i.is_deleted = 0
", 'Logged Today');

$pending_logs = max(0, $active_interns - $logged_today);
$completed_logs = $total_logs;
$missing_logs = 0; // fallback / legacy compatibility
$unread_count = executeSafeCountQuery($conn, "SELECT COUNT(*) as c FROM notifications WHERE user_id = $coordinator_id AND role = 'coordinator' AND is_read = 0", 'Unread Notifications');

$completed_internships = executeSafeCountQuery($conn, "
    SELECT COUNT(*) as c 
    FROM internship_applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.status = 'Completed' AND $internship_cond AND i.is_deleted = 0
", 'Completed Internships');

$total_prog = $completed_internships + $active_interns;
$completion_pct = $total_prog > 0 ? round(($completed_internships / $total_prog) * 100) : 0;
$assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;
$open_projects = $created_projects; // Open projects is the created projects count for this subtype

// Recent Activities (unified feed)
$recent_activities = [];
$activity_sql = "
    SELECT * FROM (
        SELECT 
            'application' AS activity_type,
            a.id AS ref_id,
            COALESCE(u.full_name, a.full_name) AS primary_name,
            i.title AS detail_name,
            a.status AS extra_info,
            a.applied_date AS activity_time
        FROM internship_applications a
        JOIN internships i ON a.internship_id = i.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $internship_cond AND i.is_deleted = 0

        UNION ALL

        SELECT 
            'team' AS activity_type,
            t.id AS ref_id,
            t.team_name AS primary_name,
            i.title AS detail_name,
            t.status AS extra_info,
            t.created_at AS activity_time
        FROM project_teams t
        LEFT JOIN internships i ON t.internship_id = i.id
        WHERE $team_filter_cond AND (i.is_deleted = 0 OR i.id IS NULL)

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
        LEFT JOIN internships i ON t.internship_id = i.id
        JOIN users u ON ptm.student_id = u.id
        WHERE $team_filter_cond AND (i.is_deleted = 0 OR i.id IS NULL)

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
        WHERE $internship_cond AND i.is_deleted = 0
    ) AS combined_activity
    ORDER BY activity_time DESC
    LIMIT 8
";
$activity_res = mysqli_query($conn, $activity_sql);
if ($activity_res) {
    while ($act = mysqli_fetch_assoc($activity_res)) {
        $recent_activities[] = $act;
    }
} else {
    error_log("IMP Coordinator Analytics API Error (Recent Activities): " . mysqli_error($conn));
}

// ── Daily log trend (last 7 days) ─────────────────────────────────────────────
$trend_q = "SELECT d.log_date, COUNT(*) as cnt, SUM(d.time_spent) as hrs
            FROM daily_logs d
            WHERE d.log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $log_where
            GROUP BY d.log_date ORDER BY d.log_date ASC";
$trend_res = mysqli_query($conn, $trend_q);
$daily_trend = [];
if ($trend_res) {
    while ($tr = mysqli_fetch_assoc($trend_res)) {
        $daily_trend[] = [
            'date'  => date('D', strtotime($tr['log_date'])),
            'count' => intval($tr['cnt']),
            'hours' => round(floatval($tr['hrs']), 1),
        ];
    }
}

// ── Recent logs (last 5) ──────────────────────────────────────────────────────
$recent_q = "SELECT d.log_date, d.tasks_completed, d.time_spent, d.focus_level,
                    u.full_name, sp.course, sp.college_name
             FROM daily_logs d
             JOIN users u ON d.user_id = u.id
             LEFT JOIN student_profiles sp ON u.id = sp.user_id
             WHERE 1=1 $log_where
             ORDER BY d.created_at DESC LIMIT 5";
$recent_res = mysqli_query($conn, $recent_q);
$recent_logs = [];
if ($recent_res) {
    while ($rl = mysqli_fetch_assoc($recent_res)) {
        $recent_logs[] = [
            'full_name'       => $rl['full_name'],
            'course'          => $rl['course'] ?? 'Student',
            'college_name'    => $rl['college_name'] ?? 'University',
            'tasks_completed' => mb_strimwidth($rl['tasks_completed'], 0, 40, '…'),
            'time_spent'      => floatval($rl['time_spent']),
            'focus_level'     => $rl['focus_level'],
            'log_date'        => date('M d', strtotime($rl['log_date'])),
        ];
    }
}

// ── Pipeline projects ─────────────────────────────────────────────────────────
$pipe_q = "
    SELECT p.id, p.title, p.project_subtype, p.duration, p.mode, p.status,
           p.mentor_name, p.team_name, p.assigned_count, p.source
    FROM (
        /* Source 1: Confirmed project_teams linked to internships */
        SELECT i.id, COALESCE(i.title, t.team_name) AS title,
                COALESCE(i.project_subtype, t.project_subtype) AS project_subtype,
                i.duration, i.mode,
                CASE 
                    WHEN i.status IN ('Closed', 'Completed') THEN 'Completed'
                    ELSE 'Active'
                END AS status,
                COALESCE(mu.full_name, 'Mentor Not Assigned') AS mentor_name,
                t.team_name,
                (SELECT COUNT(*) FROM project_team_members ptm WHERE ptm.project_team_id = t.id) AS assigned_count,
                'team' AS source
        FROM project_teams t
        LEFT JOIN internships i ON t.internship_id = i.id
        LEFT JOIN users mu ON t.mentor_id = mu.id
        WHERE t.status IN ('Active', 'Confirmed', 'confirmed', 'active')
          AND $team_filter_cond AND (i.is_deleted = 0 OR i.id IS NULL)

        UNION ALL

        /* Source 2: Internships with Active/Approved status that are not linked to any confirmed teams */
        SELECT i.id, i.title, i.project_subtype, i.duration, i.mode,
               CASE 
                   WHEN i.status IN ('Closed', 'Completed') THEN 'Completed'
                   ELSE 'Available'
               END AS status,
               'Mentor Not Assigned' AS mentor_name,
               NULL AS team_name,
               0 AS assigned_count,
               'internship' AS source
        FROM internships i
        WHERE i.status IN ('Active', 'Approved', 'Admin-Approved', 'Admin Approved')
          AND $internship_cond AND i.is_deleted = 0
           AND i.id NOT IN (
               SELECT DISTINCT internship_id FROM project_teams
           )
    ) p
    GROUP BY p.id
    ORDER BY p.assigned_count DESC, p.id DESC
    LIMIT 12";
$pipe_res = mysqli_query($conn, $pipe_q);
$pipeline_projects = [];
if ($pipe_res) {
    while ($p = mysqli_fetch_assoc($pipe_res)) {
        $pipeline_projects[] = $p;
    }
}
// Deduplicate projects by id, preferring entries with a team (active internships)
$unique_projects = [];
foreach ($pipeline_projects as $proj) {
    $id = $proj['id'];
    // If not set yet, add
    if (!isset($unique_projects[$id])) {
        $unique_projects[$id] = $proj;
    } else {
        // Prefer entry with non-empty team_name
        if (!empty($proj['team_name']) && empty($unique_projects[$id]['team_name'])) {
            $unique_projects[$id] = $proj;
        }
    }
}
$pipeline_projects = array_values($unique_projects);

// ── Internship timeline data (for specific internship) ───────────────────────
$internship_timeline = null;
if ($internship_id > 0) {
    // Fetch internship details
    $tl_res = mysqli_query($conn, "SELECT title, duration FROM internships WHERE id = $internship_id LIMIT 1");
    $tl_row = mysqli_fetch_assoc($tl_res);

    if ($tl_row) {
        // Get earliest start date for this internship
        $start_res = mysqli_query($conn,
            "SELECT MIN(applied_date) as start_date FROM internship_applications
             WHERE internship_id = $internship_id
               AND status IN ('Started','Internship Started','Active Intern','Selected','Completed')");
        $start_row = mysqli_fetch_assoc($start_res);
        $start_date = $start_row['start_date'] ?? null;

        // Parse duration to weeks (e.g. "3 Months" → 12 weeks, "6 Weeks" → 6)
        $duration_str = strtolower($tl_row['duration'] ?? '3 months');
        $total_weeks  = 12; // default
        if (preg_match('/(\d+)\s*month/i', $duration_str, $m)) {
            $total_weeks = intval($m[1]) * 4;
        } elseif (preg_match('/(\d+)\s*week/i', $duration_str, $m)) {
            $total_weeks = intval($m[1]);
        }

        // Calculate elapsed weeks
        $elapsed_weeks = 0;
        $progress_pct  = 0;
        if ($start_date) {
            $start_dt   = new DateTime($start_date);
            $today_dt   = new DateTime();
            $diff_days  = max(0, $start_dt->diff($today_dt)->days);
            $elapsed_weeks = round($diff_days / 7, 1);
            $progress_pct  = min(100, round(($diff_days / ($total_weeks * 7)) * 100));
        }

        // Determine phase label
        $phase_label = 'Onboarding';
        if ($progress_pct >= 85)      $phase_label = 'Offboarding';
        elseif ($progress_pct >= 60)  $phase_label = 'Final Review';
        elseif ($progress_pct >= 35)  $phase_label = 'Mid-term';

        $internship_timeline = [
            'duration'     => $tl_row['duration'],
            'total_weeks'  => $total_weeks,
            'elapsed_weeks'=> $elapsed_weeks,
            'progress_pct' => $progress_pct,
            'phase_label'  => $phase_label,
            'start_date'   => $start_date,
        ];
    }
}

// ── Internship list for dropdown ──────────────────────────────────────────────
function getInternshipList($conn) {
    $res = mysqli_query($conn, "SELECT id, title FROM internships WHERE coordinator_id = " . intval($_SESSION['user_id']) . " ORDER BY title ASC");
    $list = [];
    while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
    return $list;
}

echo json_encode([
    'total_interns'      => $total_interns,
    'active_interns'     => $active_interns,
    'total_logs'         => $total_logs,
    'missing_logs'       => $missing_logs,
    'completed_logs'     => $completed_logs,
    'pending_logs'       => $pending_logs,
    'completion_pct'     => $completion_pct,
    'assigned_pct'       => $assigned_pct,
    'open_projects'      => $open_projects,
    'completed_internships' => $completed_internships,
    'logged_today'          => $logged_today,
    'total_prog_interns'    => $total_prog,
    'internship_title'   => $internship_timeline ? ($tl_row['title'] ?? '') : '',
    'daily_trend'        => $daily_trend,
    'recent_logs'        => $recent_logs,
    'pipeline_projects'  => $pipeline_projects,
    'internship_list'    => getInternshipList($conn),
    'projects_list'      => (function() use ($conn, $subtype) {
        $projects_by_subtype = [];
        $proj_q = "SELECT id, title FROM internships i WHERE i.status='Active' AND i.coordinator_id = " . intval($_SESSION['user_id']);
        if (!empty($subtype)) {
            $proj_q .= " AND i.project_subtype = '" . mysqli_real_escape_string($conn, $subtype) . "'";
        }
        $proj_q .= " ORDER BY i.title ASC";
        $proj_res = mysqli_query($conn, $proj_q);
        while ($pr = mysqli_fetch_assoc($proj_res)) {
            $projects_by_subtype[] = $pr;
        }
        return $projects_by_subtype;
    })(),
    'internship_timeline'=> $internship_timeline,
    'created_projects'     => $created_projects,
    'pending_applications' => $pending_applications,
    'selected_students'    => $selected_students,
    'assigned_teams'       => $assigned_teams,
    'active_projects'      => $active_projects,
    'assigned_mentors'     => $assigned_mentors,
    'unread_notifications' => $unread_count,
    'recent_activities'    => $recent_activities,
]);
?>
