<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crypto_helper.php';
require_hr_or_admin();
include 'db.php';
include 'status_utils.php';

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
if ($app_id <= 0) {
    header('Location: hr_applications.php');
    exit();
}

// PHP POST note handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    header('Content-Type: application/json');
    $note_text = isset($_POST['note_text']) ? trim($_POST['note_text']) : '';
    $user_id = current_user_id();
    
    if ($app_id <= 0 || empty($note_text)) {
        echo json_encode(['success' => false, 'message' => 'Note text cannot be empty']);
        exit();
    }
    
    // Get updater's name
    $name_stmt = $conn->prepare("SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1");
    $name_stmt->bind_param("i", $user_id);
    $name_stmt->execute();
    $name_res = $name_stmt->get_result();
    $name_row = $name_res->fetch_assoc();
    $author_name = $name_row ? $name_row['full_name'] : 'HR';
    
    $note_stmt = $conn->prepare("INSERT INTO hr_notes (application_id, user_id, author_name, note_text) VALUES (?, ?, ?, ?)");
    $note_stmt->bind_param("iiss", $app_id, $user_id, $author_name, $note_text);
    
    if ($note_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add note']);
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
            a.education_status,
            a.preferred_duration,
            a.preferred_domain,
            a.relevant_skills,
            a.reason_for_applying,
            a.department,
            a.graduation_year,
            a.year_of_study           AS app_year_of_study,
            a.college_name            AS app_college,
            a.full_name               AS app_full_name,
            a.email                   AS app_email,
            a.phone                   AS app_phone,
            a.resume_file             AS app_resume,
            COALESCE(i.title, a.internship_name) AS internship_title,
            COALESCE(i.duration, '')  AS internship_duration,
            COALESCE(i.mode, '')      AS internship_mode,
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
            $resume_url_select,
            sp.aadhaar_file,
            sp.pan_file,
            sp.aadhaar_number         AS sp_aadhaar,
            sp.pan_number,
            a.aadhaar_number          AS app_aadhaar,
            a.pan_number              AS app_pan,
            a.pan_file                AS app_pan_file
        FROM internship_applications a
        LEFT JOIN internships i       ON a.internship_id = i.id AND a.internship_id > 0
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        LEFT JOIN users u            ON a.user_id = u.id
        WHERE a.id = $app_id
        LIMIT 1";

$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    header('Location: hr_applications.php?error=not_found');
    exit();
}
$d = mysqli_fetch_assoc($result);

$raw_aadhaar  = $d['sp_aadhaar'] ?: $d['app_aadhaar'] ?: '';
$aadhaar_file = !empty($d['aadhaar_file']) ? 'uploads/' . $d['aadhaar_file'] : '';
$pan_number   = $d['pan_number'] ?: $d['app_pan'] ?: '';
$pan_file     = !empty($d['pan_file']) ? 'uploads/' . $d['pan_file'] : (!empty($d['app_pan_file']) ? 'uploads/' . $d['app_pan_file'] : '');

// Prefer student_profiles data first, then users, then application snapshot.
$full_name  = $d['sp_full_name']    ?: $d['user_full_name']    ?: $d['app_full_name']    ?: '—';
$email      = $d['sp_email']        ?: $d['user_email']        ?: $d['app_email']        ?: '—';
$phone      = $d['sp_phone']        ?: $d['user_phone']        ?: $d['app_phone']        ?: '—';
$college    = $d['sp_college']      ?: $d['app_college']      ?: '—';
$department = $d['department']      ?: '—';
$grad_year  = $d['graduation_year']  ?: '—';
$preferred_duration = $d['preferred_duration'] ?: '—';
$preferred_domain   = $d['preferred_domain']   ?: '—';
$relevant_skills    = $d['relevant_skills']    ?: $d['sp_skills']        ?: '—';
$reason_for_applying = $d['reason_for_applying'] ?: '—';
$internship_title   = $d['internship_title']   ?: '—';
$applied_date       = $d['applied_date'] ? date('M d, Y', strtotime($d['applied_date'])) : '—';
$verification       = $d['verification_status'] ?: 'Pending';
$current_status     = $d['status'] ?: '—';
$resume             = $d['sp_resume'] ?: $d['app_resume'] ?: '';
$resume_url         = !empty($d['sp_resume_url']) ? trim($d['sp_resume_url']) : '';

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
    $resume_label = $resume_link;
    $view_href = $resume_link;
    $download_href = $resume_link;
} else {
    $resume_safe        = $resume !== '' ? urlencode(basename($resume)) : '';
    $resume_ext         = $resume !== '' ? strtolower(pathinfo($resume, PATHINFO_EXTENSION)) : '';
    $has_resume         = $resume_safe !== '' && in_array($resume_ext, ['pdf', 'doc', 'docx'], true);
    $resume_label       = $resume !== '' ? basename($resume) : 'No resume uploaded';
    $view_href = "resume_serve.php?file=" . $resume_safe . "&mode=view";
    $download_href = "resume_serve.php?file=" . $resume_safe . "&mode=download";
}

