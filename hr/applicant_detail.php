<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto_helper.php';
require_hr_or_admin();
require __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/status_utils.php';
require_once __DIR__ . '/../includes/workflow_helper.php';
require_once __DIR__ . '/../includes/cloudinary_config.php';

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
if ($app_id <= 0) {
    die('<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h2>Invalid candidate selected</h2><a href="applications.php">Go back</a></div>');
}

// PHP POST note handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    header('Content-Type: application/json');
    $note_text = isset($_POST['note_text']) ? trim($_POST['note_text']) : '';
    $hr_id = current_user_id();
    
    if ($app_id <= 0 || empty($note_text)) {
        echo json_encode(['success' => false, 'message' => 'Note text cannot be empty']);
        exit();
    }
    
    // Get student_id from application
    $student_stmt = $conn->prepare("SELECT user_id FROM internship_applications WHERE id = ? LIMIT 1");
    if (!$student_stmt) {
        error_log("Failed to prepare student_id query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: failed to prepare query']);
        exit();
    }
    $student_stmt->bind_param("i", $app_id);
    $student_stmt->execute();
    $student_res = $student_stmt->get_result()->fetch_assoc();
    $student_id = $student_res ? intval($student_res['user_id']) : 0;
    $student_stmt->close();
    
    if ($student_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student associated with this application']);
        exit();
    }
    
    // Insert note
    $note_stmt = $conn->prepare("INSERT INTO hr_notes (application_id, student_id, hr_id, note_text) VALUES (?, ?, ?, ?)");
    if (!$note_stmt) {
        error_log("Failed to prepare note insert query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: failed to prepare insertion']);
        exit();
    }
    $note_stmt->bind_param("iiis", $app_id, $student_id, $hr_id, $note_text);
    
    if ($note_stmt->execute()) {
        $note_stmt->close();
        
        // Fetch updated notes list
        $notes_q_stmt = $conn->prepare("
            SELECT n.note_text, n.created_at, u.full_name AS author_name
            FROM hr_notes n
            LEFT JOIN users u ON n.hr_id = u.id
            WHERE n.application_id = ?
            ORDER BY n.created_at DESC
        ");
        if (!$notes_q_stmt) {
            error_log("Failed to prepare updated notes selection query: " . $conn->error);
            echo json_encode(['success' => true, 'message' => 'Note saved, but failed to fetch updated list', 'notes' => []]);
            exit();
        }
        $notes_q_stmt->bind_param("i", $app_id);
        $notes_q_stmt->execute();
        $notes_res = $notes_q_stmt->get_result();
        $updated_notes = [];
        while ($row = $notes_res->fetch_assoc()) {
            $updated_notes[] = [
                'note_text' => $row['note_text'],
                'author_name' => $row['author_name'] ?: 'Unknown HR',
                'created_at' => date('M d, Y H:i', strtotime($row['created_at']))
            ];
        }
        $notes_q_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Note saved successfully',
            'notes' => $updated_notes
        ]);
    } else {
        error_log("Failed to save note: " . $note_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $note_stmt->error]);
        $note_stmt->close();
    }
    exit();
}

// Join users, student_profiles, and internship_applications in one query for full detail.
$has_resume_url = false;
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'resume_url'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $has_resume_url = true;
}
$resume_url_select = $has_resume_url ? "sp.resume_url AS sp_resume_url" : "NULL AS sp_resume_url";

$sql = "SELECT
            a.id                      AS app_id,
            a.user_id,
            a.status,
            a.verification_status,
            a.applied_date,
            a.education_status        AS app_education_status,
            a.preferred_domain,
            a.department,
            a.graduation_year,
            a.year_of_study           AS app_year_of_study,
            a.college_name            AS app_college,
            a.full_name               AS app_full_name,
            NULL                      AS app_email,
            NULL                      AS app_phone,
            a.resume_file             AS app_resume,
            a.hod_name                AS hod_name,
            a.hod_email               AS hod_email,
            a.hod_phone               AS hod_phone,
            a.hod_approval_status     AS hod_approval_status,
            a.hod_token               AS hod_token,
            a.internship_name,
            a.applied_subtype,
            a.confirmation_letter_sent_at,
            a.assigned_project_id,
            COALESCE(NULLIF(i.project_subtype, ''), '') AS i_project_subtype,
            COALESCE(NULLIF(i.project_type, ''), '')    AS i_project_type,
            COALESCE(i.title, a.internship_name)        AS internship_title,
            COALESCE(i.duration, '')                    AS internship_duration,
            COALESCE(i.mode, '')                        AS internship_mode,
            COALESCE(i.skills, '')                      AS internship_skills,
            u.full_name               AS user_full_name,
            u.email                   AS user_email,
            u.phone                   AS user_phone,
            sp.full_name              AS sp_full_name,
            sp.email                  AS sp_email,
            sp.phone                  AS sp_phone,
            sp.college_name           AS sp_college,
            sp.course,
            sp.year_of_study          AS sp_year_of_study,
            sp.skills                 AS sp_skills,
            sp.dob,
            sp.gender,
            sp.resume_file            AS sp_resume,
            sp.resume_original_name   AS sp_resume_orig,
            sp.aadhaar_original_name  AS sp_aadhaar_orig,
            sp.pan_original_name      AS sp_pan_orig,
            $resume_url_select,
            sp.aadhaar_file,
            sp.pan_file,
            sp.aadhaar_number         AS sp_aadhaar,
            sp.pan_number,
            a.aadhaar_number          AS app_aadhaar,
            a.pan_number              AS app_pan,
            a.pan_file                AS app_pan_file,
            a.resume_original_name    AS app_resume_orig,
            a.pan_original_name       AS app_pan_orig,
            a.aadhaar_original_name   AS app_aadhaar_orig,
            sp.hod_email              AS sp_hod_email,
            sp.hod_name               AS sp_hod_name,
            sp.hod_phone              AS sp_hod_phone,
            a.aadhaar_status,
            a.pan_status,
            a.hod_status,
            a.final_status,
            sp.student_type,
            sp.education_status       AS sp_education_status
        FROM internship_applications a
        LEFT JOIN internships i       ON a.internship_id = i.id AND a.internship_id > 0
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        LEFT JOIN users u            ON a.user_id = u.id
        WHERE a.id = $app_id
        LIMIT 1";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Database Query Failed: " . mysqli_error($conn) . " | SQL: " . htmlspecialchars($sql));
}
if (mysqli_num_rows($result) === 0) {
    header('Location: applications.php?error=not_found');
    exit();
}
$d = mysqli_fetch_assoc($result);

// Helper function to check if field is empty or contains only "-"
function is_empty_field($val) {
    if ($val === null) return true;
    $trimmed = trim($val);
    return ($trimmed === '' || $trimmed === '-' || strtolower($trimmed) === 'n/a');
}

// Assessment details removed as Test Module is no longer part of the workflow.
$score_row = null;
$attempts_count = 0;

$raw_aadhaar  = $d['sp_aadhaar'] ?: $d['app_aadhaar'] ?: '';

