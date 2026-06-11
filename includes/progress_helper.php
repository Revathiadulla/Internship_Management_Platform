<?php
/**
 * progress_helper.php
 * Helper functions to calculate internship progress based strictly on Approved Daily Logs.
 */

/**
 * Converts internship duration string into expected daily logs.
 * Default mapping: 1 Month = 30 Days, 1 Week = 7 Days
 */
function get_expected_daily_logs($duration_str) {
    if (empty($duration_str)) return 90; // Default to 3 months/90 days if not set
    $duration_str = strtolower(trim($duration_str));
    
    // Months
    if (preg_match('/(\d+)\s*(month|months)/i', $duration_str, $matches)) {
        return intval($matches[1]) * 30;
    }
    // Weeks
    if (preg_match('/(\d+)\s*(week|weeks)/i', $duration_str, $matches)) {
        return intval($matches[1]) * 7;
    }
    // Days
    if (preg_match('/(\d+)\s*(day|days)/i', $duration_str, $matches)) {
        return intval($matches[1]);
    }
    
    return 90; // Fallback
}

/**
 * Calculates the internship progress for a student on a specific internship.
 * 
 * @param mysqli $conn Database connection
 * @param int $student_id The user ID of the student
 * @param int $internship_id The ID of the assigned internship project
 * @return array Associative array with 'progress_percentage', 'approved_logs', and 'expected_logs'
 */
function calculate_internship_progress($conn, $student_id, $internship_id) {
    $internship_id = intval($internship_id);
    $student_id = intval($student_id);

    $expected_logs = 90;
    $is_completed = false;
    $is_assigned = true;

    // 1. Fetch internship duration to determine expected logs
    if ($internship_id > 0) {
        $res = mysqli_query($conn, "SELECT duration FROM internships WHERE id = $internship_id LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $expected_logs = get_expected_daily_logs($row['duration']);
        }
    }

    // 2. Fetch application status to see if it's already marked as completed
    $app_res = mysqli_query($conn, "SELECT status, internship_status FROM internship_applications WHERE user_id = $student_id AND internship_id = $internship_id LIMIT 1");
    if ($app_res && $arow = mysqli_fetch_assoc($app_res)) {
        $app_status = strtolower(trim($arow['internship_status'] ?? ''));
        $main_status = strtolower(trim($arow['status'] ?? ''));
        if (strpos($app_status, 'completed') !== false || strpos($main_status, 'completed') !== false) {
            $is_completed = true;
        }
        if ($main_status === 'project assigned' && empty($app_status)) {
            // Literally just assigned, 0 progress
        }
    }

    // 3. Count Approved Daily Logs
    $logs_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM daily_logs WHERE user_id = $student_id AND internship_id = $internship_id AND LOWER(TRIM(status)) = 'approved'");
    $logs_count = 0;
    if ($logs_res && $lrow = mysqli_fetch_assoc($logs_res)) {
        $logs_count = intval($lrow['count']);
    }

    // Apply rules
    if ($is_completed || $logs_count >= $expected_logs) {
        return [
            'progress_percentage' => 100,
            'approved_logs' => $logs_count,
            'expected_logs' => $expected_logs
        ];
    }

    if ($logs_count === 0) {
        return [
            'progress_percentage' => 0,
            'approved_logs' => 0,
            'expected_logs' => $expected_logs
        ];
    }

    // Calculate percentage
    $progress = round(($logs_count / max(1, $expected_logs)) * 100);
    
    // First approved log minimum 5%
    if ($progress < 5 && $logs_count > 0) {
        $progress = 5;
    }

    return [
        'progress_percentage' => min(100, $progress),
        'approved_logs' => $logs_count,
        'expected_logs' => $expected_logs
    ];
}

/**
 * Calculates the team progress based on all assigned students' approved daily logs.
 */
function calculate_team_progress($conn, $team_id, $team_name) {
    $team_id = intval($team_id);
    if (empty($team_name)) {
        // If team name is empty but we have a team_id, try to fetch team_name
        if ($team_id > 0) {
            $t_res = mysqli_query($conn, "SELECT team_name FROM project_teams WHERE id = $team_id LIMIT 1");
            if ($t_res && $t_row = mysqli_fetch_assoc($t_res)) {
                $team_name = $t_row['team_name'];
            }
        }
    }
    $team_name_escaped = $team_name; // bind_param handles escaping

    // Get all students in this team
    $students = [];
    $s_stmt = $conn->prepare("
        SELECT DISTINCT student_id, internship_id FROM (
            SELECT ptm.student_id, t.internship_id 
            FROM project_team_members ptm 
            JOIN project_teams t ON ptm.project_team_id = t.id 
            WHERE t.id = ? AND t.id > 0
            
            UNION
            
            SELECT user_id AS student_id, internship_id 
            FROM internship_applications 
            WHERE team_name = ? AND team_name != ''
              AND status IN ('Project Assigned', 'Team Assigned', 'Internship Started', 'Started', 'Active Intern', 'Selected')
        ) AS combined
    ");
    if ($s_stmt) {
        $s_stmt->bind_param('is', $team_id, $team_name_escaped);
        $s_stmt->execute();
        $s_res = $s_stmt->get_result();
        while ($row = $s_res->fetch_assoc()) {
            $students[] = $row;
        }
        $s_stmt->close();
    }

    if (empty($students)) {
        return [
            'progress_percentage' => 0,
            'approved_logs' => 0,
            'expected_logs' => 0
        ];
    }

    $total_approved = 0;
    $total_expected = 0;

    foreach ($students as $student) {
        $prog = calculate_internship_progress($conn, $student['student_id'], $student['internship_id']);
        $total_approved += $prog['approved_logs'];
        $total_expected += $prog['expected_logs'];
    }

    if ($total_expected == 0) {
        return [
            'progress_percentage' => 0,
            'approved_logs' => 0,
            'expected_logs' => 0
        ];
    }

    $progress = round(($total_approved / $total_expected) * 100);

    if ($progress < 5 && $total_approved > 0) {
        $progress = 5;
    }

    return [
        'progress_percentage' => min(100, $progress),
        'approved_logs' => $total_approved,
        'expected_logs' => $total_expected
    ];
}
?>
