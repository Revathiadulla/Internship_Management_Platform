<?php
/**
 * generate_certificate.php
 * Generates internship completion certificate as PDF, uploads to Cloudinary,
 * stores secure URL in the database, and redirects the user to the URL.
 */

session_start();
require_once "db.php";
require_once "includes/cloudinary_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$session_user_id = $_SESSION['user_id'];
$session_role    = $_SESSION['role'] ?? 'student';

// Determine target user
$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $session_user_id;
$mode = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

// Students can only generate their own certificates
if ($session_role === 'student' && $target_user_id !== $session_user_id) {
    die("Unauthorized access.");
}

// Fetch student profile
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$target_user_id' LIMIT 1";
$profile_res = mysqli_query($conn, $profile_sql);
$profile     = mysqli_fetch_assoc($profile_res);
if (!$profile) {
    die("Student profile not found. Please fill in your profile details first.");
}

// Fetch active/started internship details with full placeholder support
$intern_sql = "SELECT a.id as app_id, a.applied_date, a.education_status, a.certificate_path, a.team_name, a.internship_duration AS app_duration, a.start_date, a.end_date,
                      COALESCE(i.title, a.internship_name) as title,
                      COALESCE(i.duration, '3 Months') as duration,
                      COALESCE(i.mode, 'Remote') as mode,
                      ss.score as ss_score,
                      ss.total_questions as ss_total_questions,
                      m.full_name AS mentor_name,
                      i.project_subtype
               FROM internship_applications a
               LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
               LEFT JOIN student_scores ss ON a.id = ss.application_id
               LEFT JOIN users m ON a.mentor_id = m.id
               WHERE a.user_id = '$target_user_id' AND a.status = 'Started'
               LIMIT 1";
$intern_res  = mysqli_query($conn, $intern_sql);
$intern      = mysqli_fetch_assoc($intern_res);

if (!$intern) {
    die("No started/active internship found for this student.");
}

// If certificate_path is already generated and populated with a valid Cloudinary URL, redirect directly
if (!empty($intern['certificate_path'])) {
    $resolved_cert_url = getDocumentUrl($intern['certificate_path']);
    if ($resolved_cert_url !== 'unavailable' && (strpos($resolved_cert_url, 'http://') === 0 || strpos($resolved_cert_url, 'https://') === 0)) {
        header("Location: " . $resolved_cert_url);
        exit();
    }
}

// Compute certificate details
$cert_score = 0;
$cert_total = 30;
if (isset($intern['ss_score']) && $intern['ss_score'] !== null) {
    $cert_score = intval($intern['ss_score']);
    $cert_total = intval($intern['ss_total_questions'] ?: 30);
}

$start_date = !empty($intern['start_date']) ? new DateTime($intern['start_date']) : new DateTime($intern['applied_date']);
$end_date   = !empty($intern['end_date']) ? new DateTime($intern['end_date']) : (clone $start_date)->modify('+3 months');
$cert_id    = 'IMP-' . date('Y') . '-' . strtoupper(substr($profile['full_name'], 0, 2)) . '-' . str_pad($target_user_id, 5, '0', STR_PAD_LEFT);

// Fetch the active template from database
$template_res = mysqli_query($conn, "SELECT * FROM certificate_templates WHERE is_active = 1 LIMIT 1");
$template = mysqli_fetch_assoc($template_res);

$content_template = $template ? $template['content'] : "has successfully completed the internship program as a {project_title} at the {company_name}.";
$sig_name = $template ? $template['signature_name'] : "Program Coordinator";
$sig_designation = $template ? $template['signature_designation'] : "IMP Platform Director";
$logo_path = $template ? $template['logo_path'] : "";
$seal_image = $template ? $template['seal_image'] : "";

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

// Resolve Placeholders
$resolved_project_title = $intern['title'] ?: 'Intern';
$resolved_project_subtype = $intern['project_subtype'] ?? 'General';
$resolved_duration = !empty($intern['app_duration']) ? $intern['app_duration'] : $intern['duration'];
$resolved_mode = !empty($intern['app_mode']) ? $intern['app_mode'] : $intern['mode'];
$resolved_mentor = !empty($intern['mentor_name']) ? $intern['mentor_name'] : 'Not assigned';
$resolved_team = !empty($intern['team_name']) ? $intern['team_name'] : 'Not assigned';
$resolved_start_date = $start_date->format('M d, Y');
$resolved_completion_date = $end_date->format('M d, Y');
$resolved_company = "Internship Management Platform (IMP)";

$placeholders = [
    '{student_name}' => $profile['full_name'],
    '{certificate_id}' => $cert_id,
    '{project_title}' => $resolved_project_title,
    '{project_subtype}' => $resolved_project_subtype,
    '{duration}' => $resolved_duration,
    '{mode}' => $resolved_mode,
    '{mentor_name}' => $resolved_mentor,
    '{team_name}' => $resolved_team,
    '{start_date}' => $resolved_start_date,
    '{completion_date}' => $resolved_completion_date,
    '{company_name}' => $resolved_company
];

$resolved_content = $content_template;
foreach ($placeholders as $ph => $val) {
    $resolved_content = str_replace($ph, $val, $resolved_content);
}

