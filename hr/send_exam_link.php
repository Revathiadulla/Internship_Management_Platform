<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mail_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

try {
    $action = $_POST['action'] ?? '';
    if ($action !== 'send_exam_link') {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
    }

    $app_ids = $_POST['selected_ids'] ?? [];
    if (empty($app_ids) || !is_array($app_ids)) {
        echo json_encode(['success' => false, 'message' => 'No application selected.']);
        exit();
    }

    $app_id = intval($app_ids[0]);
    $to = filter_var($_POST['to'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject = trim($_POST['subject'] ?? '');
    $exam_link = trim($_POST['exam_link'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$to) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }
    if (empty($subject)) {
        echo json_encode(['success' => false, 'message' => 'Subject is required.']);
        exit();
    }
    if (empty($exam_link)) {
        echo json_encode(['success' => false, 'message' => 'Exam link is required.']);
        exit();
    }
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required.']);
        exit();
    }

    // Process message to replace {{EXAM_LINK}}
    if (strpos($message, '{{EXAM_LINK}}') !== false) {
        $message = str_replace('{{EXAM_LINK}}', $exam_link, $message);
    } else {
        $message .= "\n\nExam Link: " . $exam_link;
    }

    // Get Application Details
    $stmt = $conn->prepare("SELECT a.user_id, i.title as internship_title, u.full_name as student_name FROM internship_applications a JOIN internships i ON a.internship_id = i.id JOIN student_profiles u ON a.user_id = u.user_id WHERE a.id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $app_data = $res->fetch_assoc();
    if (!$app_data) {
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit();
    }
    
    $student_user_id = $app_data['user_id'];
    $internship_title = $app_data['internship_title'];
    $student_name = $app_data['student_name'];

    // Send email
    $errorOutput = '';
    $sent = sendEmailNotification($to, $subject, $message, [
        'recipient_name' => $student_name,
        'event' => 'Exam Link Sent',
        'internship' => $internship_title,
        'action_url' => $exam_link,
        'action_label' => 'Take Assessment'
    ], $errorOutput);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Email could not be sent: ' . $errorOutput]);
        exit();
    }

    // Update DB only after the email has been sent successfully.
    // Ensure Exam Link Sent status exists in logic
    $new_status = 'Exam Link Sent';
    
    mysqli_query($conn, "UPDATE internship_applications SET status = '$new_status' WHERE id = $app_id");
    
    // Log the action
    $user_role = $_SESSION['role'];
    $user_id = intval($_SESSION['user_id'] ?? 0);
    $history_notes = mysqli_real_escape_string($conn, 'Sent Exam Link: ' . $exam_link);
    mysqli_query($conn, "INSERT INTO application_status_history 
        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
        VALUES ($app_id, 'Shortlisted', '$new_status', '$user_role', 'HR', '$history_notes')");

    // Insert student notification
    $notif_msg = mysqli_real_escape_string($conn, "An exam link has been sent to your email for the internship: $internship_title.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
        VALUES ($student_user_id, 'Exam Link Sent', 'info', '$notif_msg')");

    echo json_encode(['success' => true, 'message' => 'Exam Link sent and status updated successfully.']);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
