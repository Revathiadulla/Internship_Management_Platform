<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/status_helper.php';
require_once __DIR__ . '/../includes/status_utils.php';
require_once __DIR__ . '/../includes/cloudinary_config.php';
require_once __DIR__ . '/../includes/progress_helper.php';

require_student_login();

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}

$assigned_project = null;
$assigned_team = null;
$team_members = [];
$team_messages = [];
$discussion_error = '';
$discussion_posted = false;
$has_assigned_project = false;
$has_direct_assignment = false;
$has_confirmed_team = false;

$team_assign_sql = "SELECT t.id AS team_id,
                           t.team_name,
                           t.internship_id,
                           t.status AS team_status,
                           t.mentor_id,
                           COALESCE(NULLIF(i.title, ''), NULLIF(i.project_title, ''), NULL) AS internship_title,
                           COALESCE(NULLIF(i.project_type, ''), NULL) AS project_type,
                           COALESCE(NULLIF(i.project_subtype, ''), NULL) AS project_subtype,
                           COALESCE(NULLIF(i.description, ''), NULL) AS internship_description,
                           COALESCE(NULLIF(i.skills, ''), NULLIF(i.technology_stack, ''), '') AS internship_skills,
                           i.duration AS internship_duration,
                           i.mode AS internship_mode,
                           i.technology_stack AS internship_technology_stack,
                           i.start_date AS internship_start_date,
                           i.end_date AS internship_end_date,
                           u.full_name AS mentor_name,
                           u.email AS mentor_email,
                           ptm.created_at AS assigned_date,
                           t.status AS project_status
                    FROM project_team_members ptm
                    JOIN project_teams t ON ptm.project_team_id = t.id
                    LEFT JOIN internships i ON t.internship_id = i.id
                    LEFT JOIN users u ON t.mentor_id = u.id
                    WHERE ptm.student_id = $user_id
                    ORDER BY t.id DESC
                    LIMIT 1";
$team_assign_res = mysqli_query($conn, $team_assign_sql);
if ($team_assign_res && ($team_assign_row = mysqli_fetch_assoc($team_assign_res))) {
    $has_confirmed_team = true;
    $assigned_team = [
        'id' => intval($team_assign_row['team_id']),
        'team_name' => $team_assign_row['team_name'],
        'team_status' => $team_assign_row['team_status'],
        'mentor_id' => intval($team_assign_row['mentor_id']),
        'mentor_name' => $team_assign_row['mentor_name'],
        'mentor_email' => $team_assign_row['mentor_email'],
    ];

    if (!empty($team_assign_row['internship_id'])) {
        $has_assigned_project = true;
        $assigned_project = [
            'internship_id' => intval($team_assign_row['internship_id']),
            'team_id' => intval($team_assign_row['team_id']),
            'team_name' => $team_assign_row['team_name'],
            'team_status' => $team_assign_row['team_status'],
            'mentor_name' => $team_assign_row['mentor_name'],
            'mentor_email' => $team_assign_row['mentor_email'],
            'title' => $team_assign_row['internship_title'] ?: 'Assigned Project',
            'project_type' => $team_assign_row['project_type'],
            'project_subtype' => $team_assign_row['project_subtype'],
            'description' => $team_assign_row['internship_description'],
            'skills' => !empty($team_assign_row['internship_skills']) ? $team_assign_row['internship_skills'] : $team_assign_row['internship_technology_stack'],
            'duration' => $team_assign_row['internship_duration'],
            'mode' => $team_assign_row['internship_mode'],
            'technology_stack' => $team_assign_row['internship_technology_stack'],
            'start_date' => $team_assign_row['internship_start_date'],
            'end_date' => $team_assign_row['internship_end_date'],
            'assigned_date' => $team_assign_row['assigned_date'],
            'project_status' => $team_assign_row['project_status'],
        ];
    }
}

