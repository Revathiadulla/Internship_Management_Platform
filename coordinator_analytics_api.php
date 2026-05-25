<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
include "db.php";
header('Content-Type: application/json');

$subtype = isset($_GET['subtype']) ? trim($_GET['subtype']) : '';
$internship_id = isset($_GET['internship_id']) ? intval($_GET['internship_id']) : 0;

// Build condition for internships
$internship_cond = "1=1";
if ($internship_id > 0) {
    $internship_cond = "i.id = $internship_id";
} elseif (!empty($subtype)) {
    $internship_cond = "i.project_subtype = '" . mysqli_real_escape_string($conn, $subtype) . "'";
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
if ($internship_id > 0 || !empty($subtype)) {
    if (!empty($intern_ids)) {
        $ids_str  = implode(',', $intern_ids);
        $log_where = "AND d.user_id IN ($ids_str)";
    } else {
        $log_where = "AND 1=0";
    }
}

// ── Metrics ───────────────────────────────────────────────────────────────────

// Total interns (all students registered)
$total_interns = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM users WHERE role='student'"))['c'] ?? 0);

// Active interns for selected subtype (or global)
if ($internship_id > 0 || !empty($subtype)) {
    $active_q = "SELECT COUNT(DISTINCT a.user_id) as c 
                 FROM internship_applications a
                 JOIN internships i ON a.internship_id = i.id
                 WHERE a.status IN ('Started','Internship Started','Active Intern','Selected') 
                   AND $internship_cond";
} else {
    $active_q = "SELECT COUNT(DISTINCT a.user_id) as c 
                 FROM internship_applications a
                 WHERE a.status IN ('Started','Internship Started','Active Intern','Selected')";
}
$active_interns = intval(mysqli_fetch_assoc(mysqli_query($conn, $active_q))['c'] ?? 0);

// Total logs
$total_logs = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM daily_logs d WHERE 1=1 $log_where"))['c'] ?? 0);

// Completed logs (submitted today or any day — count all)
$completed_logs = $total_logs;

// Missing logs (active interns of this subtype/project who haven't logged today)
if ($internship_id > 0 || !empty($subtype)) {
    if (!empty($intern_ids)) {
        $ids_str = implode(',', $intern_ids);
        $missing_q = "SELECT COUNT(DISTINCT a.user_id) as c
                      FROM internship_applications a
                      WHERE a.status IN ('Started','Internship Started','Active Intern')
                        AND a.user_id IN ($ids_str)
                        AND a.user_id NOT IN (
                            SELECT DISTINCT user_id FROM daily_logs WHERE log_date = CURDATE()
                        )";
    } else {
        $missing_q = "SELECT 0 as c";
    }
} else {
    $missing_q = "SELECT COUNT(DISTINCT a.user_id) as c
                  FROM internship_applications a
                  WHERE a.status IN ('Started','Internship Started','Active Intern')
                    AND a.user_id NOT IN (
                        SELECT DISTINCT user_id FROM daily_logs WHERE log_date = CURDATE()
                    )";
}
$missing_logs = intval(mysqli_fetch_assoc(mysqli_query($conn, $missing_q))['c'] ?? 0);

// Pending logs = active interns - those who logged today
$logged_today_q = "SELECT COUNT(DISTINCT d.user_id) as c FROM daily_logs d WHERE d.log_date = CURDATE() $log_where";
$logged_today = intval(mysqli_fetch_assoc(mysqli_query($conn, $logged_today_q))['c'] ?? 0);
$pending_logs = max(0, $active_interns - $logged_today);

// Completed internships
if ($internship_id > 0 || !empty($subtype)) {
    $completed_q = "SELECT COUNT(*) as c 
                    FROM internship_applications a 
                    JOIN internships i ON a.internship_id = i.id
                    WHERE a.status = 'Completed' AND $internship_cond";
} else {
    $completed_q = "SELECT COUNT(*) as c FROM internship_applications a WHERE a.status = 'Completed'";
}
$completed_internships = intval(mysqli_fetch_assoc(mysqli_query($conn, $completed_q))['c'] ?? 0);

// Completion %
$total_prog = $completed_internships + $active_interns;
$completion_pct = $total_prog > 0 ? round(($completed_internships / $total_prog) * 100) : 0;

// Assigned %
$assigned_pct = $total_interns > 0 ? round(($active_interns / $total_interns) * 100) : 0;

// Open projects
if ($internship_id > 0 || !empty($subtype)) {
    $open_projects_q = "SELECT COUNT(*) as c FROM internships i WHERE i.status = 'Active' AND $internship_cond";
} else {
    $open_projects_q = "SELECT COUNT(*) as c FROM internships WHERE status = 'Active'";
}
$open_projects = intval(mysqli_fetch_assoc(mysqli_query($conn, $open_projects_q))['c'] ?? 0);

// Internship title (if filtered)
$internship_title = '';
if ($internship_id > 0) {
    $t_res = mysqli_query($conn, "SELECT title FROM internships WHERE id = $internship_id LIMIT 1");
    if ($t_row = mysqli_fetch_assoc($t_res)) $internship_title = $t_row['title'];
}

// ── Daily log trend (last 7 days) ─────────────────────────────────────────────
$trend_q = "SELECT d.log_date, COUNT(*) as cnt, SUM(d.time_spent) as hrs
            FROM daily_logs d
            WHERE d.log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $log_where
            GROUP BY d.log_date ORDER BY d.log_date ASC";
$trend_res = mysqli_query($conn, $trend_q);
$daily_trend = [];
while ($tr = mysqli_fetch_assoc($trend_res)) {
    $daily_trend[] = [
        'date'  => date('D', strtotime($tr['log_date'])),
        'count' => intval($tr['cnt']),
        'hours' => round(floatval($tr['hrs']), 1),
    ];
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

// ── Pipeline projects ─────────────────────────────────────────────────────────
if ($internship_id > 0 || !empty($subtype)) {
    $pipe_q = "SELECT i.id, i.title, i.duration, i.mode, i.status, i.project_subtype, u.full_name as mentor_name 
               FROM internships i 
               LEFT JOIN users u ON i.assigned_mentor = u.id 
               WHERE i.status = 'Active' AND $internship_cond LIMIT 12";
} else {
    $pipe_q = "SELECT i.id, i.title, i.duration, i.mode, i.status, i.project_subtype, u.full_name as mentor_name 
               FROM internships i 
               LEFT JOIN users u ON i.assigned_mentor = u.id 
               WHERE i.status = 'Active' LIMIT 12";
}
$pipe_res = mysqli_query($conn, $pipe_q);
$pipeline_projects = [];
while ($p = mysqli_fetch_assoc($pipe_res)) {
    $ac_res = mysqli_query($conn,
        "SELECT COUNT(*) as c FROM internship_applications
         WHERE internship_id = {$p['id']}
           AND status IN ('Started','Internship Started','Active Intern','Selected')");
    $p['assigned_count'] = intval(mysqli_fetch_assoc($ac_res)['c'] ?? 0);
    $pipeline_projects[] = $p;
}

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
    $res = mysqli_query($conn, "SELECT id, title FROM internships ORDER BY title ASC");
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
    'internship_title'   => $internship_title,
    'daily_trend'        => $daily_trend,
    'recent_logs'        => $recent_logs,
    'pipeline_projects'  => $pipeline_projects,
    'internship_list'    => getInternshipList($conn),
    'projects_list'      => (function() use ($conn, $subtype) {
        $projects_by_subtype = [];
        $proj_q = "SELECT id, title FROM internships WHERE status='Active'";
        if (!empty($subtype)) {
            $proj_q .= " AND project_subtype = '" . mysqli_real_escape_string($conn, $subtype) . "'";
        }
        $proj_q .= " ORDER BY title ASC";
        $proj_res = mysqli_query($conn, $proj_q);
        while ($pr = mysqli_fetch_assoc($proj_res)) {
            $projects_by_subtype[] = $pr;
        }
        return $projects_by_subtype;
    })(),
    'internship_timeline'=> $internship_timeline,
]);
?>
