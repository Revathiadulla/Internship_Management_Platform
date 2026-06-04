<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";
$notif_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'coordinator' AND is_read = 0");
$notif_unread_row = mysqli_fetch_assoc($notif_unread_res);
$unread_count = $notif_unread_row['count'] ?? 0;
include_once __DIR__ . "/includes/mail_helper.php";

// Auto-migration: ensure question_bank and student_scores tables exist
$check_qb = $conn->query("SHOW TABLES LIKE 'question_bank'");
if ($check_qb->num_rows == 0) {
    $create_qb = "CREATE TABLE question_bank (
        id INT AUTO_INCREMENT PRIMARY KEY,
        skill VARCHAR(100) NOT NULL,
        difficulty VARCHAR(20) NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_question (question_text, skill, difficulty)
    ) ENGINE=InnoDB;";
    $conn->query($create_qb);
}
$check_ss = $conn->query("SHOW TABLES LIKE 'student_scores'");
if ($check_ss->num_rows == 0) {
    $create_ss = "CREATE TABLE student_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        internship_id INT NOT NULL,
        score INT NOT NULL,
        taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE
    ) ENGINE=InnoDB;";
    $conn->query($create_ss);
}

// Ensure internships table has num_questions column
$check_col = $conn->query("SHOW COLUMNS FROM internships LIKE 'num_questions'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE internships ADD COLUMN num_questions INT DEFAULT 5");
}

// Ensure internships table has soft-delete column
$check_deleted_col = $conn->query("SHOW COLUMNS FROM internships LIKE 'is_deleted'");
if ($check_deleted_col->num_rows == 0) {
    $conn->query("ALTER TABLE internships ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
}

// Helper function to generate test questions
function generate_test_questions($conn, $internship_id, $skillsCsv, $difficulty, $numQuestions) {
    // Delete old generated questions for this internship
    $del = $conn->prepare('DELETE FROM test_questions WHERE internship_id = ?');
    $del->bind_param('i', $internship_id);
    $del->execute();

    $skills = array_map('trim', explode(',', $skillsCsv));
    if (empty($skills)) return [false, 0];
    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($skills), '?'));
    $types = str_repeat('s', count($skills)) . 'si'; // skills (s), difficulty (s), limit (i)
    $stmt = $conn->prepare(
        "SELECT id FROM question_bank WHERE skill IN (".$placeholders.") AND difficulty = ? LIMIT ?"
    );
    $bindParams = array_merge($skills, [$difficulty, $numQuestions]);
    $stmt->bind_param($types, ...$bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while($row = $res->fetch_assoc()) $ids[] = $row['id'];
    if (count($ids) < $numQuestions) {
        return [false, count($ids)];
    }
    // Fetch full questions
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt2 = $conn->prepare("SELECT * FROM question_bank WHERE id IN (".$in.")");
    $stmt2->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt2->execute();
    $questions = $stmt2->get_result();
    // Insert into test_questions
    $ins = $conn->prepare(
        "INSERT INTO test_questions (internship_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?,?,?,?,?,?,?)"
    );
    while($q = $questions->fetch_assoc()) {
        $ins->bind_param(
            'issssss',
            $internship_id,
            $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option']
        );
        $ins->execute();
    }
    return [true, $numQuestions];
}


$success_msg = "";
$error_msg = "";

// Generate Timeline Phases helper function
function generatePhases($conn, $internship_id, $duration, $start_date_str) {
    if (empty($start_date_str)) return;
    $start_date = new DateTime($start_date_str);
    
    $phase_names = [
        1 => 'P1 Learning Phase',
        2 => 'P2 Documentation & Planning',
        3 => 'P3 Designing',
        4 => 'P4 Development',
        5 => 'P5 Testing',
        6 => 'P6 Deployment'
    ];
    
    $days = [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5];
    
    if ($duration === '1 Month') {
        $days = [1 => 3, 2 => 3, 3 => 5, 4 => 10, 5 => 5, 6 => 4];
    } elseif ($duration === '2 Months') {
        $days = [1 => 7, 2 => 7, 3 => 14, 4 => 21, 5 => 7, 6 => 7];
    } elseif ($duration === '3 Months') {
        $days = [1 => 14, 2 => 14, 3 => 21, 4 => 35, 5 => 14, 6 => 14];
    } else {
        // Fallback for custom months (e.g. 6 Months)
        preg_match('/(\d+)/', $duration, $matches);
        $num_months = isset($matches[1]) ? intval($matches[1]) : 3;
        $total_days = $num_months * 30;
        
        $days[1] = round($total_days * 0.15);
        $days[2] = round($total_days * 0.15);
        $days[3] = round($total_days * 0.20);
        $days[4] = round($total_days * 0.30);
        $days[5] = round($total_days * 0.10);
        $days[6] = $total_days - ($days[1] + $days[2] + $days[3] + $days[4] + $days[5]);
    }
    
    // Delete existing phases
    $del_stmt = mysqli_prepare($conn, "DELETE FROM internship_phases WHERE internship_id = ?");
    mysqli_stmt_bind_param($del_stmt, "i", $internship_id);
    mysqli_stmt_execute($del_stmt);
    mysqli_stmt_close($del_stmt);
    
    // Insert new phases
    $current_start = clone $start_date;
    $today = date('Y-m-d');
    for ($p = 1; $p <= 6; $p++) {
        $current_end = clone $current_start;
        $day_offset = $days[$p] - 1;
        if ($day_offset < 0) $day_offset = 0;
        $current_end->modify("+$day_offset days");
        
        $s_str = $current_start->format('Y-m-d');
        $e_str = $current_end->format('Y-m-d');
        
        $status = 'Pending';
        if ($today >= $s_str && $today <= $e_str) {
            $status = 'Active';
        } elseif ($today > $e_str) {
            $status = 'Completed';
        }
        
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO internship_phases (internship_id, phase_number, phase_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "iissss", $internship_id, $p, $phase_names[$p], $s_str, $e_str, $status);
        mysqli_stmt_execute($ins_stmt);
        mysqli_stmt_close($ins_stmt);
        
        $current_start = clone $current_end;
        $current_start->modify("+1 day");
    }
}