if ($assigned_team !== null) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS team_discussion_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_role VARCHAR(20) NOT NULL DEFAULT 'student',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(team_id),
        INDEX(sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $team_id = intval($assigned_team['id']);
    $team_members_stmt = $conn->prepare("SELECT ptm.student_id AS user_id, u.full_name, u.email, sp.college_name, COALESCE(NULLIF(sp.department, ''), NULLIF(sp.course, ''), 'N/A') AS department
        FROM project_team_members ptm
        LEFT JOIN users u ON ptm.student_id = u.id
        LEFT JOIN student_profiles sp ON ptm.student_id = sp.user_id
        WHERE ptm.project_team_id = ?
        ORDER BY u.full_name ASC");
    if ($team_members_stmt) {
        $team_members_stmt->bind_param('i', $team_id);
        $team_members_stmt->execute();
        $team_members_result = $team_members_stmt->get_result();
        while ($member_row = $team_members_result->fetch_assoc()) {
            $team_members[] = [
                'user_id' => intval($member_row['user_id'] ?? 0),
                'full_name' => $member_row['full_name'] ?? 'Unnamed Member',
                'email' => $member_row['email'] ?? '',
                'college_name' => $member_row['college_name'] ?? '',
                'department' => $member_row['department'] ?? 'N/A',
            ];
        }
        $team_members_stmt->close();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_message_submit'])) {
        $message_text = trim($_POST['team_message'] ?? '');
        if ($message_text !== '') {
            $sender_role = ($assigned_team['mentor_id'] > 0 && $assigned_team['mentor_id'] === $user_id) ? 'mentor' : 'student';
            $insert_message_stmt = $conn->prepare("INSERT INTO team_discussion_messages (team_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)");
            if ($insert_message_stmt) {
                $insert_message_stmt->bind_param('iiss', $team_id, $user_id, $sender_role, $message_text);
                if ($insert_message_stmt->execute()) {
                    $discussion_posted = true;
                    $recipient_ids = [];
                    foreach ($team_members as $member) {
                        $member_user_id = intval($member['user_id'] ?? 0);
                        if ($member_user_id > 0 && $member_user_id !== $user_id && !in_array($member_user_id, $recipient_ids, true)) {
                            $recipient_ids[] = $member_user_id;
                        }
                    }
                    if ($assigned_team['mentor_id'] > 0 && $assigned_team['mentor_id'] !== $user_id && !in_array(intval($assigned_team['mentor_id']), $recipient_ids, true)) {
                        $recipient_ids[] = intval($assigned_team['mentor_id']);
                    }

                    if (!empty($recipient_ids)) {
                        $notif_msg = 'New team update from ' . ($profile['full_name'] ?? 'your team') . ': ' . mb_substr($message_text, 0, 100);
                        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($notif_stmt) {
                            $notif_title = 'New team update';
                            $notif_type = 'info';
                            $notif_link = 'student_dashboard.php#team-discussion';
                            foreach ($recipient_ids as $recipient_id) {
                                $recipient_role = (intval($assigned_team['mentor_id']) === intval($recipient_id)) ? 'mentor' : 'student';
                                $notif_stmt->bind_param('isssss', $recipient_id, $recipient_role, $notif_title, $notif_msg, $notif_type, $notif_link);
                                $notif_stmt->execute();
                            }
                            $notif_stmt->close();
                        }
                    }
                }
                $insert_message_stmt->close();
            }
        } else {
            $discussion_error = 'Please write a message before posting.';
        }
    }

    $team_messages_stmt = $conn->prepare("SELECT tdm.id, tdm.message, tdm.created_at, tdm.sender_role, tdm.sender_id, u.full_name AS sender_name
        FROM team_discussion_messages tdm
        LEFT JOIN users u ON tdm.sender_id = u.id
        WHERE tdm.team_id = ?
        ORDER BY tdm.created_at ASC");
    if ($team_messages_stmt) {
        $team_messages_stmt->bind_param('i', $team_id);
        $team_messages_stmt->execute();
        $team_messages_result = $team_messages_stmt->get_result();
        while ($message_row = $team_messages_result->fetch_assoc()) {
            $team_messages[] = [
                'id' => intval($message_row['id']),
                'message' => $message_row['message'] ?? '',
                'created_at' => !empty($message_row['created_at']) ? date('M j, Y \a\t g:i A', strtotime($message_row['created_at'])) : '',
                'sender_role' => $message_row['sender_role'] ?? 'student',
                'sender_name' => $message_row['sender_name'] ?? '',
            ];
        }
        $team_messages_stmt->close();
    }
}

// Fetch student applications
$app_sql = "SELECT a.id as app_id,
                   a.internship_id,
                   -- Use applied_subtype for display when application hasn't been linked to an assigned project
                   CASE WHEN COALESCE(a.assigned_project_id, 0) = 0 THEN COALESCE(NULLIF(a.applied_subtype, ''), '') ELSE COALESCE(i.title, a.internship_name) END as title,
                   a.applied_subtype,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   a.status, a.applied_date, a.education_status,
                   a.team_name, a.mentor_id, a.team_status,
                   a.internship_status,
                   a.assigned_project_id,
                   a.team_id,
                   m.full_name as mentor_name, m.email as mentor_email,
                   i.description as project_desc,
                   i.project_type,
                   i.project_subtype,
                   i.skills,
                   i.technology_stack,
                   i.difficulty_level,
                   i.start_date,
                   i.end_date,
                   a.confirmation_letter_path
                   
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN users m ON a.mentor_id = m.id
            WHERE a.user_id = '$user_id'
            ORDER BY a.applied_date DESC";
$app_result = mysqli_query($conn, $app_sql);
$app_rows = [];
$app_count = 0;
$has_active = false;
$active_intern = null;
$active_statuses = ['Started', 'Internship Started', 'Active Intern', 'Internship Active'];

$build_active_intern = function(array $row) {
    $applied_date = !empty($row['applied_date']) ? $row['applied_date'] : null;

    return [
        'app_id' => $row['app_id'],
        'internship_id' => $row['internship_id'] ?: 0,
        'internship_status' => $row['internship_status'] ?? null,
        'title' => $row['title'],
        'applied_subtype' => $row['applied_subtype'] ?? null,
        'project_subtype' => $row['project_subtype'] ?? null,
        'duration' => $row['duration'],
        'mode' => $row['mode'],
        'status' => $row['status'],
        'applied_date' => $applied_date,
        'education_status' => $row['education_status'],
        'team_name' => $row['team_name'] ?? null,
        'mentor_name' => $row['mentor_name'] ?? null,
        'mentor_email' => $row['mentor_email'] ?? null,
        'team_status' => $row['team_status'] ?? 'Active',
        'project_desc' => $row['project_desc'] ?? null,
        'project_type' => $row['project_type'] ?? null,
        'project_subtype' => $row['project_subtype'] ?? null,
        'skills' => $row['skills'] ?? null,
        'technology_stack' => $row['technology_stack'] ?? null,
        'difficulty_level' => $row['difficulty_level'] ?? null,
        'start_date' => $row['start_date'] ?? null,
        'end_date' => $row['end_date'] ?? null,
        'confirmation_letter_path' => $row['confirmation_letter_path'] ?? null,
        
        'exam_link' => $row['exam_link'] ?? null
    ];
};

if ($app_result) {
    $active_app_row = null;
    $assignment_app_row = null;
    $selected_app_row = null;

    while ($row = mysqli_fetch_assoc($app_result)) {
        $app_rows[] = $row;
        $st = $row['status'] ?? '';

        if ($assignment_app_row === null && (!empty($row['assigned_project_id']) || !empty($row['team_id']) || !empty($row['team_name']) || !empty($row['mentor_id']))) {
            $assignment_app_row = $row;
        }

        if ($selected_app_row === null && $st === 'Selected') {
            $selected_app_row = $row;
        }

        if ($active_app_row === null && in_array($st, $active_statuses, true)) {
            $active_app_row = $row;
            $has_active = true;
        }

        if (!$has_direct_assignment && (!empty($row['assigned_project_id']) || !empty($row['team_id']))) {
            $has_direct_assignment = true;
        }
    }
    $app_count = count($app_rows);

    $preferred_app_row = $assignment_app_row ?: $active_app_row ?: $selected_app_row ?: ($app_rows[0] ?? null);
    if ($preferred_app_row !== null) {
        $active_intern = $build_active_intern($preferred_app_row);
    }
}

// Detect if the student is Selected but waiting for coordinator project assignment
$is_selected = false;
$selected_app = null;
foreach ($app_rows as $ar) {
    if ($ar['status'] === 'Selected') {
        $is_selected = true;
        $selected_app = $ar;
        break;
    }
}
$has_project_details = $has_assigned_project || $has_direct_assignment;
$pending_app = $selected_app ?: ($app_rows[0] ?? null);

if (!$has_active && $has_direct_assignment && $active_intern === null) {
    foreach ($app_rows as $row) {
        if (!empty($row['assigned_project_id']) || !empty($row['team_id'])) {
            $active_intern = [
                'app_id' => $row['app_id'],
                'internship_id' => $row['internship_id'] ?: 0,
                'internship_status' => $row['internship_status'] ?? 'Assigned',
                'title' => $row['title'],
                'applied_subtype' => $row['applied_subtype'] ?? null,
                'project_subtype' => $row['project_subtype'] ?? null,
                'duration' => $row['duration'],
                'mode' => $row['mode'],
                'status' => $row['status'] ?? 'Assigned',
                'applied_date' => $row['applied_date'] ?? null,
                'education_status' => $row['education_status'] ?? null,
                'team_name' => $row['team_name'] ?? null,
                'mentor_name' => $row['mentor_name'] ?? null,
                'mentor_email' => $row['mentor_email'] ?? null,
                'team_status' => $row['team_status'] ?? 'Active',
                'project_desc' => $row['project_desc'] ?? null,
                'project_type' => $row['project_type'] ?? null,
                'project_subtype' => $row['project_subtype'] ?? null,
                'skills' => $row['skills'] ?? null,
                'technology_stack' => $row['technology_stack'] ?? null,
                'difficulty_level' => $row['difficulty_level'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'confirmation_letter_path' => $row['confirmation_letter_path'] ?? null,
                
                'exam_link' => $row['exam_link'] ?? null
            ];
            break;
        }
    }
}

if ($has_assigned_project) {
    $has_active = true;
    $existing_active = is_array($active_intern) ? $active_intern : [];
    $active_intern = array_merge($existing_active, [
        'app_id' => $existing_active['app_id'] ?? null,
        'internship_id' => $assigned_project['internship_id'],
        'internship_status' => 'Assigned',
        'title' => $assigned_project['title'],
        'applied_subtype' => $assigned_project['project_subtype'] ?? null,
        'project_subtype' => $assigned_project['project_subtype'] ?? null,
        'duration' => $assigned_project['duration'],
        'mode' => $assigned_project['mode'] ?: ($existing_active['mode'] ?? ''),
        'status' => 'Assigned',
        'applied_date' => $existing_active['applied_date'] ?? null,
        'education_status' => $existing_active['education_status'] ?? null,
        'team_name' => $assigned_project['team_name'],
        'mentor_name' => $assigned_project['mentor_name'],
        'mentor_email' => $assigned_project['mentor_email'],
        'team_status' => $assigned_project['team_status'] ?? 'Active',
        'project_desc' => $assigned_project['description'],
        'project_type' => $assigned_project['project_type'],
        'project_subtype' => $assigned_project['project_subtype'],
        'skills' => $assigned_project['skills'],
        'technology_stack' => $assigned_project['technology_stack'],
        'difficulty_level' => $existing_active['difficulty_level'] ?? null,
        'start_date' => $assigned_project['start_date'],
        'end_date' => $assigned_project['end_date'],
        'confirmation_letter_path' => $existing_active['confirmation_letter_path'] ?? null,
        
        
        'assigned_date' => $assigned_project['assigned_date'] ?? null,
        'project_status' => $assigned_project['project_status'] ?? 'Active'
    ]);
}

$total_logs = 0;

// Derive company name and domain dynamically if active
if ($has_active) {
        if (!$has_assigned_project && $has_confirmed_team) {
            // Student belongs to a confirmed team but no internship is linked yet.
            $active_intern = [
                'app_id' => $active_intern['app_id'] ?? null,
                'internship_id' => $active_intern['internship_id'] ?? 0,
                'internship_status' => $active_intern['internship_status'] ?? null,
                'title' => $active_intern['title'] ?? 'Project Not Assigned Yet',
                'duration' => $active_intern['duration'] ?? '',
                'mode' => $active_intern['mode'] ?? '',
                'status' => $active_intern['status'] ?? 'Assigned',
                'applied_date' => $active_intern['applied_date'] ?? null,
                'education_status' => $active_intern['education_status'] ?? null,
                'team_name' => $active_intern['team_name'] ?? null,
                'mentor_name' => $active_intern['mentor_name'] ?? null,
                'mentor_email' => $active_intern['mentor_email'] ?? null,
                'team_status' => $active_intern['team_status'] ?? 'Active',
                'project_desc' => $active_intern['project_desc'] ?? 'Project Not Assigned Yet',
                'project_type' => $active_intern['project_type'] ?? null,
                'project_subtype' => $active_intern['project_subtype'] ?? null,
                'skills' => $active_intern['skills'] ?? null,
                'technology_stack' => $active_intern['technology_stack'] ?? null,
                'difficulty_level' => $active_intern['difficulty_level'] ?? null,
                'start_date' => $active_intern['start_date'] ?? null,
                'end_date' => $active_intern['end_date'] ?? null
            ];
        }

        $project_title = !empty($active_intern['title']) ? $active_intern['title'] : 'Assigned Project';
        $project_desc = !empty($active_intern['project_desc']) ? $active_intern['project_desc'] : 'Description not available yet.';
        $project_stack_source = !empty($active_intern['skills']) ? $active_intern['skills'] : ($active_intern['technology_stack'] ?? '');
        $project_stack = [];
        if (!empty($project_stack_source)) {
            $project_stack = array_map('trim', explode(',', $project_stack_source));
        }

        $active_domain = !empty($active_intern['project_type']) ? $active_intern['project_type'] : 'General';
        $active_intern['domain'] = $active_domain;
        $active_intern['project_name'] = $project_title;
        $active_intern['project_desc'] = $project_desc;
        $active_intern['project_stack'] = $project_stack;
        $active_intern['company_name'] = 'IMP Technologies';
    }
    $stmt_logs = $conn->prepare("SELECT COUNT(*) as cnt FROM daily_logs WHERE user_id = ?");
    $stmt_logs->bind_param("i", $user_id);
    $stmt_logs->execute();
    $total_logs_res = $stmt_logs->get_result();
    $total_logs_row = $total_logs_res->fetch_assoc();
    $total_logs     = intval($total_logs_row['cnt'] ?? 0);
    $stmt_logs->close();

    // Query phases from database
    $stmt_phases = $conn->prepare("SELECT * FROM internship_phases WHERE internship_id = ? ORDER BY phase_number ASC");
    $stmt_phases->bind_param("i", $active_intern['internship_id']);
    $stmt_phases->execute();
    $db_phases_res = $stmt_phases->get_result();
    $db_phases = [];
    while ($db_row = $db_phases_res->fetch_assoc()) {
        $db_phases[$db_row['phase_number']] = $db_row;
    }
    $db_phases_exist = !empty($db_phases);
    $stmt_phases->close();

    $base_phases = [
        1 => ['label' => 'P1 Learning Phase',           'short' => 'Learning',      'icon' => 'school'],
        2 => ['label' => 'P2 Documentation & Planning', 'short' => 'Documentation', 'icon' => 'description'],
        3 => ['label' => 'P3 Designing',                'short' => 'Designing',     'icon' => 'design_services'],
        4 => ['label' => 'P4 Development',              'short' => 'Development',   'icon' => 'code'],
        
        6 => ['label' => 'P6 Deployment',               'short' => 'Deployment',    'icon' => 'rocket_launch'],
    ];

    $current_phase_num = 1;
    $phases = [];
    foreach ($base_phases as $pnum => $bp) {
        $status = 'Pending';
        $start_date_val = '';
        $end_date_val = '';
        if ($db_phases_exist && isset($db_phases[$pnum])) {
            $status = $db_phases[$pnum]['status'];
            $start_date_val = $db_phases[$pnum]['start_date'];
            $end_date_val = $db_phases[$pnum]['end_date'];
        } else {
            // Legacy log-count fallback
            $completed_threshold = [1 => 0, 2 => 3, 3 => 6, 4 => 10, 5 => 15, 6 => 20][$pnum];
            $status = ($total_logs >= $completed_threshold) ? 'Completed' : 'Pending';
            
            // Determine active legacy phase
            if ($pnum === 1 && $total_logs < 3) $status = 'Active';
            elseif ($pnum === 2 && $total_logs >= 3 && $total_logs < 6) $status = 'Active';
            elseif ($pnum === 3 && $total_logs >= 6 && $total_logs < 10) $status = 'Active';
            elseif ($pnum === 4 && $total_logs >= 10 && $total_logs < 15) $status = 'Active';
            elseif ($pnum === 5 && $total_logs >= 15 && $total_logs < 20) $status = 'Active';
            elseif ($pnum === 6 && $total_logs >= 20) $status = 'Active';
        }
        
        if ($status === 'Active') {
            $current_phase_num = $pnum;
        }
        
        $phases[$pnum] = [
            'label' => $bp['label'],
            'short' => $bp['short'],
            'icon' => $bp['icon'],
            'status' => $status,
            'start_date' => $start_date_val,
            'end_date' => $end_date_val
        ];
    }
    
    $active_intern['current_phase_num']   = $current_phase_num;
    $active_intern['phases']              = $phases;
    $active_intern['current_phase_label'] = $phases[$current_phase_num]['label'];



$weekly_hours = 0.0;
$recent_logs  = [];
$days_active  = 0;
$internship_state = 'upcoming';
$state_badge_text = 'Upcoming';
$state_badge_class = 'bg-amber-50 text-amber-700 border-amber-100';
$banner_message = 'Your internship is scheduled to start soon. Once it begins, progress and phase updates will appear here.';
$phase_display = 'Starts soon / Not started';
if ($has_active) {
    $hours_sql    = "SELECT SUM(time_spent) as total FROM daily_logs WHERE user_id = '$user_id' AND internship_id = '{$active_intern['internship_id']}' AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $hours_result = mysqli_query($conn, $hours_sql);
    $hours_row    = mysqli_fetch_assoc($hours_result);
    $weekly_hours = isset($hours_row['total']) ? floatval($hours_row['total']) : 0.0;

    $logs_sql    = "SELECT * FROM daily_logs WHERE user_id = '$user_id' AND internship_id = '{$active_intern['internship_id']}' ORDER BY log_date DESC, id DESC LIMIT 3";
    $logs_result = mysqli_query($conn, $logs_sql);
    while ($log_row = mysqli_fetch_assoc($logs_result)) {
        $recent_logs[] = $log_row;
    }

    $start_date_str = !empty($active_intern['start_date']) ? $active_intern['start_date'] : null;
    $date_today  = new DateTime();
    $date_today->setTime(0, 0, 0);

    if (!empty($start_date_str)) {
        $date_start = new DateTime($start_date_str);
        $date_start->setTime(0, 0, 0);

        $end_date_obj = !empty($active_intern['end_date']) ? new DateTime($active_intern['end_date']) : clone $date_start;
        $end_date_obj->setTime(0, 0, 0);
        if (empty($active_intern['end_date'])) {
            $end_date_obj->modify('+3 months');
        }

        if ($date_today < $date_start) {
            $internship_state = 'upcoming';
            $state_badge_text = 'Upcoming';
            $state_badge_class = 'bg-amber-50 text-amber-700 border-amber-100';
            $banner_message = 'Your internship is scheduled to start soon. Once it begins, progress and phase updates will appear here.';
            $phase_display = 'Starts soon / Not started';
            $days_active = 0;
            $days_left = $date_today->diff($end_date_obj)->days;
            $progress_pct = 0;
        } elseif ($date_today > $end_date_obj) {
            $internship_state = 'completed';
            $state_badge_text = 'Completed';
            $state_badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-100';
            $banner_message = 'Your internship has been completed successfully. You can review your progress and outcomes below.';
            $phase_display = 'Completed';
            $days_active = max(0, $date_start->diff($date_today)->days + 1);
            $days_left = 0;
            $progress_pct = 100;
        } else {
            $internship_state = 'active';
            $state_badge_text = 'Active Intern';
            $state_badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-100';
            $banner_message = 'Your internship has started. Stay consistent, log daily, and grow every day.';
            $phase_display = $active_intern['current_phase_label'] ?? $phases[$current_phase_num]['label'];
            $days_active = $date_start->diff($date_today)->days + 1;
            if ($date_today < $date_start) {
                $days_active = 0;
            }
        }
    }
}



// Fetch unread notifications count
$unread_sql   = "
    SELECT COUNT(*) as count FROM (
        SELECT id, is_read FROM student_notifications WHERE user_id = '$user_id'
        UNION ALL
        SELECT id, is_read FROM notifications WHERE user_id = '$user_id'
    ) combined WHERE is_read = 0
";
$unread_res   = mysqli_query($conn, $unread_sql);
$unread_row   = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? intval($unread_row['count']) : 0;

// ── Extra queries required by new dashboard ──────────────────────────────────

// Fetch ALL logs for this user
$all_logs_sql    = "SELECT * FROM daily_logs WHERE user_id = '$user_id' ORDER BY log_date DESC, id DESC";
$all_logs_result = mysqli_query($conn, $all_logs_sql);

// Fetch application status history for timeline
$timeline_app_sql = "SELECT a.id as app_id, a.status, a.applied_date, a.education_status,
                            COALESCE(i.title, a.internship_name) as title
                     FROM internship_applications a
                     LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                     WHERE a.user_id = '$user_id'
                     ORDER BY a.applied_date DESC LIMIT 1";
$timeline_result = mysqli_query($conn, $timeline_app_sql);
$timeline_app    = mysqli_fetch_assoc($timeline_result);

$status_history = [];
if ($timeline_app) {
    $sh_sql    = "SELECT * FROM application_status_history WHERE application_id = {$timeline_app['app_id']} ORDER BY created_at ASC";
    $sh_result = mysqli_query($conn, $sh_sql);
    while ($sh_row = mysqli_fetch_assoc($sh_result)) {
        $status_history[] = $sh_row;
    }
}


$feedback_sql    = "SELECT * FROM mentor_feedback WHERE user_id = '$user_id' ORDER BY created_at DESC";
$feedback_result = mysqli_query($conn, $feedback_sql);
$feedback_count  = mysqli_num_rows($feedback_result);

// Fetch ALL notifications
$notification_columns = [];
$notification_cols_res = mysqli_query($conn, "SHOW COLUMNS FROM notifications");
if ($notification_cols_res) {
    while ($notification_col_row = mysqli_fetch_assoc($notification_cols_res)) {
        $notification_columns[$notification_col_row['Field']] = true;
    }
}

$sender_join_sql = isset($notification_columns['sender_id']) ? "LEFT JOIN users u ON u.id = n.sender_id" : '';
$sender_name_sql = isset($notification_columns['sender_id']) ? 'u.full_name AS sender_name' : 'NULL AS sender_name';
$attachment_path_sql = isset($notification_columns['attachment_path']) ? 'n.attachment_path' : 'NULL AS attachment_path';
$attachment_name_sql = isset($notification_columns['attachment_name']) ? 'n.attachment_name' : 'NULL AS attachment_name';
$attachment_size_sql = isset($notification_columns['attachment_size']) ? 'n.attachment_size' : 'NULL AS attachment_size';
$attachment_type_sql = isset($notification_columns['attachment_type']) ? 'n.attachment_type' : 'NULL AS attachment_type';

$all_notif_sql    = "
    SELECT id, user_id, type, message, is_read, created_at, NULL AS title, NULL AS sender_name, NULL AS attachment_path, NULL AS attachment_name, NULL AS attachment_size, NULL AS attachment_type, 'student' AS source_table
    FROM student_notifications
    WHERE user_id = '$user_id'
    UNION ALL
    SELECT n.id, n.user_id, n.type, n.message, n.is_read, n.created_at, n.title, $sender_name_sql, $attachment_path_sql, $attachment_name_sql, $attachment_size_sql, $attachment_type_sql, 'global' AS source_table
    FROM notifications n
    $sender_join_sql
    WHERE n.user_id = '$user_id'
    ORDER BY created_at DESC
";
$all_notif_result = mysqli_query($conn, $all_notif_sql);

// Pre-compute values used in multiple sections
if ($has_active) {
    $phases            = $active_intern['phases'];
    $current_phase_num = $active_intern['current_phase_num'];

    $start_date_str = !empty($active_intern['start_date']) ? $active_intern['start_date'] : null;
    $start_date_obj = null;
    if (!empty($start_date_str)) {
        $start_date_obj = new DateTime($start_date_str);
        $start_date_obj->setTime(0, 0, 0);
    }

    $end_date = !empty($active_intern['end_date']) ? new DateTime($active_intern['end_date']) : (clone $start_date_obj ?: new DateTime());
    if (!empty($active_intern['end_date'])) {
        $end_date->setTime(0, 0, 0);
    } elseif ($start_date_obj) {
        $end_date->setTime(0, 0, 0);
        $end_date->modify('+3 months');
    }

    $date_today = new DateTime();
    $date_today->setTime(0, 0, 0);

    // Calculate Progress Based on Approved Logs
    $progress_data = calculate_internship_progress($conn, $user_id, $active_intern['internship_id']);
    $progress_pct = $progress_data['progress_percentage'];
    $approved_logs = $progress_data['approved_logs'];
    $expected_logs = $progress_data['expected_logs'];

    if ($start_date_obj) {
        $days_left = ($date_today > $end_date) ? 0 : $date_today->diff($end_date)->days;
    } else {
        $days_left = 0;
    }

    $is_completed = ($progress_pct >= 100) || strtolower($active_intern['status']) === 'completed';
} else {
    $phases            = [];
    $current_phase_num = 0;
    $progress_pct      = 0;
    $approved_logs     = 0;
    $expected_logs     = 0;
    $end_date          = null;
    $days_left         = 0;
    $is_completed      = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard — IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: 'class'
    };
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }

    /* Section transitions */
    .dashboard-section {
      display: none;
      opacity: 0;
      transform: translateY(8px);
      transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .dashboard-section.active {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }

    /* Profile dropdown */
    #profile-dropdown { transform-origin: top right; }

    /* Timeline */
    .timeline-line { position: absolute; left: 15px; top: 24px; bottom: 0; width: 2px; background: #e2e8f0; }
    .timeline-item { position: relative; padding-left: 44px; padding-bottom: 24px; }
    .timeline-dot { position: absolute; left: 8px; top: 4px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #e2e8f0; background: #fff; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200">

<?php
$success_message = '';
if (isset($_GET['msg'])) {
    $msg_map = [
        'profile_updated' => 'Profile saved successfully! Welcome to your dashboard.',
        'profile_saved'   => 'Profile saved successfully!',
    ];
    $raw_msg = $_GET['msg'];
    $success_message = isset($msg_map[$raw_msg]) ? $msg_map[$raw_msg] : htmlspecialchars($raw_msg);
} elseif (isset($_GET['success'])) {
    // Use the actual message from the URL if it's a non-empty string, otherwise fall back to generic
    $raw_success = trim($_GET['success']);
    $success_message = ($raw_success !== '' && $raw_success !== '1')
        ? htmlspecialchars($raw_success)
        : 'Daily log submitted successfully.';
} elseif (isset($_GET['deleted'])) {
    $success_message = 'Today’s daily log deleted successfully.';
} elseif (isset($_GET['edited'])) {
    $success_message = 'Daily log updated successfully.';
}
$error_message = '';
if (isset($_GET['error'])) {
    $error_message = $_GET['error'] === '1' ? 'Unable to process daily log.' : $_GET['error'];
}
$warning_message = '';
if (isset($_GET['warning'])) {
    $warning_message = $_GET['warning'];
}
?>
<?php if ($success_message): ?>
<div id="success-toast" class="fixed top-6 right-6 z-50 bg-green-600 text-white rounded-2xl shadow-xl px-5 py-4 flex items-center gap-3 transform translate-x-[420px] transition-transform duration-500 ease-out">
  <span class="material-symbols-outlined">check_circle</span>
  <span class="text-sm font-semibold"><?php echo htmlspecialchars($success_message); ?></span>
  <button onclick="this.parentElement.remove()" class="ml-3 hover:opacity-70"><span class="material-symbols-outlined text-[18px]">close</span></button>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const t = document.getElementById('success-toast');
    if (!t) return;
    setTimeout(() => t.classList.remove('translate-x-[420px]'), 100);
    setTimeout(() => { t.classList.add('translate-x-[420px]'); setTimeout(() => t.remove(), 500); }, 4500);
  });
</script>
<?php endif; ?>

<?php if ($error_message): ?>
<div id="error-toast" class="fixed top-6 right-6 z-50 bg-red-600 text-white rounded-2xl shadow-xl px-5 py-4 flex items-center gap-3 transform translate-x-[420px] transition-transform duration-500 ease-out">
  <span class="material-symbols-outlined">warning</span>
  <span class="text-sm font-semibold"><?php echo htmlspecialchars($_GET['error']); ?></span>
  <button onclick="this.parentElement.remove()" class="ml-3 hover:opacity-70"><span class="material-symbols-outlined text-[18px]">close</span></button>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const t = document.getElementById('error-toast');
    if (!t) return;
    setTimeout(() => t.classList.remove('translate-x-[420px]'), 100);
    setTimeout(() => { t.classList.add('translate-x-[420px]'); setTimeout(() => t.remove(), 500); }, 4500);
  });
</script>
<?php endif; ?>

<?php if ($warning_message): ?>
<div id="warning-toast" class="fixed top-6 right-6 z-50 bg-amber-500 text-white rounded-2xl shadow-xl px-5 py-4 flex items-center gap-3 transform translate-x-[420px] transition-transform duration-500 ease-out">
  <span class="material-symbols-outlined">warning</span>
  <span class="text-sm font-semibold"><?php echo htmlspecialchars($warning_message); ?></span>
  <button onclick="this.parentElement.remove()" class="ml-3 hover:opacity-70"><span class="material-symbols-outlined text-[18px]">close</span></button>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const t = document.getElementById('warning-toast');
    if (!t) return;
    setTimeout(() => t.classList.remove('translate-x-[420px]'), 100);
    setTimeout(() => { t.classList.add('translate-x-[420px]'); setTimeout(() => t.remove(), 500); }, 4500);
  });
