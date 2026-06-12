<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    session_start();
    include __DIR__ . '/../includes/db.php';
    include_once __DIR__ . '/../includes/auth.php';
    include_once __DIR__ . '/../includes/mail_helper.php';

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

    // Fetch helper to download/resolve logo/seal path for FPDF
    if (!function_exists('get_local_image_path')) {
        function get_local_image_path($path) {
            if (empty($path)) return '';
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                $temp_dir = sys_get_temp_dir();
                $filename = 'fpdf_img_' . md5($path) . '.' . pathinfo($path, PATHINFO_EXTENSION);
                $local = $temp_dir . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($local)) {
                    $ch = curl_init($path);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    $data = curl_exec($ch);
                    curl_close($ch);
                    if ($data) {
                        @file_put_contents($local, $data);
                    }
                }
                if (file_exists($local) && filesize($local) > 0) {
                    return $local;
                }
            } else {
                $local = __DIR__ . '/' . ltrim($path, '/');
                if (file_exists($local)) {
                    return $local;
                }
            }
            return '';
        }
    }

    // Fetch application details with full placeholder support
    $app_sql = "SELECT a.id, a.status, a.user_id, a.internship_id, a.internship_name, a.applied_subtype, a.confirmation_letter_path, a.confirmation_letter_sent_at,
                       a.team_name, a.applied_date, 
                       COALESCE(a.selected_at, a.applied_date, CURDATE()) AS start_date,
                       COALESCE(i.title, a.internship_name) AS internship_title, i.project_subtype, i.duration AS intern_duration, i.mode AS intern_mode, i.technology_stack AS intern_tech_stack,
                       u.full_name AS student_name, u.email AS student_email,
                       m.full_name AS mentor_name
                FROM internship_applications a
                LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN users m ON a.mentor_id = m.id
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
    $student_email    = $app['student_email'] ?? '';

    $project_subtype  = trim($app['project_subtype'] ?? ($app['applied_subtype'] ?? ''));
    $duration         = trim($app['intern_duration'] ?? '');
    $mode             = trim($app['intern_mode'] ?? '');
    $tech_stack       = trim($app['intern_tech_stack'] ?? '');

    $resolved_internship_name = !empty($project_subtype) ? $project_subtype : 'Not specified';
    $resolved_duration        = !empty($duration) ? $duration : 'Not specified';
    $resolved_mode            = !empty($mode) ? $mode : 'Not specified';
    $resolved_tech_stack      = !empty($tech_stack) ? $tech_stack : 'Not specified';

    // Fetch the active template from database
    $template_res = mysqli_query($conn, "SELECT * FROM confirmation_letter_templates WHERE is_active = 1 LIMIT 1");
    $template = mysqli_fetch_assoc($template_res);

    $subject_template = $template ? $template['subject'] : "Congratulations! You have been selected for the internship";
    $content_template = $template ? $template['content'] : "Dear {student_name},\n\nWe are pleased to inform you that your application for the internship position \"{project_title}\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
    $sig_name = $template ? $template['signature_name'] : "HR Team";
    $sig_designation = $template ? $template['signature_designation'] : "IMP Platform";
    $logo_path = $template ? $template['logo_path'] : "";

    // Resolve Placeholders
    $resolved_mentor = !empty($app['mentor_name']) ? $app['mentor_name'] : 'Not assigned';
    $resolved_team = !empty($app['team_name']) ? $app['team_name'] : 'Not assigned';
    $resolved_joining_date = !empty($app['start_date']) ? date('F d, Y', strtotime($app['start_date'])) : (!empty($app['applied_date']) ? date('F d, Y', strtotime($app['applied_date'])) : date('F d, Y'));
    $resolved_selection_date = date('F d, Y');
    $resolved_company = "Internship Management Platform (IMP)";

    $placeholders = [
        '{student_name}' => $student_name,
        '{application_id}' => $app_id,
        '{project_title}' => $resolved_internship_name,
        '{project_subtype}' => $resolved_internship_name,
        '{duration}' => $resolved_duration,
        '{mode}' => $resolved_mode,
        '{technology_stack}' => $resolved_tech_stack,
        '{mentor_name}' => $resolved_mentor,
        '{team_name}' => $resolved_team,
        '{company_name}' => $resolved_company,
        '{joining_date}' => $resolved_joining_date,
        '{selection_date}' => $resolved_selection_date
    ];

    $resolved_subject = $subject_template;
    $resolved_content = $content_template;

    foreach ($placeholders as $ph => $val) {
        $resolved_subject = str_replace($ph, $val, $resolved_subject);
        $resolved_content = str_replace($ph, $val, $resolved_content);
    }

    // Generate PDF
    require_once __DIR__ . '/../includes/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Render Custom Logo on Letterhead if provided
    $local_logo = get_local_image_path($logo_path);
    if (!empty($local_logo)) {
        $pdf->Image($local_logo, 160, 15, 30);
    }

    // Header Branding
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(0, 74, 198); // Blue
    $pdf->Cell(0, 15, 'IMP', 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 5, 'INTERNSHIP MANAGEMENT PLATFORM', 0, 1, 'L');
    $pdf->Ln(15);
    
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'INTERNSHIP SELECTION CONFIRMATION LETTER', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Details
    $pdf->SetFont('Arial', '', 11);
    $ref_no = 'IMP/' . date('Y') . '/' . str_pad($app_id, 4, '0', STR_PAD_LEFT);
    $pdf->Cell(0, 6, 'Reference No: ' . $ref_no, 0, 1);
    $pdf->Cell(0, 6, 'Date: ' . $resolved_selection_date, 0, 1);
    $pdf->Ln(5);
    
    $pdf->Cell(0, 6, 'Student Name: ' . $student_name, 0, 1);
    $pdf->Cell(0, 6, 'Student Email: ' . $student_email, 0, 1);
    $pdf->Cell(0, 6, 'Application ID: ' . $app_id, 0, 1);
    $pdf->Cell(0, 6, 'Internship Name: ' . $resolved_internship_name, 0, 1);
    $pdf->Cell(0, 6, 'Duration: ' . $resolved_duration, 0, 1);
    $pdf->Cell(0, 6, 'Mode: ' . $resolved_mode, 0, 1);
    $pdf->Cell(0, 6, 'Technology Stack: ' . $resolved_tech_stack, 0, 1);
    $pdf->Cell(0, 6, 'Status: SELECTED', 0, 1);
    $pdf->Ln(8);
    
    // Body Text Paragraphs
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->MultiCell(0, 6, $resolved_content);
    $pdf->Ln(15);
    
    // Signature block
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Sincerely,', 0, 1);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, $sig_name, 0, 1);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, $sig_designation, 0, 1);
    $pdf->Ln(10);
    
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->Cell(0, 10, 'This is a system-generated confirmation letter. No physical signature is required.', 0, 1, 'C');
    
    // Save PDF locally
    $dir = __DIR__ . '/uploads/offer_letters';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            throw new Exception("Unable to create uploads folder: $dir");
        }
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
        if (!is_writable($dir)) {
            throw new Exception("Uploads folder is not writable: $dir");
        }
    }
    $pdf_filename = 'Offer_Letter_' . $app_id . '_' . time() . '.pdf';
    $local_pdf_path = $dir . DIRECTORY_SEPARATOR . $pdf_filename;
    
    $pdf->Output('F', $local_pdf_path);
    
    $relative_pdf_path = 'uploads/offer_letters/' . $pdf_filename;
    $final_pdf_path = $relative_pdf_path;
    
    // Upload to Cloudinary if env vars are present, otherwise fallback to local
    require_once __DIR__ . '/../includes/cloudinary_config.php';
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
        error_log("Failed to upload confirmation letter to Cloudinary, falling back to local: " . $e->getMessage());
    }
    
    // Notify student via email
    $student_subject = $resolved_subject;
    $student_message = "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship: \"$resolved_internship_name\". Please find your Confirmation Letter attached.\n\nNote: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator.\n\nBest regards,\nIMP Team";
    
    $errorOutput = '';
    $sent = sendEmailNotification($student_email, $student_subject, $student_message, [
        'recipient_name' => $student_name,
        'event' => 'Application Status Update',
        'internship' => $resolved_internship_name,
        'status' => 'Selected',
        'action_url' => 'http://localhost/IMP/student_applications.php',
        'action_label' => 'View Application',
        'attachments' => [
            ['path' => $local_pdf_path, 'name' => $pdf_filename]
        ]
    ], $errorOutput);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Email could not be sent: ' . $errorOutput]);
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
    $notif_msg = mysqli_real_escape_string($conn, "Your confirmation letter has been generated and sent for the internship: $resolved_internship_name.");
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
