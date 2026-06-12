<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_role('mentor');
include __DIR__ . '/../includes/db.php';

$mentor_id = current_user_id();

// Set header metadata for CSV download
$filename = "interns_daily_logs_" . date('Y-m-d_H-i') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel/LibreOffice encoding compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Output column headers
fputcsv($output, [
    'Student Name',
    'Email',
    'Internship Name',
    'Log Date',
    'Hours Spent',
    'Focus Level',
    'Tasks Completed',
    'Issues Faced',
    'Next Plan',
    'Status',
    'Mentor Rating',
    'Feedback Comment',
    'Reviewed At'
]);

// Query daily logs for this mentor's active assigned students
$query = "
    SELECT 
        u.full_name as student_name,
        u.email as student_email,
        app.internship_name,
        dl.log_date,
        dl.time_spent,
        dl.focus_level,
        dl.tasks_completed,
        dl.issues_faced,
        dl.next_plan,
        dl.status,
        dl.mentor_rating,
        dl.mentor_feedback,
        dl.reviewed_at
    FROM daily_logs dl
    JOIN users u ON dl.user_id = u.id
    JOIN mentor_assignments ma ON dl.user_id = ma.student_id AND dl.application_id = ma.application_id
    JOIN internship_applications app ON ma.application_id = app.id
    WHERE ma.mentor_id = ? AND ma.status = 'active'
    ORDER BY dl.log_date DESC, dl.created_at DESC
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('i', $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['student_name'],
            $row['student_email'],
            $row['internship_name'],
            $row['log_date'],
            $row['time_spent'],
            $row['focus_level'],
            $row['tasks_completed'],
            $row['issues_faced'],
            $row['next_plan'],
            $row['status'],
            $row['mentor_rating'] ?: 'N/A',
            $row['mentor_feedback'] ?: '',
            $row['reviewed_at'] ?: ''
        ]);
    }
    $stmt->close();
}

fclose($output);
exit();
?>
