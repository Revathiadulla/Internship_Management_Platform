<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$log_date = trim($_POST['log_date'] ?? date('Y-m-d'));
$tasks_completed = trim($_POST['tasks_completed'] ?? '');
$time_spent = floatval($_POST['time_spent'] ?? 0);
$focus_level = trim($_POST['focus_level'] ?? '');
$issues_faced = trim($_POST['issues_faced'] ?? '');
$next_plan = trim($_POST['next_plan'] ?? '');
$internship_id = intval($_POST['internship_id'] ?? 0);

$redirect_base = 'student_dashboard.php?section=daily_logs';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$redirect_base}&error=" . urlencode('Invalid request method.'));
    exit();
}

if ($tasks_completed === '' || $time_spent <= 0 || $focus_level === '' || $log_date === '') {
    header("Location: {$redirect_base}&error=" . urlencode('Please fill all required fields correctly.'));
    exit();
}

$date_obj = DateTime::createFromFormat('Y-m-d', $log_date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $log_date) {
    header("Location: {$redirect_base}&error=" . urlencode('Invalid log date format.'));
    exit();
}

$app_id = null;
if ($internship_id > 0) {
    $app_stmt = $conn->prepare("SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1");
    if ($app_stmt) {
        $app_stmt->bind_param('ii', $user_id, $internship_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        if ($app_result && $row = $app_result->fetch_assoc()) {
            $app_id = intval($row['id']);
        }
        $app_stmt->close();
    }
}

$dup_stmt = $conn->prepare("SELECT id FROM daily_logs WHERE user_id = ? AND log_date = ? LIMIT 1");
if ($dup_stmt) {
    $dup_stmt->bind_param('is', $user_id, $log_date);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    if ($dup_result && $dup_result->fetch_assoc()) {
        header("Location: {$redirect_base}&duplicate=1");
        exit();
    }
    $dup_stmt->close();
}

$insert_stmt = $conn->prepare(
    "INSERT INTO daily_logs (user_id, internship_id, application_id, tasks_completed, time_spent, focus_level, issues_faced, next_plan, log_date, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted')"
);
if (!$insert_stmt) {
    echo 'Prepare failed: ' . htmlspecialchars($conn->error);
    exit();
}

$insert_stmt->bind_param(
    'iiidsssss',
    $user_id,
    $internship_id,
    $app_id,
    $tasks_completed,
    $time_spent,
    $focus_level,
    $issues_faced,
    $next_plan,
    $log_date
);

if (!$insert_stmt->execute()) {
    echo 'Insert failed: ' . htmlspecialchars($insert_stmt->error);
    exit();
}

$log_id = $conn->insert_id;

$insert_stmt->close();

// Fetch student full name, team name, and mentor_id
$team_name = "";
$mentor_id = 0;
$student_name = "";

// Student Name
$u_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
if ($u_stmt) {
    $u_stmt->bind_param('i', $user_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    if ($u_row = $u_res->fetch_assoc()) {
        $student_name = $u_row['full_name'];
    }
    $u_stmt->close();
}

// Mentor & Team
$m_stmt = $conn->prepare("SELECT t.mentor_id, t.team_name 
                          FROM project_team_members tm 
                          JOIN project_teams t ON tm.project_team_id = t.id 
                          WHERE tm.student_id = ? LIMIT 1");
if ($m_stmt) {
    $m_stmt->bind_param('i', $user_id);
    $m_stmt->execute();
    $m_res = $m_stmt->get_result();
    if ($m_row = $m_res->fetch_assoc()) {
        $mentor_id = intval($m_row['mentor_id']);
        $team_name = $m_row['team_name'];
    }
    $m_stmt->close();
}

if ($mentor_id > 0) {
    $notif_title = "New Daily Log Submitted";
    $notif_msg = ($student_name ?: 'Student') . " submitted today's daily log for " . ($team_name ?: 'your team') . ".";
    $link = "mentor_daily_logs.php?log_id=" . $log_id;
    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link, related_id, related_type) VALUES (?, 'mentor', ?, ?, 'log_submission', ?, ?, 'daily_log')");
    if ($notif_stmt) {
        $notif_stmt->bind_param('isssi', $mentor_id, $notif_title, $notif_msg, $link, $log_id);
        $notif_stmt->execute();
        $notif_stmt->close();
    }
}

header("Location: {$redirect_base}&success=1");
exit();