// Status history timeline
$history_sql    = "SELECT * FROM application_status_history WHERE application_id = $app_id ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);
$history_rows   = [];
while ($row = mysqli_fetch_assoc($history_result)) {
    $history_rows[] = $row;
}

// Fetch HR notes
$notes_sql = "SELECT * FROM hr_notes WHERE application_id = $app_id ORDER BY created_at DESC";
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
$back = 'hr_applications.php';
if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'hr_applications.php') !== false) {
    $back = htmlspecialchars($_SERVER['HTTP_REFERER']);
}

$status_icons = [
    'Applied'        => 'send',
    'Test Completed' => 'quiz',
    'HR Round'       => 'manage_search',
    'HOD Approved'   => 'verified_user',
    'Selected'       => 'verified',
    'Rejected'       => 'cancel',
    'Pending'        => 'schedule',
];
$status_colors = [
    'Applied'        => 'bg-blue-100 text-blue-600',
    'Test Completed' => 'bg-amber-100 text-amber-600',
    'HR Round'       => 'bg-purple-100 text-purple-600',
    'HOD Approved'   => 'bg-indigo-100 text-indigo-600',
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
  <title>Applicant Detail — <?php echo htmlspecialchars($full_name); ?> | IMP</title>
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
      <a href="hr_dashboard.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <a href="hr_applications.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3"><span class="material-symbols-outlined">assignment</span><span>Applications</span></a>
      <a href="candidates.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">group</span><span>Candidates</span></a>
      <a href="student_logs.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">description</span><span>Student Logs</span></a>
      <a href="workflows.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">account_tree</span><span>Workflows</span></a>
      <a href="reports.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
    </nav>
    <div class="mt-auto border-t border-gray-200 pt-4 flex flex-col gap-1">
      <a href="#" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">help</span><span>Help Center</span></a>
      <a href="logout.php" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
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
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Current status</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold <?php echo getStatusBadgeClass($current_status); ?>">
              <span class="material-symbols-outlined text-base">info</span>
              <?php echo htmlspecialchars($current_status); ?>
            </div>
          </div>
          <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Verification</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold <?php echo getVerificationBadgeClass($verification); ?>">
              <span class="material-symbols-outlined text-base">verified</span>
              <?php echo htmlspecialchars($verification); ?>
            </div>
          </div>
          <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Traction context</p>
            <div class="mt-2 space-y-1">
              <p class="text-[11px] font-semibold text-slate-600">Mentor Rating: <span class="font-extrabold text-amber-500"><?php echo $avg_rating > 0 ? $avg_rating . ' / 5' : 'No rating'; ?></span></p>
              <p class="text-[11px] font-semibold text-slate-600">Shortlisted: <span class="font-extrabold text-slate-800"><?php echo $shortlisted_count; ?> <?php echo $shortlisted_count === 1 ? 'Company' : 'Companies'; ?></span></p>
              <p class="text-[11px] font-semibold text-slate-600">Contacts: <span class="font-extrabold text-slate-800"><?php echo $contacted_count; ?></span></p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
        <section class="space-y-6">
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
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Full name</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($full_name); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Email</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($email); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Phone</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($phone); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">College</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($college); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Department</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($department); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Graduation year</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($grad_year); ?></p>
              </div>
            </div>
          </div>

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
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Internship position</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($internship_title); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Preferred duration</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($preferred_duration); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Preferred domain</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($preferred_domain); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Relevant skills</p>
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo nl2br(htmlspecialchars($relevant_skills)); ?></p>
              </div>
            </div>
            <div class="mt-6 rounded-3xl bg-slate-50 p-4 border border-slate-200">
              <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Why this internship</p>
              <p class="mt-3 text-sm leading-6 text-slate-700"><?php echo nl2br(htmlspecialchars($reason_for_applying)); ?></p>
            </div>
          </div>

          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Resume actions</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Candidate resume</h2>
              </div>
              <?php if ($has_resume): ?>
                <div class="flex flex-wrap gap-2">
                  <a href="<?php echo $view_href; ?>" target="_blank" class="inline-flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-all">
                    <span class="material-symbols-outlined">visibility</span>
                    View resume
                  </a>
                  <?php if (!$is_remote): ?>
                  <a href="<?php echo $download_href; ?>" class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-all">
                    <span class="material-symbols-outlined">download</span>
                    Download resume
                  </a>
                  <?php endif; ?>
                </div>
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
                <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo $is_remote ? 'Remote URL' : ($resume_ext ? htmlspecialchars(strtoupper($resume_ext)) : '—'); ?></p>
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
                  <?php if (!empty($aadhaar_file)): ?>
                    <a href="<?php echo htmlspecialchars($aadhaar_file); ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-750 font-bold">
                      <span class="material-symbols-outlined text-[16px]">visibility</span> View Aadhaar File
                    </a>
                  <?php else: ?>
                    <p class="text-xs text-slate-400 italic">No document uploaded</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200 flex flex-col justify-between">
                <div>
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400">PAN Number</p>
                  <p class="mt-2 font-mono text-sm font-bold text-slate-800">
                    <?php echo htmlspecialchars($pan_number ? substr($pan_number, 0, 5) . '****' . substr($pan_number, -1) : '—'); ?>
                  </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200">
                  <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">PAN Document</p>
                  <?php if (!empty($pan_file)): ?>
                    <a href="<?php echo htmlspecialchars($pan_file); ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-750 font-bold">
                      <span class="material-symbols-outlined text-[16px]">visibility</span> View PAN File
                    </a>
                  <?php else: ?>
                    <p class="text-xs text-slate-400 italic">No document uploaded</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Verification Action Buttons for HR -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between flex-wrap gap-4">
              <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Aadhaar Validation Actions</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Toggle verification status for onboarding approval</p>
              </div>
              <div class="flex gap-2">
                <button type="button" onclick="setVerificationStatus('Verified')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all cursor-pointer">
                  <span class="material-symbols-outlined text-sm">check_circle</span> Verify Documents
                </button>
                <button type="button" onclick="setVerificationStatus('Rejected')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-xl border border-red-100 transition-all cursor-pointer">
                  <span class="material-symbols-outlined text-sm">cancel</span> Reject Verification
                </button>
              </div>
            </div>
          </div>

          <!-- Mentor Supervision & Evaluations Card -->
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Supervision</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Mentor Supervision & Evaluations</h2>
              </div>
              <div class="flex items-center gap-2 text-slate-500">
                <span class="material-symbols-outlined">rate_review</span>
                <span class="text-sm font-semibold">Evaluations (<?php echo count($mentor_feedbacks); ?>)</span>
              </div>
            </div>
            
            <div class="space-y-4">
              <!-- Active Assigned Mentor Info -->
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Assigned Mentor</p>
                <?php if ($assigned_mentor): ?>
                  <div class="mt-2 flex items-center justify-between">
                    <div>
                      <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($assigned_mentor['mentor_name']); ?></p>
                      <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($assigned_mentor['mentor_email']); ?></p>
                    </div>
                    <span class="text-xs text-slate-400 font-semibold bg-white border border-slate-200 rounded-lg px-2.5 py-1">Assigned: <?php echo date('M d, Y', strtotime($assigned_mentor['assigned_at'])); ?></span>
                  </div>
                <?php else: ?>
                  <p class="mt-2 text-sm font-semibold text-slate-500 italic">No active mentor assigned to this student.</p>
                <?php endif; ?>
              </div>

              <!-- Mentor Feedback History list -->
              <div class="space-y-3">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Evaluation History</p>
                <?php if (empty($mentor_feedbacks)): ?>
                  <p class="text-xs text-slate-400 italic">No mentor evaluations recorded for this candidate.</p>
                <?php else: ?>
                  <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($mentor_feedbacks as $fb): ?>
                      <?php 
                        $stars = str_repeat('★', $fb['rating']) . str_repeat('☆', 5 - $fb['rating']);
                        $statusColor = $fb['status'] === 'Approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                                      ($fb['status'] === 'Needs Update' ? 'bg-red-50 text-red-750 border-red-250' : 
                                      'bg-blue-50 text-blue-700 border-blue-200');
                      ?>
                      <div class="p-4 bg-slate-50/50 border border-slate-200 rounded-3xl space-y-2">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($fb['title'] ?: 'Log Review'); ?></span>
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider <?php echo $statusColor; ?>"><?php echo htmlspecialchars($fb['status'] ?: 'Reviewed'); ?></span>
                          </div>
                          <span class="text-[10px] text-slate-400 font-semibold"><?php echo date('M d, Y H:i', strtotime($fb['created_at'])); ?></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                          <span class="text-amber-500 text-xs font-bold font-mono tracking-tighter"><?php echo $stars; ?></span>
                          <span class="text-[10px] text-slate-400 font-semibold">by <?php echo htmlspecialchars($fb['mentor_name']); ?></span>
                        </div>
                        <p class="text-xs text-slate-600 italic break-words" style="word-break: break-word;">"<?php echo htmlspecialchars($fb['comments']); ?>"</p>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
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
                <p class="mt-2 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($current_status); ?></p>
              </div>
              <div class="rounded-3xl bg-slate-50 p-4 border border-slate-200">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Verification status</p>
                <p class="mt-2 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($verification); ?></p>
              </div>
              
              <?php if ($current_status !== 'Selected' && $current_status !== 'Rejected'): ?>
                <div class="mt-5 border-t border-slate-100 pt-5 space-y-3">
                  <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Available Actions</p>
                  <?php
                    $next_status = '';
                    $btn_label = '';
                    $education_status = $d['education_status'];
                    if ($current_status === 'Applied') {
                        $next_status = 'Test Completed';
                        $btn_label = 'Move to Test Completed';
                    } elseif ($current_status === 'Test Completed') {
                        $next_status = 'HR Round';
                        $btn_label = 'Move to HR Round';
                    } elseif ($current_status === 'HR Round') {
                        if ($education_status === 'Pursuing') {
                            $next_status = 'HOD Approved';
                            $btn_label = 'Recommend HOD Approval';
                        } else {
                            $next_status = 'Selected';
                            $btn_label = 'Confirm Selection';
                        }
                    } elseif ($current_status === 'HOD Approved') {
                        $next_status = 'Selected';
                        $btn_label = 'Confirm Selection';
                    }
                  ?>
                  <?php if ($next_status !== ''): ?>
                    <button type="button" 
                            id="btn-move-next" 
                            data-next-status="<?php echo htmlspecialchars($next_status); ?>"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-all shadow-sm">
                      <span class="material-symbols-outlined text-base">forward</span>
                      <?php echo htmlspecialchars($btn_label); ?>
                    </button>
                  <?php endif; ?>
                  
                  <button type="button" 
                          id="btn-trigger-reject"
                          class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all border border-red-100">
                    <span class="material-symbols-outlined text-base">cancel</span>
                    Reject Candidate
                  </button>

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

          <!-- Corporate Interest & Shortlists Card -->
          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 mb-4">
              <div>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Employer traction</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Corporate Interest</h2>
              </div>
              <span class="material-symbols-outlined text-slate-400">handshake</span>
            </div>

            <div class="space-y-5">
              <?php if (empty($shortlisted_companies) && empty($contacted_companies)): ?>
                <p class="text-xs text-slate-400 italic text-center py-6">No company activity yet.</p>
              <?php else: ?>
                <!-- Shortlisted By Companies -->
                <?php if (!empty($shortlisted_companies)): ?>
                  <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Shortlisted By</p>
                    <div class="space-y-2 max-h-32 overflow-y-auto pr-1">
                      <?php foreach ($shortlisted_companies as $sh): ?>
                        <div class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-150 rounded-xl text-xs">
                          <span class="font-extrabold text-slate-800 break-all"><?php echo htmlspecialchars($sh['company_name']); ?></span>
                          <span class="text-slate-400 font-semibold bg-white border border-slate-200 px-2 py-0.5 rounded shrink-0 ml-2"><?php echo date('M d, Y', strtotime($sh['created_at'])); ?></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Contacted / Invitations -->
                <?php if (!empty($contacted_companies)): ?>
                  <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Contact Invitations</p>
                    <div class="space-y-2.5 max-h-48 overflow-y-auto pr-1">
                      <?php foreach ($contacted_companies as $co): ?>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs space-y-1.5">
                          <div class="flex items-center justify-between text-[10px]">
                            <span class="font-extrabold text-slate-850 break-all"><?php echo htmlspecialchars($co['company_name']); ?></span>
                            <span class="text-slate-400 font-semibold shrink-0 ml-2"><?php echo date('M d, Y', strtotime($co['contacted_at'])); ?></span>
                          </div>
                          <p class="text-slate-600 italic break-words" style="word-break: break-word;">"<?php echo htmlspecialchars($co['message']); ?>"</p>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

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
              <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-4">Note History (<?php echo count($notes_rows); ?>)</p>
              <?php if (empty($notes_rows)): ?>
                <p class="text-xs text-slate-400 italic">No notes added yet for this applicant.</p>
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

          <div class="rounded-[2rem] bg-white border border-slate-200 p-6 shadow-sm relative overflow-hidden">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Status history</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">Timeline</h2>
            <div class="relative mt-8 pl-4">
              <div class="timeline-line"></div>
              <?php if (empty($history_rows)): ?>
                <p class="text-sm text-slate-500">No timeline events recorded yet.</p>
              <?php else: ?>
                <?php foreach ($history_rows as $index => $event): ?>
                  <?php $color = $status_colors[$event['new_status']] ?? 'bg-slate-100 text-slate-600'; ?>
                  <div class="timeline-item">
                    <div class="timeline-dot <?php echo $color; ?> text-white">
                      <span class="material-symbols-outlined text-[14px]"><?php echo htmlspecialchars($status_icons[$event['new_status']] ?? 'circle'); ?></span>
                    </div>
                    <div class="rounded-3xl bg-slate-50 border border-slate-200 p-4">
                      <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($event['new_status']); ?></p>
                        <span class="text-xs uppercase tracking-[0.24em] text-slate-400"><?php echo date('M d, Y', strtotime($event['created_at'])); ?></span>
                      </div>
                      <p class="mt-2 text-sm leading-6 text-slate-600">Updated by <?php echo htmlspecialchars($event['updated_by_role']); ?><?php echo $event['updated_by_name'] ? ' (' . htmlspecialchars($event['updated_by_name']) . ')' : ''; ?></p>
                      <?php if (!empty($event['notes'])): ?>
                        <p class="mt-3 text-sm text-slate-500"><?php echo nl2br(htmlspecialchars($event['notes'])); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </main>

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

    // Add note submit handler
    const noteForm = document.getElementById('note-form');
    if (noteForm) {
      noteForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        try {
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1200);
          } else {
            showToast('error', 'Error', result.message);
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to save note');
        }
      });
    }

    // Move next handler
    const btnMoveNext = document.getElementById('btn-move-next');
    if (btnMoveNext) {
      btnMoveNext.addEventListener('click', async function() {
        const nextStatus = this.dataset.nextStatus;
        if (!confirm(`Are you sure you want to transition candidate to "${nextStatus}"?`)) {
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('application_id', '<?php echo $app_id; ?>');
          formData.append('new_status', nextStatus);
          formData.append('notes', 'Status transitioned from candidate details view.');
          
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
      });
    }

    // Rejection UI toggles
    const btnTriggerReject = document.getElementById('btn-trigger-reject');
    const rejectionFormContainer = document.getElementById('rejection-form-container');
    const btnCancelReject = document.getElementById('btn-cancel-reject');
    const btnConfirmReject = document.getElementById('btn-confirm-reject');
    const rejectionReasonInput = document.getElementById('rejection-reason');

    if (btnTriggerReject) {
      btnTriggerReject.addEventListener('click', () => {
        rejectionFormContainer.classList.remove('hidden');
        btnTriggerReject.classList.add('hidden');
        if (btnMoveNext) btnMoveNext.classList.add('hidden');
      });
    }

    if (btnCancelReject) {
      btnCancelReject.addEventListener('click', () => {
        rejectionFormContainer.classList.add('hidden');
        if (btnTriggerReject) btnTriggerReject.classList.remove('hidden');
        if (btnMoveNext) btnMoveNext.classList.remove('hidden');
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
  </script>
</body>
</html>
