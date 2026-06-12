<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch student profile
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile_res = mysqli_query($conn, $profile_sql);
$profile     = mysqli_fetch_assoc($profile_res);
if (!$profile) { header("Location: student_profile_form.php"); exit(); }

// Fetch active/started internship
$intern_sql = "SELECT a.id as app_id, a.applied_date, a.education_status,
                      COALESCE(i.title, a.internship_name) as title,
                      COALESCE(i.duration, '3 Months') as duration,
                      COALESCE(i.mode, 'Remote') as mode,
                      ss.score as ss_score,
                      ss.total_questions as ss_total_questions
               FROM internship_applications a
               LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
               LEFT JOIN student_scores ss ON a.id = ss.application_id
               WHERE a.user_id = '$user_id' AND a.status = 'Started'
               LIMIT 1";
$intern_res  = mysqli_query($conn, $intern_sql);
$intern      = mysqli_fetch_assoc($intern_res);
$has_intern  = ($intern !== null);

$cert_score = 0;
$cert_total = 30;
if ($has_intern) {
    if (isset($intern['ss_score']) && $intern['ss_score'] !== null) {
        $cert_score = intval($intern['ss_score']);
        $cert_total = intval($intern['ss_total_questions'] ?: 30);
    } else {
        if ($p > 30) {
            $cert_score = intval(round(($p / 100) * 30));
        } else {
            $cert_score = $p;
        }
    }
}

// Fetch total logs
$logs_res  = mysqli_query($conn, "SELECT COUNT(*) as cnt, SUM(time_spent) as total_hours FROM daily_logs WHERE user_id='$user_id'");
$logs_row  = mysqli_fetch_assoc($logs_res);
$total_logs  = intval($logs_row['cnt'] ?? 0);
$total_hours = round(floatval($logs_row['total_hours'] ?? 0), 1);

// Compute dates
$start_date = $has_intern ? new DateTime($intern['applied_date']) : new DateTime();
$end_date   = clone $start_date;
$end_date->modify('+3 months');
$cert_id    = 'IMP-' . date('Y') . '-' . strtoupper(substr($profile['full_name'], 0, 2)) . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);