</script>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     FIXED LEFT SIDEBAR
═══════════════════════════════════════════════════════════════ -->
<aside class="fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col shadow-sm">
  <!-- Logo -->
  <div class="px-6 py-5 border-b border-gray-100">
    <a href="../index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
      <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill="currentColor"/>
        <circle cx="16" cy="16" r="3" fill="white"/>
        <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
        <circle cx="16" cy="8" r="1.5" fill="white"/>
        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
        <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
        <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
        <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
        <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
        <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
        <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
      </svg>
      <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
    </a>
    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1.5">Student Portal</p>
  </div>

  <!-- Nav -->
  <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
    <button data-section="sec-dashboard"     class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all bg-blue-50 text-blue-700 font-semibold">
      <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
    </button>
    <button data-section="sec-internship"    class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">badge</span> My Internship
    </button>
    <button data-section="sec-daily-logs"    class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">edit_note</span> Daily Logs
    </button>
    <button data-section="sec-project"       class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">terminal</span> Project
    </button>
    <button data-section="sec-feedback"      class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">reviews</span> Feedback
    </button>
    <button data-section="sec-certificate"   class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">workspace_premium</span> Certificate
    </button>
    <button data-section="sec-notifications" class="nav-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all text-gray-600 hover:bg-gray-50 hover:text-blue-600">
      <span class="material-symbols-outlined text-[20px]">notifications</span>
      <span>Notifications</span>
      <?php if ($unread_count > 0): ?>
      <span class="ml-auto bg-red-100 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo $unread_count; ?></span>
      <?php endif; ?>
    </button>
  </nav>

  <!-- Bottom links -->
  <div class="px-3 py-4 border-t border-gray-100 space-y-1">
    <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-500 hover:bg-gray-50 hover:text-blue-600 transition-all">
      <span class="material-symbols-outlined text-[20px]">help</span> Help Center
    </a>
    <a href="../login.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-500 hover:bg-red-50 hover:text-red-600 transition-all">
      <span class="material-symbols-outlined text-[20px]">logout</span> Logout
    </a>
  </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════════
     MAIN AREA
