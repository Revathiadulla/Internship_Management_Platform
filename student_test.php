<?php
// student_test.php
// Handles test submission, calculates score, updates application, sends notifications.

include_once __DIR__ . '/ensure_extended_schema.php';
include_once __DIR__ . '/db.php'; // Assuming this provides $conn

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expected POST fields: application_id, answers (array of answer IDs), optional other data
    $app_id = intval($_POST['application_id'] ?? 0);
    $answers = $_POST['answers'] ?? [];
    if ($app_id <= 0 || !is_array($answers) || count($answers) === 0) {
        die('Invalid submission');
    }

    // Fetch correct answers for this application’s internship test
    // Assuming a table `test_questions` with columns: id, internship_id, correct_option
    // First, get internship_id from application
    $stmt = $conn->prepare('SELECT internship_id FROM internship_applications WHERE id = ?');
    $stmt->bind_param('i', $app_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        die('Application not found');
    }
    $internship_id = $row['internship_id'];

    // Retrieve all correct options for this internship test
    $correct_map = [];
    $qstmt = $conn->prepare('SELECT id, correct_option FROM test_questions WHERE internship_id = ?');
    $qstmt->bind_param('i', $internship_id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    while ($qrow = $qres->fetch_assoc()) {
        $correct_map[$qrow['id']] = $qrow['correct_option'];
    }
    $qstmt->close();

    // Calculate score (each question worth 1 point)
    $score = 0;
    $total = count($correct_map);
    foreach ($answers as $qid => $given) {
        if (isset($correct_map[$qid]) && $given == $correct_map[$qid]) {
            $score++;
        }
    }

    // Determine result based on score (>=60% passes)
    $percentage = $total > 0 ? ($score / $total) * 100 : 0;
    $result_text = $percentage >= 60 ? 'Passed' : 'Failed';
    $new_status = $result_text === 'Passed' ? 'Test Completed' : 'Rejected';

    // Update application record safely
    $upd = $conn->prepare(
        "UPDATE internship_applications SET test_score = ?, test_result = ?, test_status = 'Completed', test_submitted_date = NOW(), status = ? WHERE id = ?"
    );
    $upd->bind_param('issi', $score, $result_text, $new_status, $app_id);
    $upd->execute();
    $upd->close();

    // Fetch student email for notification
    $email_stmt = $conn->prepare('SELECT email FROM users WHERE id = (SELECT user_id FROM internship_applications WHERE id = ?)');
    $email_stmt->bind_param('i', $app_id);
    $email_stmt->execute();
    $email_res = $email_stmt->get_result();
    $email_row = $email_res->fetch_assoc();
    $email_stmt->close();
    $student_email = $email_row['email'] ?? '';

    // Send email summary
    if (!empty($student_email)) {
        $subject = "Test Result for your application";
        $message = "Dear Student,\n\nYour test for the internship has been evaluated.\nScore: $score / $total (" . round($percentage, 2) . "% )\nResult: $result_text\n\n";
        $message .= "You can view more details in your dashboard.\n\nBest regards,\nInternship Management Team";
        // Simple mail function – adjust headers as needed
        $headers = "From: no-reply@internshipplatform.com" . "\r\n" . "Content-Type: text/plain; charset=UTF-8";
        @mail($student_email, $subject, $message, $headers);
    }

    // Optional: trigger HR notification if passed (status already Test Completed)
    // generate_notifications.php will pick up the status change.
    // Redirect to a confirmation page or display a simple message
    echo "Test submission processed successfully.";
    exit;
}
?>