// Generate PDF
require_once __DIR__ . '/includes/fpdf.php';
$pdf = new FPDF('L', 'mm', 'A4'); // Landscape, millimeters, A4 size (297mm x 210mm)
$pdf->AddPage();

// Draw decorative border
$pdf->SetLineWidth(2);
$pdf->SetDrawColor(29, 78, 216); // Blue #1d4ed8
$pdf->Rect(10, 10, 277, 190);

$pdf->SetLineWidth(0.5);
$pdf->SetDrawColor(99, 102, 241); // Indigo #6366f1
$pdf->Rect(13, 13, 271, 184);

// Logo / Branding
$pdf->SetY(25);
$local_logo = get_local_image_path($logo_path);
if (!empty($local_logo)) {
    // Render custom logo
    $pdf->Image($local_logo, 138, 20, 20);
    $pdf->SetY(42);
}

$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(29, 78, 216); // Blue
$pdf->Cell(0, 12, 'IMP', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(100, 116, 139); // Slate-400
$pdf->Cell(0, 6, 'INTERNSHIP MANAGEMENT PLATFORM', 0, 1, 'C');

$pdf->Ln(10);

// Certificate Title
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(79, 70, 229); // Indigo
$pdf->Cell(0, 10, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

$pdf->Ln(5);

// Certification text
$pdf->SetFont('Arial', 'I', 14);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 8, 'This is to certify that', 0, 1, 'C');

// Student Name
$pdf->Ln(2);
$pdf->SetFont('Times', 'BI', 32);
$pdf->SetTextColor(15, 23, 42); // Slate-900
$pdf->Cell(0, 15, $profile['full_name'], 0, 1, 'C');

// Description text
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(51, 65, 85); // Slate-700
$pdf->Cell(0, 8, $resolved_content, 0, 1, 'C');

// Details Box
$pdf->Ln(8);
$pdf->SetDrawColor(241, 245, 249); // Slate-100
$pdf->SetFillColor(248, 250, 252); // Slate-50
$pdf->Rect(35, 115, 227, 35, 'DF');

// Draw columns inside the details box
$pdf->SetY(118);
$pdf->SetX(40);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(148, 163, 184); // Slate-400
$pdf->Cell(70, 5, 'PROJECT', 0, 0);
$pdf->Cell(50, 5, 'PROGRAM DATES', 0, 0);
$pdf->Cell(40, 5, 'DURATION', 0, 0);
$pdf->Cell(50, 5, 'ASSESSMENT SCORE', 0, 1);

// Project values
$pdf->SetX(40);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 41, 59); // Slate-800

$pdf->Cell(70, 7, $resolved_project_title, 0, 0);

// Dates value
$date_range = $resolved_start_date . ' - ' . $resolved_completion_date;
$pdf->Cell(50, 7, $date_range, 0, 0);

// Duration value
$pdf->Cell(40, 7, $resolved_duration, 0, 0);

// Score value
$score_text = 'N/A';
$pdf->Cell(50, 7, $score_text, 0, 1);

// Signatures
$pdf->SetY(162);

// Left Signee
$pdf->SetX(35);
$pdf->SetFont('Times', 'I', 14);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(65, 6, $sig_name, 'B', 0, 'C');

// Center Seal
$pdf->SetX(110);
$local_seal = get_local_image_path($seal_image);
if (!empty($local_seal)) {
    $pdf->Image($local_seal, 138, 150, 20);
    // placeholder space
    $pdf->Cell(77, 6, '', 0, 0, 'C');
} else {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(29, 78, 216);
    $pdf->Cell(77, 6, 'OFFICIAL SEAL - VERIFIED', 0, 0, 'C');
}

// Right Signee
$pdf->SetX(197);
$pdf->SetFont('Times', 'I', 14);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(65, 6, $sig_designation, 'B', 1, 'C');

// Signee Titles
$pdf->SetY(169);
$pdf->SetX(35);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(65, 5, 'Program Coordinator', 0, 0, 'C');

$pdf->SetX(197);
$pdf->Cell(65, 5, 'Program Director', 0, 1, 'C');

// Certificate ID
$pdf->SetY(185);
$pdf->SetFont('Courier', '', 8);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(0, 5, 'Certificate ID: ' . $cert_id, 0, 1, 'C');

// Save to temporary file
$cert_filename = 'Certificate_' . $target_user_id . '_' . time() . '.pdf';
$temp_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cert_filename;
$pdf->Output('F', $temp_path);

// Upload to Cloudinary
try {
    $secure_url = uploadToCloudinary($temp_path, 'certificates', true);
} catch (Exception $e) {
    @unlink($temp_path);
    die("Cloudinary upload failed: " . $e->getMessage());
}

@unlink($temp_path);

// Save secure URL to internship_applications table
$esc_url = mysqli_real_escape_string($conn, $secure_url);
$app_id = intval($intern['app_id']);
$update_sql = "UPDATE internship_applications SET certificate_path = '$esc_url' WHERE id = $app_id";
mysqli_query($conn, $update_sql);

// Redirect to Cloudinary URL
$target_url = $secure_url;
header("Location: " . $target_url);
exit();