═══════════════════════════════════════════════════════════════ -->
<div class="pl-64 flex flex-col min-h-screen">

  <!-- STICKY TOP HEADER -->
  <header class="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 gap-4">
    <!-- Left: badge + search -->
    <div class="flex items-center gap-4 flex-1 min-w-0">
      <span class="shrink-0 text-xs font-semibold text-slate-400 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-lg">Student Workspace</span>
      <div class="relative w-full max-w-sm">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
        <input id="header-search" type="text" placeholder="Search sections…"
               class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:bg-white transition-colors">
      </div>
    </div>

    <!-- Right: bell + profile -->
    <div class="flex items-center gap-4 shrink-0">
      <!-- Notification bell -->
      <button data-section="sec-notifications" class="nav-item relative p-2 rounded-full text-gray-500 hover:bg-gray-100 transition-colors">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($unread_count > 0): ?>
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
        <?php endif; ?>
      </button>

      <!-- Theme Switcher -->
      <button id="theme-toggle" class="p-2 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors flex items-center justify-center cursor-pointer">
        <span class="material-symbols-outlined text-[20px]" id="theme-toggle-icon">dark_mode</span>
      </button>

      <div class="h-7 w-px bg-gray-200"></div>

      <!-- Profile avatar + dropdown -->
      <div class="relative">
        <button id="profile-toggle" class="flex items-center gap-3 p-1 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
          <div class="text-right hidden md:block">
            <p class="text-sm font-semibold text-slate-800 leading-tight"><?php echo htmlspecialchars($profile['full_name']); ?></p>
            <p class="text-xs text-gray-400">Student Account</p>
          </div>
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff"
               alt="Avatar" class="w-9 h-9 rounded-full border border-gray-200 shadow-sm">
        </button>

        <!-- Dropdown -->
        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
          <!-- Header -->
          <div class="p-5 bg-slate-50 border-b border-gray-100 flex items-center gap-4">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff"
                 alt="Avatar" class="w-14 h-14 rounded-full border-2 border-white shadow">
            <div>
              <p class="font-bold text-slate-800"><?php echo htmlspecialchars($profile['full_name']); ?></p>
              <p class="text-xs text-slate-500"><?php echo htmlspecialchars($profile['email']); ?></p>
              <span class="mt-1 inline-block px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase">Student</span>
            </div>
          </div>
          <!-- Details -->
          <div class="p-5 space-y-3 text-sm">
            <div class="grid grid-cols-2 gap-y-2 gap-x-3">
              <span class="text-slate-400 font-medium">Phone</span>
              <span class="text-slate-700 truncate"><?php echo htmlspecialchars($profile['phone'] ?? '—'); ?></span>
              <span class="text-slate-400 font-medium">College</span>
              <span class="text-slate-700 truncate"><?php echo htmlspecialchars($profile['college_name'] ?? '—'); ?></span>
              <span class="text-slate-400 font-medium">Course</span>
              <span class="text-slate-700 truncate"><?php echo htmlspecialchars($profile['course'] ?? '—'); ?></span>
              <span class="text-slate-400 font-medium">Skills</span>
              <span class="text-slate-700 truncate"><?php echo htmlspecialchars($profile['skills'] ?? '—'); ?></span>
            </div>
            <?php if (!empty($profile['resume_file']) || !empty($profile['resume_url'])): ?>
            <?php 
                $raw_resume = !empty($profile['resume_url']) ? $profile['resume_url'] : $profile['resume_file'];
                $label_html = '
                    <span class="material-symbols-outlined text-red-400 text-[18px]">picture_as_pdf</span>
                    <span class="truncate flex-1 text-xs font-medium">' . htmlspecialchars(!empty($profile['resume_original_name']) ? $profile['resume_original_name'] : basename($raw_resume)) . '</span>
                    <span class="text-blue-600 text-xs font-semibold">View</span>
                ';
                echo renderViewButton(
                    $raw_resume, 
                    'flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 transition-colors text-slate-600 group', 
                    $label_html
                );
            ?>
            <?php endif; ?>
          </div>
          <!-- Actions -->
          <div class="p-3 bg-gray-50 border-t border-gray-100 grid grid-cols-2 gap-2">
            <a href="student_profile_form.php" class="py-2 text-center text-sm font-semibold text-slate-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">Edit Profile</a>
            <a href="../login.php" class="py-2 text-center text-sm font-semibold text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- CONTENT AREA -->
  <main class="flex-1 p-8">

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: DASHBOARD
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-dashboard" class="dashboard-section active">
      <?php
        $d_end = null; $d_left = 0; $d_pct = 0; $d_approved = 0; $d_expected = 0;
        if ($has_active) {
          $d_end  = $end_date;
          $d_left = $days_left;
          $d_pct  = $progress_pct;
          $d_approved = $approved_logs;
          $d_expected = $expected_logs;
        }
      ?>
      <?php if ($has_active): ?>

      <!-- ══ INTERN WORKSPACE BANNER ══ -->
      <div class="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 rounded-2xl p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-lg">
        <div>
          <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-1">Intern Workspace</p>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>! 🎉</h1>
          <p class="text-blue-200 text-sm mt-1"><?php echo htmlspecialchars($banner_message); ?></p>
        </div>
        <div class="shrink-0">
          <span class="flex items-center gap-2 bg-white/20 border border-white/30 px-4 py-2.5 rounded-xl text-white text-sm font-bold">
            <span class="w-2.5 h-2.5 rounded-full <?php echo $internship_state === 'active' ? 'bg-emerald-400' : ($internship_state === 'completed' ? 'bg-blue-400' : 'bg-amber-400'); ?> animate-ping"></span>
            Status: <?php echo htmlspecialchars($state_badge_text); ?>
          </span>
        </div>
      </div>

      <!-- ══ ROW 1: Internship Card + Project Card ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

        <!-- Internship Card -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
              <span class="material-symbols-outlined text-[24px]">badge</span>
            </div>
            <span class="px-2.5 py-1 <?php echo htmlspecialchars($state_badge_class); ?> text-[10px] font-extrabold rounded-full uppercase border"><?php echo htmlspecialchars($state_badge_text); ?></span>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internship</p>
            <h3 class="text-base font-extrabold text-slate-800 mt-0.5 leading-snug"><?php echo htmlspecialchars(!empty($active_intern['project_subtype']) ? $active_intern['project_subtype'] : 'Internship Not Assigned'); ?></h3>
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs border-t border-slate-100 pt-4">
            <div><p class="text-slate-400 font-semibold">Start Date</p><p class="font-bold text-slate-700 mt-0.5"><?php echo !empty($active_intern['start_date']) || !empty($active_intern['applied_date']) ? date('M d, Y', strtotime($active_intern['start_date'] ?: $active_intern['applied_date'])) : 'Not available'; ?></p></div>
            <div><p class="text-slate-400 font-semibold">End Date</p><p class="font-bold text-slate-700 mt-0.5"><?php echo $d_end->format('M d, Y'); ?></p></div>
            <div><p class="text-slate-400 font-semibold">Duration</p><p class="font-bold text-slate-700 mt-0.5"><?php echo !empty($active_intern['duration'])?htmlspecialchars($active_intern['duration']):'3 Months'; ?></p></div>
            <div><p class="text-slate-400 font-semibold">Mode</p><p class="font-bold text-slate-700 mt-0.5 capitalize"><?php echo !empty($active_intern['mode'])?htmlspecialchars($active_intern['mode']):'Hybrid'; ?></p></div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-400 font-semibold">Overall Progress</span>
              <span class="font-bold text-slate-700"><?php echo $d_pct; ?>%</span>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
              <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $d_pct; ?>%"></div>
            </div>
            <p class="text-[10px] text-slate-400 mt-1"><?php echo $days_active; ?> days active · <?php echo $d_left; ?> days remaining</p>
          </div>
        </div>

        <!-- Project Card -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[24px]">terminal</span>
              </div>
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assigned Project</p>
                <h3 class="text-base font-extrabold text-slate-800 mt-0.5"><?php echo htmlspecialchars($active_intern['project_name']); ?></h3>
                <?php if (!empty($active_intern['team_name'])): ?>
                  <p class="text-xs text-indigo-600 font-bold mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-xs">groups</span> Team: <?php echo htmlspecialchars($active_intern['team_name']); ?></p>
                <?php endif; ?>
              </div>
            </div>
            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-extrabold rounded-full uppercase border border-indigo-100 shrink-0"><?php echo htmlspecialchars($active_intern['team_status'] ?: 'In Progress'); ?></span>
          </div>
          <p class="text-sm text-slate-500 leading-relaxed"><?php echo htmlspecialchars($active_intern['project_desc']); ?></p>
          <div class="flex flex-wrap gap-2">
            <?php foreach($active_intern['project_stack'] as $tech): ?>
            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg"><?php echo htmlspecialchars($tech); ?></span>
            <?php endforeach; ?>
          </div>
          <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 flex items-center gap-2 text-sm">
            <span class="material-symbols-outlined text-blue-500 text-[18px]">layers</span>
            <span class="text-blue-600 font-semibold text-xs">Current Phase:</span>
            <span class="font-extrabold text-blue-800 text-xs"><?php echo htmlspecialchars($phase_display); ?></span>
          </div>
          <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4 text-xs">
            <div class="flex items-center gap-2">
              <?php 
              $mentor_name_disp = !empty($active_intern['mentor_name']) ? $active_intern['mentor_name'] : 'Mentor assignment pending'; 
              $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($mentor_name_disp) . "&background=6366F1&color=fff";
              ?>
              <img src="<?php echo $avatar_url; ?>" class="w-8 h-8 rounded-full border" alt="Mentor">
              <div>
                <p class="text-[9px] text-slate-400 font-semibold uppercase">Assigned Mentor</p>
                <p class="font-bold text-slate-700">
                  <?php if (!empty($active_intern['mentor_name'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($active_intern['mentor_email']); ?>" class="hover:underline text-indigo-600"><?php echo htmlspecialchars($active_intern['mentor_name']); ?></a>
                  <?php else: ?>
                    Mentor assignment pending
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <div>
              <p class="text-[9px] text-slate-400 font-semibold uppercase">Project Deadline</p>
              <p class="font-bold text-red-600">In <?php echo $d_left; ?> Days</p>
            </div>
            <button data-section="sec-project" class="nav-item text-blue-600 font-bold hover:underline">View Project →</button>
          </div>
        </div>
      </div>

      <!-- ══ ROW 2: Phase Tracker ══ -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-5">
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">account_tree</span>
            </div>
            <div>
              <h3 class="font-bold text-slate-800 text-sm">Internship Phase Tracker</h3>
              <p class="text-xs text-slate-400">Complete each phase to advance your internship progress.</p>
            </div>
          </div>
          <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-extrabold rounded-full border border-blue-100">Phase <?php echo $current_phase_num; ?> of 6</span>
        </div>
        <div class="grid grid-cols-6 gap-3">
          <?php foreach($phases as $pnum => $phase):
            $is_done    = $phase['status'] === 'Completed';
            $is_current = $phase['status'] === 'Active';
            $box_cls    = $is_done    ? 'bg-emerald-50 border-emerald-200' : ($is_current ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-100' : 'bg-slate-50 border-slate-200');
            $icon_cls   = $is_done    ? 'bg-emerald-500 text-white' : ($is_current ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400');
            $lbl_cls    = $is_done    ? 'text-emerald-700 font-bold' : ($is_current ? 'text-blue-700 font-extrabold' : 'text-slate-400');
            $sub_cls    = $is_done    ? 'text-emerald-500' : ($is_current ? 'text-blue-500 font-bold' : 'text-slate-400');
          ?>
          <div class="flex flex-col items-center gap-2 p-3 rounded-xl border <?php echo $box_cls; ?> text-center">
            <div class="w-10 h-10 rounded-full <?php echo $icon_cls; ?> flex items-center justify-center shadow-sm">
              <span class="material-symbols-outlined text-[18px]"><?php echo $is_done ? 'check' : $phase['icon']; ?></span>
            </div>
            <div>
              <p class="text-[10px] font-bold <?php echo $lbl_cls; ?>">P<?php echo $pnum; ?></p>
              <p class="text-[10px] <?php echo $lbl_cls; ?>"><?php echo $phase['short']; ?></p>
              <?php if (!empty($phase['start_date']) && !empty($phase['end_date'])): ?>
                <p class="text-[8px] text-slate-400 mt-0.5 whitespace-nowrap"><?php echo date('M d', strtotime($phase['start_date'])) . ' - ' . date('M d', strtotime($phase['end_date'])); ?></p>
              <?php endif; ?>
              <p class="text-[9px] uppercase tracking-wide <?php echo $sub_cls; ?> mt-0.5"><?php echo $is_done ? 'Done' : ($is_current ? 'Current' : 'Pending'); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- APPLICATION TIMELINE REMOVED -->
      <?php $tl_app = null; // timeline removed ?>

      <!-- ══ ROW 3: Activity Logbook + Progress/Mentor/Deadlines ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Activity Logbook (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
              </div>
              <div>
                <h3 class="font-bold text-slate-800 text-sm">Recent Activity Logbook</h3>
                <p class="text-xs text-slate-400">Document your daily milestones, technical blocks, and timelines.</p>
              </div>
            </div>
            <button data-section="sec-daily-logs" class="nav-item inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors shadow-sm">
              <span class="material-symbols-outlined text-[15px]">add</span> Submit Today's Log
            </button>
          </div>

          <?php if (count($recent_logs) === 0): ?>
          <div class="text-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[36px] text-slate-300 block mb-2">edit_note</span>
            <p class="text-slate-400 text-sm font-medium">No logs yet. Start logging your daily work!</p>
          </div>
          <?php else: ?>
          <div class="space-y-4">
            <?php foreach($recent_logs as $log):
              $fc = match($log['focus_level']) {
                'High'   => 'bg-emerald-100 text-emerald-700',
                'Medium' => 'bg-blue-100 text-blue-700',
                'Low'    => 'bg-red-100 text-red-600',
                default  => 'bg-slate-100 text-slate-600',
              };
            ?>
            <div class="border border-slate-100 rounded-xl p-4 hover:bg-slate-50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-widest"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></span>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-600"><?php echo number_format($log['time_spent'],1); ?> Hrs spent</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $fc; ?>"><?php echo htmlspecialchars($log['focus_level']); ?> Progress</span>
                </div>
              </div>
              <div class="space-y-1.5 text-xs">
                <div>
                  <p class="text-slate-400 font-bold uppercase tracking-wide text-[9px]">Tasks Done:</p>
                  <p class="text-slate-700"><?php echo htmlspecialchars(mb_strimwidth($log['tasks_completed'],0,100,'…')); ?></p>
                </div>
                <?php if (!empty($log['issues_faced'])): ?>
                <div>
                  <p class="text-red-400 font-bold uppercase tracking-wide text-[9px]">Blockers:</p>
                  <p class="text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($log['issues_faced'],0,80,'…')); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($log['next_plan'])): ?>
                <div>
                  <p class="text-blue-400 font-bold uppercase tracking-wide text-[9px]">Roadmap Next:</p>
                  <p class="text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($log['next_plan'],0,80,'…')); ?></p>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right column: Progress + Mentor + Deadlines (5 cols) -->
        <div class="lg:col-span-5 flex flex-col gap-5">

          <!-- Progress Overview -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-emerald-500 text-[20px]">monitoring</span>
              <h4 class="font-bold text-slate-800 text-sm">Progress Overview</h4>
            </div>
            <div class="space-y-3 text-sm">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-slate-500">Overall Progress (Based on Approved Logs)</span>
                  <span class="font-bold text-slate-700"><?php echo $d_pct; ?>% (<?php echo $d_approved; ?> / <?php echo $d_expected; ?>)</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                  <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $d_pct; ?>%"></div>
                </div>
              </div>
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-slate-500">Weekly Hours</span>
                  <span class="font-bold text-emerald-600"><?php echo number_format($weekly_hours,1); ?>h logged</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                  <div class="bg-emerald-400 h-full rounded-full" style="width:<?php echo min(100,round($weekly_hours/40*100)); ?>%"></div>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                <div class="text-center p-3 bg-slate-50 rounded-xl">
                  <p class="text-lg font-extrabold text-slate-800"><?php echo count($recent_logs); ?></p>
                  <p class="text-[10px] text-slate-400 font-semibold uppercase">Logs Filed</p>
                </div>
                <div class="text-center p-3 bg-slate-50 rounded-xl">
                  <p class="text-lg font-extrabold text-slate-800"><?php echo $days_active; ?></p>
                  <p class="text-[10px] text-slate-400 font-semibold uppercase">Days Active</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Mentor Review -->
          <div id="mentor-feedback-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-[20px]">rate_review</span>
                <h4 class="font-bold text-slate-800 text-sm">Mentor Review</h4>
              </div>
              <?php if ($feedback_count > 0): ?>
              <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full">Approved</span>
              <?php endif; ?>
            </div>
            <?php if ($feedback_count === 0): ?>
            <div class="bg-slate-50 rounded-xl p-4 text-center border border-dashed border-slate-200">
              <p class="text-slate-400 text-xs">No mentor feedback yet.</p>
            </div>
            <?php else:
              $lf = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM mentor_feedback WHERE user_id='$user_id' ORDER BY created_at DESC LIMIT 1"));
            ?>
            <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
              <p class="text-sm text-slate-700 italic leading-relaxed">"<?php echo htmlspecialchars(mb_strimwidth($lf['comments']??'Great work!',0,120,'…')); ?>"</p>
              <p class="text-xs text-slate-500 mt-2 text-right font-semibold">— <?php echo htmlspecialchars($lf['given_by']??'Dr. Sarah Jenkins'); ?></p>
            </div>
            <?php endif; ?>
          </div>

          <!-- Upcoming Deadlines -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-red-500 text-[20px]">notifications_active</span>
              <h4 class="font-bold text-slate-800 text-sm">Upcoming Deadlines</h4>
            </div>
            <div class="space-y-3">
              <?php
                $deadlines = [];
                // Add Daily Log deadline
                $deadlines[] = ['label' => 'Daily Log Due', 'sub' => 'Submit today\'s activity log', 'days' => 0, 'urgent' => true];
                
                if (isset($db_phases_exist) && $db_phases_exist) {
                    foreach ($db_phases as $pnum => $db_p) {
                        if ($db_p['status'] !== 'Completed') {
                            $p_end = new DateTime($db_p['end_date']);
                            $p_today = new DateTime();
                            $diff = 0;
                            if ($p_today <= $p_end) {
                                $diff = $p_today->diff($p_end)->days;
                            }
                            $deadlines[] = [
                                'label' => $db_p['phase_name'] . ' Deadline',
                                'sub' => $db_p['status'] === 'Active' ? 'Current active phase review' : 'Upcoming phase deadline',
                                'days' => $diff,
                                'urgent' => ($diff <= 3 || $db_p['status'] === 'Active')
                            ];
                        }
                    }
                } else {
                    // Legacy fallback
                    $deadlines[] = ['label'=>'Development Phase Review','sub'=>'Submit code for approval','days'=>min($d_left,5),'urgent'=>$d_left<=7];
                    $deadlines[] = ['label'=>'Final Project Submission','sub'=>'Complete project deployment check','days'=>min($d_left,20),'urgent'=>false];
                }
                
                foreach($deadlines as $dl):
                  $dl_bg  = $dl['urgent'] ? 'bg-red-50 border-red-100' : 'bg-slate-50 border-slate-100';
                  $dl_day = $dl['urgent'] ? 'text-red-600 font-extrabold' : 'text-slate-500 font-semibold';
              ?>
              <div class="flex items-center justify-between p-3 rounded-xl border <?php echo $dl_bg; ?>">
                <div>
                  <p class="text-xs font-bold text-slate-800"><?php echo $dl['label']; ?></p>
                  <p class="text-[10px] text-slate-400 mt-0.5"><?php echo $dl['sub']; ?></p>
                </div>
                <span class="text-xs <?php echo $dl_day; ?> shrink-0 ml-3">
                  <?php echo $dl['days'] === 0 ? 'Today' : 'In '.$dl['days'].' Days'; ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div><!-- /right col -->
      </div><!-- /row 3 -->

      <?php elseif ($is_selected && !$has_project_details): ?>
      <!-- ══ SELECTED — WAITING FOR COORDINATOR ASSIGNMENT ══ -->

      <!-- Congratulations Banner -->
      <div class="bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 rounded-2xl p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-lg">
        <div>
          <p class="text-emerald-200 text-[10px] font-bold uppercase tracking-widest mb-1">Congratulations!</p>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">You have been selected! 🎉</h1>
          <p class="text-emerald-200 text-sm mt-1">Project assignment is pending from the coordinator. You will be notified once a project is assigned.</p>
        </div>
        <div class="shrink-0">
          <span class="flex items-center gap-2 bg-white/20 border border-white/30 px-4 py-2.5 rounded-xl text-white text-sm font-bold">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            Status: Selected
          </span>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">check_circle</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Status</p><p class="text-sm font-extrabold text-emerald-600">Selected</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">assignment</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Applications</p><p class="text-sm font-extrabold text-slate-800"><?php echo $app_count; ?></p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">hourglass_top</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Project</p><p class="text-sm font-extrabold text-amber-600">Pending</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">notifications</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alerts</p><p class="text-sm font-extrabold text-slate-800"><?php echo $unread_count; ?></p></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Waiting for Coordinator Card -->
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white flex flex-col gap-4 shadow-lg">
          <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">pending_actions</span></div>
          <div>
            <h3 class="text-lg font-extrabold">Waiting for Project Assignment</h3>
            <p class="text-amber-100 text-sm mt-1 leading-relaxed">You are selected. Project assignment is pending from coordinator. You'll be notified once assigned.</p>
          </div>
          <a href="student_applications.php" class="inline-flex items-center gap-2 bg-white text-amber-700 font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-amber-50 transition-colors shadow-sm w-fit">
            <span class="material-symbols-outlined text-[18px]">assignment</span> View Application Status
          </a>
        </div>

        <!-- Recent Applications -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800 text-sm">Recent Applications</h3>
            <a href="student_applications.php" class="text-blue-600 text-xs font-bold hover:underline">View All →</a>
          </div>
          <?php if(count($app_rows)===0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">assignment_late</span>
            <p class="text-slate-400 text-sm">No applications yet.</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="border-b border-slate-100">
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Title</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
              </tr></thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach(array_slice($app_rows,0,5) as $app):
                  $st=$app['status'];
                  $badge_info = getStatusBadge($st);
                  $bg = $badge_info['color'] . " border";
                  $label = $badge_info['label'];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <?php
                    $is_assigned = in_array($app['status'], ['Project Assigned', 'Internship Active', 'Started', 'Internship Started', 'Active Intern']);
                    $display_title = $is_assigned 
                        ? $app['title'] 
                        : (!empty($app['applied_subtype']) 
                            ? $app['applied_subtype'] 
                            : (!empty($app['project_subtype']) 
                                ? $app['project_subtype'] 
                                : $app['title']));
                  ?>
                  <td class="py-3 px-3 font-medium text-slate-800 max-w-[180px] truncate"><?php echo htmlspecialchars($display_title); ?></td>
                  <td class="py-3 px-3"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $bg; ?>"><?php echo htmlspecialchars($label); ?></span></td>
                  <td class="py-3 px-3 text-slate-400 text-xs"><?php echo !empty($app['applied_date']) ? date('M d, Y', strtotime($app['applied_date'])) : 'Not available'; ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php else: ?>
      <!-- ══ NO ACTIVE INTERNSHIP ══ -->
      <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-slate-800">Dashboard</h2>
        <p class="text-sm text-slate-400 mt-0.5">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>! Start your internship journey.</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">badge</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Status</p><p class="text-sm font-extrabold text-slate-400">Not Started</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">assignment</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Applications</p><p class="text-sm font-extrabold text-slate-800"><?php echo $app_count; ?></p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">notifications</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alerts</p><p class="text-sm font-extrabold text-slate-800"><?php echo $unread_count; ?></p></div>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-white flex flex-col gap-4 shadow-lg">
          <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">rocket_launch</span></div>
          <div><h3 class="text-lg font-extrabold">Start Your Journey</h3><p class="text-blue-200 text-sm mt-1 leading-relaxed">Browse available internships and apply to kickstart your career.</p></div>
          <a href="student_browse_internships.php" class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-blue-50 transition-colors shadow-sm w-fit">
            <span class="material-symbols-outlined text-[18px]">search</span> Browse Internships
          </a>
        </div>
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800 text-sm">Recent Applications</h3>
            <a href="student_applications.php" class="text-blue-600 text-xs font-bold hover:underline">View All →</a>
          </div>
          <?php if(count($app_rows)===0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">assignment_late</span>
            <p class="text-slate-400 text-sm">No applications yet.</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="border-b border-slate-100">
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Title</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
              </tr></thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach(array_slice($app_rows,0,5) as $app):
                  $st=$app['status'];
                  $badge_info = getStatusBadge($st);
                  $bg = $badge_info['color'] . " border";
                  $label = $badge_info['label'];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <?php
                    $is_assigned = in_array($app['status'], ['Project Assigned', 'Internship Active', 'Started', 'Internship Started', 'Active Intern']);
                    $display_title = $is_assigned 
                        ? $app['title'] 
                        : (!empty($app['applied_subtype']) 
                            ? $app['applied_subtype'] 
                            : (!empty($app['project_subtype']) 
                                ? $app['project_subtype'] 
                                : $app['title']));
                  ?>
                  <td class="py-3 px-3 font-medium text-slate-800 max-w-[180px] truncate"><?php echo htmlspecialchars($display_title); ?></td>
                  <td class="py-3 px-3"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $bg; ?>"><?php echo htmlspecialchars($label); ?></span></td>
                  <td class="py-3 px-3 text-slate-400 text-xs"><?php echo !empty($app['applied_date']) ? date('M d, Y', strtotime($app['applied_date'])) : 'Not available'; ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </section>

        <section id="sec-internship" class="dashboard-section">
      <!-- Header row -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">My Internship</h2>
          <p class="text-sm text-slate-400 mt-0.5">Full details of your active internship and application journey</p>
        </div>
        <?php if ($has_active): ?>
        <span class="flex items-center gap-2 px-4 py-2 <?php echo htmlspecialchars($state_badge_class); ?> border rounded-full text-xs font-bold uppercase tracking-wide">
          <span class="w-2 h-2 rounded-full <?php echo $internship_state === 'active' ? 'bg-emerald-500' : ($internship_state === 'completed' ? 'bg-blue-500' : 'bg-amber-500'); ?> animate-ping inline-block"></span> <?php echo htmlspecialchars($state_badge_text); ?>
        </span>
        <?php elseif ($is_selected && !$has_project_details): ?>
        <span class="flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold uppercase tracking-wide">
          <span class="material-symbols-outlined text-[14px]">check_circle</span> Selected
        </span>
        <?php endif; ?>
      </div>

      <?php if (!$has_project_details): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center max-w-lg mx-auto">
          <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <span class="material-symbols-outlined text-[40px] text-slate-300">hourglass_empty</span>
          </div>
          <h3 class="text-lg font-bold text-slate-700 mb-2">Project not assigned yet.</h3>
          <p class="text-slate-500 text-sm mb-4">You will see project details after coordinator assignment.</p>
          <?php if (!empty($pending_app)): ?>
          <div class="text-left mx-auto inline-block text-sm text-slate-600 mb-4">
            <?php 
              $applied_internship = '';
              if (!empty($pending_app['applied_subtype'])) {
                  $applied_internship = trim($pending_app['applied_subtype']);
              } elseif (!empty($pending_app['project_subtype'])) {
                  $applied_internship = trim($pending_app['project_subtype']);
              }
              if (empty($applied_internship) || in_array(strtolower($applied_internship), ['internship management platform', 'imp'], true)) {
                  $applied_internship = 'Not Assigned';
              }
            ?>
            <p class="mb-1"><span class="font-semibold">Applied Internship:</span> <?php echo htmlspecialchars($applied_internship); ?></p>
            <p><span class="font-semibold">Application Status:</span> <?php echo htmlspecialchars(formatStatusLabel($pending_app['status'] ?? 'Pending')); ?></p>
          </div>
          <?php endif; ?>
          <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="student_applications.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
              <span class="material-symbols-outlined text-[18px]">assignment</span> View Application Status
            </a>
          </div>
        </div>
      <?php else:
        $intern_end = clone $end_date;
        $resolved_applied_date = !empty($active_intern['applied_date']) ? $active_intern['applied_date'] : null;
        $display_applied_date = !empty($resolved_applied_date) ? date('M d, Y', strtotime($resolved_applied_date)) : null;
        $display_start_date = !empty($active_intern['start_date']) || !empty($resolved_applied_date) ? date('M d, Y', strtotime($active_intern['start_date'] ?: $resolved_applied_date)) : null;
        $internship_subtype_display = !empty($active_intern['project_subtype']) ? $active_intern['project_subtype'] : (!empty($active_intern['applied_subtype']) ? $active_intern['applied_subtype'] : 'Internship Not Assigned');
        $internship_state_label = $internship_state === 'active' ? 'Active' : ($internship_state === 'completed' ? 'Completed' : 'Upcoming');
        $status_tile_class = $internship_state === 'active' ? 'bg-emerald-50 border-emerald-100' : ($internship_state === 'completed' ? 'bg-blue-50 border-blue-100' : 'bg-amber-50 border-amber-100');
        $status_text_class = $internship_state === 'active' ? 'text-emerald-700' : ($internship_state === 'completed' ? 'text-blue-700' : 'text-amber-700');
        $internship_header_subtitle = '';
        $normalized_subtype = strtolower(trim($internship_subtype_display));
        $normalized_domain = strtolower(trim($active_intern['domain'] ?? ''));
        if (!empty($active_intern['domain']) && $normalized_subtype !== 'web development' && $normalized_domain !== $normalized_subtype) {
            $internship_header_subtitle = htmlspecialchars($active_intern['domain']);
        }
      ?>
      <div class="grid grid-cols-1 gap-6">

        <!-- Main details -->
        <div class="space-y-5">

          <!-- Hero internship card -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <!-- Gradient banner -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 flex items-start justify-between gap-4">
              <div>
                <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-1">Internship</p>
                <h3 class="text-xl font-extrabold text-white leading-snug"><?php echo htmlspecialchars($internship_subtype_display); ?></h3>
                <?php if ($internship_header_subtitle !== ''): ?>
                  <p class="text-blue-200 text-xs mt-1"><?php echo $internship_header_subtitle; ?></p>
                <?php endif; ?>
              </div>
              <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-white text-[26px]">badge</span>
              </div>
            </div>
            <!-- Details grid -->
            <div class="p-6">
              <div class="grid grid-cols-2 md:grid-cols-3 gap-5 text-sm">
                <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100">
                  <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wide mb-1">Start Date</p>
                  <p class="font-bold text-slate-800"><?php echo !empty($display_start_date) ? htmlspecialchars($display_start_date) : 'Not available'; ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100">
                  <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wide mb-1">End Date</p>
                  <p class="font-bold text-slate-800"><?php echo $end_date->format('M d, Y'); ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100">
                  <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wide mb-1">Duration</p>
                  <p class="font-bold text-slate-800"><?php echo !empty($active_intern['duration']) ? htmlspecialchars($active_intern['duration']) : '3 Months'; ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100">
                  <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wide mb-1">Mode</p>
                  <p class="font-bold text-slate-800 capitalize"><?php echo !empty($active_intern['mode']) ? htmlspecialchars($active_intern['mode']) : 'Remote'; ?></p>
                </div>
                <div class="<?php echo $status_tile_class; ?> rounded-xl p-3.5 border">
                  <p class="text-[10px] font-bold uppercase tracking-wide mb-1">Status</p>
                  <p class="font-bold <?php echo $status_text_class; ?>"><?php echo htmlspecialchars($internship_state_label); ?></p>
                </div>
              </div>
              <!-- Progress bar -->
              <div class="mt-5 pt-5 border-t border-slate-100">
                <div class="flex justify-between text-xs mb-2">
                  <span class="text-slate-500 font-semibold">Overall Progress</span>
                  <span class="font-bold text-slate-700"><?php echo $progress_pct; ?>% · <?php echo $days_active; ?> days active · <?php echo $days_left; ?> remaining</span>
                </div>
                <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                  <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-700" style="width:<?php echo $progress_pct; ?>%"></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
      <?php endif; ?>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: DAILY LOGS
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-daily-logs" class="dashboard-section">
      <h2 class="text-xl font-bold text-slate-800 mb-6">Daily Logs</h2>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- A) Submit New Log Form -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-5">Submit Today's Log</h3>
          <form id="log-form" method="POST" action="submit_daily_log.php" class="space-y-4">
            <input type="hidden" name="log_date" value="<?php echo date('Y-m-d'); ?>">
            <input type="hidden" name="internship_id" value="<?php echo $has_active ? intval($active_intern['internship_id']) : 0; ?>">

            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tasks Completed <span class="text-red-500">*</span></label>
              <textarea name="tasks_completed" rows="4" required
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
                placeholder="Describe what you worked on today…"></textarea>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time Spent (hours) <span class="text-red-500">*</span></label>
              <input type="number" name="time_spent" min="0.5" max="12" step="0.5" required
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-colors"
                placeholder="e.g. 4">
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">Focus Level <span class="text-red-500">*</span></label>
              <select name="focus_level" required
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-colors bg-white">
                <option value="">Select focus level</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">Issues Faced <span class="text-slate-400 font-normal">(optional)</span></label>
              <textarea name="issues_faced" rows="2"
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
                placeholder="Any blockers or challenges?"></textarea>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">Next Day Plan <span class="text-slate-400 font-normal">(optional)</span></label>
              <textarea name="next_plan" rows="2"
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 resize-none transition-colors"
                placeholder="What will you work on tomorrow?"></textarea>
            </div>

            <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm">
              Submit Today's Log
            </button>
          </form>
        </div>

        <!-- B) Previous Logs Table -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-5">Previous Logs</h3>
          <?php $all_log_rows = []; while ($lr = mysqli_fetch_assoc($all_logs_result)) $all_log_rows[] = $lr; ?>
          <?php if (count($all_log_rows) === 0): ?>
          <div class="text-center py-12">
            <span class="material-symbols-outlined text-[40px] text-slate-300 block mb-3">edit_note</span>
            <p class="text-slate-400 text-sm">No logs submitted yet. Start logging your daily work!</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100">
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Tasks</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Hours</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Focus</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Issues Faced</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Next Day Plan</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach ($all_log_rows as $lr): ?>
                <?php
                  $focus_cls = match($lr['focus_level']) {
                    'High'   => 'bg-emerald-100 text-emerald-700',
                    'Medium' => 'bg-amber-100 text-amber-700',
                    'Low'    => 'bg-red-100 text-red-600',
                    default  => 'bg-slate-100 text-slate-600',
                  };
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-3 px-3 text-slate-500 whitespace-nowrap"><?php echo date('M d, Y', strtotime($lr['log_date'])); ?></td>
                  <td class="py-3 px-3 text-slate-700 max-w-xs">
                    <span title="<?php echo htmlspecialchars($lr['tasks_completed']); ?>">
                      <?php echo htmlspecialchars(mb_strimwidth($lr['tasks_completed'], 0, 60, '…')); ?>
                    </span>
                  </td>
                  <td class="py-3 px-3 font-semibold text-slate-700"><?php echo number_format($lr['time_spent'], 1); ?>h</td>
                  <td class="py-3 px-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?php echo $focus_cls; ?>"><?php echo htmlspecialchars($lr['focus_level']); ?></span></td>
                  <td class="py-3 px-3 text-slate-700 max-w-xs">
                    <span title="<?php echo htmlspecialchars($lr['issues_faced'] ?? ''); ?>">
                      <?php echo htmlspecialchars(mb_strimwidth($lr['issues_faced'] ?? '', 0, 60, '…')); ?>
                    </span>
                  </td>
                  <td class="py-3 px-3 text-slate-700 max-w-xs">
                    <span title="<?php echo htmlspecialchars($lr['next_plan'] ?? ''); ?>">
                      <?php echo htmlspecialchars(mb_strimwidth($lr['next_plan'] ?? '', 0, 60, '…')); ?>
                    </span>
                  </td>
                  <td class="py-3 px-3">
                    <?php if ($lr['log_date'] === date('Y-m-d')): ?>
                    <a href="edit_daily_log.php?id=<?php echo intval($lr['id']); ?>" class="text-xs font-bold text-blue-600 hover:text-blue-800 mr-3">Edit</a>
                    <form method="POST" action="/IMP/mentor/delete_daily_log.php" class="inline">
                      <input type="hidden" name="log_id" value="<?php echo intval($lr['id']); ?>">
                      <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-800">Delete</button>
                    </form>
                    <?php else: ?>
                      <span class="text-xs text-slate-500">View Only</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: PROJECT
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-project" class="dashboard-section">
      <h2 class="text-xl font-bold text-slate-800 mb-6">My Project</h2>

      <?php if (!$has_project_details): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center">
        <span class="material-symbols-outlined text-[48px] text-slate-300 mb-4 block">terminal</span>
        <p class="text-slate-700 font-bold text-base mb-3">Project not assigned yet.</p>
        <p class="text-slate-400 text-sm mb-4">You will see project details after coordinator assignment.</p>
        <?php if (!empty($pending_app)): ?>
        <div class="text-left mx-auto inline-block text-sm text-slate-600">
          <?php 
            $applied_internship_sec = '';
            if (!empty($pending_app['applied_subtype'])) {
                $applied_internship_sec = trim($pending_app['applied_subtype']);
            } elseif (!empty($pending_app['project_subtype'])) {
                $applied_internship_sec = trim($pending_app['project_subtype']);
            }
            if (empty($applied_internship_sec) || in_array(strtolower($applied_internship_sec), ['internship management platform', 'imp'], true)) {
                $applied_internship_sec = 'Not Assigned';
            }
          ?>
          <p class="mb-1"><span class="font-semibold">Applied Internship:</span> <?php echo htmlspecialchars($applied_internship_sec); ?></p>
          <p><span class="font-semibold">Application Status:</span> <?php echo htmlspecialchars(formatStatusLabel($pending_app['status'] ?? 'Pending')); ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Project Details -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-5">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[26px]">terminal</span>
            </div>
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assigned Project</p>
              <h3 class="text-lg font-extrabold text-slate-800 mt-0.5"><?php echo htmlspecialchars($active_intern['project_name']); ?></h3>
            </div>
          </div>

          <p class="text-sm text-slate-600 leading-relaxed"><?php echo htmlspecialchars($active_intern['project_desc']); ?></p>

          <!-- Tech Stack -->
          <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Tech Stack</p>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($active_intern['project_stack'] as $tech): ?>
              <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full border border-indigo-100"><?php echo htmlspecialchars($tech); ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Meta -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-50 text-sm">
            <div>
              <p class="text-slate-400 text-xs font-medium">Project Type</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['project_type'] ?: 'General'); ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Project Subtype</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['project_subtype'] ?: 'General'); ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Duration</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['duration'] ?: ($active_intern['internship_duration'] ?? 'N/A')); ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Mode</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['mode'] ?: 'N/A'); ?></p>
            </div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-50 text-sm">
            <div>
              <p class="text-slate-400 text-xs font-medium">Assigned Team</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['team_name'] ?: 'Not Assigned'); ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Mentor</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($active_intern['mentor_name'] ?: 'Not Assigned'); ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Assigned Date</p>
              <p class="font-semibold text-slate-700 mt-0.5">
                <?php echo (!empty($active_intern['assigned_date'])) ? htmlspecialchars(date('M d, Y', strtotime($active_intern['assigned_date']))) : 'N/A'; ?>
              </p>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-medium">Project Status</p>
              <p class="font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars(formatStatusLabel($active_intern['project_status'] ?? 'Active')); ?></p>
            </div>
          </div>
          <div class="pt-4 border-t border-slate-50">
            <p class="text-slate-400 text-xs font-medium mb-3">Team Collaboration</p>
            <?php if (!empty($assigned_team)): ?>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
              <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                <div class="flex items-center justify-between mb-3">
                  <h5 class="text-sm font-semibold text-slate-700">Team Members</h5>
                  <span class="text-xs font-semibold text-slate-400"><?php echo count($team_members); ?> member<?php echo count($team_members) !== 1 ? 's' : ''; ?></span>
                </div>
                <?php if (!empty($team_members)): ?>
                <div class="space-y-2">
                  <?php foreach ($team_members as $member): ?>
                  <div class="flex items-start justify-between gap-3 rounded-lg bg-white px-3 py-2 border border-slate-100">
                    <div>
                      <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($member['full_name'] ?: 'Unnamed Member'); ?></p>
                      <p class="text-xs text-slate-500"><?php echo htmlspecialchars($member['college_name'] ?: 'College N/A'); ?> • <?php echo htmlspecialchars($member['department'] ?: 'Department N/A'); ?></p>
                    </div>
                    <?php if (!empty($member['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Email</a>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-slate-500">No team members listed yet.</p>
                <?php endif; ?>
              </div>

              <div id="team-discussion" class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                <div class="flex items-center justify-between mb-3">
                  <h5 class="text-sm font-semibold text-slate-700">Team Discussion</h5>
                  <span class="text-xs font-semibold text-slate-400"><?php echo count($team_messages); ?> message<?php echo count($team_messages) !== 1 ? 's' : ''; ?></span>
                </div>
                <?php if (!empty($discussion_posted)): ?>
                <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Message posted successfully.</div>
                <?php endif; ?>
                <?php if (!empty($discussion_error)): ?>
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?php echo htmlspecialchars($discussion_error); ?></div>
                <?php endif; ?>
                <form method="POST" class="space-y-3">
                  <input type="hidden" name="team_message_submit" value="1">
                  <textarea name="team_message" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="Share a team update, question, or note with your mentor and teammates"></textarea>
                  <div class="flex justify-end">
                    <button type="submit" class="px-3 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Post Update</button>
                  </div>
                </form>
                <div class="mt-4 space-y-3 max-h-72 overflow-y-auto pr-1">
                  <?php if (!empty($team_messages)): ?>
                  <?php foreach ($team_messages as $message): ?>
                  <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div class="flex items-center justify-between gap-2 mb-1">
                      <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($message['sender_name'] ?: ($message['sender_role'] === 'mentor' ? 'Mentor' : 'Team Member')); ?></p>
                      <p class="text-[11px] text-slate-400"><?php echo htmlspecialchars($message['created_at']); ?></p>
                    </div>
                    <p class="text-sm text-slate-600 whitespace-pre-line"><?php echo htmlspecialchars($message['message']); ?></p>
                  </div>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <p class="text-sm text-slate-500">No discussion updates yet. Start the conversation below.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php else: ?>
            <p class="text-sm text-slate-500">Your team will appear here once your coordinator assigns one.</p>
            <?php endif; ?>
          </div>
          <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50 text-sm">
            <div>
              <p class="text-slate-400 text-xs font-medium">Current Phase</p>
              <span class="inline-block mt-0.5 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full"><?php echo htmlspecialchars($phases[$current_phase_num]['short']); ?></span>
            </div>
          </div>
        </div>

        <!-- Phase-wise Progress -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-5">Phase Progress</h4>
          <div class="space-y-4">
            <?php foreach ($phases as $pnum => $phase):
              $pct = ($pnum < $current_phase_num) ? 100 : (($pnum === $current_phase_num) ? 50 : 0);
              $bar_cls = ($pct === 100) ? 'bg-emerald-500' : (($pct === 50) ? 'bg-blue-500' : 'bg-slate-200');
              $label_cls = ($pnum < $current_phase_num) ? 'text-emerald-600' : (($pnum === $current_phase_num) ? 'text-blue-700 font-bold' : 'text-slate-400');
            ?>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="<?php echo $label_cls; ?>"><?php echo htmlspecialchars($phase['label']); ?></span>
                <span class="font-semibold text-slate-500"><?php echo $pct; ?>%</span>
              </div>
              <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                <div class="<?php echo $bar_cls; ?> h-full rounded-full transition-all" style="width:<?php echo $pct; ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: FEEDBACK
    ═══════════════════════════════════════════════════════════ -->
    <!-- ═══════════════════════════════════════════════════════════
         SECTION: FEEDBACK
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-feedback" class="dashboard-section">

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Mentor Feedback</h2>
          <p class="text-sm text-slate-400 mt-0.5">Reviews and evaluations from your mentor and HR team</p>
        </div>
        <?php if ($feedback_count > 0): ?>
        <span class="px-3 py-1.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full text-xs font-bold">
          <?php echo $feedback_count; ?> Review<?php echo $feedback_count > 1 ? 's' : ''; ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if ($feedback_count === 0): ?>
      <!-- Empty state -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center max-w-md mx-auto">
        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-5">
          <span class="material-symbols-outlined text-[40px] text-slate-300">reviews</span>
        </div>
        <h3 class="text-lg font-bold text-slate-600 mb-2">No Feedback Available Yet</h3>
        <p class="text-slate-400 text-sm leading-relaxed">Your mentor and HR team haven't submitted any feedback yet. Check back after your first review session.</p>
        <div class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-400">
          <span class="material-symbols-outlined text-[16px]">info</span>
          <span>Feedback is typically shared after weekly check-ins</span>
        </div>
      </div>

      <?php else: ?>
      <?php
        // Collect all feedback rows
        $fb_rows = [];
        while ($fb = mysqli_fetch_assoc($feedback_result)) $fb_rows[] = $fb;
        // Compute average rating
        $rated = array_filter($fb_rows, fn($r) => !empty($r['rating']));
        $avg_rating = count($rated) ? round(array_sum(array_column($rated, 'rating')) / count($rated), 1) : null;
      ?>

      <!-- Summary bar -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6 flex flex-wrap items-center gap-6">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-500 text-[24px]" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;">star</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Avg Rating</p>
            <p class="text-2xl font-extrabold text-slate-800"><?php echo $avg_rating ?? '—'; ?><span class="text-sm text-slate-400 font-normal">/5</span></p>
          </div>
        </div>
        <div class="h-10 w-px bg-slate-100 hidden sm:block"></div>
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-indigo-500 text-[24px]">reviews</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Total Reviews</p>
            <p class="text-2xl font-extrabold text-slate-800"><?php echo $feedback_count; ?></p>
          </div>
        </div>
        <?php if ($avg_rating): ?>
        <div class="h-10 w-px bg-slate-100 hidden sm:block"></div>
        <div class="flex items-center gap-1.5">
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <span class="material-symbols-outlined text-[22px] <?php echo $s <= round($avg_rating) ? 'text-amber-400' : 'text-slate-200'; ?>"
                style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;">star</span>
          <?php endfor; ?>
          <span class="text-sm text-slate-500 font-semibold ml-1"><?php echo $avg_rating; ?> out of 5</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Feedback cards grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($fb_rows as $fb):
          $rating = intval($fb['rating'] ?? 0);
          $rating_label = match(true) {
            $rating >= 5 => ['Excellent',  'bg-emerald-100 text-emerald-700 border-emerald-200'],
            $rating >= 4 => ['Good',       'bg-blue-100 text-blue-700 border-blue-200'],
            $rating >= 3 => ['Average',    'bg-amber-100 text-amber-700 border-amber-200'],
            $rating >= 1 => ['Needs Work', 'bg-red-100 text-red-600 border-red-200'],
            default      => ['Pending',    'bg-slate-100 text-slate-500 border-slate-200'],
          };
        ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col overflow-hidden group">

          <!-- Card top accent -->
          <div class="h-1 w-full <?php echo $rating >= 4 ? 'bg-gradient-to-r from-emerald-400 to-teal-400' : ($rating >= 3 ? 'bg-gradient-to-r from-amber-400 to-orange-400' : 'bg-gradient-to-r from-slate-200 to-slate-300'); ?>"></div>

          <div class="p-5 flex flex-col gap-4 flex-1">
            <!-- Title row -->
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <h4 class="font-bold text-slate-800 text-sm leading-snug truncate">
                  <?php echo htmlspecialchars($fb['feedback_title'] ?? 'Performance Review'); ?>
                </h4>
                <div class="flex items-center gap-1.5 mt-1">
                  <span class="material-symbols-outlined text-[13px] text-slate-400">person</span>
                  <p class="text-xs text-slate-400">by <span class="font-semibold text-slate-600"><?php echo htmlspecialchars($fb['given_by'] ?? 'Mentor'); ?></span></p>
                </div>
              </div>
              <!-- Status badge -->
              <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-bold border <?php echo $rating_label[1]; ?>">
                <?php echo $rating_label[0]; ?>
              </span>
            </div>

            <!-- Star rating -->
            <?php if ($rating > 0): ?>
            <div class="flex items-center gap-1">
              <?php for ($s = 1; $s <= 5; $s++): ?>
              <span class="material-symbols-outlined text-[18px] <?php echo $s <= $rating ? 'text-amber-400' : 'text-slate-200'; ?>"
                    style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;">star</span>
              <?php endfor; ?>
              <span class="text-xs text-slate-400 ml-1 font-medium"><?php echo $rating; ?>/5</span>
            </div>
            <?php endif; ?>

            <!-- Comments -->
            <?php if (!empty($fb['comments'])): ?>
            <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100 flex-1">
              <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide mb-1.5">Comments</p>
              <p class="text-sm text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($fb['comments'])); ?></p>
            </div>
            <?php else: ?>
            <div class="bg-slate-50 rounded-xl p-3.5 border border-dashed border-slate-200 flex-1 flex items-center justify-center">
              <p class="text-xs text-slate-400 italic">No comments provided</p>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
              <div class="flex items-center gap-1.5 text-xs text-slate-400">
                <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                <span><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></span>
              </div>
              <div class="flex items-center gap-1.5 text-xs text-slate-400">
                <span class="material-symbols-outlined text-[14px]">schedule</span>
                <span><?php echo date('g:i A', strtotime($fb['created_at'])); ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: CERTIFICATE
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-certificate" class="dashboard-section">

      <!-- Header -->
      <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Certificate</h2>
        <p class="text-sm text-slate-400 mt-0.5">Your internship completion certificate and eligibility status</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Status card (2 cols) -->
        <div class="lg:col-span-2">
          <?php if (!$has_active): ?>
          <!-- Not started -->
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 flex flex-col items-center text-center">
            <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mb-5">
              <span class="material-symbols-outlined text-[48px] text-slate-400">workspace_premium</span>
            </div>
            <span class="px-3 py-1 bg-slate-100 text-slate-500 text-xs font-bold rounded-full uppercase tracking-wide mb-4">Not Eligible</span>
            <h3 class="text-xl font-extrabold text-slate-700 mb-2">No Active Internship</h3>
            <p class="text-slate-400 text-sm max-w-sm leading-relaxed">You need to complete an internship before you can receive a certificate. Apply for an internship to get started.</p>
            <a href="student_browse_internships.php" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
              <span class="material-symbols-outlined text-[18px]">search</span> Browse Internships
            </a>
          </div>

          <?php elseif ($is_completed): ?>
          <!-- Eligible — certificate ready -->
          <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl shadow-sm overflow-hidden">
            <!-- Top banner -->
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-8 py-6 flex items-center gap-5">
              <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-white text-[36px]" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 48;">workspace_premium</span>
              </div>
              <div>
                <p class="text-emerald-100 text-xs font-bold uppercase tracking-widest mb-1">Certificate Status</p>
                <h3 class="text-2xl font-extrabold text-white">Available for Download</h3>
                <p class="text-emerald-100 text-sm mt-0.5">Internship successfully completed</p>
              </div>
            </div>
            <!-- Body -->
            <div class="p-8">
              <div class="flex items-center gap-3 mb-6 p-4 bg-white rounded-xl border border-emerald-100 shadow-sm">
                <span class="material-symbols-outlined text-emerald-500 text-[22px]">check_circle</span>
                <div>
                  <p class="text-sm font-bold text-slate-800">Congratulations, <?php echo htmlspecialchars($profile['full_name']); ?>! 🎉</p>
                  <p class="text-xs text-slate-500 mt-0.5">You have successfully completed your internship at <?php echo htmlspecialchars($active_intern['company_name'] ?? 'IMP Technologies'); ?>.</p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div class="bg-white rounded-xl p-4 border border-emerald-100">
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide mb-1">Internship</p>
                  <p class="font-bold text-slate-800"><?php echo htmlspecialchars($active_intern['title']); ?></p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-emerald-100">
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide mb-1">Duration</p>
                  <p class="font-bold text-slate-800"><?php echo !empty($active_intern['duration']) ? htmlspecialchars($active_intern['duration']) : '3 Months'; ?></p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-emerald-100">
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide mb-1">Completed On</p>
                  <p class="font-bold text-slate-800"><?php echo $end_date ? $end_date->format('M d, Y') : date('M d, Y'); ?></p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-emerald-100">
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide mb-1">Domain</p>
                  <p class="font-bold text-slate-800"><?php echo htmlspecialchars($active_intern['domain'] ?? 'General'); ?></p>
                </div>
              </div>
              <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?php echo htmlspecialchars("generate_certificate.php?user_id=" . intval($user_id) . "&mode=view"); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="flex-1 flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-md text-sm">
                  <span class="material-symbols-outlined text-[20px]">visibility</span>
                  View Certificate
                </a>
                <a href="<?php echo htmlspecialchars("generate_certificate.php?user_id=" . intval($user_id) . "&mode=download"); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="flex-1 flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3.5 rounded-xl transition-all text-sm border border-slate-200">
                  <span class="material-symbols-outlined text-[20px]">download</span>
                  Download
                </a>
              </div>
              <p class="text-center text-xs text-slate-400 mt-3">Certificate is digitally signed and verifiable</p>
            </div>
          </div>

          <?php else: ?>
          <!-- In progress -->
          <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
            <!-- Top banner -->
            <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-8 py-6 flex items-center gap-5">
              <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-white text-[36px]">hourglass_top</span>
              </div>
              <div>
                <p class="text-amber-100 text-xs font-bold uppercase tracking-widest mb-1">Certificate Status</p>
                <h3 class="text-2xl font-extrabold text-white">In Progress</h3>
                <p class="text-amber-100 text-sm mt-0.5"><?php echo $days_left; ?> days remaining to completion</p>
              </div>
            </div>
            <!-- Body -->
            <div class="p-8">
              <!-- Progress ring area -->
              <div class="flex items-center gap-6 mb-6 p-5 bg-amber-50 rounded-xl border border-amber-100">
                <!-- Circular progress (CSS) -->
                <div class="relative w-20 h-20 shrink-0">
                  <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="34" fill="none" stroke="#fde68a" stroke-width="8"/>
                    <circle cx="40" cy="40" r="34" fill="none" stroke="#f59e0b" stroke-width="8"
                            stroke-dasharray="<?php echo round(2 * 3.14159 * 34); ?>"
                            stroke-dashoffset="<?php echo round(2 * 3.14159 * 34 * (1 - $progress_pct / 100)); ?>"
                            stroke-linecap="round"/>
                  </svg>
                  <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-sm font-extrabold text-amber-700"><?php echo $progress_pct; ?>%</span>
                  </div>
                </div>
                <div>
                  <p class="font-bold text-slate-800 text-sm">Keep going, you're doing great!</p>
                  <p class="text-xs text-slate-500 mt-1"><?php echo $days_active; ?> days completed · <?php echo $days_left; ?> days remaining</p>
                  <p class="text-xs text-amber-600 font-semibold mt-2">Certificate unlocks after 90 days</p>
                </div>
              </div>
              <!-- Progress bar -->
              <div class="mb-4">
                <div class="flex justify-between text-xs mb-2">
                  <span class="text-slate-500 font-semibold">Internship Progress</span>
                  <span class="font-bold text-amber-600"><?php echo $progress_pct; ?>% of 100%</span>
                </div>
                <div class="w-full bg-amber-100 h-3 rounded-full overflow-hidden">
                  <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-full rounded-full transition-all duration-700" style="width:<?php echo $progress_pct; ?>%"></div>
                </div>
              </div>
              <!-- Locked download button -->
              <button disabled class="w-full flex items-center justify-center gap-2.5 bg-slate-200 text-slate-400 font-bold py-3.5 rounded-xl cursor-not-allowed text-sm">
                <span class="material-symbols-outlined text-[20px]">lock</span>
                Download Certificate (Locked)
              </button>
              <p class="text-center text-xs text-slate-400 mt-3">Available after internship completion</p>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right: Requirements checklist -->
        <div class="space-y-4">
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-4">Eligibility Checklist</h4>
            <div class="space-y-3">
              <?php
                $total_logs = $total_logs ?? 0;
                $checks = [
                  ['label' => 'Internship Started',      'done' => $has_active],
                  ['label' => 'Daily Logs Submitted',    'done' => $total_logs > 0],
                  
                  ['label' => 'Internship Completed',    'done' => $is_completed],
                ];
              ?>
              <?php foreach ($checks as $chk): ?>
              <div class="flex items-center gap-3 p-3 rounded-xl <?php echo $chk['done'] ? 'bg-emerald-50 border border-emerald-100' : 'bg-slate-50 border border-slate-100'; ?>">
                <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 <?php echo $chk['done'] ? 'bg-emerald-500' : 'bg-slate-200'; ?>">
                  <span class="material-symbols-outlined text-[14px] <?php echo $chk['done'] ? 'text-white' : 'text-slate-400'; ?>">
                    <?php echo $chk['done'] ? 'check' : 'close'; ?>
                  </span>
                </div>
                <span class="text-sm font-medium <?php echo $chk['done'] ? 'text-emerald-700' : 'text-slate-500'; ?>">
                  <?php echo $chk['label']; ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Info card -->
          <div class="bg-blue-50 rounded-2xl border border-blue-100 p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="material-symbols-outlined text-blue-500 text-[18px]">info</span>
              <p class="text-sm font-bold text-blue-700">About Your Certificate</p>
            </div>
            <ul class="space-y-2 text-xs text-blue-600">
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] mt-0.5 shrink-0">arrow_right</span>Digitally signed PDF certificate</li>
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] mt-0.5 shrink-0">arrow_right</span>Includes internship title, duration & domain</li>
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] mt-0.5 shrink-0">arrow_right</span>Verifiable with unique certificate ID</li>
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] mt-0.5 shrink-0">arrow_right</span>Share on LinkedIn or add to resume</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         SECTION: NOTIFICATIONS
    ═══════════════════════════════════════════════════════════ -->
    <section id="sec-notifications" class="dashboard-section">
      <?php
        $notif_rows = [];
        while ($nr = mysqli_fetch_assoc($all_notif_result)) $notif_rows[] = $nr;
        $total_n  = count($notif_rows);
        $unread_n = count(array_filter($notif_rows, fn($n) => !$n['is_read']));
        // Refined type config — meaningful labels + IMP color system
        // Blue=Application, Green=Approval, Orange=Reminder, Red=Rejection, Purple=Feedback, Yellow=Verification
        $type_cfg = [
          
          'warning'     => ['icon'=>'warning',           'icon_bg'=>'bg-orange-100',  'text'=>'text-orange-600', 'badge'=>'bg-orange-100 text-orange-700',  'dot'=>'bg-orange-500',  'label'=>'Reminder'],
          'success'     => ['icon'=>'check_circle',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Approval'],
          'approved'    => ['icon'=>'verified',          'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Approval'],
          'selected'    => ['icon'=>'emoji_events',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Approval'],
          'certificate' => ['icon'=>'workspace_premium', 'icon_bg'=>'bg-purple-100',  'text'=>'text-purple-600', 'badge'=>'bg-purple-100 text-purple-700',  'dot'=>'bg-purple-500',  'label'=>'Feedback'],
          'feedback'    => ['icon'=>'reviews',           'icon_bg'=>'bg-purple-100',  'text'=>'text-purple-600', 'badge'=>'bg-purple-100 text-purple-700',  'dot'=>'bg-purple-500',  'label'=>'Feedback'],
          'mentor'      => ['icon'=>'person_pin',        'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Application'],
          'internship'  => ['icon'=>'badge',             'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Application'],
          'error'       => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge'=>'bg-red-100 text-red-700',        'dot'=>'bg-red-500',     'label'=>'Rejection'],
          'rejected'    => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge'=>'bg-red-100 text-red-700',        'dot'=>'bg-red-500',     'label'=>'Rejection'],
          'info'        => ['icon'=>'info',              'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Application'],
          'log'         => ['icon'=>'edit_note',         'icon_bg'=>'bg-orange-100',  'text'=>'text-orange-600', 'badge'=>'bg-orange-100 text-orange-700',  'dot'=>'bg-orange-500',  'label'=>'Reminder'],
          'verification'=> ['icon'=>'verified_user',     'icon_bg'=>'bg-yellow-100',  'text'=>'text-yellow-700', 'badge'=>'bg-yellow-100 text-yellow-700',  'dot'=>'bg-yellow-500',  'label'=>'Verification'],
          'mentor_message'=> ['icon'=>'chat',            'icon_bg'=>'bg-indigo-100',  'text'=>'text-indigo-600', 'badge'=>'bg-indigo-100 text-indigo-700',  'dot'=>'bg-indigo-500',  'label'=>'Mentor Message'],
          'default'     => ['icon'=>'notifications',     'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Application'],
        ];
      ?>

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
            Recent Notifications
            <span id="notif-unread-badge" class="<?php echo $unread_n > 0 ? '' : 'hidden'; ?> inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 bg-red-500 text-white text-[11px] font-extrabold rounded-full"><?php echo $unread_n; ?></span>
          </h2>
          <p class="text-sm text-slate-400 mt-0.5">Real-time updates about your internship, tests, feedback and more</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Filter tabs -->
          <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-1">
            <button data-filter="all"    class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-white text-slate-700 shadow-sm">All</button>
            <button data-filter="unread" class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Unread</button>
            <button data-filter="read"   class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Read</button>
          </div>
          <!-- Single Mark all read button -->
          <button id="btn-mark-all-read" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-[14px]">done_all</span> Mark all read
          </button>
          <!-- Single Clear all button -->
          <button id="btn-clear-all" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-[14px]">delete_sweep</span> Clear all
          </button>
        </div>
      </div>

      <!-- Main grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Notification list -->
        <div class="lg:col-span-2">
          <!-- Empty state -->
          <div id="notif-empty-state" class="<?php echo $total_n > 0 ? 'hidden' : ''; ?> bg-white rounded-2xl border border-slate-100 shadow-sm p-14 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
              <span class="material-symbols-outlined text-[36px] text-slate-300">notifications_none</span>
            </div>
            <h3 class="text-base font-bold text-slate-600 mb-1">No Notifications Available</h3>
            <p class="text-slate-400 text-sm">You're all caught up! Updates will appear here automatically.</p>
          </div>
          <!-- Filter empty -->
          <div id="notif-filter-empty" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm p-10 text-center">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">filter_list_off</span>
            <p class="text-slate-500 text-sm font-medium">No notifications match this filter.</p>
          </div>
          <!-- Cards -->
          <div id="notif-list" class="space-y-3">
            <?php foreach ($notif_rows as $nr):
              $type   = strtolower($nr['type'] ?? 'default');
              $tc     = $type_cfg[$type] ?? $type_cfg['default'];
              $unread = !$nr['is_read'];
              $nid    = intval($nr['id']);
              $cr = new DateTime($nr['created_at']); $now2 = new DateTime(); $df = $now2->diff($cr);
              if ($df->days >= 7)     $ta = date('M d, Y', strtotime($nr['created_at']));
              elseif ($df->days >= 1) $ta = $df->days . 'd ago';
              elseif ($df->h >= 1)    $ta = $df->h . 'h ago';
              elseif ($df->i >= 1)    $ta = $df->i . 'm ago';
              else                    $ta = 'Just now';
            ?>
            <div id="notif-card-<?php echo $nid; ?>" data-id="<?php echo $nid; ?>" data-read="<?php echo $unread ? '0' : '1'; ?>" data-source="<?php echo htmlspecialchars($nr['source_table'] ?? 'student'); ?>"
                 class="notif-card group bg-white rounded-2xl border <?php echo $unread ? 'border-blue-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
              <div class="flex">
                <div class="w-1 shrink-0 rounded-l-2xl <?php echo $unread ? $tc['dot'] : 'bg-slate-200'; ?>"></div>
                <div class="flex-1 p-4 flex items-start gap-4">
                  <div class="w-11 h-11 rounded-xl <?php echo $tc['icon_bg']; ?> flex items-center justify-center shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[22px] <?php echo $tc['text']; ?>"><?php echo $tc['icon']; ?></span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $tc['badge']; ?>"><?php echo htmlspecialchars($tc['label']); ?></span>
                        <?php if ($unread): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-600 text-white">New</span><?php endif; ?>
                      </div>
                      <?php if ($unread): ?><div class="w-2.5 h-2.5 <?php echo $tc['dot']; ?> rounded-full shrink-0 mt-1 animate-pulse"></div><?php endif; ?>
                    </div>
                    <?php if (!empty($nr['title'])): ?>
                      <h4 class="text-sm font-bold text-slate-900 mb-0.5"><?php echo htmlspecialchars($nr['title']); ?></h4>
                    <?php endif; ?>
                    <p class="<?php echo !empty($nr['title']) ? 'text-xs text-slate-600' : 'text-sm text-slate-700 ' . ($unread ? 'font-semibold' : 'font-medium'); ?> leading-snug"><?php echo htmlspecialchars($nr['message']); ?></p>
                    <?php if (!empty($nr['attachment_path'])): ?>
                        <div class="mt-3 flex items-center gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100 max-w-fit">
                            <span class="material-symbols-outlined text-[16px] text-gray-500">attachment</span>
                            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($nr['attachment_name']); ?></span>
                            <span class="text-gray-400"> (<?php echo round($nr['attachment_size'] / 1024, 1); ?> KB)</span>
                            <span class="text-slate-300">|</span>
                            <a href="<?php echo htmlspecialchars($nr['attachment_path']); ?>" target="_blank" class="text-blue-600 font-bold hover:underline">View</a>
                            <a href="<?php echo htmlspecialchars($nr['attachment_path']); ?>" download class="text-indigo-600 font-bold hover:underline ml-1">Download</a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($nr['sender_name'])): ?>
                      <p class="text-[11px] text-indigo-600 font-semibold mt-1.5">From: <?php echo htmlspecialchars($nr['sender_name']); ?></p>
                    <?php endif; ?>
                    <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100">
                      <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <span class="material-symbols-outlined text-[13px]">schedule</span>
                        <span><?php echo $ta; ?></span>
                        <span class="text-slate-300 mx-1">·</span>
                        <span><?php echo date('M d, Y · g:i A', strtotime($nr['created_at'])); ?></span>
                      </div>
                      <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <?php if ($unread): ?>
                        <button onclick="notifMarkRead(<?php echo $nid; ?>, this)" class="flex items-center gap-1 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 rounded-lg text-[11px] font-semibold transition-colors">
                          <span class="material-symbols-outlined text-[13px]">done</span> Mark read
                        </button>
                        <?php endif; ?>
                        <button onclick="notifDelete(<?php echo $nid; ?>, this)" class="flex items-center gap-1 px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-lg text-[11px] font-semibold transition-colors">
                          <span class="material-symbols-outlined text-[13px]">close</span> Remove
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- RIGHT: Summary card — Total + Unread only, no duplicate buttons -->
        <div>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sticky top-24">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-4">Summary</h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-2 text-sm text-slate-600">
                  <span class="material-symbols-outlined text-[16px] text-slate-400">notifications</span> Total
                </div>
                <span id="stat-total" class="font-extrabold text-slate-800"><?php echo $total_n; ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl border border-red-100">
                <div class="flex items-center gap-2 text-sm text-red-600 font-medium">
                  <span class="material-symbols-outlined text-[16px] text-red-400">mark_email_unread</span> Unread
                </div>
                <span id="stat-unread" class="font-extrabold text-red-600"><?php echo $unread_n; ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- /main -->
</div><!-- /pl-64 -->

<script>
// ── Section navigation ────────────────────────────────────────────────────────
(function () {
  const navItems = document.querySelectorAll('.nav-item[data-section]');
  const sections = document.querySelectorAll('.dashboard-section');
  function showSection(id) {
    sections.forEach(s => {
      if (s.id === id) { s.style.display = 'block'; requestAnimationFrame(() => requestAnimationFrame(() => s.classList.add('active'))); }
      else { s.classList.remove('active'); s.style.display = 'none'; }
    });
    navItems.forEach(btn => {
      if (btn.dataset.section === id) { btn.classList.add('bg-blue-50','text-blue-700','font-semibold'); btn.classList.remove('text-gray-600','hover:bg-gray-50','hover:text-blue-600'); }
      else { btn.classList.remove('bg-blue-50','text-blue-700','font-semibold'); btn.classList.add('text-gray-600','hover:bg-gray-50','hover:text-blue-600'); }
    });
    history.replaceState(null, '', '#' + id);
  }
  navItems.forEach(btn => btn.addEventListener('click', () => showSection(btn.dataset.section)));
  const hash = location.hash.replace('#', '');
  const validIds = Array.from(sections).map(s => s.id);
  showSection(hash && validIds.includes(hash) ? hash : 'sec-dashboard');
})();

// ── Profile dropdown ──────────────────────────────────────────────────────────
(function () {
  const toggle = document.getElementById('profile-toggle');
  const dropdown = document.getElementById('profile-dropdown');
  if (!toggle || !dropdown) return;
  toggle.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('hidden'); });
  document.addEventListener('click', e => { if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden'); });
})();

// ── Daily log form validation ─────────────────────────────────────────────────
(function () {
  const form = document.getElementById('log-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    const tasks = form.querySelector('[name="tasks_completed"]').value.trim();
    const hours = parseFloat(form.querySelector('[name="time_spent"]').value);
    const focus = form.querySelector('[name="focus_level"]').value;
    if (!tasks) { e.preventDefault(); alert('Please describe the tasks you completed today.'); return; }
    if (isNaN(hours) || hours < 0.5 || hours > 12) { e.preventDefault(); alert('Please enter a valid time between 0.5 and 12 hours.'); return; }
    if (!focus) { e.preventDefault(); alert('Please select a focus level.'); }
  });
})();

