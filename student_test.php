<?php
session_start();
include "db.php";
include_once __DIR__ . "/includes/mail_helper.php";
include "questions_pool.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$app_id  = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

// Fetch application and internship details
$app_sql = "SELECT a.*, COALESCE(i.title, a.internship_name) AS title,
                   COALESCE(i.duration,'') AS duration, COALESCE(i.mode,'') AS mode,
                   COALESCE(i.skills,'') AS internship_skills
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.id = '$app_id' AND a.user_id = '$user_id' LIMIT 1";
$app_res = mysqli_query($conn, $app_sql);
$app     = mysqli_fetch_assoc($app_res);

if (!$app) {
    header("Location: student_applications.php?error=" . urlencode("Invalid application."));
    exit();
}

if ($app['test_status'] === 'Completed') {
    header("Location: student_applications.php?msg=" . urlencode("Test already completed."));
    exit();
}

// Fetch student profile details
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile     = mysqli_fetch_assoc(mysqli_query($conn, $profile_sql));

// Check test deadline (48 hours from application date)
$applied_time = strtotime($app['applied_date']);
$deadline_time = $applied_time + (48 * 60 * 60); // 48 hours
$current_time = time();
$is_deadline_expired = ($current_time > $deadline_time);

if ($is_deadline_expired) {
    header("Location: student_applications.php?error=" . urlencode("The 48-hour test window has expired. Please contact HR."));
    exit();
}

// ── Domain detection ────────────────────────────────────────────────────────
$title  = strtolower($app['title']);
$iskills = strtolower($app['internship_skills'] ?? '');
$sskills = strtolower($profile['skills'] ?? '');
$combined = $title . ' ' . $iskills . ' ' . $sskills;

$domain = "General Aptitude";
if (preg_match('/mobile|android|ios|flutter|react native|kotlin|swift|app dev/i', $combined)) {
    $domain = "Mobile Development"; // Will fall back to General Aptitude since we don't have separate pool
} elseif (preg_match('/frontend|react|vue|angular|html|css|javascript|tailwind|web dev/i', $combined)) {
    $domain = "Frontend Development";
} elseif (preg_match('/data science|pandas|numpy|matplotlib|machine learning|ml|ai|tensorflow|sklearn/i', $combined)) {
    $domain = "Data Science";
} elseif (preg_match('/ui|ux|figma|wireframe|prototype|design|user experience|user interface/i', $combined)) {
    $domain = "UI/UX Design";
} elseif (preg_match('/backend|node|php|django|flask|laravel|api|rest|mysql|postgresql|mongodb|database/i', $combined)) {
    $domain = "Backend Development";
} elseif (preg_match('/devops|docker|kubernetes|ci\/cd|aws|cloud|linux|jenkins|terraform/i', $combined)) {
    $domain = "DevOps & Cloud"; // Fallback to General Aptitude
} elseif (preg_match('/marketing|seo|social media|content|digital marketing|campaign/i', $combined)) {
    $domain = "Digital Marketing"; // Fallback to General Aptitude
}

// Ensure the detected domain is one of our 5 main question banks
$supported_domains = ["Frontend Development", "Backend Development", "Data Science", "UI/UX Design", "General Aptitude"];
if (!in_array($domain, $supported_domains)) {
    $domain = "General Aptitude";
}

$questions = isset($all_questions[$domain]) ? $all_questions[$domain] : $all_questions["General Aptitude"];

