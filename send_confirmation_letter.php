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

    $user_role = $_SESSION['role'];
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
    $app_sql = "SELECT a.id, a.status, a.user_id, a.internship_id, a.internship_name,
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
    if ($app['status'] !== 'Selected') {
        echo json_encode(['success' => false, 'message' => 'Confirmation letter can only be sent for selected students']);
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
    
    // Save PDF temporarily
    $pdf_filename = 'Confirmation_Letter_' . $app_id . '.pdf';
    $temp_pdf_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $pdf_filename;
    
    $pdf->Output('F', $temp_pdf_path);
    
    // Upload to Cloudinary
    require_once __DIR__ . '/includes/cloudinary_config.php';
    try {
        $secure_url = uploadToCloudinary($temp_pdf_path, 'offer_letters', true);
    } catch (Exception $e) {
        @unlink($temp_pdf_path);
        echo json_encode(['success' => false, 'message' => 'Failed to upload confirmation letter: ' . $e->getMessage()]);
        exit();
    }
    
    // Delete temp file
    @unlink($temp_pdf_path);
    
    // Update DB
    $esc_pdf_path = mysqli_real_escape_string($conn, $secure_url);
    mysqli_query($conn, "UPDATE internship_applications SET confirmation_letter_path = '$esc_pdf_path', confirmation_letter_sent_at = NOW() WHERE id = $app_id");
    
    // Attach to email
    $GLOBALS['mail_options_attachments'] = [
        ['path' => $secure_url, 'name' => $pdf_filename]
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

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Confirmation letter sent successfully.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Confirmation letter generated, but email sending failed.']);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