// Handle AJAX timeline fetch
if (isset($_GET['action']) && $_GET['action'] === 'get_timeline' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = mysqli_query($conn, "SELECT * FROM internship_phases WHERE internship_id = $id ORDER BY phase_number ASC");
    $phases = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $phases[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($phases);
    exit();
}

// Handle Timeline Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_timeline') {
    $internship_id = intval($_POST['internship_id']);
    $phase_ids = $_POST['phase_id'];
    $start_dates = $_POST['start_date'];
    $end_dates = $_POST['end_date'];
    $statuses = $_POST['status'];
    
    $success = true;
    for ($i = 0; $i < count($phase_ids); $i++) {
        $pid = intval($phase_ids[$i]);
        $s_date = $start_dates[$i];
        $e_date = $end_dates[$i];
        $stat = $statuses[$i];
        
        $stmt = mysqli_prepare($conn, "UPDATE internship_phases SET start_date = ?, end_date = ?, status = ? WHERE id = ? AND internship_id = ?");
        mysqli_stmt_bind_param($stmt, "sssii", $s_date, $e_date, $stat, $pid, $internship_id);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
        }
        mysqli_stmt_close($stmt);
    }
    if ($success) {
        header("Location: coordinator_internships.php?success=" . urlencode("Timeline workflow updated successfully!"));
        exit();
    } else {
        $error_msg = "Error updating timeline.";
    }
}

// Fetch mentors list for dropdowns
$mentors_res = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role='mentor' ORDER BY full_name ASC");
$mentors = [];
while ($row = mysqli_fetch_assoc($mentors_res)) {
    $mentors[] = $row;
}

$active_project_types = [];
$type_stmt = mysqli_prepare($conn, "SELECT pt.id, pt.type_name FROM project_types pt JOIN coordinator_assignments ca ON pt.id = ca.project_type_id WHERE pt.status = 'Active' AND ca.coordinator_id = ? ORDER BY pt.type_name ASC");
if ($type_stmt) {
    $coord_id = intval($_SESSION['user_id']);
    $type_stmt->bind_param('i', $coord_id);
    $type_stmt->execute();
    $type_result = $type_stmt->get_result();
    while ($type_row = mysqli_fetch_assoc($type_result)) {
        $active_project_types[] = $type_row;
    }
    $type_stmt->close();
}

