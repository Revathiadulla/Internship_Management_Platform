<?php
ob_start();
session_start();
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'company') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$company_id = current_user_id();
$student_id = intval($_POST['student_id'] ?? 0);

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
    exit();
}

// Ensure the student exists and fetch their details
$cand_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$cand_stmt->bind_param("i", $student_id);
$cand_stmt->execute();
$cand_res = $cand_stmt->get_result();
$candidate = $cand_res->fetch_assoc();
$cand_stmt->close();

if (!$candidate) {
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit();
}
$candidate_name = $candidate['full_name'];

// Fetch company name
$company_title = 'Nexus Tech';
$q_prof = mysqli_query($conn, "SELECT company_name FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
}

// Check if already requested
$check_h = mysqli_query($conn, "SELECT id FROM hiring_requests WHERE company_id = $company_id AND student_id = $student_id LIMIT 1");
if ($check_h && mysqli_num_rows($check_h) > 0) {
    echo json_encode(['success' => false, 'message' => 'Hiring request already sent.']);
    exit();
}

// Insert hiring request
$title = "Direct Hiring Request";
$department = "General";
$openings = 1;
$description = "Direct recruitment request from $company_title.";
$requirements = "Talent Pool selection.";

$stmt = $conn->prepare("INSERT INTO hiring_requests (company_id, title, department, openings, description, requirements, student_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ississi", $company_id, $title, $department, $openings, $description, $requirements, $student_id);

if ($stmt->execute()) {
    log_activity($conn, 'Hiring Request Sent', "Company \"$company_title\" sent a direct hiring request to $candidate_name.");
    
    // Notify the student
    $notif_title = 'New Hiring Request';
    $notif_msg = "Company \"$company_title\" has sent you a direct hiring request. Please check your messages/dashboard for further details.";
    $stmt_notif = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) VALUES (?, 'success', ?, ?)");
    $stmt_notif->bind_param("iss", $student_id, $notif_title, $notif_msg);
    $stmt_notif->execute();
    $stmt_notif->close();

    // Trigger Email Notification to Candidate
    include_once __DIR__ . '/../includes/mail_helper.php';
    $email_subject = "IMP: Hiring Request from $company_title!";
    $email_body = "Dear $candidate_name,\n\nGreat news! Company \"$company_title\" has reviewed your profile in the Talent Pool and has sent you a direct hiring request.\n\nPlease log in to your student dashboard to review details and respond to the recruiter.\n\nBest regards,\nIMP Placement Team";
    sendEmailNotification($student_id, $email_subject, $email_body, [
        'event' => 'Hiring Request',
        'company_name' => $company_title,
        'action_url' => 'http://localhost/IMP/student_dashboard.php',
        'action_label' => 'Go to Student Dashboard'
    ]);

    echo json_encode(['success' => true, 'message' => 'Hiring request sent successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
$stmt->close();
?>