// Determine base URL dynamically based on project root
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$script_dir = str_replace('\\', '/', $script_dir);
if (substr($script_dir, -1) !== '/') {
    $script_dir .= '/';
}
$base_url = $proto . '://' . $host . $script_dir;

$aadhaar_file_url = null;
$db_aadhaar = !empty($d['aadhaar_file']) ? trim($d['aadhaar_file']) : '';
$aadhaar_uploaded = ($db_aadhaar !== '');
if ($aadhaar_uploaded) {
    $resolved_aadhaar = getDocumentUrl($db_aadhaar);
    if ($resolved_aadhaar === 'unavailable') {
        $aadhaar_file_url = 'unavailable';
    } elseif (strpos($resolved_aadhaar, 'http://') === 0 || strpos($resolved_aadhaar, 'https://') === 0) {
        $aadhaar_file_url = $resolved_aadhaar;
    } else {
        $aadhaar_file_url = $base_url . 'view_document.php?file=' . urlencode(basename($resolved_aadhaar));
    }
}

$pan_number   = $d['pan_number'] ?: $d['app_pan'] ?: '';

$pan_file_url = null;
$db_pan = !empty($d['pan_file']) ? trim($d['pan_file']) : (!empty($d['app_pan_file']) ? trim($d['app_pan_file']) : '');
$pan_uploaded = ($db_pan !== '');
if ($pan_uploaded) {
    $resolved_pan = getDocumentUrl($db_pan);
    if ($resolved_pan === 'unavailable') {
        $pan_file_url = 'unavailable';
    } elseif (strpos($resolved_pan, 'http://') === 0 || strpos($resolved_pan, 'https://') === 0) {
        $pan_file_url = $resolved_pan;
    } else {
        $pan_file_url = $base_url . 'view_document.php?file=' . urlencode(basename($resolved_pan));
    }
}

$aadhaar_orig_filename = $d['app_aadhaar_orig'] ?: $d['sp_aadhaar_orig'] ?: '';
$aadhaar_label = $aadhaar_orig_filename !== '' ? $aadhaar_orig_filename : ($db_aadhaar !== '' ? basename($db_aadhaar) : 'View Aadhaar File');

$pan_orig_filename = $d['app_pan_orig'] ?: $d['sp_pan_orig'] ?: '';
$pan_label = $pan_orig_filename !== '' ? $pan_orig_filename : ($db_pan !== '' ? basename($db_pan) : 'View PAN File');


// Prefer student_profiles data first, then users, then application snapshot.
$full_name  = $d['sp_full_name']    ?: $d['user_full_name']    ?: $d['app_full_name']    ?: 'â€”';
$email      = $d['sp_email']        ?: $d['user_email']        ?: $d['app_email']        ?: 'â€”';
$phone      = $d['sp_phone']        ?: $d['user_phone']        ?: $d['app_phone']        ?: 'â€”';
$college    = $d['sp_college']      ?: $d['app_college']      ?: 'â€”';
$department = $d['department']      ?: 'â€”';
$grad_year  = $d['graduation_year']  ?: 'â€”';
$sp_education_status = isset($d['sp_education_status']) ? trim($d['sp_education_status']) : '';
$app_education_status = isset($d['app_education_status']) ? trim($d['app_education_status']) : '';
$education_status = $sp_education_status !== '' ? $sp_education_status : $app_education_status;
$preferred_duration = 'â€”';
$preferred_domain   = $d['preferred_domain']   ?: 'â€”';
$relevant_skills    = $d['sp_skills']        ?: 'â€”';
$reason_for_applying = 'â€”';
// Resolve applied internship subtype from best available source
$applied_internship_subtype = '';
if (!empty($d['applied_subtype'])) {
    $applied_internship_subtype = trim($d['applied_subtype']);
} elseif (!empty($d['i_project_subtype'])) {
    $applied_internship_subtype = trim($d['i_project_subtype']);
} elseif (!empty($d['internship_name'])) {
    // Extract subtype from internship_name by stripping Intern/Internship suffix
    $applied_internship_subtype = trim(preg_replace('/(\s+Internship|\s+Intern)$/i', '', $d['internship_name']));
}
if (empty($applied_internship_subtype) || in_array(strtolower($applied_internship_subtype), ['internship management platform', 'imp'], true)) {
    $applied_internship_subtype = 'Not Assigned';
}

// Resolve project type
$applied_project_type = !empty($d['i_project_type']) ? trim($d['i_project_type']) : '';

// Internship duration, mode, skills from joined internship record
$internship_duration = !empty($d['internship_duration']) ? trim($d['internship_duration']) : '';
$internship_mode     = !empty($d['internship_mode'])     ? trim($d['internship_mode'])     : '';
$internship_skills   = !empty($d['internship_skills'])   ? trim($d['internship_skills'])   : '';
$applied_date       = $d['applied_date'] ? date('M d, Y', strtotime($d['applied_date'])) : 'â€”';
$verification_value = trim((string) ($d['verification_status'] ?? ''));
$aadhaar_verified = strtolower((string) ($d['aadhaar_status'] ?? '')) === 'verified';
$pan_verified = strtolower((string) ($d['pan_status'] ?? '')) === 'verified';
$documents_verified = (strtolower($verification_value) === 'verified') || ($aadhaar_verified && $pan_verified);
$verification = $documents_verified ? 'Verified' : 'Pending';
$current_status     = $d['status'] ?: 'â€”';
$current_status_key = normalize_workflow_status($current_status);
$is_hod_pending_status = is_hod_pending_status($current_status);
$is_hod_approved_status = is_hod_approved_status($current_status);
$is_hod_rejected_status = is_hod_rejected_status($current_status);
$is_selected_status = is_selected_status($current_status);
$is_rejected_status = is_rejected_status($current_status);
$confirmation_letter_ready = (!empty($d['confirmation_letter_path']) || !empty($d['confirmation_letter_sent_at']) || (!empty($d['confirmation_letter_sent']) && (int) $d['confirmation_letter_sent'] === 1) || !empty($d['offer_letter_path']));
$display_status = $is_selected_status ? ($confirmation_letter_ready ? 'Selected / Confirmation Letter Sent' : 'Selected') : $current_status;
$hod_name  = !empty($d['hod_name'])  ? trim($d['hod_name'])  : (!empty($d['sp_hod_name'])  ? trim($d['sp_hod_name'])  : '');
$hod_email = !empty($d['hod_email']) ? trim($d['hod_email']) : (!empty($d['sp_hod_email']) ? trim($d['sp_hod_email']) : '');
$hod_phone = !empty($d['hod_phone']) ? trim($d['hod_phone']) : (!empty($d['sp_hod_phone']) ? trim($d['sp_hod_phone']) : '');

$student_type = trim((string) ($d['student_type'] ?? ''));
$is_pursuing = is_pursuing_student($education_status, $student_type);
$is_passed_out = !$is_pursuing;

$resume             = $d['sp_resume'] ?: $d['app_resume'] ?: '';
$resume_url         = !empty($d['sp_resume_url']) ? trim($d['sp_resume_url']) : '';
$raw_resume         = !empty($resume_url) ? $resume_url : $resume;