$active_project_subtypes = [];
if (!empty($active_project_types)) {
    $first_type_id = intval($active_project_types[0]['id']);
    $subtype_stmt = mysqli_prepare($conn, "SELECT id, subtype_name FROM project_subtypes WHERE project_type_id = ? AND status = 'Active' ORDER BY subtype_name ASC");
    if ($subtype_stmt) {
        $subtype_stmt->bind_param('i', $first_type_id);
        $subtype_stmt->execute();
        $subtype_result = $subtype_stmt->get_result();
        while ($subtype_row = mysqli_fetch_assoc($subtype_result)) {
            $active_project_subtypes[] = $subtype_row;
        }
        $subtype_stmt->close();
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Check each table for linked records (safely check if table exists first)
    $scores_count = 0;
    $applications_count = 0;
    $questions_count = 0;
    $teams_count = 0;
    $logs_count = 0;

    // Check if table exists before querying
    $table_exists = function($table_name) use ($conn) {
        $res = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
        return mysqli_num_rows($res) > 0;
    };

    if ($table_exists('student_scores')) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM student_scores WHERE internship_id = $id");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $scores_count = intval($row['cnt']);
        }
    }

    if ($table_exists('internship_applications')) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM internship_applications WHERE internship_id = $id");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $applications_count = intval($row['cnt']);
        }
    }

    if ($table_exists('test_questions')) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM test_questions WHERE internship_id = $id");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $questions_count = intval($row['cnt']);
        }
    }

    if ($table_exists('project_teams')) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM project_teams WHERE internship_id = $id");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $teams_count = intval($row['cnt']);
        }
    }

    if ($table_exists('daily_logs')) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM daily_logs WHERE internship_id = $id");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $logs_count = intval($row['cnt']);
        }
    }

    // Check if there are critical blockers (scores, applications, teams, logs)
    $critical_blockers = $scores_count + $applications_count + $teams_count + $logs_count;

    // Build error message with specific table information
    $blocking_reasons = [];
    if ($scores_count > 0) {
        $blocking_reasons[] = "$scores_count student score" . ($scores_count !== 1 ? 's' : '');
    }
    if ($applications_count > 0) {
        $blocking_reasons[] = "$applications_count student application" . ($applications_count !== 1 ? 's' : '');
    }
    if ($teams_count > 0) {
        $blocking_reasons[] = "$teams_count project team" . ($teams_count !== 1 ? 's' : '');
    }
    if ($logs_count > 0) {
        $blocking_reasons[] = "$logs_count daily log" . ($logs_count !== 1 ? 's' : '');
    }

    // If there are only test questions (no critical data), offer deletion option
    if ($critical_blockers === 0 && $questions_count > 0) {
        // Allow deletion of test questions automatically before soft delete
        if ($table_exists('test_questions')) {
            mysqli_query($conn, "DELETE FROM test_questions WHERE internship_id = $id");
        }
        $stmt = mysqli_prepare($conn, "UPDATE internships SET is_deleted = 1, status = 'Inactive' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: coordinator_internships.php?success=" . urlencode("Project posting archived" . ($questions_count > 0 ? " (test questions removed)." : ".")));
            exit();
        } else {
            $error_msg = "Error archiving posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } elseif ($critical_blockers > 0) {
        // Critical data exists - cannot hard delete, offer soft delete instead
        $error_msg = "Cannot delete because: " . implode(", ", $blocking_reasons) . ". The posting will be marked as inactive instead.";
        // Perform soft delete for critical blockers
        $stmt = mysqli_prepare($conn, "UPDATE internships SET is_deleted = 1, status = 'Inactive' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: coordinator_internships.php?success=" . urlencode("Project posting archived to preserve student records."));
            exit();
        } else {
            $error_msg = "Error archiving posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        // No related records - safe to soft delete
        $stmt = mysqli_prepare($conn, "UPDATE internships SET is_deleted = 1, status = 'Inactive' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: coordinator_internships.php?success=" . urlencode("Project posting removed."));
            exit();
        } else {
            $error_msg = "Error deleting posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Create Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title']);
    $project_title = $title;
    $task_title = $title;
    $duration = trim($_POST['duration']);
    $mode = trim($_POST['mode']);
    $technology_stack = trim($_POST['technology_stack']);
    $skills = $technology_stack; // sync skills with technology stack
    
    $description = trim($_POST['description']);
    $project_type = trim($_POST['project_type']);
    $project_subtype = trim($_POST['project_subtype']);
    $difficulty_level = trim($_POST['difficulty_level']);
    $openings = intval($_POST['openings']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    $valid_category = false;
    if (!empty($project_type) && !empty($project_subtype)) {
        $type_check = mysqli_prepare($conn, "SELECT id FROM project_types WHERE type_name = ? AND status = 'Active' LIMIT 1");
        mysqli_stmt_bind_param($type_check, "s", $project_type);
        mysqli_stmt_execute($type_check);
        mysqli_stmt_bind_result($type_check, $selected_type_id);
        if (mysqli_stmt_fetch($type_check)) {
            mysqli_stmt_close($type_check);
            $subtype_check = mysqli_prepare($conn, "SELECT id FROM project_subtypes WHERE project_type_id = ? AND subtype_name = ? AND status = 'Active' LIMIT 1");
            mysqli_stmt_bind_param($subtype_check, "is", $selected_type_id, $project_subtype);
            mysqli_stmt_execute($subtype_check);
            mysqli_stmt_store_result($subtype_check);
            if (mysqli_stmt_num_rows($subtype_check) > 0) {
                $valid_category = true;
            }
            mysqli_stmt_close($subtype_check);
        } else {
            mysqli_stmt_close($type_check);
        }
    }

        if (empty($title) || empty($project_title) || empty($duration) || empty($mode) || empty($technology_stack) || empty($description) || empty($project_type) || empty($project_subtype) || empty($difficulty_level)) {
        $error_msg = "Please fill in all required fields.";
    } elseif (!$valid_category) {
        $error_msg = "Selected project type and subtype combination is invalid.";
    } else {
        $coord_id = intval($_SESSION['user_id']);
        $stmt = mysqli_prepare($conn, "INSERT INTO internships (title, duration, mode, skills, status, approval_status, description, project_type, project_subtype, project_title, task_title, technology_stack, difficulty_level, openings, start_date, end_date, coordinator_id, submission_date) VALUES (?, ?, ?, ?, 'Pending Approval', 'Pending Approval', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
        mysqli_stmt_bind_param($stmt, "sssssssssssissi", $title, $duration, $mode, $skills, $description, $project_type, $project_subtype, $project_title, $task_title, $technology_stack, $difficulty_level, $openings, $start_date, $end_date, $coord_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            generatePhases($conn, $new_id, $duration, $start_date);

            // Fetch coordinator's name for the email
            $coord_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $coord_id LIMIT 1");
            $coord_row = mysqli_fetch_assoc($coord_res);
            $coord_name = $coord_row['full_name'] ?? 'A Coordinator';

            // Notify Admin about new project awaiting approval
            $admin_email = 'imp.webportal2026@gmail.com';
            $admin_subject = "IMP – New Project Awaiting Approval: $title";
            $admin_message = "Hello Admin,\n\nCoordinator $coord_name has submitted a new project posting that requires your review and approval.\n\nProject: $title\nType: $project_type" . ($project_subtype ? " / $project_subtype" : "") . "\nDuration: $duration | Mode: $mode\n\nPlease log in to the Admin panel to Approve, Reject, or Request Changes.";
            sendEmailNotification($admin_email, $admin_subject, $admin_message, [
                'event'         => 'New Project Pending Approval',
                'project_title' => $title,
                'submitted_by'  => $coord_name,
                'duration'      => $duration,
                'mode'          => $mode,
                'action_url'    => 'http://localhost/IMP/admin_internships.php?status=Pending+Approval',
                'action_label'  => 'Review Project Posting'
            ]);

            header("Location: coordinator_internships.php?success=" . urlencode("Project posting submitted for Admin approval!"));
            exit();
        } else {
            $error_msg = "Error creating project posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $project_title = $title;
    $task_title = $title;
    $duration = trim($_POST['duration']);
    $mode = trim($_POST['mode']);
    $technology_stack = trim($_POST['technology_stack']);
    $skills = $technology_stack; // sync skills with technology stack
    
    $description = trim($_POST['description']);
    $project_type = trim($_POST['project_type']);
    $project_subtype = trim($_POST['project_subtype']);
    $difficulty_level = trim($_POST['difficulty_level']);
    $openings = intval($_POST['openings']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    $valid_category = false;
    if (!empty($project_type) && !empty($project_subtype)) {
        $type_check = mysqli_prepare($conn, "SELECT id FROM project_types WHERE type_name = ? AND status = 'Active' LIMIT 1");
        mysqli_stmt_bind_param($type_check, "s", $project_type);
        mysqli_stmt_execute($type_check);
        mysqli_stmt_bind_result($type_check, $selected_type_id);
        if (mysqli_stmt_fetch($type_check)) {
            mysqli_stmt_close($type_check);
            $subtype_check = mysqli_prepare($conn, "SELECT id FROM project_subtypes WHERE project_type_id = ? AND subtype_name = ? AND status = 'Active' LIMIT 1");
            mysqli_stmt_bind_param($subtype_check, "is", $selected_type_id, $project_subtype);
            mysqli_stmt_execute($subtype_check);
            mysqli_stmt_store_result($subtype_check);
            if (mysqli_stmt_num_rows($subtype_check) > 0) {
                $valid_category = true;
            }
            mysqli_stmt_close($subtype_check);
        } else {
            mysqli_stmt_close($type_check);
        }
    }

        if (empty($title) || empty($project_title) || empty($duration) || empty($mode) || empty($technology_stack) || empty($description) || empty($project_type) || empty($project_subtype) || empty($difficulty_level)) {
        $error_msg = "Please fill in all required fields.";
    } elseif (!$valid_category) {
        $error_msg = "Selected project type and subtype combination is invalid.";
    } else {
        // Fetch old values to check for changes
        $old_res = mysqli_query($conn, "SELECT start_date, duration FROM internships WHERE id = $id");
        $old_row = mysqli_fetch_assoc($old_res);
        $date_changed = ($old_row && $old_row['start_date'] !== $start_date);
        $duration_changed = ($old_row && $old_row['duration'] !== $duration);

        $stmt = mysqli_prepare($conn, "UPDATE internships SET title = ?, duration = ?, mode = ?, skills = ?, description = ?, project_type = ?, project_subtype = ?, project_title = ?, task_title = ?, technology_stack = ?, difficulty_level = ?, openings = ?, start_date = ?, end_date = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssssssissi", $title, $duration, $mode, $skills, $description, $project_type, $project_subtype, $project_title, $task_title, $technology_stack, $difficulty_level, $openings, $start_date, $end_date, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Check if timeline phases exist
            $check_phases_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM internship_phases WHERE internship_id = $id");
            $check_phases_row = mysqli_fetch_assoc($check_phases_res);
            $has_phases = intval($check_phases_row['cnt'] ?? 0) > 0;

            if (!$has_phases || $date_changed || $duration_changed) {
                generatePhases($conn, $id, $duration, $start_date);
            }

            header("Location: coordinator_internships.php?success=" . urlencode("Project posting updated successfully!"));
            exit();
        } else {
            $error_msg = "Error updating project posting: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// Fetch all internships
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$types = "";
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(i.title LIKE ? OR i.project_type LIKE ? OR i.project_subtype LIKE ? OR i.technology_stack LIKE ? OR m.full_name LIKE ?)";
    $search_param = "%" . $search . "%";
    $types = "sssss";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param];
}

$where_clauses[] = "i.coordinator_id = " . intval($_SESSION['user_id']);
$where_clauses[] = "i.is_deleted = 0";
$sql = "
    SELECT i.*, m.full_name as mentor_name 
    FROM internships i 
    LEFT JOIN users m ON i.assigned_mentor = m.id 
";

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY i.project_type ASC, i.project_subtype ASC, i.project_title ASC, i.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$internships_res = mysqli_stmt_get_result($stmt);
$internships = [];
while ($row = mysqli_fetch_assoc($internships_res)) {
    $internships[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Postings - Coordinator</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                 body { font-family: 'Inter', sans-serif; }
                 .material-symbols-outlined {
                         font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                         vertical-align: middle;
                 }
                  aside {
                          transition: transform 0.3s ease-in-out;
                  }
                  main {
                          transition: margin-left 0.3s ease-in-out;
                          min-width: 0;
                          overflow-x: hidden;
                  }
                  @media (max-width: 767px) {
                         aside {
                                 transform: translateX(-100%);
                         }
                         main {
                                 margin-left: 0 !important;
                         }
                         body.sidebar-open aside {
                                 transform: translateX(0);
                         }
                 }
                 @media (min-width: 768px) {
                         body.sidebar-closed aside {
                                 transform: translateX(-100%);
                         }
                         body.sidebar-closed main {
                                 margin-left: 0 !important;
                         }
                 }
        </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- ════════════════ SIDEBAR ════════════════ -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-white border-r border-gray-200 flex flex-col py-6">
                <div class="px-6 mb-8">
                        <a href="index.html" class="flex items-center gap-2">
                                <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none">
                                        <rect width="32" height="32" rx="8" fill="currentColor"/>
                                        <circle cx="16" cy="16" r="3" fill="white"/>
                                        <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
                                        <circle cx="16" cy="8" r="1.5" fill="white"/>
                                        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
                                        <line x1="17.8" y1="18.4" x2="20" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="14.2" y1="18.4" x2="12" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
                                </svg>
                                <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
                        </a>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2 ml-0.5">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-0.5 px-3">
                        <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
                        </a>
                        <a href="coordinator_internships.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
                                <span class="material-symbols-outlined text-[20px]">work</span> Postings
                        </a>
                        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">group</span> Candidates
                        </a>
                        <a href="coordinator_generate_test.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">quiz</span> Generate Test
                        </a>
                        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
                        </a>
                        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
                        </a>
                        <a href="coordinator_teams.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Teams
                        </a>
                </nav>
                <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
                        <a href="coordinator_profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
                        </a>
                        <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">help</span> Help Center
                        </a>
                        <a href="logout.php" class="flex items-center gap-3 text-red-650 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-red-400">logout</span> Logout
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <?php
                $header_uid = $_SESSION['user_id'];
                $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
                $header_user = mysqli_fetch_assoc($header_res);
                $header_name = $header_user['full_name'] ?? 'Coordinator';
                $header_photo = $header_user['profile_photo'] ?? '';
                ?>
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Postings</h2>
                        </div>
                        
                        <div class="flex items-center gap-6">
                                <!-- Notifications Bell -->
                                <a href="coordinator_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
                                        <span class="material-symbols-outlined">notifications</span>
                                        <?php if ($unread_count > 0): ?>
                                                <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                </a>

                                <!-- Profile Dropdown Section -->
                                <div class="relative" id="profile-container">
                                        <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block">
                                                        <?php echo htmlspecialchars($header_name); ?>
                                                </span>
                                                <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-500 transition-colors">
                                                        <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                                                                <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=0D8ABC&color=fff" alt="Profile" class="w-full h-full object-cover">
                                                        <?php endif; ?>
                                                </div>
                                                <span class="material-symbols-outlined text-gray-500 text-[18px] group-hover:text-blue-600 transition-colors">arrow_drop_down</span>
                                        </button>
                                        
                                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                                                <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                        <span>My Profile</span>
                                                </a>
                                                <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                        <span>Settings</span>
                                                </a>
                                                <hr class="my-1 border-gray-100">
                                                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                        <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                        <span>Logout</span>
                                                </a>
                                        </div>
                                </div>
                        </div>
                </header>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                        const profileBtn = document.getElementById('profile-menu-button');
                        const profileDropdown = document.getElementById('profile-dropdown');
                        
                        if (profileBtn && profileDropdown) {
                                profileBtn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        profileDropdown.classList.toggle('hidden');
                                });
                                
                                document.addEventListener('click', function(e) {
                                        if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                                                profileDropdown.classList.add('hidden');
                                        }
                                });
                        }
                });
                </script>


                <div class="flex-1 p-8 space-y-6">
                        <?php if ($success_msg): ?>
                            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 alert-success">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 alert-danger">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Title and Add button -->
                        <div class="flex justify-between items-center">
                                <div>
                                        <h1 class="text-2xl font-bold text-gray-900">Project / Internship Postings</h1>
                                        <p class="text-gray-500 text-sm mt-1">Create and manage project specifications available for cohorts.</p>
                                </div>
                                <button onclick="openCreateModal()" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 shadow-sm transition-all text-sm cursor-pointer">
                                        <span class="material-symbols-outlined text-md">add</span>
                                        New Project Posting
                                </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                            <form method="GET" action="coordinator_internships.php" class="flex gap-2 max-w-md">
                                <div class="relative flex-grow">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search postings, type, tech stack..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="coordinator_internships.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Postings List Table -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                                <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100 whitespace-nowrap">
                                                        <tr>
                                                        <th class="px-6 py-4">Project Title</th>
                                                        <th class="px-6 py-4">Project Type</th>
                                                        <th class="px-6 py-4">Tech Stack</th>
                                                        <th class="px-6 py-4">Difficulty</th>
                                                        <th class="px-6 py-4">Openings</th>
                                                        <th class="px-6 py-4">Assigned Mentor</th>
                                                        <th class="px-6 py-4">Start Date</th>
                                                        <th class="px-6 py-4">End Date</th>
                                                        <th class="px-6 py-4">Status</th>
                                                        <th class="px-6 py-4 text-right">Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-gray-600">
                                                <?php if (empty($internships)): ?>
                                                    <tr>
                                                        <td colspan="10" class="px-6 py-10 text-center text-gray-400">No project postings found. Click "New Project Posting" to create one.</td>
                                                    </tr>
                                                <?php else: ?>
                                                <?php foreach ($internships as $item):
                                                        
                                                    $today = date('Y-m-d');
                                                    $computed_status = $item['status'] ?? 'Active';
                                                        if ($computed_status === 'Active' && !empty($item['start_date'])) {
                                                            if ($item['start_date'] > $today) {
                                                                $computed_status = 'Upcoming';
                                                            } elseif (!empty($item['end_date']) && $item['end_date'] < $today) {
                                                                $computed_status = 'Completed';
                                                            }
                                                        }
                                                        
                                                        $badge_cls = match(true) {
                                                            $computed_status === 'Active'   => 'bg-green-50 text-green-700 border-green-200',
                                                            $computed_status === 'Upcoming' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                            $computed_status === 'Completed'=> 'bg-amber-50 text-amber-700 border-amber-200',
                                                            in_array($item['status'] ?? '', ['Pending Approval', 'Changes Requested']) => 'bg-orange-50 text-orange-700 border-orange-200',
                                                            ($item['status'] ?? '') === 'Rejected' => 'bg-red-50 text-red-700 border-red-200',
                                                            default => 'bg-slate-50 text-slate-700 border-slate-200'
                                                        };
                                                        $approval_status = $item['approval_status'] ?? $item['status'] ?? 'Pending Approval';

                                                        $difficulty_cls = match($item['difficulty_level'] ?? 'Medium') {
                                                            'Easy' => 'bg-emerald-50 text-emerald-600',
                                                            'Hard' => 'bg-red-50 text-red-600',
                                                            default => 'bg-amber-50 text-amber-600'
                                                        };
                                                    ?>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                                <td class="px-6 py-4 font-semibold text-gray-900">
                                                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($item['project_title'] ?: $item['title']); ?></p>
                                                                    <?php if (!empty($item['task_title']) && $item['task_title'] !== $item['project_title']): ?>
                                                                        <p class="text-xs font-semibold text-indigo-600 mt-0.5">↳ <?php echo htmlspecialchars($item['task_title']); ?></p>
                                                                    <?php endif; ?>
                                                                    <p class="text-[10px] text-gray-400 mt-1"><?php echo htmlspecialchars($item['mode'] ?? 'Remote'); ?> • <?php echo htmlspecialchars($item['duration'] ?? '3 Months'); ?></p>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($item['project_type'] ?: 'General'); ?></span>
                                                                    <?php if (!empty($item['project_subtype'])): ?>
                                                                        <p class="text-[10px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($item['project_subtype']); ?></p>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4 max-w-xs truncate" title="<?php echo htmlspecialchars($item['technology_stack'] ?: $item['skills']); ?>"><?php echo htmlspecialchars($item['technology_stack'] ?: $item['skills']); ?></td>
                                                                <td class="px-6 py-4">
                                                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold <?php echo $difficulty_cls; ?>"><?php echo htmlspecialchars($item['difficulty_level'] ?: 'Medium'); ?></span>
                                                                </td>
                                                                <td class="px-6 py-4 font-medium text-gray-700"><?php echo intval($item['openings'] ?? 1); ?></td>
                                                                <td class="px-6 py-4 font-medium text-gray-700"><?php echo htmlspecialchars($item['mentor_name'] ?: 'None'); ?></td>
                                                                <td class="px-6 py-4 text-xs font-semibold text-gray-700 whitespace-nowrap"><?php echo $item['start_date'] ? date('M d, Y', strtotime($item['start_date'])) : '—'; ?></td>
                                                                <td class="px-6 py-4 text-xs font-semibold text-gray-700 whitespace-nowrap"><?php echo $item['end_date'] ? date('M d, Y', strtotime($item['end_date'])) : '—'; ?></td>
                                                                <td class="px-6 py-4">
                                                                        <span class="px-2.5 py-0.5 border rounded-full text-xs font-bold <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($item['status'] ?? $computed_status); ?></span>
                                                                        <?php if (in_array($item['status'] ?? '', ['Changes Requested', 'Rejected']) && !empty($item['admin_remarks'])): ?>
                                                                            <p class="text-[10px] text-red-600 mt-1 font-semibold" title="<?php echo htmlspecialchars($item['admin_remarks']); ?>">⚠ Admin: <?php echo htmlspecialchars(mb_substr($item['admin_remarks'], 0, 40)) . (mb_strlen($item['admin_remarks']) > 40 ? '...' : ''); ?></p>
                                                                        <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                                                    <button onclick='openViewModal(<?php echo json_encode($item); ?>)' class="text-indigo-600 hover:text-indigo-800 font-bold text-xs cursor-pointer mr-1">View</button>
                                                                    <button onclick='openEditModal(<?php echo json_encode($item); ?>)' class="text-blue-600 hover:text-blue-800 font-bold text-xs cursor-pointer mr-1">Edit</button>
                                                                    <a href="coordinator_internships.php?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure you want to delete this project posting?');" class="text-red-600 hover:text-red-800 font-bold text-xs">Delete</a>
                                                                </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                        </tbody>
                                </table>
                                </div>
                        </div>
                </div>
        </main>

        <!-- Create/Edit Modal -->
        <div id="internship-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 id="modal-title" class="text-lg font-bold text-gray-900 font-sans">New Project Posting</h3>
                                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined">close</span>
                                </button>
                        </div>
                        <form id="internship-form" method="POST" action="coordinator_internships.php">
                                <input type="hidden" name="action" id="form-action" value="create">
                                <input type="hidden" name="id" id="internship-id">
                                
                                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                                        <div class="mb-2 p-3 bg-gray-50 border border-gray-200 rounded-xl" id="admin-remarks-container" style="display: none;">
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Admin Remarks</label>
                                                <p id="form-admin-remarks" class="text-sm text-gray-800"></p>
                                        </div>

                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Title</label>
                                                <input type="text" name="title" id="form-title" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. Internship Management Portal">
                                        </div>

                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Technology Stack (comma separated)</label>
                                                <input type="text" name="technology_stack" id="form-tech-stack" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="HTML,CSS,JavaScript">
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Type</label>
                                                        <select name="project_type" id="form-project-type" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer" required>
                                                                <?php if (empty($active_project_types)): ?>
                                                                    <option value="" selected>No project types available</option>
                                                                <?php else: ?>
                                                                    <?php foreach ($active_project_types as $type): ?>
                                                                        <option value="<?php echo htmlspecialchars($type['type_name']); ?>" data-type-id="<?php echo (int)$type['id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Subtype</label>
                                                        <select name="project_subtype" id="form-project-subtype" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer" required>
                                                                <?php if (empty($active_project_subtypes)): ?>
                                                                    <option value="">Select a type first</option>
                                                                <?php else: ?>
                                                                    <?php foreach ($active_project_subtypes as $subtype): ?>
                                                                        <option value="<?php echo htmlspecialchars($subtype['subtype_name']); ?>"><?php echo htmlspecialchars($subtype['subtype_name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                        </select>
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Duration</label>
                                                        <select name="duration" id="form-duration" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="1 Month">1 Month</option>
                                                                <option value="2 Months">2 Months</option>
                                                                <option value="3 Months" selected>3 Months</option>
                                                                <option value="4 Months">4 Months</option>
                                                                <option value="5 Months">5 Months</option>
                                                                <option value="6 Months">6 Months</option>
                                                                <option value="7 Months">7 Months</option>
                                                                <option value="8 Months">8 Months</option>
                                                                <option value="9 Months">9 Months</option>
                                                                <option value="10 Months">10 Months</option>
                                                                <option value="11 Months">11 Months</option>
                                                                <option value="12 Months">12 Months</option>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Mode</label>
                                                        <select name="mode" id="form-mode" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Remote" selected>Remote</option>
                                                                <option value="Hybrid">Hybrid</option>
                                                                <option value="On-Site">On-Site</option>
                                                                <option value="Online">Online</option>
                                                        </select>
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Difficulty Level</label>
                                                        <select name="difficulty_level" id="form-difficulty-level" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                <option value="Easy">Easy</option>
                                                                <option value="Medium" selected>Medium</option>
                                                                <option value="Hard">Hard</option>
                                                        </select>
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Openings</label>
                                                        <input type="number" name="openings" id="form-openings" required min="1" value="1" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Start Date</label>
                                                        <input type="date" name="start_date" id="form-start-date" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                                <div>
                                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">End Date</label>
                                                        <input type="date" name="end_date" id="form-end-date" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                                                </div>
                                        </div>

                                        <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project Description</label>
                                                <textarea name="description" id="form-description" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Describe the milestones, requirements, and deliverables..." rows="3"></textarea>
                                        </div>
                                </div>
                                <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3 font-sans">
                                                                        <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-white transition-colors cursor-pointer">Cancel</button>
                                                                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm cursor-pointer">Save Posting</button>
                                                                </div>
                                                        </form>
                                                </div>
                                        </div>

        <!-- Timeline Modal -->
        <!-- View Modal -->
        <div id="view-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="text-lg font-bold text-gray-900 font-sans">Internship Details</h3>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div><strong>Title:</strong> <span id="view-title"></span></div>
                    <div><strong>Project Type:</strong> <span id="view-project-type"></span></div>
                    <div><strong>Project Subtype:</strong> <span id="view-project-subtype"></span></div>
                    <div><strong>Technology Stack:</strong> <span id="view-tech-stack"></span></div>
                    <div><strong>Difficulty:</strong> <span id="view-difficulty"></span></div>
                    <div><strong>Openings:</strong> <span id="view-openings"></span></div>
                    <div><strong>Mode:</strong> <span id="view-mode"></span></div>
                    <div><strong>Duration:</strong> <span id="view-duration"></span></div>
                    <div><strong>Start Date:</strong> <span id="view-start-date"></span></div>
                    <div><strong>End Date:</strong> <span id="view-end-date"></span></div>
                    <div><strong>Mentor:</strong> <span id="view-mentor"></span></div>
                    <div><strong>Status:</strong> <span id="view-status"></span></div>
                    <div><strong>Admin Remarks:</strong> <span id="view-admin-remarks"></span></div>
                    <div><strong>Description:</strong> <p id="view-description" class="whitespace-pre-wrap"></p></div>
                </div>
            </div>
        </div>

        <div id="timeline-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <div>
                                        <h3 class="text-lg font-bold text-gray-900 font-sans">Internship Timeline & Phase Workflow</h3>
                                        <p id="timeline-modal-subtitle" class="text-xs text-gray-500 mt-1"></p>
                                </div>
                                <button onclick="closeTimelineModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined">close</span>
                                </button>
                        </div>
                        <form id="timeline-form" method="POST" action="coordinator_internships.php">
                                <input type="hidden" name="action" value="update_timeline">
                                <input type="hidden" name="internship_id" id="timeline-internship-id">
                                
                                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                                        <div id="timeline-phases-container" class="space-y-4">
                                                <div class="flex items-center justify-center py-8">
                                                        <p class="text-slate-400 text-sm">Loading timeline details...</p>
                                                </div>
                                        </div>
                                </div>
                                <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3 font-sans">
                                        <button type="button" onclick="closeTimelineModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-white transition-colors cursor-pointer">Cancel</button>
                                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm cursor-pointer">Save Timeline</button>
                                </div>
                        </form>
                </div>
        </div>

        <script>
                const modal = document.getElementById('internship-modal');
                const form = document.getElementById('internship-form');
                const formAction = document.getElementById('form-action');
                const modalTitle = document.getElementById('modal-title');
                const internshipIdInput = document.getElementById('internship-id');
                const titleInput = document.getElementById('form-title');
                const durationInput = document.getElementById('form-duration');
                const modeInput = document.getElementById('form-mode');
                
                const typeInput = document.getElementById('form-project-type');
                const subtypeInput = document.getElementById('form-project-subtype');
                const difficultyInput = document.getElementById('form-difficulty-level');
                const openingsInput = document.getElementById('form-openings');
                const startDateInput = document.getElementById('form-start-date');
                const endDateInput = document.getElementById('form-end-date');
                const techStackInput = document.getElementById('form-tech-stack');
                const descriptionInput = document.getElementById('form-description');

                let currentSubtypes = [];

                function getSelectedTypeId(typeValue) {
                        const option = Array.from(typeInput.options).find(opt => opt.value === typeValue);
                        return option ? option.dataset.typeId : '';
                }

                function autofillSubtypeFields() {
                        const selectedSubtypeName = subtypeInput.value;
                        const subtypeObj = currentSubtypes.find(sub => sub.subtype_name === selectedSubtypeName);
                        if (subtypeObj) {
                                if (subtypeObj.skills) {
                                        techStackInput.value = subtypeObj.skills;
                                }
                                if (subtypeObj.mode) {
                                        let modeValue = subtypeObj.mode;
                                        if (modeValue === 'Offline') {
                                                modeValue = 'On-Site';
                                        }
                                        let modeExists = Array.from(modeInput.options).some(opt => opt.value === modeValue);
                                        if (!modeExists && modeValue) {
                                                const opt = document.createElement('option');
                                                opt.value = modeValue;
                                                opt.textContent = modeValue;
                                                modeInput.appendChild(opt);
                                        }
                                        if (modeValue) {
                                                modeInput.value = modeValue;
                                        }
                                }
                                if (subtypeObj.duration) {
                                        let durationExists = Array.from(durationInput.options).some(opt => opt.value === subtypeObj.duration);
                                        if (!durationExists && subtypeObj.duration) {
                                                const opt = document.createElement('option');
                                                opt.value = subtypeObj.duration;
                                                opt.textContent = subtypeObj.duration;
                                                durationInput.appendChild(opt);
                                        }
                                        durationInput.value = subtypeObj.duration;
                                }
                                calculateEndDate();
                        }
                }

                function updateSubtypes(selectedType, selectedSubtype = '') {
                        const typeId = getSelectedTypeId(selectedType);
                        subtypeInput.innerHTML = '';

                        if (!typeId) {
                                const opt = document.createElement('option');
                                opt.value = '';
                                opt.textContent = 'Select a valid project type';
                                subtypeInput.appendChild(opt);
                                return;
                        }

                        fetch('project_category_api.php?action=get_subtypes&type_id=' + encodeURIComponent(typeId))
                            .then(response => response.json())
                            .then(list => {
                                currentSubtypes = list;
                                if (!Array.isArray(list) || list.length === 0) {
                                    const opt = document.createElement('option');
                                    opt.value = '';
                                    opt.textContent = 'No subtypes available for this type';
                                    subtypeInput.appendChild(opt);
                                    if (selectedSubtype) {
                                        const customOpt = document.createElement('option');
                                        customOpt.value = selectedSubtype;
                                        customOpt.textContent = selectedSubtype;
                                        customOpt.selected = true;
                                        subtypeInput.appendChild(customOpt);
                                    }
                                    return;
                                }

                                list.forEach(sub => {
                                        const opt = document.createElement('option');
                                        opt.value = sub.subtype_name;
                                        opt.textContent = sub.subtype_name;
                                        subtypeInput.appendChild(opt);
                                });

                                if (selectedSubtype && Array.from(subtypeInput.options).some(opt => opt.value === selectedSubtype)) {
                                        subtypeInput.value = selectedSubtype;
                                } else if (selectedSubtype) {
                                        const opt = document.createElement('option');
                                        opt.value = selectedSubtype;
                                        opt.textContent = selectedSubtype;
                                        customOpt.selected = true;
                                        subtypeInput.appendChild(opt);
                                        subtypeInput.value = selectedSubtype;
                                } else if (subtypeInput.options.length > 0) {
                                        subtypeInput.value = subtypeInput.options[0].value;
                                }

                                if (formAction.value === 'create') {
                                        autofillSubtypeFields();
                                }
                            })
                            .catch(() => {
                                subtypeInput.innerHTML = '';
                                const opt = document.createElement('option');
                                opt.value = '';
                                opt.textContent = 'Unable to load subtypes';
                                subtypeInput.appendChild(opt);
                            });
                }

                typeInput.addEventListener('change', (e) => {
                        updateSubtypes(e.target.value);
                });

                subtypeInput.addEventListener('change', () => {
                        autofillSubtypeFields();
                });

                function calculateDuration() {
                        const startVal = startDateInput.value;
                        const endVal = endDateInput.value;
                        if (!startVal || !endVal) return;

                        const start = new Date(startVal);
                        const end = new Date(endVal);
                        if (isNaN(start.getTime()) || isNaN(end.getTime())) return;

                        const diffTime = end - start;
                        if (diffTime <= 0) return;

                        // Calculate month difference
                        let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
                        const dayDiff = end.getDate() - start.getDate();
                        if (dayDiff > 15) {
                                months += 1;
                        } else if (dayDiff < -15) {
                                months -= 1;
                        }

                        if (months < 1) months = 1;

                        const durationText = months === 1 ? "1 Month" : `${months} Months`;

                        // Ensure the option exists in select element
                        let optionExists = false;
                        for (let i = 0; i < durationInput.options.length; i++) {
                                if (durationInput.options[i].value === durationText) {
                                        optionExists = true;
                                        break;
                                }
                        }

                        if (!optionExists) {
                                const opt = document.createElement('option');
                                opt.value = durationText;
                                opt.textContent = durationText;
                                durationInput.appendChild(opt);
                        }

                        durationInput.value = durationText;
                }

                function calculateEndDate() {
                        const startVal = startDateInput.value;
                        const durVal = durationInput.value;
                        
                        if (!startVal || !durVal) return;
                        
                        const start = new Date(startVal);
                        if (isNaN(start.getTime())) return;
                        
                        const match = durVal.match(/^(\d+)/);
                        if (!match) return;
                        const months = parseInt(match[1], 10);
                        
                        const end = new Date(start);
                        end.setMonth(end.getMonth() + months);
                        
                        const yyyy = end.getFullYear();
                        const mm = String(end.getMonth() + 1).padStart(2, '0');
                        const dd = String(end.getDate()).padStart(2, '0');
                        
                        endDateInput.value = `${yyyy}-${mm}-${dd}`;
                }

                startDateInput.addEventListener('change', calculateEndDate);
                durationInput.addEventListener('change', calculateEndDate);
                endDateInput.addEventListener('change', calculateDuration);

                function openCreateModal() {
                        form.reset();
                        formAction.value = 'create';
                        modalTitle.textContent = 'New Project Posting';
                        internshipIdInput.value = '';
                        document.getElementById('admin-remarks-container').style.display = 'none';
                        document.getElementById('form-admin-remarks').textContent = '';
                        updateSubtypes(typeInput.value || (typeInput.options[0] ? typeInput.options[0].value : '' ));
                        modal.classList.remove('hidden');
                }

                function openEditModal(item) {
                        formAction.value = 'edit';
                        modalTitle.textContent = 'Edit Project Posting';
                        internshipIdInput.value = item.id;
                        
                        document.getElementById('admin-remarks-container').style.display = 'block';
                        document.getElementById('form-admin-remarks').textContent = item.admin_remarks ? item.admin_remarks : 'No remarks available.';

                        titleInput.value = item.title;
                        
                        if (![...typeInput.options].some(o => o.value === item.project_type)) {
                                const option = document.createElement('option');
                                option.value = item.project_type;
                                option.textContent = item.project_type || 'Unknown Type';
                                typeInput.appendChild(option);
                        }
                        typeInput.value = item.project_type || (typeInput.options[0] ? typeInput.options[0].value : '');
                        updateSubtypes(typeInput.value, item.project_subtype || '');

                        // Ensure option exists in duration select to avoid blank selection
                        let durationExists = false;
                        for (let i = 0; i < durationInput.options.length; i++) {
                                if (durationInput.options[i].value === item.duration) {
                                        durationExists = true;
                                        break;
                                }
                        }
                        if (item.duration && !durationExists) {
                                const opt = document.createElement('option');
                                opt.value = item.duration;
                                opt.textContent = item.duration;
                                durationInput.appendChild(opt);
                        }
                        durationInput.value = item.duration || '3 Months';

                        modeInput.value = item.mode;
                        difficultyInput.value = item.difficulty_level || 'Medium';
                        openingsInput.value = item.openings || 1;
                        startDateInput.value = item.start_date || '';
                        endDateInput.value = item.end_date || '';
                        techStackInput.value = item.technology_stack || '';
                        descriptionInput.value = item.description || '';

                        modal.classList.remove('hidden');
                }

                function closeModal() {
                    modal.classList.add('hidden');
                }
                function closeViewModal() {
                    document.getElementById('view-modal').classList.add('hidden');
                }

                const timelineModal = document.getElementById('timeline-modal');
                const viewModal = document.getElementById('view-modal');
                const timelineInternshipId = document.getElementById('timeline-internship-id');
                const timelineModalSubtitle = document.getElementById('timeline-modal-subtitle');
                const timelinePhasesContainer = document.getElementById('timeline-phases-container');

                function openTimelineModal(item) {
                        timelinePhasesContainer.innerHTML = `
                                <div class="flex flex-col items-center justify-center py-8">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                        <p class="text-slate-400 text-xs mt-2">Loading timeline...</p>
                                </div>
                        `;
                        timelineModal.classList.remove('hidden');

                        fetch(`coordinator_internships.php?action=get_timeline&id=${item.id}`)
                                .then(response => response.json())
                                .then(phases => {
                                        if (phases.length === 0) {
                                                timelinePhasesContainer.innerHTML = `
                                                        <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                                                <span class="material-symbols-outlined text-[36px] text-slate-300 block mb-2">calendar_today</span>
                                                                <p class="text-slate-400 text-sm">No timeline has been generated for this posting yet.</p>
                                                                <p class="text-xs text-slate-400 mt-1">Please ensure the project posting has a start date and save it again to generate.</p>
                                                        </div>
                                                `;
                                                return;
                                        }

                                        timelinePhasesContainer.innerHTML = '';
                                        phases.forEach(phase => {
                                                const card = document.createElement('div');
                                                card.className = 'p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3';
                                                card.innerHTML = `
                                                        <div class="flex items-center justify-between">
                                                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Phase ${phase.phase_number}: ${phase.phase_name}</span>
                                                                <input type="hidden" name="phase_id[]" value="${phase.id}">
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-3">
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Start Date</label>
                                                                        <input type="date" name="start_date[]" value="${phase.start_date}" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                                                </div>
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">End Date (Deadline)</label>
                                                                        <input type="date" name="end_date[]" value="${phase.end_date}" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                                                </div>
                                                                <div>
                                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                                                                        <select name="status[]" required class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer">
                                                                                <option value="Pending" ${phase.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                                                <option value="Active" ${phase.status === 'Active' ? 'selected' : ''}>Active</option>
                                                                                <option value="Completed" ${phase.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                                                        </select>
                                                                </div>
                                                        </div>
                                                `;
                                                timelinePhasesContainer.appendChild(card);
                                        });
                                })
                                .catch(err => {
                                        console.error(err);
                                        timelinePhasesContainer.innerHTML = `
                                                <div class="text-center py-8 text-red-500">
                                                        <p class="text-sm font-semibold">Error loading timeline.</p>
                                                </div>
                                        `;
                                });
                }

function openViewModal(item) {
    document.getElementById('view-title').textContent = item.title || '';
    document.getElementById('view-project-type').textContent = item.project_type || '';
    document.getElementById('view-project-subtype').textContent = item.project_subtype || '';
    document.getElementById('view-tech-stack').textContent = item.technology_stack || '';
    document.getElementById('view-difficulty').textContent = item.difficulty_level || '';
    document.getElementById('view-openings').textContent = item.openings ?? '';
    document.getElementById('view-mode').textContent = item.mode || '';
    document.getElementById('view-duration').textContent = item.duration || '';
    document.getElementById('view-start-date').textContent = item.start_date ? new Date(item.start_date).toLocaleDateString() : '';
    document.getElementById('view-end-date').textContent = item.end_date ? new Date(item.end_date).toLocaleDateString() : '';
    document.getElementById('view-mentor').textContent = item.mentor_name || '';
    document.getElementById('view-status').textContent = item.status || '';
    document.getElementById('view-admin-remarks').textContent = item.admin_remarks || 'No admin remarks available.';
    document.getElementById('view-description').textContent = item.description || '';
    viewModal.classList.remove('hidden');
}

                function closeTimelineModal() {
                         timelineModal.classList.add('hidden');
                }

                // Sidebar Toggle Handler
                const toggleBtn = document.getElementById('sidebar-toggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        if (window.innerWidth < 768) {
                            document.body.classList.toggle('sidebar-open');
                            document.body.classList.remove('sidebar-closed');
                        } else {
                            document.body.classList.toggle('sidebar-closed');
                            document.body.classList.remove('sidebar-open');
                        }
                    });
                }
        </script>
<script src="js/alerts.js"></script>
</body>
</html>