// Unread notifications
$unread_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM student_notifications WHERE user_id='$user_id' AND is_read=0");
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = intval($unread_row['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificate - <?php echo htmlspecialchars($profile['full_name']); ?> | IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24" rel="stylesheet">
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    .cert-font { font-family: 'Playfair Display', serif; }
    .cert-border {
      border: 12px solid transparent;
      border-image: linear-gradient(135deg, #1d4ed8, #6366f1, #1d4ed8) 1;
    }
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .pl-64 { padding-left: 0 !important; }
      aside { display: none !important; }
    }
  </style>
</head>
<body class="bg-[#f8f9fa] text-slate-800 antialiased">

  <!-- Sidebar -->
  <aside class="no-print fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col py-6 shadow-sm">
    <div class="px-6 mb-8">
      <a href="../index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
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
        <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
      </a>
      <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Student Portal</p>
    </div>
    <nav class="flex-1 space-y-1 px-4 overflow-y-auto">
      <a class="flex items-center gap-3 text-gray-600 rounded-lg px-4 py-3 hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#active-internship-card">
        <span class="material-symbols-outlined">badge</span>
        <span class="text-sm font-medium">My Internship</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_daily_log.php">
        <span class="material-symbols-outlined">edit_note</span>
        <span class="text-sm font-medium">Daily Logs</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#assigned-project-card">
        <span class="material-symbols-outlined">terminal</span>
        <span class="text-sm font-medium">Project</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#mentor-feedback-card">
        <span class="material-symbols-outlined">reviews</span>
        <span class="text-sm font-medium">Feedback</span>
      </a>
      <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium shadow-sm" href="student_certificate.php">
        <span class="material-symbols-outlined">workspace_premium</span>
        <span class="text-sm font-medium">Certificate</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
          <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
    </nav>
    <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1">
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
        <span class="material-symbols-outlined">help</span>
        <span class="text-sm font-medium">Help Center</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="../login.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="pl-64 flex flex-col min-h-screen">

    <!-- Topbar -->
    <header class="no-print w-full sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
      <div class="flex items-center gap-3">
        <a href="student_dashboard.php" class="p-2 hover:bg-gray-50 rounded-lg transition-colors">
          <span class="material-symbols-outlined text-slate-600">arrow_back</span>
        </a>
        <div>
          <h1 class="text-base font-bold text-slate-800">Certificate of Completion</h1>
          <p class="text-xs text-slate-500"><?php echo htmlspecialchars($profile['full_name']); ?> · <?php echo $has_intern ? htmlspecialchars($intern['title']) : 'Internship'; ?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a href="<?php echo htmlspecialchars("generate_certificate.php?user_id=" . $user_id . "&mode=download"); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg shadow-sm transition-all">
          <span class="material-symbols-outlined text-[18px]">download</span> Download PDF
        </a>
        <button onclick="shareLinkedIn()" class="flex items-center gap-2 px-4 py-2 bg-[#0077b5] hover:bg-[#006097] text-white text-sm font-bold rounded-lg shadow-sm transition-all">
          <span class="material-symbols-outlined text-[18px]">share</span> Share
        </button>
      </div>
    </header>

    <main class="flex-1 p-8">
      <div class="max-w-6xl mx-auto flex flex-col lg:flex-row gap-8">

        <!-- Certificate Preview -->
        <div class="flex-1" id="certificate-area">
          <div class="bg-white shadow-2xl rounded-2xl overflow-hidden cert-border relative">

            <!-- Top decorative band -->
            <div class="h-2 bg-gradient-to-r from-blue-600 via-indigo-500 to-blue-600"></div>

            <div class="px-16 py-14 flex flex-col items-center text-center relative">

              <!-- Decorative circles -->
              <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-60"></div>
              <div class="absolute bottom-0 right-0 w-56 h-56 bg-indigo-50 rounded-full translate-x-1/4 translate-y-1/4 opacity-60"></div>

              <!-- Logo -->
              <div class="relative z-10 mb-8 flex flex-col items-center">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg mb-3">
                  <span class="text-white font-black text-3xl">I</span>
                </div>
                <p class="text-blue-600 font-black text-xl tracking-tight">IMP</p>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Internship Management Platform</p>
              </div>

              <!-- Certificate label -->
              <div class="relative z-10 mb-6">
                <div class="inline-flex items-center gap-2 px-5 py-2 bg-blue-50 border border-blue-200 rounded-full">
                  <span class="material-symbols-outlined text-blue-600 text-[18px]">workspace_premium</span>
                  <span class="text-blue-700 font-extrabold text-xs uppercase tracking-widest">Certificate of Completion</span>
                </div>
              </div>

              <!-- Body text -->
              <div class="relative z-10 mb-8 space-y-3">
                <p class="text-slate-500 text-sm italic">This is to certify that</p>
                <h1 class="cert-font text-5xl font-bold text-slate-800 my-2"><?php echo htmlspecialchars($profile['full_name']); ?></h1>
                <p class="text-slate-500 text-sm max-w-xl mx-auto leading-relaxed">
                  has successfully completed the internship program as a
                  <span class="font-bold text-blue-700"><?php echo htmlspecialchars($has_intern ? $intern['title'] : 'Intern'); ?></span>
                  at the Internship Management Platform (IMP).
                </p>
              </div>

              <!-- Details grid -->
              <div class="relative z-10 w-full max-w-2xl border-t border-b border-slate-100 py-8 my-4 grid grid-cols-2 gap-8 text-left">
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Project</p>
                  <p class="font-bold text-slate-800 text-sm">
                    <?php
                      if ($has_intern) {
                          $t = strtolower($intern['title']);
                          if (strpos($t,'mobile')!==false||strpos($t,'android')!==false||strpos($t,'flutter')!==false)
                              echo 'Mobile App Development Project';
                          elseif (strpos($t,'frontend')!==false||strpos($t,'react')!==false||strpos($t,'web')!==false)
                              echo 'Responsive Web Application';
                          elseif (strpos($t,'data')!==false||strpos($t,'python')!==false)
                              echo 'Sales Data Analysis Dashboard';
                          elseif (strpos($t,'ui')!==false||strpos($t,'ux')!==false||strpos($t,'design')!==false)
                              echo 'Mobile App UI Redesign';
                          elseif (strpos($t,'backend')!==false||strpos($t,'node')!==false)
                              echo 'RESTful API Service';
                          else echo 'General Internship Project';
                      } else { echo 'Internship Project'; }
                    ?>
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Program Dates</p>
                  <p class="font-bold text-slate-800 text-sm">
                    <?php echo $start_date->format('M d, Y'); ?> – <?php echo $end_date->format('M d, Y'); ?>
                  </p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Duration</p>
                  <p class="font-bold text-slate-800 text-sm"><?php echo $has_intern ? htmlspecialchars($intern['duration']) : '3 Months'; ?></p>
                </div>
                <div class="text-right">
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Assessment Score</p>
                  <p class="font-bold text-slate-800 text-sm">N/A</p>
                </div>
              </div>

              <!-- Signatures -->
              <div class="relative z-10 w-full max-w-2xl flex items-end justify-between mt-8">
                <div class="text-center">
                  <div class="h-12 flex items-end justify-center mb-2">
                    <p class="cert-font text-2xl text-slate-600 italic border-b border-slate-400 pb-1 px-4">IMP Coordinator</p>
                  </div>
                  <p class="text-xs font-bold text-slate-700">Program Coordinator</p>
                  <p class="text-[10px] text-slate-400">IMP Platform</p>
                </div>

                <!-- Official Seal -->
                <div class="flex flex-col items-center">
                  <div class="w-20 h-20 rounded-full border-4 border-blue-200 flex items-center justify-center relative">
                    <div class="w-16 h-16 rounded-full border border-blue-300 flex items-center justify-center bg-blue-50">
                      <span class="material-symbols-outlined text-blue-600 text-3xl">verified</span>
                    </div>
                  </div>
                  <span class="text-[9px] font-extrabold text-blue-600 uppercase tracking-widest mt-1">Official Seal</span>
                </div>

                <div class="text-center">
                  <div class="h-12 flex items-end justify-center mb-2">
                    <p class="cert-font text-2xl text-slate-600 italic border-b border-slate-400 pb-1 px-4">IMP Director</p>
                  </div>
                  <p class="text-xs font-bold text-slate-700">Program Director</p>
                  <p class="text-[10px] text-slate-400">IMP Platform</p>
                </div>
              </div>

              <!-- Certificate ID -->
              <div class="relative z-10 mt-10">
                <p class="text-[10px] text-slate-400 font-mono uppercase tracking-widest">Certificate ID: <?php echo $cert_id; ?></p>
              </div>

            </div>

            <!-- Bottom band -->
            <div class="h-2 bg-gradient-to-r from-blue-600 via-indigo-500 to-blue-600"></div>
          </div>
        </div>

        <!-- Right Panel -->
        <div class="no-print w-full lg:w-80 flex flex-col gap-5">

          <!-- Status Card -->
          <?php if ($has_intern): ?>
          <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 flex items-start gap-3">
            <span class="material-symbols-outlined text-emerald-600 text-[28px] shrink-0">check_circle</span>
            <div>
              <p class="font-bold text-emerald-800 text-sm">Certificate Available</p>
              <p class="text-xs text-emerald-600 mt-0.5">Your internship is active. Certificate will be finalized upon completion.</p>
            </div>
          </div>
          <?php else: ?>
          <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-3">
            <span class="material-symbols-outlined text-amber-600 text-[28px] shrink-0">info</span>
            <div>
              <p class="font-bold text-amber-800 text-sm">Internship Not Started</p>
              <p class="text-xs text-amber-600 mt-0.5">Start your internship to generate your certificate.</p>
            </div>
          </div>
          <?php endif; ?>

          <!-- Actions -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
            <h3 class="font-bold text-slate-800 text-sm mb-1">Certificate Actions</h3>
            <a href="<?php echo htmlspecialchars("generate_certificate.php?user_id=" . $user_id . "&mode=download"); ?>" target="_blank" rel="noopener noreferrer" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md shadow-blue-500/20 transition-all">
              <span class="material-symbols-outlined text-[18px]">download</span> Download PDF
            </a>
            <button onclick="shareLinkedIn()" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-[#0077b5] hover:bg-[#006097] text-white font-bold text-sm rounded-xl transition-all">
              <span class="material-symbols-outlined text-[18px]">share</span> Share on LinkedIn
            </button>
            <button onclick="copyCertId()" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition-all">
              <span class="material-symbols-outlined text-[18px]">content_copy</span> Copy Certificate ID
            </button>
          </div>

          <!-- Stats -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
            <h3 class="font-bold text-slate-800 text-sm mb-1">Internship Summary</h3>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-slate-50 rounded-xl p-3 text-center">
                <p class="text-[10px] text-slate-400 font-bold uppercase">Duration</p>
                <p class="text-base font-black text-slate-800 mt-0.5"><?php echo $has_intern ? htmlspecialchars($intern['duration']) : '—'; ?></p>
              </div>
              <div class="bg-slate-50 rounded-xl p-3 text-center">
                <p class="text-[10px] text-slate-400 font-bold uppercase">Logs Filed</p>
                <p class="text-base font-black text-slate-800 mt-0.5"><?php echo $total_logs; ?></p>
              </div>
              <div class="bg-slate-50 rounded-xl p-3 text-center">
                <p class="text-[10px] text-slate-400 font-bold uppercase">Hours Logged</p>
                <p class="text-base font-black text-slate-800 mt-0.5"><?php echo $total_hours; ?>h</p>
              </div>
              <div class="bg-slate-50 rounded-xl p-3 text-center">
                <p class="text-[10px] text-slate-400 font-bold uppercase">Test Score</p>
                <p class="text-base font-black text-slate-800 mt-0.5">—</p>
              </div>
            </div>
          </div>

          <!-- Verification -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
            <h3 class="font-bold text-slate-800 text-sm">Verification</h3>
            <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-xl border border-blue-100">
              <span class="material-symbols-outlined text-blue-600 text-[22px]">verified_user</span>
              <div>
                <p class="text-xs font-bold text-blue-800">IMP Verified</p>
                <p class="text-[10px] text-blue-600 font-mono mt-0.5"><?php echo $cert_id; ?></p>
              </div>
            </div>
            <div class="space-y-2 text-xs">
              <div class="flex items-center justify-between py-2 border-b border-slate-50">
                <span class="text-slate-500 font-medium">Student Portal</span>
                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full text-[10px] font-bold">Active</span>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-slate-500 font-medium">Public Link</span>
                <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full text-[10px] font-bold">Private</span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  </div>

  <!-- Toast -->
  <div id="copy-toast" class="fixed bottom-6 right-6 z-50 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-xl text-sm font-semibold flex items-center gap-2 translate-y-20 opacity-0 transition-all duration-300">
    <span class="material-symbols-outlined text-[18px] text-emerald-400">check_circle</span>
    Certificate ID copied!
  </div>

  <script>
    function copyCertId() {
      const id = '<?php echo $cert_id; ?>';
      navigator.clipboard.writeText(id).then(() => {
        const toast = document.getElementById('copy-toast');
        toast.classList.remove('translate-y-20', 'opacity-0');
        setTimeout(() => toast.classList.add('translate-y-20', 'opacity-0'), 2500);
      });
    }

    function shareLinkedIn() {
      const name  = '<?php echo addslashes($profile['full_name']); ?>';
      const title = '<?php echo $has_intern ? addslashes($intern['title']) : 'Intern'; ?>';
      const text  = encodeURIComponent(`I have successfully completed my internship as ${title} at IMP (Internship Management Platform)! Certificate ID: <?php echo $cert_id; ?>`);
      window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(window.location.href)}&summary=${text}`, '_blank');
    }
  </script>

</body>
</html>
