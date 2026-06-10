<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    session_start();
    include "db.php";
    include_once __DIR__ . "/includes/auth.php";
    include_once __DIR__ . "/includes/mail_helper.php";

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    // Only HR and Coordinator and Admin can send confirmation letter
    if ($user_role !== 'hr' && $user_role !== 'coordinator' && $user_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action']);
        exit();
    }

    $app_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
    if ($app_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        exit();
    }

    // Fetch application details
    $app_sql = "SELECT a.id, a.status, a.user_id, a.internship_id, a.internship_name, a.confirmation_letter_path, a.confirmation_letter_sent_at,
                       COALESCE(i.title, a.internship_name) AS internship_title,
                       u.full_name AS student_name, u.email AS student_email
                FROM internship_applications a
                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.id = $app_id LIMIT 1";
    $app_result = mysqli_query($conn, $app_sql);

    if (!$app_result || mysqli_num_rows($app_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }

    $app = mysqli_fetch_assoc($app_result);
    $current_status = trim((string) ($app['status'] ?? ''));
    if (strtolower($current_status) !== 'selected') {
        echo json_encode(['success' => false, 'message' => 'Confirmation letter can only be sent for selected students']);
        exit();
    }

    $confirmation_letter_sent = !empty($app['confirmation_letter_path']) || !empty($app['confirmation_letter_sent_at']);
    if ($confirmation_letter_sent) {
        echo json_encode(['success' => false, 'message' => 'Confirmation letter already sent.']);
        exit();
    }

    $student_name     = $app['student_name'] ?? 'Student';
    $internship_title = $app['internship_title'] ?? 'Internship';
    $student_user_id  = intval($app['user_id']);

    // Generate PDF
    require_once __DIR__ . '/includes/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(0, 74, 198); // Blue
    $pdf->Cell(0, 15, 'IMP', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 10, 'Internship Management Platform', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'INTERNSHIP SELECTION CONFIRMATION LETTER', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Details
    $pdf->SetFont('Arial', '', 12);
    $ref_no = 'IMP/' . date('Y') . '/' . str_pad($app_id, 4, '0', STR_PAD_LEFT);
    $pdf->Cell(0, 8, 'Reference No: ' . $ref_no, 0, 1);
    $pdf->Cell(0, 8, 'Date: ' . date('F j, Y'), 0, 1);
    $pdf->Ln(5);
    
    $pdf->Cell(0, 8, 'Student Name: ' . $student_name, 0, 1);
    $pdf->Cell(0, 8, 'Student Email: ' . ($app['student_email'] ?? ''), 0, 1);
    $pdf->Cell(0, 8, 'Application ID: ' . $app_id, 0, 1);
    $pdf->Cell(0, 8, 'Status: SELECTED', 0, 1);
    $pdf->Ln(10);
    
    // Body
    $pdf->SetFont('Arial', '', 12);
    $msg = "Dear $student_name,\n\nWe are pleased to inform you that your application for the internship position \"$internship_title\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
    $pdf->MultiCell(0, 8, $msg);
    $pdf->Ln(20);
    
    // Signature
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Best Regards,', 0, 1);
    $pdf->Cell(0, 8, 'HR Team, IMP', 0, 1);
    $pdf->Ln(10);
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 10, 'This is a system-generated confirmation letter. No signature is required.', 0, 1, 'C');
    
    // Save PDF locally
    $dir = __DIR__ . '/uploads/offer_letters';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $pdf_filename = 'Offer_Letter_' . $app_id . '_' . time() . '.pdf';
    $local_pdf_path = $dir . DIRECTORY_SEPARATOR . $pdf_filename;
    
    $pdf->Output('F', $local_pdf_path);
    
    $relative_pdf_path = 'uploads/offer_letters/' . $pdf_filename;
    $final_pdf_path = $relative_pdf_path;
    
    // Upload to Cloudinary if env vars are present, otherwise fallback to local
    require_once __DIR__ . '/includes/cloudinary_config.php';
    try {
        $cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
        $api_key    = getenv('CLOUDINARY_API_KEY');
        $api_secret = getenv('CLOUDINARY_API_SECRET');
        if (!empty($cloud_name) && !empty($api_key) && !empty($api_secret)) {
            $secure_url = uploadToCloudinary($local_pdf_path, 'offer_letters', true);
            if (!empty($secure_url)) {
                $final_pdf_path = $secure_url;
            }
        }
    } catch (Exception $e) {
        // Fallback to local storage on missing credentials or upload error
        error_log("Failed to upload confirmation letter to Cloudinary, falling back to local: " . $e->getMessage());
    }
    
    // Attach to email
    $GLOBALS['mail_options_attachments'] = [
        ['path' => $local_pdf_path, 'name' => $pdf_filename]
    ];

    // Notify student via email
    $student_subject = "Congratulations! You have been selected for the internship";
    $student_message = "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship: \"$internship_title\". Please find your Confirmation Letter attached.\n\nNote: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator.\n\nBest regards,\nIMP Team";
    
    $sent = sendStudentNotification($student_user_id, $student_name, $student_subject, $student_message, [
        'event' => 'Application Status Update',
        'internship' => $internship_title,
        'status' => 'Selected',
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application'
    ]);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Email could not be sent. The confirmation letter was not marked as sent.']);
        exit();
    }

    // Update DB only after the email has been sent successfully.
    $esc_pdf_path = mysqli_real_escape_string($conn, $final_pdf_path);
    mysqli_query($conn, "UPDATE internship_applications SET confirmation_letter_path = '$esc_pdf_path', confirmation_letter_sent_at = NOW() WHERE id = $app_id");

    $offer_col = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'offer_letter_path'");
    if ($offer_col && mysqli_num_rows($offer_col) > 0) {
        mysqli_query($conn, "UPDATE internship_applications SET offer_letter_path = '$esc_pdf_path' WHERE id = $app_id");
    }

    $confirmation_sent_col = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'confirmation_letter_sent'");
    if ($confirmation_sent_col && mysqli_num_rows($confirmation_sent_col) > 0) {
        mysqli_query($conn, "UPDATE internship_applications SET confirmation_letter_sent = 1 WHERE id = $app_id");
    }
    
    // Log the action while keeping the application under the Selected status
    $user_id = intval($_SESSION['user_id'] ?? 0);
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);
    $history_notes = mysqli_real_escape_string($conn, 'Confirmation letter generated and emailed.');
    mysqli_query($conn, "INSERT INTO application_status_history 
        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
        VALUES ($app_id, 'Selected', 'Selected', '$user_role', '$updated_by_name', '$history_notes')");

    // Insert student notification
    $notif_msg = mysqli_real_escape_string($conn, "Your confirmation letter has been generated and sent for the internship: $internship_title.");
    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
        VALUES ($student_user_id, 'Confirmation Letter Generated', 'success', '$notif_msg')");

    echo json_encode(['success' => true, 'message' => 'Confirmation letter generated and emailed successfully.']);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