// ── Handle POST Submission ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers_json_str = isset($_POST['answers_json']) ? $_POST['answers_json'] : '[]';
    $answers_array = json_decode($answers_json_str, true);
    
    if (!is_array($answers_array)) {
        $answers_array = array_fill(0, 30, -1);
    }
    
    $score = 0;
    // Verify answers and count correct ones
    for ($i = 0; $i < 30; $i++) {
        $selected_opt = isset($answers_array[$i]) ? intval($answers_array[$i]) : -1;
        $correct_opt = intval($questions[$i]['correct']);
        if ($selected_opt === $correct_opt) {
            $score++;
        }
    }
    
    $escaped_answers = mysqli_real_escape_string($conn, json_encode($answers_array));
    
    // Update application details
    $update_sql = "UPDATE internship_applications 
                   SET test_status = 'Completed', 
                       test_score = '$score', 
                       test_answers = '$escaped_answers',
                       test_submitted_date = NOW(),
                       status = 'Test Completed'
                   WHERE id = '$app_id' AND user_id = '$user_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        // Record in status history
        $student_name = mysqli_real_escape_string($conn, $profile['full_name'] ?? 'Student');
        $old_status = mysqli_real_escape_string($conn, $app['status']);
        $notes = "Assessment test completed with score: $score/30";
        
        $history_sql = "INSERT INTO application_status_history 
                        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) 
                        VALUES ('$app_id', '$old_status', 'Test Completed', 'Student', '$student_name', '$notes')";
        mysqli_query($conn, $history_sql);
        
        // Send email notification for assessment test completion
        $test_subject = "IMP Assessment Test Completed: " . $app['title'];
        $test_message = "Dear " . ($profile['full_name'] ?? 'Student') . ",\n\nYou have successfully completed the online skills assessment test for the \"" . $app['title'] . "\" internship.\n\nHere is your test summary:\n- Assessment Domain: **$domain**\n- Final Score: **$score / 30** (" . round(($score / 30) * 100, 1) . "%)\n\nYour application status has been updated to **Test Completed**. The hiring managers and coordinators will review your results shortly.\n\nThank you!";
        sendEmailNotification($user_id, $test_subject, $test_message, [
            'event' => 'Assessment Completion',
            'internship_position' => $app['title'],
            'assessment_domain' => $domain,
            'achieved_score' => "$score / 30",
            'percentage' => round(($score / 30) * 100, 1) . "%",
            'action_url' => 'http://localhost/IMP/student_applications.php',
            'action_label' => 'View Test Breakdown'
        ]);

        header("Location: student_applications.php?msg=" . urlencode("Assessment Completed! Score: $score/30"));
        exit();
    } else {
        $error = "Failed to submit assessment: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Assessment: <?php echo htmlspecialchars($app['title']); ?> - IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
  
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            "on-surface-variant": "#434655",
            "secondary-fixed-dim": "#c0c7d0",
            "on-primary-container": "#eeefff",
            "surface-container-low": "#f3f4f5",
            "primary-fixed": "#dbe1ff",
            "outline": "#737686",
            "secondary": "#585f67",
            "tertiary-container": "#bc4800",
            "error": "#ba1a1a",
            "primary-container": "#2563eb",
            "surface": "#f8f9fa",
            "on-primary-fixed-variant": "#003ea8",
            "on-primary": "#ffffff",
            "on-secondary-container": "#5e656d",
            "on-background": "#191c1d",
            "surface-container": "#edeeef",
            "on-tertiary": "#ffffff",
            "on-secondary": "#ffffff",
            "secondary-fixed": "#dce3ec",
            "on-tertiary-container": "#ffede6",
            "on-tertiary-fixed-variant": "#7d2d00",
            "outline-variant": "#c3c6d7",
            "inverse-primary": "#b4c5ff",
            "secondary-container": "#dce3ec",
            "surface-dim": "#d9dadb",
            "on-surface": "#191c1d",
            "on-secondary-fixed-variant": "#40484f",
            "inverse-surface": "#2e3132",
            "on-error": "#ffffff",
            "background": "#f8f9fa",
            "primary": "#004ac6",
            "tertiary": "#943700",
            "tertiary-fixed": "#ffdbcd",
            "surface-variant": "#e1e3e4",
            "surface-container-highest": "#e1e3e4",
            "on-tertiary-fixed": "#360f00",
            "surface-container-lowest": "#ffffff",
            "error-container": "#ffdad6",
            "on-primary-fixed": "#00174b",
            "surface-tint": "#0053db",
            "primary-fixed-dim": "#b4c5ff",
            "on-error-container": "#93000a",
            "surface-bright": "#f8f9fa",
            "inverse-on-surface": "#f0f1f2",
            "on-secondary-fixed": "#151c23",
            "surface-container-high": "#e7e8e9",
            "tertiary-fixed-dim": "#ffb596"
          },
          "borderRadius": {
            "DEFAULT": "0.25rem",
            "lg": "0.5rem",
            "xl": "0.75rem",
            "full": "9999px"
          },
          "spacing": {
            "xl": "32px",
            "lg": "24px",
            "container-margin": "40px",
            "md": "16px",
            "sm": "8px",
            "xs": "4px",
            "gutter": "20px",
            "unit": "4px"
          },
          "fontFamily": {
            "body-lg": ["Inter"],
            "label-md": ["Inter"],
            "body-md": ["Inter"],
            "h1": ["Inter"],
            "label-sm": ["Inter"],
            "h3": ["Inter"],
            "h2": ["Inter"]
          },
          "fontSize": {
            "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
            "label-md": ["14px", {"lineHeight": "20px", "fontWeight": "500"}],
            "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
            "h1": ["30px", {"lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
            "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
            "h3": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
            "h2": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}]
          }
        },
      },
    }
  </script>
  
  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body {
      background-color: #f8f9fa;
      color: #191c1d;
    }
  </style>