// ── Header search — real-time section content filter ─────────────────────────
(function () {
  const input = document.getElementById('header-search');
  if (!input) return;

  // Section keyword → nav section id mapping (for Enter-to-jump)
  const sectionMap = {
    'dashboard':'sec-dashboard','internship':'sec-internship',
    'log':'sec-daily-logs','daily':'sec-daily-logs','project':'sec-project',
    'feedback':'sec-feedback','certificate':'sec-certificate','notification':'sec-notifications'
  };

  // ── Real-time: filter cards/rows in the ACTIVE section ───────────────────
  input.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();

    // Find which section is currently active
    const activeSection = document.querySelector('.dashboard-section.active');
    if (!activeSection) return;

    // Searchable items: cards, table rows, notification cards, log rows
    const searchTargets = [
      ...activeSection.querySelectorAll('.notif-card'),
      ...activeSection.querySelectorAll('tbody tr:not(#imp-search-empty-row)'),
      ...activeSection.querySelectorAll('.bg-white.rounded-2xl[class*="border"]'),
    ];

    // Deduplicate (a card might match multiple selectors)
    const seen = new Set();
    const items = searchTargets.filter(el => {
      if (seen.has(el)) return false;
      seen.add(el);
      return true;
    });

    if (items.length === 0) return;

    let visible = 0;
    items.forEach(item => {
      const text = item.textContent.toLowerCase();
      const match = q === '' || text.includes(q);
      item.style.display = match ? '' : 'none';
      if (match) {
        item.style.opacity = '0';
        item.style.transform = 'translateY(4px)';
        requestAnimationFrame(() => {
          item.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
          item.style.opacity = '1';
          item.style.transform = 'translateY(0)';
        });
        visible++;
      }
    });

    // Empty state
    let emptyEl = activeSection.querySelector('#dash-search-empty');
    if (visible === 0 && q !== '') {
      if (!emptyEl) {
        emptyEl = document.createElement('div');
        emptyEl.id = 'dash-search-empty';
        emptyEl.className = 'bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center mt-4';
        emptyEl.innerHTML = `
          <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3">
            <span class="material-symbols-outlined text-[28px] text-slate-300">search_off</span>
          </div>
          <p class="text-slate-500 font-semibold text-sm">No results found for "<span id="dash-search-query"></span>"</p>
          <p class="text-slate-400 text-xs mt-1">Try a different keyword.</p>`;
        activeSection.appendChild(emptyEl);
      }
      const qSpan = emptyEl.querySelector('#dash-search-query');
      if (qSpan) qSpan.textContent = this.value;
      emptyEl.style.display = 'block';
    } else if (emptyEl) {
      emptyEl.style.display = 'none';
    }
  });

  // ── Enter key: jump to matching section ──────────────────────────────────
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      this.value = '';
      this.dispatchEvent(new Event('input'));
      this.blur();
      return;
    }
    if (e.key !== 'Enter') return;
    const q = this.value.toLowerCase().trim();
    for (const [kw, id] of Object.entries(sectionMap)) {
      if (q.includes(kw)) {
        document.querySelector(`.nav-item[data-section="${id}"]`)?.click();
        this.value = '';
        this.blur();
        return;
      }
    }
  });

  // ── Clear hidden items when section changes ───────────────────────────────
  document.querySelectorAll('.nav-item[data-section]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (input.value) {
        input.value = '';
        // Restore all hidden items
        document.querySelectorAll('.dashboard-section [style*="display: none"]').forEach(el => {
          el.style.display = '';
        });
        document.querySelectorAll('#dash-search-empty').forEach(el => el.style.display = 'none');
      }
    });
  });
})();

