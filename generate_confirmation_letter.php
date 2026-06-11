<?php
// generate_confirmation_letter.php
// Usage: POST or GET with ?app_id=123
// Requires: db.php, email_helper.php, includes/fpdf.php

require_once "db.php";
require_once "email_helper.php";
require_once __DIR__ . '/includes/fpdf.php';
session_start();

$app_id = isset($_REQUEST['app_id']) ? intval($_REQUEST['app_id']) : 0;
if ($app_id <= 0) {
    die('Invalid application id');
}

// Fetch application + student + internship info
$sql = "SELECT a.id, a.user_id, a.internship_id, a.internship_name, a.applied_subtype, a.status, a.applied_date, a.start_date, a.internship_duration AS app_duration, a.confirmation_letter_path,
               u.email AS student_email, u.full_name AS student_name,
               i.title AS internship_title, i.project_subtype, i.duration AS intern_duration, i.mode AS intern_mode
        FROM internship_applications a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN internships i ON a.internship_id = i.id
        WHERE a.id = $app_id LIMIT 1";
$res = mysqli_query($conn, $sql);
$app = mysqli_fetch_assoc($res);
if (!$app) {
    die('Application not found');
}

// Prepare confirmation details
$student_name = $app['student_name'] ?: 'Student';
$internship_title = $app['internship_title'] ?: ($app['internship_name'] ?: 'Internship');
$mode = trim($app['intern_mode'] ?? 'Remote');
$duration = trim($app['intern_duration'] ?? ($app['app_duration'] ?? 'TBD'));
$start_date = $app['start_date'] ?: date('Y-m-d');
$organization = 'Internship Management Platform (IMP)';
$reference = 'IMP-CONF-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT);

$project_subtype  = trim($app['project_subtype'] ?? '');
$applied_subtype  = trim($app['applied_subtype'] ?? '');

// Correct fallback order:
// internship_name = project_subtype
// if project_subtype empty, use internship subtype (applied_subtype)
// if subtype empty, use internship title
$resolved_internship_name = $project_subtype;
if (empty($resolved_internship_name)) {
    $resolved_internship_name = $applied_subtype;
}
if (empty($resolved_internship_name)) {
    $resolved_internship_name = $internship_title;
}
if (empty($resolved_internship_name)) {
    $resolved_internship_name = 'Internship';
}

// Build PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, 'Confirmation Letter', 0, 1, 'C');
$pdf->Ln(6);

$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 6, "Date: " . date('M d, Y') . "\nReference No: $reference\n\n", 0, 'L');

$body = "To,\n$student_name\n\nSubject: Confirmation of Internship Placement\n\n";
$body .= "Dear $student_name,\n\n";
$body .= "We are pleased to confirm your placement for the following internship:\n\n";
$body .= "Internship Name: $resolved_internship_name\nMode: $mode\nDuration: $duration\nStart Date: " . date('M d, Y', strtotime($start_date)) . "\nOrganization: $organization\n\n";
$body .= "Please treat this as an official confirmation letter. Kindly retain a copy for your records.\n\n";
$body .= "Best regards,\nHuman Resources\n$organization\n";

$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 6, $body);

// Footer / signature area
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 11);
$pdf->Cell(0, 6, 'This is a system generated confirmation letter from Internship Management Platform (IMP).', 0, 1, 'L');

// Save file
$dir = __DIR__ . '/uploads/confirmation_letters';
if (!is_dir($dir)) {
    if (!@mkdir($dir, 0777, true)) {
        throw new Exception("Unable to create uploads folder: $dir");
    }
}
if (!is_writable($dir)) {
    @chmod($dir, 0777);
}
$filename = 'Confirmation_' . $app['id'] . '_' . time() . '.pdf';
$path = $dir . '/' . $filename;
$pdf->Output('F', $path);

// Update DB: save path and change status after email success
$esc_path = mysqli_real_escape_string($conn, 'uploads/confirmation_letters/' . $filename);

// Send email with attachment
$to = $app['student_email'];
$subject = 'Confirmation Letter - ' . $resolved_internship_name;
$html = "<p>Dear " . htmlspecialchars($student_name) . ",</p>\n";
$html .= "<p>Please find attached your confirmation letter for the internship: <strong>" . htmlspecialchars($resolved_internship_name) . "</strong>.</p>\n";
$html .= "<p>Reference: <strong>$reference</strong></p>\n";
$html .= "<p>Regards,<br/>HR Team</p>\n";

$GLOBALS['mail_options_attachments'] = [['path' => $path, 'name' => $filename]];
$debugInfo = '';
$sent = sendEmail($to, $subject, $html, null, $debugInfo);

if ($sent) {
    // Update application record without changing the application status
    $update_sql = "UPDATE internship_applications SET confirmation_letter_path = '$esc_path', confirmation_letter_sent_at = NOW() WHERE id = $app_id";
    mysqli_query($conn, $update_sql);

    // Insert into history while keeping the current state as Selected
    $user_id = intval($_SESSION['user_id'] ?? 0);
    $who = $user_id ? $user_id : 0;
    $old_status_val = mysqli_real_escape_string($conn, $app['status'] ?? 'Selected');
    $ins = "INSERT INTO application_status_history (application_id, old_status, new_status, notes, updated_by, updated_by_name, created_at)
            VALUES ($app_id, '$old_status_val', 'Selected', 'Confirmation letter generated and emailed', '$who', 'HR System', NOW())";
    mysqli_query($conn, $ins);

    echo json_encode(['ok' => true, 'message' => 'Confirmation letter generated and emailed.']);
} else {
    // Email failed: keep file saved but do not change status
    echo json_encode(['ok' => false, 'message' => 'Email failed: ' . $debugInfo]);
}

?>
