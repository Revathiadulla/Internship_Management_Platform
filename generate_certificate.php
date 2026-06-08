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

// Fetch active/started internship details
$intern_sql = "SELECT a.id as app_id, a.applied_date, a.test_score, a.education_status, a.certificate_path,
                      COALESCE(i.title, a.internship_name) as title,
                      COALESCE(i.duration, '3 Months') as duration,
                      COALESCE(i.mode, 'Remote') as mode,
                      ss.score as ss_score,
                      ss.total_questions as ss_total_questions
               FROM internship_applications a
               LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
               LEFT JOIN student_scores ss ON a.id = ss.application_id
               WHERE a.user_id = '$target_user_id' AND a.status = 'Started'
               LIMIT 1";
$intern_res  = mysqli_query($conn, $intern_sql);
$intern      = mysqli_fetch_assoc($intern_res);

if (!$intern) {
    die("No started/active internship found for this student.");
}

// If certificate_path is already generated and populated with a Cloudinary URL, redirect directly
if (!empty($intern['certificate_path']) && (strpos($intern['certificate_path'], 'http://') === 0 || strpos($intern['certificate_path'], 'https://') === 0)) {
    $target_url = $intern['certificate_path'];
    if ($mode === 'view') {
        $target_url = 'https://docs.google.com/gview?embedded=true&url=' . urlencode($target_url);
    }
    header("Location: " . $target_url);
    exit();
}

// Compute certificate details
$cert_score = 0;
$cert_total = 30;
if (isset($intern['ss_score']) && $intern['ss_score'] !== null) {
    $cert_score = intval($intern['ss_score']);
    $cert_total = intval($intern['ss_total_questions'] ?: 30);
} else if (isset($intern['test_score']) && $intern['test_score'] !== null) {
    $p = intval($intern['test_score']);
    if ($p > 30) {
        $cert_score = intval(round(($p / 100) * 30));
    } else {
        $cert_score = $p;
    }
}

$start_date = new DateTime($intern['applied_date']);
$end_date   = clone $start_date;
$end_date->modify('+3 months');
$cert_id    = 'IMP-' . date('Y') . '-' . strtoupper(substr($profile['full_name'], 0, 2)) . '-' . str_pad($target_user_id, 5, '0', STR_PAD_LEFT);

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
$title_text = $intern['title'] ? $intern['title'] : 'Intern';
$msg = "has successfully completed the internship program as a " . $title_text . " at the Internship Management Platform (IMP).";
$pdf->Cell(0, 8, $msg, 0, 1, 'C');

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

// Resolve project name (same as student_certificate.php)
$p_name = 'General Internship Project';
$t = strtolower($intern['title']);
if (strpos($t,'mobile')!==false||strpos($t,'android')!==false||strpos($t,'flutter')!==false)
    $p_name = 'Mobile App Development Project';
elseif (strpos($t,'frontend')!==false||strpos($t,'react')!==false||strpos($t,'web')!==false)
    $p_name = 'Responsive Web Application';
elseif (strpos($t,'data')!==false||strpos($t,'python')!==false)
    $p_name = 'Sales Data Analysis Dashboard';
elseif (strpos($t,'ui')!==false||strpos($t,'ux')!==false||strpos($t,'design')!==false)
    $p_name = 'Mobile App UI Redesign';
elseif (strpos($t,'backend')!==false||strpos($t,'node')!==false)
    $p_name = 'RESTful API Service';
    
$pdf->Cell(70, 7, $p_name, 0, 0);

// Dates value
$date_range = $start_date->format('M d, Y') . ' - ' . $end_date->format('M d, Y');
$pdf->Cell(50, 7, $date_range, 0, 0);

// Duration value
$pdf->Cell(40, 7, $intern['duration'], 0, 0);

// Score value
$score_text = ($intern['test_score'] !== null && $cert_total > 0) ? $cert_score . '/' . $cert_total . ' (' . round(($cert_score / $cert_total) * 100) . '%)' : 'N/A';
$pdf->Cell(50, 7, $score_text, 0, 1);

// Signatures
$pdf->SetY(162);

// Left Signee
$pdf->SetX(35);
$pdf->SetFont('Times', 'I', 14);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(65, 6, 'IMP Coordinator', 'B', 0, 'C');

// Center Seal
$pdf->SetX(110);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(29, 78, 216);
$pdf->Cell(77, 6, 'OFFICIAL SEAL - VERIFIED', 0, 0, 'C');

// Right Signee
$pdf->SetX(197);
$pdf->SetFont('Times', 'I', 14);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(65, 6, 'IMP Director', 'B', 1, 'C');

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

// Redirect to Cloudinary URL or Google Docs Viewer
$target_url = $secure_url;
if ($mode === 'view') {
    $target_url = 'https://docs.google.com/gview?embedded=true&url=' . urlencode($target_url);
}
header("Location: " . $target_url);
exit();