$is_remote = false;
$resume_link = '#';

if ($resume_url !== '' && (strpos($resume_url, 'http://') === 0 || strpos($resume_url, 'https://') === 0)) {
    $resume_link = $resume_url;
    $is_remote = true;
} elseif ($resume !== '' && (strpos($resume, 'http://') === 0 || strpos($resume, 'https://') === 0)) {
    $resume_link = $resume;
    $is_remote = true;
}

if ($is_remote) {
    $has_resume = true;
    $resume_ext = 'url';
    $orig_filename = $d['app_resume_orig'] ?: $d['sp_resume_orig'] ?: '';
    $resume_label = $orig_filename !== '' ? $orig_filename : $resume_link;
    $view_href = getDocumentViewUrl($resume_link);
    $download_href = $resume_link;
} else {
    $resume_safe        = $resume !== '' ? urlencode(basename($resume)) : '';
    $resume_ext         = $resume !== '' ? strtolower(pathinfo($resume, PATHINFO_EXTENSION)) : '';
    $has_resume         = $resume_safe !== '' && in_array($resume_ext, ['pdf', 'doc', 'docx'], true);
    $orig_filename = $d['app_resume_orig'] ?: $d['sp_resume_orig'] ?: '';
    $resume_label       = $orig_filename !== '' ? $orig_filename : ($resume !== '' ? basename($resume) : 'No resume uploaded');
    $view_href = getDocumentViewUrl("resume_serve.php?file=" . $resume_safe . "&mode=view");
    $download_href = "resume_serve.php?file=" . $resume_safe . "&mode=download";
}

$profile_mock = [
    'resume_file' => $resume,
    'resume_url' => $resume_url
];
$exists = check_resume_exists($profile_mock);

// Status history timeline
$history_sql    = "SELECT * FROM application_status_history WHERE application_id = $app_id ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);
$history_rows   = [];
if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history_rows[] = $row;
    }
}

// Fetch HR notes
$notes_sql = "SELECT n.*, u.full_name AS author_name FROM hr_notes n LEFT JOIN users u ON n.hr_id = u.id WHERE n.application_id = $app_id ORDER BY n.created_at DESC";
$notes_result = mysqli_query($conn, $notes_sql);
$notes_rows = [];
if ($notes_result) {
    while ($row = mysqli_fetch_assoc($notes_result)) {
        $notes_rows[] = $row;
    }
}