// ══════════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS — fully functional AJAX system
// ══════════════════════════════════════════════════════════════════════════════
(function () {

  // ── Helpers ──────────────────────────────────────────────────────────────
  function updateStats() {
    const all         = document.querySelectorAll('.notif-card');
    const unread      = document.querySelectorAll('.notif-card[data-read="0"]');
    const total       = all.length;
    const unreadCount = unread.length;

    // Update summary card — Total + Unread only
    const statTotal  = document.getElementById('stat-total');
    const statUnread = document.getElementById('stat-unread');
    if (statTotal)  statTotal.textContent  = total;
    if (statUnread) statUnread.textContent = unreadCount;

    // Update header unread badge
    const badge = document.getElementById('notif-unread-badge');
    if (badge) {
      badge.textContent = unreadCount;
      badge.classList.toggle('hidden', unreadCount === 0);
    }

    // Update sidebar nav badge
    const sidebarBadge = document.querySelector('.nav-item[data-section="sec-notifications"] span.bg-red-100');
    if (sidebarBadge) {
      sidebarBadge.textContent = unreadCount;
      sidebarBadge.classList.toggle('hidden', unreadCount === 0);
    }

    // Disable/enable action buttons
    const markAllBtn  = document.getElementById('btn-mark-all-read');
    const clearAllBtn = document.getElementById('btn-clear-all');
    if (markAllBtn)  markAllBtn.disabled  = unreadCount === 0;
    if (clearAllBtn) clearAllBtn.disabled = total === 0;

    // Show/hide empty state
    const emptyState = document.getElementById('notif-empty-state');
    if (emptyState) emptyState.classList.toggle('hidden', total > 0);

    // Re-apply current filter
    applyFilter(currentFilter);
  }

  function showToast(msg, type = 'success') {
    const existing = document.getElementById('notif-toast');
    if (existing) existing.remove();
    const colors = type === 'success'
      ? 'bg-emerald-600 border-emerald-500'
      : type === 'error'
      ? 'bg-red-600 border-red-500'
      : 'bg-blue-600 border-blue-500';
    const icon = type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info';
    const toast = document.createElement('div');
    toast.id = 'notif-toast';
    toast.className = `fixed top-6 right-6 z-[999] ${colors} text-white rounded-2xl shadow-xl px-5 py-3.5 flex items-center gap-3 border transform translate-x-[420px] transition-transform duration-500 ease-out`;
    toast.innerHTML = `<span class="material-symbols-outlined text-[20px]">${icon}</span><span class="text-sm font-semibold">${msg}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-x-[420px]'), 50);
    setTimeout(() => { toast.classList.add('translate-x-[420px]'); setTimeout(() => toast.remove(), 500); }, 3000);
  }

  function slideOut(el, cb) {
    el.style.transition = 'all 0.3s ease';
    el.style.opacity    = '0';
    el.style.transform  = 'translateX(40px)';
    el.style.maxHeight  = el.offsetHeight + 'px';
    setTimeout(() => { el.style.maxHeight = '0'; el.style.marginBottom = '0'; el.style.padding = '0'; }, 150);
    setTimeout(() => { el.remove(); if (cb) cb(); }, 350);
  }

  // ── Filter ────────────────────────────────────────────────────────────────
  let currentFilter = 'all';

  function applyFilter(filter) {
    currentFilter = filter;
    const cards   = document.querySelectorAll('.notif-card');
    let visible   = 0;
    cards.forEach(card => {
      const isRead = card.dataset.read === '1';
      let show = filter === 'all' || (filter === 'unread' && !isRead) || (filter === 'read' && isRead);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const filterEmpty = document.getElementById('notif-filter-empty');
    if (filterEmpty) filterEmpty.classList.toggle('hidden', visible > 0 || cards.length === 0);
  }

  document.querySelectorAll('.notif-tab').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.notif-tab').forEach(t => {
        t.classList.remove('bg-white', 'text-slate-700', 'shadow-sm');
        t.classList.add('text-slate-500');
      });
      this.classList.add('bg-white', 'text-slate-700', 'shadow-sm');
      this.classList.remove('text-slate-500');
      applyFilter(this.dataset.filter);
    });
  });

  // ── Mark single as read ───────────────────────────────────────────────────
  window.notifMarkRead = async function (id, btn) {
    btn.disabled = true;
    const card = document.getElementById(`notif-card-${id}`);
    const source = card ? card.dataset.source : 'student';
    try {
      const res  = await fetch(`mark_notification_read.php?action=read&id=${id}&source=${source}`);
      const data = await res.json();
      if (data.success) {
        const card = document.getElementById(`notif-card-${id}`);
        if (card) {
          card.dataset.read = '1';
          // Remove blue border + bg
          card.classList.remove('border-blue-200');
          card.classList.add('border-slate-100');
          // Remove left accent color
          const bar = card.querySelector('.w-1');
          if (bar) { bar.className = bar.className.replace(/bg-\w+-\d+/g, ''); bar.classList.add('bg-slate-200'); }
          // Remove "New" badge
          card.querySelectorAll('.bg-blue-600.text-white').forEach(el => el.remove());
          // Remove unread dot
          card.querySelectorAll('.animate-pulse').forEach(el => el.remove());
          // Remove mark-read button
          btn.closest('button')?.remove();
          // Make text normal weight
          const msg = card.querySelector('p.font-semibold');
          if (msg) { msg.classList.remove('font-semibold'); msg.classList.add('font-medium'); }
        }
        updateStats();
        showToast('Marked as read');
      }
    } catch { showToast('Failed to update', 'error'); btn.disabled = false; }
  };

  // ── Delete single ─────────────────────────────────────────────────────────
  window.notifDelete = async function (id, btn) {
    btn.disabled = true;
    const card = document.getElementById(`notif-card-${id}`);
    const source = card ? card.dataset.source : 'student';
    try {
      const res  = await fetch(`mark_notification_read.php?action=delete&id=${id}&source=${source}`);
      const data = await res.json();
      if (data.success) {
        const card = document.getElementById(`notif-card-${id}`);
        if (card) slideOut(card, updateStats);
        showToast('Notification removed');
      } else { showToast('Failed to remove', 'error'); btn.disabled = false; }
    } catch { showToast('Failed to remove', 'error'); btn.disabled = false; }
  };

  // ── Mark ALL as read ──────────────────────────────────────────────────────
  async function markAllRead() {
    const unreadCards = document.querySelectorAll('.notif-card[data-read="0"]');
    if (unreadCards.length === 0) return;
    try {
      const res  = await fetch('mark_notification_read.php?action=read_all');
      const data = await res.json();
      if (data.success) {
        unreadCards.forEach(card => {
          card.dataset.read = '1';
          card.classList.remove('border-blue-200'); card.classList.add('border-slate-100');
          const bar = card.querySelector('.w-1');
          if (bar) { bar.className = bar.className.replace(/bg-\w+-\d+/g, ''); bar.classList.add('bg-slate-200'); }
          card.querySelectorAll('.bg-blue-600.text-white, .animate-pulse').forEach(el => el.remove());
          const msg = card.querySelector('p.font-semibold');
          if (msg) { msg.classList.remove('font-semibold'); msg.classList.add('font-medium'); }
          // Remove mark-read buttons
          card.querySelectorAll('button').forEach(b => { if (b.textContent.includes('Mark read')) b.remove(); });
        });
        updateStats();
        showToast(`${unreadCards.length} notification${unreadCards.length > 1 ? 's' : ''} marked as read`);
      }
    } catch { showToast('Failed to update', 'error'); }
  }

  // ── Clear ALL ─────────────────────────────────────────────────────────────
  async function clearAll() {
    const cards = document.querySelectorAll('.notif-card');
    if (cards.length === 0) return;
    if (!confirm(`Remove all ${cards.length} notification${cards.length > 1 ? 's' : ''}? This cannot be undone.`)) return;
    try {
      const res  = await fetch('mark_notification_read.php?action=delete_all');
      const data = await res.json();
      if (data.success) {
        const list = document.getElementById('notif-list');
        cards.forEach((card, i) => {
          setTimeout(() => slideOut(card, i === cards.length - 1 ? updateStats : null), i * 60);
        });
        showToast('All notifications cleared');
      } else { showToast('Failed to clear', 'error'); }
    } catch { showToast('Failed to clear', 'error'); }
  }


  // ── Wire up buttons — single Mark all read + single Clear all ────────────
  document.getElementById('btn-mark-all-read')?.addEventListener('click', markAllRead);
  document.getElementById('btn-clear-all')?.addEventListener('click', clearAll);

  // ── Init ──────────────────────────────────────────────────────────────────
  updateStats();

})();
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');
    
    if (themeToggle && themeToggleIcon) {
        // Set initial icon
        if (document.documentElement.classList.contains('dark')) {
            themeToggleIcon.textContent = 'light_mode';
        } else {
            themeToggleIcon.textContent = 'dark_mode';
        }
        
        themeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                themeToggleIcon.textContent = 'dark_mode';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                themeToggleIcon.textContent = 'light_mode';
            }
        });
    }
});
</script>
<?php print_resume_not_found_js(); ?>