</head>
<body class="font-body-md overflow-hidden select-none">

  <!-- TopNavBar Implementation -->
  <nav class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3">
    <div class="flex items-center gap-md">
      <a href="student_applications.php" class="text-xl font-bold text-blue-600 hover:opacity-80 transition-opacity cursor-pointer">IMP</a>
      <div class="h-6 w-px bg-outline-variant mx-sm"></div>
      <span class="font-label-md text-on-surface-variant font-bold">Skills Assessment: <?php echo htmlspecialchars($domain); ?></span>
    </div>
    
    <div class="flex items-center gap-lg">
      <!-- Timer Component (45 minutes standard) -->
      <div id="timer-box" class="flex items-center gap-sm bg-red-50 text-red-700 border border-red-200 px-4 py-2 rounded-lg transition-colors">
        <span class="material-symbols-outlined text-lg">timer</span>
        <span id="countdown-display" class="font-mono font-bold text-lg">45:00</span>
      </div>
      <div class="flex items-center gap-sm">
        <div class="w-8 h-8 rounded-full overflow-hidden border border-outline-variant">
          <img alt="User profile" class="w-full h-full object-cover" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name'] ?? 'Student'); ?>&background=0D8ABC&color=fff">
        </div>
      </div>
    </div>
  </nav>

  <div class="flex h-[calc(100vh-64px)]">
    <!-- SideNavBar as Question Navigator -->
    <aside class="w-72 bg-gray-50 border-r border-gray-200 flex flex-col h-full py-6 shrink-0">
      <div class="px-6 mb-lg">
        <h2 class="font-label-sm uppercase tracking-wider text-on-surface-variant mb-xs font-bold">Progress Overview</h2>
        <div class="flex items-center justify-between text-xs font-label-md">
          <span id="answered-count-text" class="font-semibold text-slate-600">0 of 30 Answered</span>
          <span id="progress-percent" class="text-blue-600 font-bold">0%</span>
        </div>
        <div class="w-full bg-slate-200 h-1.5 rounded-full mt-xs overflow-hidden">
          <div id="progress-bar-fill" class="bg-blue-600 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
      </div>
      
      <!-- Grid of 1 to 30 Questions -->
      <div class="px-4 overflow-y-auto flex-grow">
        <div class="grid grid-cols-5 gap-2" id="question-nav-grid">
          <!-- JS will inject 30 navigation boxes here -->
        </div>
      </div>
      
      <div class="mt-auto px-6 pt-lg border-t border-gray-200">
        <div class="space-y-sm mb-lg">
          <div class="flex items-center gap-sm text-xs font-label-md text-on-surface-variant">
            <div class="w-3 h-3 rounded bg-blue-600"></div>
            <span class="font-medium">Current</span>
          </div>
          <div class="flex items-center gap-sm text-xs font-label-md text-on-surface-variant">
            <div class="w-3 h-3 rounded bg-blue-100 border border-blue-400"></div>
            <span class="font-medium">Answered</span>
          </div>
          <div class="flex items-center gap-sm text-xs font-label-md text-on-surface-variant">
            <div class="w-3 h-3 rounded bg-amber-500"></div>
            <span class="font-medium">Flagged for Review</span>
          </div>
        </div>
        <button onclick="confirmSubmitTest()" class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-lg">check_circle</span>
          Submit Test
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-grow bg-surface p-xl overflow-y-auto">
      <div class="max-w-3xl mx-auto">
        <!-- Question Header -->
        <div class="flex items-center justify-between mb-lg">
          <div class="space-y-xs">
            <span id="question-header-num" class="text-blue-600 font-bold text-xs uppercase tracking-widest">Question 1 of 30</span>
            <h1 class="font-bold text-2xl text-slate-800 tracking-tight" id="question-header-domain"><?php echo htmlspecialchars($app['title']); ?></h1>
          </div>
          <button onclick="toggleFlagCurrentQuestion()" id="flag-button" class="flex items-center gap-xs text-slate-600 font-bold text-xs border border-slate-200 px-4 py-2.5 rounded-lg bg-white shadow-sm hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined text-lg" id="flag-icon">flag</span>
            <span id="flag-btn-text">Flag Question</span>
          </button>
        </div>
        
        <!-- Question Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 mb-6">
          <p class="text-slate-800 text-lg font-medium leading-relaxed mb-8" id="question-text">
            Loading question...
          </p>
          <div class="space-y-4" id="options-container">
            <!-- 4 Multiple Choice Options injected by JS -->
          </div>
        </div>
        
        <!-- Navigation Controls -->
        <div class="flex items-center justify-between">
          <button onclick="prevQuestion()" id="prev-btn" class="flex items-center gap-xs px-5 py-3 text-slate-700 font-bold text-sm bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined">arrow_back</span>
            Previous Question
          </button>
          <div class="flex items-center gap-md">
            <button onclick="clearSelection()" id="clear-btn" class="px-5 py-3 bg-white border border-red-200 text-red-600 font-bold text-sm rounded-lg hover:bg-red-50 transition-colors">
              Clear Selection
            </button>
            <button onclick="nextQuestion()" id="next-btn" class="flex items-center gap-xs px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
              Next Question
              <span class="material-symbols-outlined">arrow_forward</span>
            </button>
          </div>
        </div>
        
        <!-- Informational box -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-slate-100/60 p-5 rounded-xl border border-slate-200/50 flex items-start gap-4">
            <span class="material-symbols-outlined text-blue-600">info</span>
            <div>
              <h4 class="font-bold text-slate-800 text-sm mb-1">Exam Protocol</h4>
              <p class="text-xs text-slate-500 leading-relaxed">Ensure a stable connection. Do not close or refresh this tab. Your answers will be submitted automatically when the timer expires.</p>
            </div>
          </div>
          <div class="bg-slate-100/60 p-5 rounded-xl border border-slate-200/50 flex items-start gap-4">
            <span class="material-symbols-outlined text-amber-500">lightbulb</span>
            <div>
              <h4 class="font-bold text-slate-800 text-sm mb-1">Time Allocation</h4>
              <p class="text-xs text-slate-500 leading-relaxed">You have 45 minutes to answer all 30 questions. Feel free to skip and return to flagged items at any time.</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Submit form -->
  <form id="submit-test-form" method="POST" action="">
    <input type="hidden" name="answers_json" id="answers_json_input" value="[]">
  </form>

  <!-- JS State & Core Logic -->
  <script>
    // Questions from PHP questions pool
    const questions = <?php echo json_encode($questions); ?>;
    
    // Core state
    let currentIdx = 0;
    const answers = new Array(30).fill(-1);
    const flagged = new Array(30).fill(false);
    
    // Timer details
    let timeRemaining = 45 * 60; // 45 minutes in seconds
    const timerInterval = setInterval(updateTimer, 1000);
    
    // Initialize Navigation Grid & Display
    document.addEventListener('DOMContentLoaded', () => {
        initNavGrid();
        loadQuestion(0);
        updateProgress();
    });
    
    function initNavGrid() {
        const grid = document.getElementById('question-nav-grid');
        grid.innerHTML = '';
        for (let i = 0; i < 30; i++) {
            const btn = document.createElement('div');
            btn.id = `nav-box-${i}`;
            btn.className = `w-10 h-10 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 font-bold relative cursor-pointer hover:bg-slate-50 transition-all select-none text-sm`;
            btn.textContent = i + 1;
            btn.onclick = () => loadQuestion(i);
            grid.appendChild(btn);
        }
    }
    
    function loadQuestion(idx) {
        // Un-highlight previous active item in grid
        const oldBox = document.getElementById(`nav-box-${currentIdx}`);
        if (oldBox) {
            updateBoxStyle(currentIdx);
        }
        
        currentIdx = idx;
        
        // Highlight active item in grid
        const newBox = document.getElementById(`nav-box-${currentIdx}`);
        if (newBox) {
            newBox.className = `w-10 h-10 flex items-center justify-center rounded-lg border-2 border-blue-600 bg-blue-50 text-blue-700 font-bold relative cursor-pointer shadow-sm select-none text-sm`;
        }
        
        // Update elements
        document.getElementById('question-header-num').textContent = `Question ${currentIdx + 1} of 30`;
        const q = questions[currentIdx];
        document.getElementById('question-text').textContent = q.q;
        
        // Load options
        const optContainer = document.getElementById('options-container');
        optContainer.innerHTML = '';
        
        const optionLetters = ['A', 'B', 'C', 'D'];
        q.options.forEach((opt, oIdx) => {
            const isSelected = (answers[currentIdx] === oIdx);
            
            const optDiv = document.createElement('div');
            optDiv.className = `group cursor-pointer flex items-center p-4 rounded-xl border-2 transition-all duration-150 ${
                isSelected 
                ? 'border-blue-600 bg-blue-50/50 shadow-sm' 
                : 'border-slate-200 hover:border-blue-300 hover:bg-slate-50'
            }`;
            optDiv.onclick = () => selectOption(oIdx);
            
            optDiv.innerHTML = `
                <div class="w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold mr-4 shrink-0 transition-colors ${
                    isSelected 
                    ? 'border-blue-600 bg-blue-600 text-white' 
                    : 'border-slate-300 text-slate-500 group-hover:border-blue-500 group-hover:text-blue-600 bg-white'
                }">${optionLetters[oIdx]}</div>
                <span class="text-slate-700 font-medium flex-grow text-sm md:text-base">${escapeHtml(opt)}</span>
                <div class="w-6 h-6 rounded-full border-2 shrink-0 flex items-center justify-center ${
                    isSelected 
                    ? 'border-blue-600 bg-blue-600 text-white' 
                    : 'border-slate-300'
                }">
                    ${isSelected ? '<span class="material-symbols-outlined text-sm font-extrabold">check</span>' : ''}
                </div>
            `;
            
            optContainer.appendChild(optDiv);
        });
        
        // Flag state
        const flagButton = document.getElementById('flag-button');
        const flagIcon = document.getElementById('flag-icon');
        const flagBtnText = document.getElementById('flag-btn-text');
        
        if (flagged[currentIdx]) {
            flagButton.className = "flex items-center gap-xs text-white font-bold text-xs border border-amber-600 px-4 py-2.5 rounded-lg bg-amber-500 shadow-sm transition-colors hover:bg-amber-600";
            flagIcon.textContent = "flag";
            flagIcon.style.fontVariationSettings = "'FILL' 1";
            flagBtnText.textContent = "Flagged";
        } else {
            flagButton.className = "flex items-center gap-xs text-slate-600 font-bold text-xs border border-slate-200 px-4 py-2.5 rounded-lg bg-white shadow-sm hover:bg-slate-50 transition-colors";
            flagIcon.textContent = "flag";
            flagIcon.style.fontVariationSettings = "'FILL' 0";
            flagBtnText.textContent = "Flag Question";
        }
        
        // Prev/Next buttons
        document.getElementById('prev-btn').disabled = (currentIdx === 0);
        
        const nextBtn = document.getElementById('next-btn');
        if (currentIdx === 29) {
            nextBtn.innerHTML = `Finish Test <span class="material-symbols-outlined">done_all</span>`;
            nextBtn.className = "flex items-center gap-xs px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-lg shadow-sm hover:shadow transition-colors";
            nextBtn.onclick = confirmSubmitTest;
        } else {
            nextBtn.innerHTML = `Next Question <span class="material-symbols-outlined">arrow_forward</span>`;
            nextBtn.className = "flex items-center gap-xs px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-lg shadow-sm hover:shadow transition-colors";
            nextBtn.onclick = nextQuestion;
        }
    }
    
    function selectOption(oIdx) {
        answers[currentIdx] = oIdx;
        loadQuestion(currentIdx);
        updateProgress();
    }
    
    function clearSelection() {
        answers[currentIdx] = -1;
        loadQuestion(currentIdx);
        updateProgress();
    }
    
    function prevQuestion() {
        if (currentIdx > 0) {
            loadQuestion(currentIdx - 1);
        }
    }
    
    function nextQuestion() {
        if (currentIdx < 29) {
            loadQuestion(currentIdx + 1);
        }
    }
    
    function toggleFlagCurrentQuestion() {
        flagged[currentIdx] = !flagged[currentIdx];
        loadQuestion(currentIdx);
    }
    
    function updateBoxStyle(idx) {
        const box = document.getElementById(`nav-box-${idx}`);
        if (!box) return;
        
        if (flagged[idx]) {
            box.className = `w-10 h-10 flex items-center justify-center rounded-lg bg-amber-500 text-white border border-amber-600 font-bold relative cursor-pointer select-none text-sm`;
            // Add a small flag symbol inside
            box.innerHTML = `${idx + 1}<span class="absolute -top-1.5 -right-1 text-[10px] text-amber-800">🚩</span>`;
        } else if (answers[idx] !== -1) {
            box.className = `w-10 h-10 flex items-center justify-center rounded-lg bg-blue-50 text-blue-700 border border-blue-400 font-semibold relative cursor-pointer select-none text-sm`;
            box.innerHTML = idx + 1;
        } else {
            box.className = `w-10 h-10 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 font-medium relative cursor-pointer hover:bg-slate-50 transition-all select-none text-sm`;
            box.innerHTML = idx + 1;
        }
    }
    
    function updateProgress() {
        let count = 0;
        for (let i = 0; i < 30; i++) {
            if (answers[i] !== -1) count++;
        }
        
        document.getElementById('answered-count-text').textContent = `${count} of 30 Answered`;
        const percentage = Math.round((count / 30) * 100);
        document.getElementById('progress-percent').textContent = `${percentage}%`;
        document.getElementById('progress-bar-fill').style.width = `${percentage}%`;
        
        // Update all box styles to keep them in sync
        for (let i = 0; i < 30; i++) {
            if (i !== currentIdx) {
                updateBoxStyle(i);
            }
        }
    }
    
    // Timer ticking
    function updateTimer() {
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert("Time has expired! Submitting your answers automatically.");
            submitTest();
            return;
        }
        
        timeRemaining--;
        const mins = Math.floor(timeRemaining / 60);
        const secs = timeRemaining % 60;
        
        const display = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        document.getElementById('countdown-display').textContent = display;
        
        // Visual warning when time is critical (< 5 mins)
        if (timeRemaining < 5 * 60) {
            const box = document.getElementById('timer-box');
            box.className = "flex items-center gap-sm bg-red-600 text-white px-4 py-2 rounded-lg transition-colors animate-pulse";
        }
    }
    
    function confirmSubmitTest() {
        let unansweredCount = 0;
        for (let i = 0; i < 30; i++) {
            if (answers[i] === -1) unansweredCount++;
        }
        
        let confirmMsg = "Are you sure you want to submit your assessment?";
        if (unansweredCount > 0) {
            confirmMsg += `\n\nYou have ${unansweredCount} unanswered questions. If you submit now, they will be marked as incorrect.`;
        }
        
        if (confirm(confirmMsg)) {
            submitTest();
        }
    }
    
    function submitTest() {
        clearInterval(timerInterval);
        document.getElementById('answers_json_input').value = JSON.stringify(answers);
        document.getElementById('submit-test-form').submit();
    }
    
    // Helper to escape HTML characters
    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
  </script>
</body>
</html>