// 1. Active Mentor Assignment
$assigned_mentor = null;
$student_user_id = intval($d['user_id']);
if ($student_user_id > 0) {
    $mentor_stmt = $conn->prepare("
        SELECT u.full_name AS mentor_name, u.email AS mentor_email, ma.assigned_at
        FROM mentor_assignments ma
        JOIN users u ON u.id = ma.mentor_id
        WHERE ma.student_id = ? AND ma.status = 'active'
        LIMIT 1
    ");
    $mentor_stmt->bind_param("i", $student_user_id);
    $mentor_stmt->execute();
    $assigned_mentor = $mentor_stmt->get_result()->fetch_assoc();
    $mentor_stmt->close();
}

// 2. Mentor Feedback History
$mentor_feedbacks = [];
if ($student_user_id > 0) {
    $fb_stmt = $conn->prepare("
        SELECT mf.feedback_title AS title, mf.comments, mf.rating, mf.status, mf.created_at, mf.given_by AS mentor_name
        FROM mentor_feedback mf
        WHERE mf.user_id = ?
        ORDER BY mf.created_at DESC
    ");
    $fb_stmt->bind_param("i", $student_user_id);
    $fb_stmt->execute();
    $mentor_feedbacks = $fb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fb_stmt->close();
}

// 3. Company Shortlists
$shortlisted_companies = [];
if ($student_user_id > 0) {
    $sh_stmt = $conn->prepare("
        SELECT cp.company_name, cs.created_at
        FROM company_shortlists cs
        JOIN company_profiles cp ON cp.user_id = cs.company_id
        WHERE cs.candidate_id = ?
        ORDER BY cs.created_at DESC
    ");
    $sh_stmt->bind_param("i", $student_user_id);
    $sh_stmt->execute();
    $shortlisted_companies = $sh_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sh_stmt->close();
}

// 4. Company Contacts
$contacted_companies = [];
if ($student_user_id > 0) {
    $co_stmt = $conn->prepare("
        SELECT cp.company_name, cc.message, cc.contacted_at
        FROM company_contacts cc
        JOIN company_profiles cp ON cp.user_id = cc.company_id
        WHERE cc.candidate_id = ?
        ORDER BY cc.contacted_at DESC
    ");
    $co_stmt->bind_param("i", $student_user_id);
    $co_stmt->execute();
    $contacted_companies = $co_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $co_stmt->close();
}

// 5. Assigned Project Details (for Project Assigned / Team Assigned / Active statuses)
$assigned_project_details = null;
if ($student_user_id > 0) {
    // Try to get team assignment via project_team_members -> project_teams
    $proj_stmt = $conn->prepare("
        SELECT 
            pt.team_name,
            pt.project_type,
            pt.project_subtype,
            pt.status AS team_status,
            pt.created_at AS team_created_at,
            pt.mentor_id,
            COALESCE(i.title, '') AS internship_title,
            COALESCE(i.duration, '') AS internship_duration,
            COALESCE(i.mode, '') AS internship_mode,
            COALESCE(i.skills, '') AS tech_stack,
            COALESCE(mu.full_name, '') AS mentor_name,
            COALESCE(mu.email, '') AS mentor_email
        FROM project_team_members ptm
        JOIN project_teams pt ON pt.id = ptm.project_team_id
        LEFT JOIN internships i ON pt.internship_id = i.id
        LEFT JOIN users mu ON pt.mentor_id = mu.id
        WHERE ptm.student_id = ?
        ORDER BY ptm.created_at DESC
        LIMIT 1
    ");
    if ($proj_stmt) {
        $proj_stmt->bind_param("i", $student_user_id);
        $proj_stmt->execute();
        $assigned_project_details = $proj_stmt->get_result()->fetch_assoc();
        $proj_stmt->close();
    }
    // Fallback: try mentor_assignments if no team assignment found
    if (!$assigned_project_details && !empty($assigned_mentor)) {
        $assigned_project_details = [
            'team_name' => '',
            'project_type' => $applied_project_type ?: '',
            'project_subtype' => $applied_internship_subtype ?: '',
            'team_status' => '',
            'team_created_at' => $assigned_mentor['assigned_at'] ?? '',
            'mentor_name' => $assigned_mentor['mentor_name'] ?? '',
            'mentor_email' => $assigned_mentor['mentor_email'] ?? '',
            'internship_title' => $d['internship_title'] ?? '',
            'internship_duration' => $internship_duration,
            'internship_mode' => $internship_mode,
            'tech_stack' => $internship_skills,
        ];
    }
}

// Compute aggregate metrics
$avg_rating = 0.0;
$total_ratings = 0;
$sum_ratings = 0;
foreach ($mentor_feedbacks as $fb) {
    if ($fb['rating'] > 0) {
        $sum_ratings += $fb['rating'];
        $total_ratings++;
    }
}
if ($total_ratings > 0) {
    $avg_rating = round($sum_ratings / $total_ratings, 1);
}
$shortlisted_count = count($shortlisted_companies);
$contacted_count = count($contacted_companies);

// Back link from HR applications list
$back = 'applications.php';
if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'applications.php') !== false) {
    $back = htmlspecialchars($_SERVER['HTTP_REFERER']);
}

$status_icons = [
    'Applied'        => 'send',
    'HR Review'      => 'rate_review',
    'Shortlisted'    => 'star',
      'Exam Link Sent' => 'forward_to_inbox',
    'HOD Pending'    => 'hourglass_empty',
    'HOD Approved'   => 'verified_user',
    'Selected'       => 'verified',
    'Rejected'       => 'cancel',
    'Pending'        => 'schedule',
];
$status_colors = [
    'Applied'        => 'bg-blue-100 text-blue-600',
    'HR Review'      => 'bg-indigo-100 text-indigo-600',
    'Shortlisted'    => 'bg-amber-100 text-amber-600',
      'Exam Link Sent' => 'bg-purple-100 text-purple-600',
    'HOD Pending'    => 'bg-orange-100 text-orange-600',
    'HOD Approved'   => 'bg-teal-100 text-teal-600',
    'Selected'       => 'bg-emerald-100 text-emerald-600',
    'Rejected'       => 'bg-red-100 text-red-600',
    'Pending'        => 'bg-slate-100 text-slate-600',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applicant Detail â€” <?php echo htmlspecialchars($full_name); ?> | IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet"/>
  <style>
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
    body { font-family:'Inter',sans-serif; }
    .timeline-line { position:absolute; left:1.375rem; top:2.5rem; bottom:0; width:2px; background:#e2e8f0; }
    .timeline-item { position:relative; padding-left:3.5rem; padding-bottom:1.75rem; }
    .timeline-dot { position:absolute; left:0; top:0.25rem; width:1.25rem; height:1.25rem; border-radius:9999px; display:inline-flex; align-items:center; justify-content:center; background:white; border:2px solid #cbd5e1; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
  <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 text-sm font-medium">
    <div class="px-6 mb-8">
      <a href="index.html" class="flex items-center gap-2">
        <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="32" height="32" rx="8" fill="currentColor"/>
          <circle cx="16" cy="16" r="3" fill="white"/>
          <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
          <circle cx="16" cy="8" r="1.5" fill="white"/>
          <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
          <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
          <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
          <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
          <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
          <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
        </svg>
        <span class="text-xl font-bold text-blue-600">IMP</span>
      </a>
      <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">HR Portal</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a href="dashboard.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <a href="applications.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3"><span class="material-symbols-outlined">assignment</span><span>Applications</span></a>
      <a href="candidates.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">group</span><span>Candidates</span></a>
      <a href="student_logs.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">description</span><span>Student Logs</span></a>

      <a href="reports.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <a href="notifications.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">notifications</span><span>Notifications</span></a>
    </nav>
    <div class="mt-auto border-t border-gray-200 pt-4 flex flex-col gap-1">
      <a href="#" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">help</span><span>Help Center</span></a>
      <a href="../logout.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </div>
  </aside>

  <main class="pl-60 min-h-screen bg-slate-50">
    <div class="max-w-[1600px] mx-auto px-6 py-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-6">
        <div>
          <a href="<?php echo $back; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-blue-600">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to applications
          </a>
          <h1 class="mt-4 text-3xl font-extrabold text-slate-900">Applicant profile</h1>
          <p class="mt-2 text-sm text-slate-500">Full candidate details, application data, workflow timeline, and resume actions.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Current status</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold <?php echo getStatusBadgeClass($display_status); ?>">
              <span class="material-symbols-outlined text-base">info</span>
              <?php echo htmlspecialchars($display_status); ?>
            </div>
          </div>
          <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Verification</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold <?php echo getVerificationBadgeClass($verification); ?>">
              <span class="material-symbols-outlined text-base">verified</span>
              <?php echo htmlspecialchars($verification); ?>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
        <section class="space-y-6">
          <?php
          $has_basic_info = !is_empty_field($full_name) || !is_empty_field($email) || !is_empty_field($phone) || !is_empty_field($college) || !is_empty_field($department) || !is_empty_field($grad_year);
          if ($has_basic_info):
          ?>
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Basic info</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Candidate details</h2>
              </div>
              <div class="flex items-center gap-2 text-slate-500">
                <span class="material-symbols-outlined">person</span>
                <span class="text-sm font-semibold">Applicant#<?php echo $app_id; ?></span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <?php if (!is_empty_field($full_name)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Full name</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($full_name); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($email)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Email</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($email); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($phone)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Phone</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($phone); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($college)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">College</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($college); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($department)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Department</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($department); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($grad_year)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Graduation year</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($grad_year); ?></p>
              </div>
              <?php endif; ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Student Type</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars(($is_passed_out) ? 'Passed Out / Graduated' : 'Pursuing / Current Student'); ?></p>
              </div>
              <?php if ($is_pursuing): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">HOD Name</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($hod_name ?: 'Not Provided'); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">HOD Email</p>
                <p class="mt-2 text-sm font-semibold text-slate-900">
                  <?php if (!empty($hod_email)): ?>
                    <a href="mailto:<?php echo htmlspecialchars($hod_email); ?>" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($hod_email); ?></a>
                  <?php else: ?>
                    Not Provided
                  <?php endif; ?>
                </p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">HOD Phone</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($hod_phone ?: 'Not Provided'); ?></p>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>



          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Application info</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Internship application</h2>
              </div>
              <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                Applied <?php echo htmlspecialchars($applied_date); ?>
              </span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Applied Internship</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($applied_internship_subtype); ?></p>
              </div>
              <?php if (!is_empty_field($applied_project_type)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Internship Type</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($applied_project_type); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($internship_duration)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Duration</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($internship_duration); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($internship_mode)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Mode</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($internship_mode); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($education_status)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Education status</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($is_passed_out ? 'Passed Out / Graduated' : 'Pursuing / Current Student'); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($department)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Department</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($department); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($college)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">College</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($college); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!is_empty_field($internship_skills)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200 md:col-span-2">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Skills / Technology Stack</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo nl2br(htmlspecialchars($internship_skills)); ?></p>
              </div>
              <?php elseif (!is_empty_field($relevant_skills)): ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200 md:col-span-2">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Relevant skills</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo nl2br(htmlspecialchars($relevant_skills)); ?></p>
              </div>
              <?php endif; ?>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Application Status</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($current_status); ?></p>
              </div>
            </div>
          </div>

          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Resume actions</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Candidate resume</h2>
              </div>
              <?php if ($has_resume): ?>
                <?php if (getDocumentUrl($raw_resume) === 'unavailable'): ?>
                  <span class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                    <span class="material-symbols-outlined text-[18px]">warning</span>
                    Document unavailable. Please ask student to update/reupload document.
                  </span>
                <?php else: ?>
                  <div class="flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars($view_href); ?>" target="_blank" rel="noopener noreferrer" data-resume-exists="<?php echo $exists ? 'true' : 'false'; ?>" class="inline-flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-all">
                      <span class="material-symbols-outlined">visibility</span>
                      View resume
                    </a>
                    <?php if (!$is_remote): ?>
                    <a href="<?php echo $download_href; ?>" data-resume-exists="<?php echo $exists ? 'true' : 'false'; ?>" class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-all">
                      <span class="material-symbols-outlined">download</span>
                      Download resume
                    </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-500">
                  <span class="material-symbols-outlined">description</span>
                  No resume available
                </span>
              <?php endif; ?>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Resume file</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($resume_label); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Resume format</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo $is_remote ? 'Remote URL' : ($resume_ext ? htmlspecialchars(strtoupper($resume_ext)) : 'â€”'); ?></p>
              </div>
            </div>
          </div>

          <!-- Identity Verification Documents Card -->
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Identity details</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Verified Candidate Onboarding (Aadhaar / PAN)</h2>
              </div>
              <div class="flex items-center gap-2 text-slate-500">
                <span class="material-symbols-outlined">verified_user</span>
                <span class="text-sm font-semibold">Verification status: <?php echo htmlspecialchars($verification); ?></span>
              </div>
            </div>
            
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200 flex flex-col justify-between">
                <div>
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Aadhaar Number</p>
                  <div class="mt-2 flex items-center gap-2">
                    <span class="font-mono text-sm font-bold text-slate-800" id="aadhaar-display-val">
                      <?php echo htmlspecialchars(mask_aadhaar($raw_aadhaar)); ?>
                    </span>
                    <?php if (!empty($raw_aadhaar)): ?>
                      <button type="button" onclick="toggleAadhaarReveal()" class="text-xs text-blue-600 font-bold hover:underline cursor-pointer">Reveal</button>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200">
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">Aadhaar Document</p>
                  <?php if ($aadhaar_file_url !== null): ?>
                    <a href="<?php echo htmlspecialchars(getDocumentViewUrl($aadhaar_file_url)); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-750 font-bold">
                      <span class="material-symbols-outlined text-[16px]">visibility</span> <?php echo htmlspecialchars($aadhaar_label); ?>
                    </a>
                  <?php elseif ($aadhaar_uploaded): ?>
                    <p class="text-xs text-red-500 font-semibold">Aadhaar document not found.</p>
                  <?php else: ?>
                    <p class="text-xs text-slate-400 italic">No document uploaded</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200 flex flex-col justify-between">
                <div>
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400">PAN Number</p>
                  <p class="mt-2 font-mono text-sm font-bold text-slate-800">
                    <?php echo htmlspecialchars($pan_number ? substr($pan_number, 0, 5) . '****' . substr($pan_number, -1) : 'â€”'); ?>
                  </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200">
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">PAN Document</p>
                  <?php if ($pan_file_url !== null): ?>
                    <a href="<?php echo htmlspecialchars(getDocumentViewUrl($pan_file_url)); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-750 font-bold">
                      <span class="material-symbols-outlined text-[16px]">visibility</span> <?php echo htmlspecialchars($pan_label); ?>
                    </a>
                  <?php elseif ($pan_uploaded): ?>
                    <p class="text-xs text-red-500 font-semibold">PAN document not found.</p>
                  <?php else: ?>
                    <p class="text-xs text-slate-400 italic">No document uploaded</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Verification Action Buttons for HR -->
            <div class="mt-6 pt-5 border-t border-slate-100 grid gap-4 sm:grid-cols-2">
              <div class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-200 rounded-2xl">
                <div>
                  <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Aadhaar Status</p>
                  <p class="text-xs font-semibold text-slate-800 mt-1 capitalize"><?php echo htmlspecialchars($d['aadhaar_status'] ?? 'pending'); ?></p>
                </div>
                <div class="flex gap-2">
                  <?php if (($d['aadhaar_status'] ?? 'pending') !== 'verified' && !empty($d['aadhaar_file'])): ?>
                    <a href="../admin/verify_document.php?app_id=<?php echo $app_id; ?>&type=aadhaar&action=verify" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                      Verify
                    </a>
                  <?php endif; ?>
                  <?php if (($d['aadhaar_status'] ?? 'pending') !== 'rejected' && !empty($d['aadhaar_file'])): ?>
                    <a href="../admin/verify_document.php?app_id=<?php echo $app_id; ?>&type=aadhaar&action=reject" onclick="return confirm('Are you sure you want to reject this Aadhaar document?');" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                      Reject
                    </a>
                  <?php endif; ?>
                </div>
              </div>

              <div class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-200 rounded-2xl">
                <div>
                  <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">PAN Status</p>
                  <p class="text-xs font-semibold text-slate-800 mt-1 capitalize"><?php echo htmlspecialchars($d['pan_status'] ?? 'pending'); ?></p>
                </div>
                <div class="flex gap-2">
                  <?php if (($d['pan_status'] ?? 'pending') !== 'verified' && !empty($d['pan_file'])): ?>
                    <a href="../admin/verify_document.php?app_id=<?php echo $app_id; ?>&type=pan&action=verify" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                      Verify
                    </a>
                  <?php endif; ?>
                  <?php if (($d['pan_status'] ?? 'pending') !== 'rejected' && !empty($d['pan_file'])): ?>
                    <a href="../admin/verify_document.php?app_id=<?php echo $app_id; ?>&type=pan&action=reject" onclick="return confirm('Are you sure you want to reject this PAN document?');" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                      Reject
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </section>

        <aside class="space-y-6">
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">HR workflow</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">Current application state</h2>
            <div class="mt-5 space-y-4">
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Current status</p>
                <p class="mt-2 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($display_status); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Verification status</p>
                <p class="mt-2 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($verification); ?></p>
              </div>
              
              <?php
                // Fetch HOD info from the application
                $hod_appr_status = $d['hod_approval_status'] ?? '';
                $hod_email_val   = $d['hod_email'] ?? $d['sp_hod_email'] ?? '';
                $is_assigned_or_completed = in_array($current_status_key, ['project_assigned', 'team_assigned', 'started', 'internship_started', 'active_intern', 'completed', 'internship_completed', 'certificate_issued'], true);
              ?>

              <?php if ($is_assigned_or_completed): ?>
                <div class="mt-5 border-t border-slate-100 pt-5 space-y-3">
                  <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Available Actions</p>
                  <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-xs">
                    <p class="font-bold text-emerald-750 flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                      Workflow Completed / Student Assigned to Project
                    </p>
                  </div>
                </div>

                <!-- Assigned Project Details -->
                <div class="mt-5 border-t border-slate-100 pt-5 space-y-3">
                  <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Assigned Project Details</p>
                  <?php if ($assigned_project_details): ?>
                    <div class="grid gap-3">
                      <?php
                        $proj_fields = [
                          'Project Title' => $assigned_project_details['internship_title'] ?? '',
                          'Project Type' => $assigned_project_details['project_type'] ?? '',
                          'Project Subtype' => $assigned_project_details['project_subtype'] ?? '',
                          'Technology Stack' => $assigned_project_details['tech_stack'] ?? '',
                          'Duration' => $assigned_project_details['internship_duration'] ?? '',
                          'Mode' => $assigned_project_details['internship_mode'] ?? '',
                          'Mentor Name' => $assigned_project_details['mentor_name'] ?? '',
                          'Team Name' => $assigned_project_details['team_name'] ?? '',
                          'Assigned Date' => !empty($assigned_project_details['team_created_at']) ? date('M d, Y', strtotime($assigned_project_details['team_created_at'])) : '',
                          'Project Status' => $assigned_project_details['team_status'] ?? '',
                        ];
                      ?>
                      <?php foreach ($proj_fields as $label => $value): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-xl">
                          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400"><?php echo $label; ?></span>
                          <span class="text-xs font-semibold text-slate-700 text-right max-w-[60%] truncate"><?php echo htmlspecialchars(!empty(trim($value)) ? $value : 'Not assigned'); ?></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                      <p class="font-bold text-amber-700 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">info</span>
                        Project assignment details pending coordinator sync.
                      </p>
                    </div>
                  <?php endif; ?>
                </div>
              <?php elseif (!$is_rejected_status): ?>
                <div class="mt-5 border-t border-slate-100 pt-5 space-y-3">
                  <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Available Actions</p>
                  
                  <?php
                    $can_approve = $documents_verified;
                  ?>

                  <?php if ($is_selected_status): ?>
                    <?php $confirmation_letter_sent = (!empty($d['confirmation_letter_path']) || !empty($d['confirmation_letter_sent_at']) || (!empty($d['confirmation_letter_sent']) && (int) $d['confirmation_letter_sent'] === 1)); ?>
                    <?php if ($confirmation_letter_sent): ?>
                      <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-xs mb-2">
                        <p class="font-bold text-emerald-800 flex items-center gap-1.5 text-sm">
                          <span class="material-symbols-outlined text-[18px]">check_circle</span>
                          Workflow Completed
                        </p>
                        <p class="mt-2 text-emerald-700 font-semibold">Confirmation Letter Sent âœ“</p>
                        <?php if (!empty($d['confirmation_letter_sent_at'])): ?>
                          <p class="mt-1 text-slate-500">Sent Date: <?php echo date('M d, Y, h:i A', strtotime($d['confirmation_letter_sent_at'])); ?></p>
                        <?php else: ?>
                          <p class="mt-1 text-slate-500">Sent Date: <?php echo date('M d, Y'); ?></p>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <button type="button" 
                              id="btn-send-conf-letter" 
                              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-base">mail</span>
                        Send Confirmation Letter
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($current_status_key === 'applied'): ?>
                    <?php if ($can_approve): ?>
                      <button type="button" onclick="if(confirm('Move this candidate to HR Review?')) performTransition('HR Review');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-base">rate_review</span>
                        Move to HR Review
                      </button>
                    <?php else: ?>
                      <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                        <p class="font-bold text-amber-700 flex items-center gap-1">
                          <span class="material-symbols-outlined text-[16px]">warning</span>
                          Aadhaar &amp; PAN Verification Required
                        </p>
                        <p class="mt-1 text-amber-600">Please verify Aadhaar and PAN documents before moving to HR Review.</p>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($current_status_key === 'hr_review'): ?>
                    <?php if ($can_approve): ?>
                      <button type="button" onclick="if(confirm('Are you sure you want to shortlist this candidate?')) performTransition('Shortlisted');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-base">star</span>
                        Shortlist Candidate
                      </button>
                    <?php else: ?>
                      <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                        <p class="font-bold text-amber-700 flex items-center gap-1">
                          <span class="material-symbols-outlined text-[16px]">warning</span>
                          Aadhaar & PAN Verification Required
                        </p>
                        <p class="mt-1 text-amber-600">Please verify Aadhaar and PAN documents to shortlist this candidate.</p>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($current_status_key === 'shortlisted'): ?>
                    <?php if ($can_approve): ?>
                      <button type="button" id="btn-trigger-exam" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-purple-700 transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-base">forward_to_inbox</span>
                        Send Exam Link
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if (in_array($current_status_key, ['exam_link_sent'])): ?>
                    <?php if ($can_approve): ?>
                      <?php if ($is_pursuing): ?>
                        <?php if (!empty($hod_email)): ?>
                          <a href="forward_to_hod.php?app_id=<?php echo $app_id; ?>" onclick="return confirm('Are you sure you want to send HOD approval email?');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer">
                            <span class="material-symbols-outlined text-base">forward</span>
                            Send HOD Approval
                          </a>
                        <?php else: ?>
                          <div class="rounded-2xl bg-red-50 border border-red-200 p-4 text-xs">
                            <p class="font-bold text-red-700 flex items-center gap-1">
                              <span class="material-symbols-outlined text-[16px]">error</span>
                              HOD email not available.
                            </p>
                            <p class="mt-1 text-red-650">HOD details must be updated in candidate profile or application to request HOD approval.</p>
                          </div>
                        <?php endif; ?>
                      <?php elseif ($is_passed_out): ?>
                        <button type="button" onclick="if(confirm('Are you sure you want to select this candidate?')) performTransition('Selected');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-all shadow-sm cursor-pointer">
                          <span class="material-symbols-outlined text-base">verified</span>
                          Select Candidate
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                        <p class="font-bold text-amber-700 flex items-center gap-1">
                          <span class="material-symbols-outlined text-[16px]">warning</span>
                          Verification Required
                        </p>
                        <p class="mt-1 text-amber-600">Please verify the candidate's Aadhaar and PAN documents or confirmation status to proceed.</p>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($is_hod_pending_status): ?>
                    <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                      <p class="font-bold text-amber-700 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">hourglass_empty</span>
                        Waiting for HOD Approval
                      </p>
                      <p class="mt-1 text-amber-600">HOD approval email has been sent<?php echo !empty($hod_email_val) ? ' to <strong>' . htmlspecialchars($hod_email_val) . '</strong>' : ''; ?>. Awaiting HOD decision.</p>
                    </div>
                  <?php endif; ?>

                  <?php if ($is_hod_approved_status): ?>
                    <?php if ($can_approve && $is_pursuing): ?>
                      <button type="button" onclick="if(confirm('Are you sure you want to select this candidate?')) performTransition('Selected');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-base">verified</span>
                        Select Candidate
                      </button>
                    <?php elseif (!$can_approve): ?>
                      <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                        <p class="font-bold text-amber-700 flex items-center gap-1">
                          <span class="material-symbols-outlined text-[16px]">warning</span>
                          Aadhaar & PAN Verification Required
                        </p>
                        <p class="mt-1 text-amber-600">Please verify the candidate's Aadhaar and PAN documents to proceed with selection.</p>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php
                    $hr_review_stages = ['applied', 'shortlisted', 'hr_review', 'exam_link_sent', 'hod_pending', 'hod_approved'];
                    $can_reject = in_array($current_status_key, $hr_review_stages, true) && !$is_selected_status && !$is_rejected_status && !$is_hod_rejected_status;
                  ?>
                  <?php if ($can_reject): ?>
                    <button type="button" 
                            id="btn-trigger-reject"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all border border-red-100 cursor-pointer mt-2">
                      <span class="material-symbols-outlined text-base">cancel</span>
                      Reject Candidate
                    </button>
                  <?php endif; ?>

                  <!-- Hidden Rejection Form -->
                  <div id="rejection-form-container" class="hidden border border-red-200 bg-red-50/50 rounded-2xl p-4 mt-3 space-y-3">
                    <p class="text-xs font-bold text-red-700">Specify Rejection Reason (Optional)</p>
                    <textarea id="rejection-reason" 
                              rows="2" 
                              placeholder="Reason for rejection..." 
                              class="w-full rounded-xl border border-red-200 p-2.5 text-xs text-slate-700 outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent transition-all"></textarea>
                    <div class="flex gap-2">
                      <button type="button" 
                              id="btn-confirm-reject"
                              class="flex-1 inline-flex items-center justify-center gap-1 rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                        Confirm Rejection
                      </button>
                      <button type="button" 
                              id="btn-cancel-reject"
                              class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
                        Cancel
                      </button>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Removed Corporate Interest Card -->

          <!-- HR Notes & Comments -->
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">HR Notes & Comments</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">Add a note</h2>
            
            <form id="note-form" class="mt-4 space-y-3">
              <input type="hidden" name="action" value="add_note">
              <textarea name="note_text" 
                        rows="3" 
                        placeholder="Write note/feedback about candidate..." 
                        class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all"
                        required></textarea>
              <button type="submit" 
                      class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition-all">
                <span class="material-symbols-outlined text-base">note_add</span>
                Save Note
              </button>
            </form>
            
            <div class="mt-6 border-t border-slate-100 pt-5">
              <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-4">Note History (<span id="notes-count-badge"><?php echo count($notes_rows); ?></span>)</p>
              <div id="notes-list-container">
                <?php if (empty($notes_rows)): ?>
                  <p class="text-xs text-slate-400 italic" id="no-notes-placeholder">No notes added yet for this applicant.</p>
                <?php else: ?>
                  <div class="space-y-4 max-h-[300px] overflow-y-auto pr-1">
                    <?php foreach ($notes_rows as $note): ?>
                      <div class="rounded-2xl bg-slate-50 border border-slate-100 p-3.5 text-xs">
                        <div class="flex items-center justify-between text-slate-400 font-medium mb-1">
                          <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($note['author_name']); ?></span>
                          <span><?php echo date('M d, Y H:i', strtotime($note['created_at'])); ?></span>
                        </div>
                        <p class="text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($note['note_text'])); ?></p>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>


        </aside>
      </div>
    </div>
  </main>


  <!-- Exam Compose Modal -->
  <div id="exam-compose-modal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="exam-modal-backdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-2 text-white">
            <span class="material-symbols-outlined">mail</span>
            <h3 class="text-lg font-bold">Send Exam Link</h3>
          </div>
          <button type="button" id="exam-modal-close" class="text-white/80 hover:text-white transition-colors">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form id="exam-compose-form" class="p-6 space-y-4">
          <input type="hidden" name="action" value="send_exam_link">
          <input type="hidden" name="selected_ids[]" value="<?php echo $app_id; ?>">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">To (Student Email)</label>
            <input type="text" name="to" value="<?php echo htmlspecialchars($email); ?>" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required readonly>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Subject</label>
            <input type="text" name="subject" value="Exam Link for Internship Selection" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Exam Link</label>
            <input type="url" name="exam_link" placeholder="https://exam-platform.com/test/123" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Message</label>
            <textarea name="message" rows="5" 
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>Dear <?php echo htmlspecialchars($full_name); ?>,

You have been shortlisted for the <?php echo htmlspecialchars($d['internship_title'] ?? 'internship'); ?> program.

Please click the link below to take your assessment:
{{EXAM_LINK}}

Best regards,
HR Team</textarea>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="submit" id="exam-send-btn"
                    class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-purple-700 transition-all shadow-sm">
              <span class="material-symbols-outlined text-base">send</span>
              Send Exam Link
            </button>
            <button type="button" id="exam-modal-cancel"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 transition-all">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center" id="toast-icon-container">
      <span class="material-symbols-outlined text-[20px]" id="toast-icon">check_circle</span>
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-wider" id="toast-title">Success</p>
      <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-message">Status updated successfully</p>
    </div>
  </div>

  <script>
    let aadhaarRevealed = false;
    const maskedAadhaar = '<?php echo htmlspecialchars(mask_aadhaar($raw_aadhaar)); ?>';
    const plainAadhaar = '<?php echo htmlspecialchars(decrypt_aadhaar($raw_aadhaar)); ?>';

    function toggleAadhaarReveal() {
      const display = document.getElementById('aadhaar-display-val');
      const btn = event.target;
      if (aadhaarRevealed) {
        display.textContent = maskedAadhaar;
        btn.textContent = 'Reveal';
      } else {
        display.textContent = plainAadhaar;
        btn.textContent = 'Hide';
      }
      aadhaarRevealed = !aadhaarRevealed;
    }

    async function setVerificationStatus(status) {
      if (!confirm(`Are you sure you want to set candidate verification status to "${status}"?`)) {
        return;
      }
      try {
        const formData = new FormData();
        formData.append('application_id', '<?php echo $app_id; ?>');
        formData.append('verification_status', status);
        
        const response = await fetch('update_verification_status.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          showToast('success', 'Verification Updated', result.message);
          setTimeout(() => location.reload(), 1200);
        } else {
          showToast('error', 'Update Failed', result.message);
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to update verification status.');
      }
    }

    const toast = document.getElementById('toast');
    function showToast(type, title, message) {
      const toastIcon = document.getElementById('toast-icon');
      const toastIconContainer = document.getElementById('toast-icon-container');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      
      if (type === 'success') {
        toast.classList.remove('border-red-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600');
        toastTitle.classList.add('text-green-600');
      } else {
        toast.classList.remove('border-green-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 500);
      }, 3000);
    }

    <?php if (isset($_GET['success'])): ?>
      <?php if ($_GET['success'] === 'approved'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('success', 'Student selected successfully.', 'The candidate has been successfully selected.');
      });
      <?php elseif ($_GET['success'] === 'forwarded_to_hod'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('success', 'Sent to HOD approval successfully.', 'The HOD approval request has been sent.');
      });
      <?php elseif ($_GET['success'] === 'rejected'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('success', 'Candidate Rejected', 'The candidate has been rejected.');
      });
      <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <?php if ($_GET['error'] === 'requires_hod'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('error', 'HOD Approval Required', 'Pursuing candidates must be approved by HOD first.');
      });
      <?php elseif ($_GET['error'] === 'unverified_docs'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('error', 'Documents Unverified', 'Both Aadhaar and PAN must be verified.');
      });
      <?php endif; ?>
    <?php endif; ?>

    // Add note submit handler
    const noteForm = document.getElementById('note-form');
    if (noteForm) {
      noteForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const textarea = this.querySelector('textarea[name="note_text"]');
        if (!textarea) return;
        const noteText = textarea.value.trim();
        if (noteText === '') {
          showToast('error', 'Error', 'Note text cannot be empty');
          return;
        }

        const formData = new FormData(this);
        try {
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', result.message);
            textarea.value = '';
            
            // Helper for HTML escaping
            const escapeHTML = (str) => {
              return String(str || '').replace(/[&<>'"]/g, 
                tag => ({
                  '&': '&amp;',
                  '<': '&lt;',
                  '>': '&gt;',
                  "'": '&#39;',
                  '"': '&quot;'
                }[tag] || tag)
              );
            };
            
            const nl2br = (str) => {
              return escapeHTML(str).replace(/\n/g, '<br>');
            };

            const container = document.getElementById('notes-list-container');
            const countBadge = document.getElementById('notes-count-badge');
            if (container && countBadge && result.notes) {
              countBadge.textContent = result.notes.length;
              if (result.notes.length === 0) {
                container.innerHTML = '<p class="text-xs text-slate-400 italic" id="no-notes-placeholder">No notes added yet for this applicant.</p>';
              } else {
                let html = '<div class="space-y-4 max-h-[300px] overflow-y-auto pr-1">';
                result.notes.forEach(note => {
                  html += `
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-3.5 text-xs">
                      <div class="flex items-center justify-between text-slate-400 font-medium mb-1">
                        <span class="font-semibold text-slate-700">${escapeHTML(note.author_name)}</span>
                        <span>${escapeHTML(note.created_at)}</span>
                      </div>
                      <p class="text-slate-600 leading-relaxed">${nl2br(note.note_text)}</p>
                    </div>
                  `;
                });
                html += '</div>';
                container.innerHTML = html;
              }
            }
          } else {
            showToast('error', 'Error', result.message);
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to save note');
        }
      });
    }

    async function performTransition(nextStatus, notes = 'Status transitioned from candidate details view.') {
      try {
        const formData = new FormData();
        formData.append('application_id', '<?php echo $app_id; ?>');
        formData.append('new_status', nextStatus);
        formData.append('notes', notes);
        
        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          showToast('success', 'Status Updated', result.message);
          setTimeout(() => location.reload(), 1200);
        } else {
          showToast('error', 'Transition Failed', result.message);
        }
      } catch (error) {
        showToast('error', 'Error', 'AJAX request failed.');
      }
    }

    // Send Confirmation Letter handler
    const btnSendConfLetter = document.getElementById('btn-send-conf-letter');
    if (btnSendConfLetter) {
      btnSendConfLetter.addEventListener('click', async function() {
        if (!confirm('Are you sure you want to generate and send the confirmation letter?')) {
          return;
        }
        btnSendConfLetter.disabled = true;
        btnSendConfLetter.textContent = 'Sending...';
        try {
          const formData = new FormData();
          formData.append('application_id', '<?php echo $app_id; ?>');
          const response = await fetch('send_confirmation_letter.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Confirmation Letter Sent', result.message);
            setTimeout(() => location.reload(), 1200);
          } else {
            showToast('error', 'Failed to Send', result.message);
            btnSendConfLetter.disabled = false;
            btnSendConfLetter.textContent = 'Send Confirmation Letter';
          }
        } catch (e) {
          showToast('error', 'Error', 'Failed to send request.');
          btnSendConfLetter.disabled = false;
          btnSendConfLetter.textContent = 'Send Confirmation Letter';
        }
      });
    }

    const isAadhaarVerified = <?php echo (($d['aadhaar_status'] ?? 'pending') === 'verified') ? 'true' : 'false'; ?>;
    const isPanVerified = <?php echo (($d['pan_status'] ?? 'pending') === 'verified') ? 'true' : 'false'; ?>;

    // Rejection UI toggles
    const btnTriggerReject = document.getElementById('btn-trigger-reject');
    const rejectionFormContainer = document.getElementById('rejection-form-container');
    const btnCancelReject = document.getElementById('btn-cancel-reject');
    const btnConfirmReject = document.getElementById('btn-confirm-reject');
    const rejectionReasonInput = document.getElementById('rejection-reason');

    const btnSendExamLink = document.getElementById('btn-trigger-exam');
    const workflowButtons = [btnSendConfLetter, btnSendExamLink];

    if (btnTriggerReject) {
      btnTriggerReject.addEventListener('click', () => {
        rejectionFormContainer.classList.remove('hidden');
        workflowButtons.forEach(btn => { if (btn && btn !== btnTriggerReject) btn.classList.add('hidden'); });
      });
    }

    if (btnCancelReject) {
      btnCancelReject.addEventListener('click', () => {
        rejectionFormContainer.classList.add('hidden');
        if (btnTriggerReject) btnTriggerReject.classList.remove('hidden');
        workflowButtons.forEach(btn => { if (btn && btn !== btnTriggerReject) btn.classList.remove('hidden'); });
        rejectionReasonInput.value = '';
      });
    }

    if (btnConfirmReject) {
      btnConfirmReject.addEventListener('click', async function() {
        const reason = rejectionReasonInput.value.trim() || 'Candidate rejected in HR Round';
        if (!confirm('Are you sure you want to REJECT this candidate?')) {
          return;
        }

        try {
          const formData = new FormData();
          formData.append('application_id', '<?php echo $app_id; ?>');
          formData.append('new_status', 'Rejected');
          formData.append('notes', reason);

          const response = await fetch('update_application_status.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Candidate Rejected', result.message);
            setTimeout(() => location.reload(), 1200);
          } else {
            showToast('error', 'Rejection Failed', result.message);
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to reject candidate');
        }
      });
    }
    // Exam Modal Logic
    const examTrigger = document.getElementById('btn-trigger-exam');
    const examModal = document.getElementById('exam-compose-modal');
    const examClose = document.getElementById('exam-modal-close');
    const examCancel = document.getElementById('exam-modal-cancel');
    const examBackdrop = document.getElementById('exam-modal-backdrop');
    const examForm = document.getElementById('exam-compose-form');
    
    if(examTrigger) {
      examTrigger.addEventListener('click', () => {
        examModal.classList.remove('hidden');
      });
    }
    
    const closeExamModal = () => examModal.classList.add('hidden');
    if(examClose) examClose.addEventListener('click', closeExamModal);
    if(examCancel) examCancel.addEventListener('click', closeExamModal);
    if(examBackdrop) examBackdrop.addEventListener('click', closeExamModal);

    if(examForm) {
      examForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('exam-send-btn');
        const origText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">refresh</span> Sending...';
        submitBtn.disabled = true;

        try {
          const formData = new FormData(examForm);
          const response = await fetch('send_exam_link.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', 'Exam Link Sent successfully!');
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Failed', result.message || 'Error sending exam link.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to send exam link');
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      });
    }
  </script>
<?php print_resume_not_found_js(); ?>
</body>
</html>

