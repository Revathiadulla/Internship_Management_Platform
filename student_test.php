<?php
// student_test.php - Test display and submission handler
// Handles test display with prepared statements, multiple attempt prevention, and scoring

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ensure_extended_schema.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail_helper.php';

// Helper function to show error page
function show_error_page($title, $message) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md border border-red-200">
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-red-600 text-4xl">error</span>
            </div>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2"><?php echo htmlspecialchars($title); ?></h2>
        <p class="text-gray-600 text-center mb-6"><?php echo htmlspecialchars($message); ?></p>
        <a href="student_applications.php" class="block text-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors">Back to Applications</a>
    </div>
</body>
</html>
    <?php
    exit;
}

// Verify user is logged in as student
if (!isset($_SESSION['user_id'])) {
    show_error_page('Unauthorized', 'Please log in to access the test.');
}
$current_student_id = intval($_SESSION['user_id']);

// Helper: fetch application joined with internship details and honor both student_id/user_id columns
function fetch_application_with_internship(mysqli $conn, int $app_id, int $student_id) {
    // Detect which columns exist
    $has_student_col = false;
    $has_user_col = false;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'student_id'");
    if ($res && mysqli_num_rows($res) > 0) $has_student_col = true;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'user_id'");
    if ($res && mysqli_num_rows($res) > 0) $has_user_col = true;

    if ($has_student_col && $has_user_col) {
        $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level, i.title FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND (a.student_id = ? OR a.user_id = ?) LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('iii', $app_id, $student_id, $student_id);
    } elseif ($has_student_col) {
        $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level, i.title FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.student_id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('ii', $app_id, $student_id);
    } else {
        // Fallback to user_id only
        $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level, i.title FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.user_id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('ii', $app_id, $student_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

// Handle GET request to display test
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['application_id'])) {
        show_error_page('Missing Parameter', 'The test link is incomplete. Please try accessing the test from your applications page.');
    }
    $app_id = intval($_GET['application_id']);
    if ($app_id <= 0) {
        show_error_page('Invalid Application ID', 'The application ID is invalid. Please try accessing the test from your applications page.');
    }

    // Temporary debug output (remove after verification)
    error_log("DEBUG: student_test.php application_id={$app_id} student_id={$current_student_id}");
    echo "<!-- DEBUG: application_id={$app_id} student_id={$current_student_id} -->\n";

    // Fetch internship details from application (honor student_id/user_id)
    $row = fetch_application_with_internship($conn, $app_id, $current_student_id);
    if (!$row) {
        show_error_page('Application Not Found', 'Application not found or you are not allowed to access this test.');
    }

    // Ensure numeric casting
    $internship_id = intval($row['internship_id']);
    $student_id = intval($row['student_id'] ?? $row['user_id'] ?? 0);
    $subtype = $row['project_subtype'];
    $difficulty = $row['difficulty_level'];

    // Check if student already completed this test (by application)
    $check_score_stmt = $conn->prepare('SELECT id FROM student_scores WHERE student_id = ? AND application_id = ? LIMIT 1');
    if (!$check_score_stmt) {
        die('Database error: ' . $conn->error);
    }
    $check_score_stmt->bind_param('ii', $student_id, $app_id);
    $check_score_stmt->execute();
    $score_check = $check_score_stmt->get_result();
    $check_score_stmt->close();

    if ($score_check->num_rows > 0) {
        // Student already completed this test
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Already Completed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md border border-amber-200">
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-amber-600 text-4xl">check_circle</span>
            </div>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Test Already Completed</h2>
        <p class="text-gray-600 text-center mb-6">You have already completed this test. Multiple attempts are not allowed.</p>
        <a href="student_applications.php" class="block text-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors">Back to Applications</a>
    </div>
</body>
</html>
        <?php
        exit;
    }

    // Find matching subtype test (latest) for this project_subtype
    $test_stmt = $conn->prepare('SELECT * FROM subtype_tests WHERE project_subtype = ? AND status = "active" ORDER BY id DESC LIMIT 1');
    if (!$test_stmt) {
        show_error_page('Database Error', 'A database error occurred. Please try again later.');
    }
    $test_stmt->bind_param('s', $subtype);
    $test_stmt->execute();
    $test_res = $test_stmt->get_result();
    $test_row = $test_res->fetch_assoc();
    $test_stmt->close();

    if (!$test_row) {
        // Log for debugging
        @error_log("[TEST NOT FOUND] Student: $student_id, App: $app_id, Subtype: $subtype");
        show_error_page('Test Not Available', 'Test for this project subtype has not been generated yet. Please contact your coordinator.');
    }
    $subtype_test_id = $test_row['id'];
    $duration_minutes = intval($test_row['duration_minutes'] ?? 30);

    // Retrieve test questions with all options
    $qstmt = $conn->prepare('SELECT * FROM subtype_test_questions WHERE subtype_test_id = ? ORDER BY id ASC');
    if (!$qstmt) {
        show_error_page('Database Error', 'A database error occurred. Please try again later.');
    }
    $qstmt->bind_param('i', $subtype_test_id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    $questions = [];
    while ($qrow = $qres->fetch_assoc()) {
        $questions[] = $qrow;
    }
    $qstmt->close();

    if (empty($questions)) {
        show_error_page('No Test Questions', 'The test exists but has no questions. Please contact support.');
    }

    // Render test form with Tailwind CSS
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Management Platform - Online Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .palette-button { min-width: 3rem; min-height: 3rem; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <header class="mb-6 rounded-3xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-5 md:flex md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-blue-100">Internship Management Platform - Online Test</p>
                    <h1 class="mt-3 text-3xl font-semibold">Online Entrance Exam</h1>
                </div>
                <div class="space-y-2 text-right">
                    <div class="text-sm text-blue-100">Time Left</div>
                    <div class="inline-flex min-w-[120px] items-center justify-center rounded-2xl bg-white/15 px-4 py-3 text-xl font-semibold shadow-sm ring-1 ring-white/20">
                        <span id="time-remaining">--:--</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 border-t border-slate-200 bg-slate-50 px-6 py-5 md:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Project Type</p>
                    <p class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($row['project_type'] ?? 'N/A'); ?></p>
                </div>
                <div class="space-y-1">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Project Subtype</p>
                    <p class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($row['project_subtype'] ?? 'N/A'); ?></p>
                </div>
                <div class="space-y-1">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Difficulty Level</p>
                    <p class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($row['difficulty_level'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </header>

        <form method="POST" action="" id="test-form">
            <input type="hidden" name="application_id" value="<?php echo htmlspecialchars(intval($app_id)); ?>">
            <input type="hidden" name="auto_submitted" id="auto_submitted" value="0">
            <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="space-y-5">
                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Question Navigation</h2>
                                <p class="text-xs text-slate-500">Jump to any question</p>
                            </div>
                            <span class="text-xs text-slate-500"><?php echo count($questions); ?> Qs</span>
                        </div>
                        <div id="palette" class="grid grid-cols-5 gap-2 sm:grid-cols-4"></div>
                    </div>
                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Legend</h3>
                        <div class="grid gap-2 text-sm text-slate-600">
                            <div class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded-full bg-gray-200 border border-gray-300"></span>Not Visited</div>
                            <div class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded-full bg-red-500"></span>Not Answered</div>
                            <div class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded-full bg-green-600"></span>Answered</div>
                            <div class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded-full bg-purple-600"></span>Marked for Review</div>
                            <div class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded-full bg-white border-2 border-blue-600 ring-2 ring-blue-600/30"></span>Current Question</div>
                        </div>
                    </div>
                </aside>

                <section class="space-y-5">
                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Question <span id="current-question-number"></span> of <?php echo count($questions); ?></p>
                                <h2 id="question-text" class="mt-3 text-2xl font-semibold text-slate-900"></h2>
                            </div>
                            <div id="review-badge" class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hidden">
                                <span class="material-symbols-outlined">flag</span>
                                Marked for Review
                            </div>
                        </div>
                        <div id="answers-list" class="mt-6 space-y-4"></div>
                    </div>

                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-3">
                            <button type="button" id="prev-btn" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Previous</button>
                            <button type="button" id="save-next-btn" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Save & Next</button>
                            <button type="button" id="mark-review-btn" class="inline-flex items-center justify-center rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 transition hover:bg-amber-100">Mark For Review</button>
                            <button type="button" id="clear-btn" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Clear Response</button>
                        </div>
                        <button type="button" id="submit-btn" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Submit Test</button>
                    </div>

                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900 mb-3">Test Instructions</p>
                        <ul class="list-disc list-inside space-y-2">
                            <li>Answers are saved automatically and restored if you refresh.</li>
                            <li>Use the navigation palette to jump directly to any question.</li>
                            <li>Auto-submit occurs when time reaches 00:00.</li>
                            <li>Review and clear responses before submitting.</li>
                        </ul>
                    </div>
                </section>
            </div>
            <div id="hidden-answers"></div>
        </form>
    </div>

    <script>
        const QUESTIONS = <?php echo json_encode($questions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const APP_ID = <?php echo intval($app_id); ?>;
        const TEST_ID = <?php echo intval($subtype_test_id); ?>;
        const DURATION_MINUTES = <?php echo intval($duration_minutes); ?>;
        const STORAGE_KEY = 'student_test_app_' + APP_ID + '_' + TEST_ID;

        const form = document.getElementById('test-form');
        const palette = document.getElementById('palette');
        const questionNumberEl = document.getElementById('current-question-number');
        const questionTextEl = document.getElementById('question-text');
        const answersListEl = document.getElementById('answers-list');
        const reviewBadge = document.getElementById('review-badge');
        const timeRemainingEl = document.getElementById('time-remaining');
        const hiddenAnswers = document.getElementById('hidden-answers');
        const prevBtn = document.getElementById('prev-btn');
        const saveNextBtn = document.getElementById('save-next-btn');
        const markReviewBtn = document.getElementById('mark-review-btn');
        const clearBtn = document.getElementById('clear-btn');
        const submitBtn = document.getElementById('submit-btn');
        const autoSubmittedInput = document.getElementById('auto_submitted');

        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        let currentIndex = Number.isInteger(stored.currentQuestion) ? stored.currentQuestion : 0;
        let answers = stored.answers || {};
        let reviewMap = stored.reviewMap || {};
        let visitedIndices = stored.visitedIndices || {};
        let expiry = stored.expiry || (Date.now() + DURATION_MINUTES * 60000);
        let warnings = stored.warnings || {10: false, 5: false, 1: false};

        // Mark starting index as visited
        visitedIndices[currentIndex] = true;

        if (currentIndex < 0 || currentIndex >= QUESTIONS.length) {
            currentIndex = 0;
        }
        if (expiry <= Date.now()) {
            expiry = Date.now() + DURATION_MINUTES * 60000;
        }

        function saveState() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                currentQuestion: currentIndex,
                answers: answers,
                reviewMap: reviewMap,
                visitedIndices: visitedIndices,
                expiry: expiry,
                warnings: warnings
            }));
        }

        function getQuestionStatus(index) {
            const qid = QUESTIONS[index].id;
            if (index === currentIndex) return 'current';
            if (reviewMap[qid]) return 'review';
            if (answers[qid]) return 'answered';
            if (visitedIndices[index]) return 'visited';
            return 'not-visited';
        }

        function buildPalette() {
            palette.innerHTML = '';
            QUESTIONS.forEach((question, idx) => {
                const status = getQuestionStatus(idx);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'palette-button relative rounded-2xl border text-xs font-semibold transition focus:outline-none w-10 h-10 flex items-center justify-center';
                btn.textContent = idx + 1;
                btn.addEventListener('click', () => {
                    currentIndex = idx;
                    renderQuestion();
                });

                if (idx === currentIndex) {
                    btn.className += ' ring-4 ring-blue-600 ring-offset-2 border-transparent';
                }

                if (reviewMap[question.id]) {
                    btn.classList.add('bg-purple-600', 'text-white', 'border-purple-700');
                } else if (answers[question.id]) {
                    btn.classList.add('bg-green-600', 'text-white', 'border-green-700');
                } else if (visitedIndices[idx]) {
                    btn.classList.add('bg-red-500', 'text-white', 'border-red-600');
                } else {
                    btn.classList.add('bg-gray-200', 'text-slate-700', 'border-gray-300');
                }
                palette.appendChild(btn);
            });
        }

        function renderQuestion() {
            // Mark current index as visited
            visitedIndices[currentIndex] = true;
            saveState();

            const question = QUESTIONS[currentIndex];
            questionNumberEl.textContent = currentIndex + 1;
            questionTextEl.textContent = question.question_text;
            reviewBadge.classList.toggle('hidden', !reviewMap[question.id]);

            answersListEl.innerHTML = '';
            ['A', 'B', 'C', 'D'].forEach(option => {
                const optionText = question['option_' + option.toLowerCase()];
                const label = document.createElement('label');
                label.className = 'flex items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-blue-300 hover:bg-blue-50 transition-colors cursor-pointer';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'answer_option';
                radio.value = option;
                radio.className = 'mt-1 h-4 w-4 text-blue-600 accent-blue-600 cursor-pointer';
                if (answers[question.id] === option) {
                    radio.checked = true;
                }
                radio.addEventListener('change', () => {
                    answers[question.id] = option;
                    saveState();
                    buildPalette();
                });
                const text = document.createElement('span');
                text.className = 'text-sm text-slate-800';
                text.innerHTML = '<span class="font-semibold text-blue-600 mr-2">Option ' + option + ':</span>' + escapeHtml(optionText);
                label.appendChild(radio);
                label.appendChild(text);
                answersListEl.appendChild(label);
            });
            buildPalette();
            saveState();
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function changeQuestion(delta) {
            const next = currentIndex + delta;
            if (next < 0 || next >= QUESTIONS.length) return;
            currentIndex = next;
            renderQuestion();
        }

        function saveCurrentAndNext() {
            if (currentIndex < QUESTIONS.length - 1) {
                currentIndex += 1;
            }
            renderQuestion();
        }

        function toggleReview() {
            const qid = QUESTIONS[currentIndex].id;
            if (reviewMap[qid]) {
                delete reviewMap[qid];
            } else {
                reviewMap[qid] = true;
            }
            saveState();
            // Auto advance
            if (currentIndex < QUESTIONS.length - 1) {
                currentIndex += 1;
            }
            renderQuestion();
        }

        function clearResponse() {
            const qid = QUESTIONS[currentIndex].id;
            delete answers[qid];
            saveState();
            renderQuestion();
        }

        function showWarning(message) {
            const banner = document.createElement('div');
            banner.className = 'fixed left-1/2 top-6 z-50 -translate-x-1/2 rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white shadow-lg';
            banner.textContent = message;
            document.body.appendChild(banner);
            setTimeout(() => banner.remove(), 7000);
        }

        function setHiddenAnswers() {
            hiddenAnswers.innerHTML = '';
            Object.entries(answers).forEach(([qid, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'answers[' + qid + ']';
                input.value = value;
                hiddenAnswers.appendChild(input);
            });
        }

        function submitTest(force = false) {
            if (!force) {
                const shouldSubmit = confirm('Submit test now? Once submitted, you cannot change your answers.');
                if (!shouldSubmit) return;
            }
            setHiddenAnswers();
            form.submit();
        }

        prevBtn.addEventListener('click', () => changeQuestion(-1));
        saveNextBtn.addEventListener('click', saveCurrentAndNext);
        markReviewBtn.addEventListener('click', toggleReview);
        clearBtn.addEventListener('click', clearResponse);
        submitBtn.addEventListener('click', () => submitTest(false));

        window.addEventListener('beforeunload', function (event) {
            event.preventDefault();
            event.returnValue = 'Your answers are saved automatically. Refreshing or leaving will end the exam.';
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'F5' || (event.ctrlKey && event.key.toLowerCase() === 'r')) {
                event.preventDefault();
                alert('Your progress is automatically saved. Please use the submit button to finish the exam.');
            }
        });

        let intervalId;
        function startTimer() {
            let secondsLeft = Math.max(0, Math.round((expiry - Date.now()) / 1000));

            function tick() {
                if (secondsLeft <= 0) {
                    timeRemainingEl.textContent = '00:00';
                    if (intervalId) clearInterval(intervalId);
                    autoSubmitted();
                    return;
                }
                const minutes = Math.floor(secondsLeft / 60);
                const seconds = secondsLeft % 60;
                timeRemainingEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

                if (minutes < 10 && !warnings[10]) {
                    warnings[10] = true;
                    saveState();
                    showWarning('10 minutes left');
                }
                if (minutes < 5 && !warnings[5]) {
                    warnings[5] = true;
                    saveState();
                    showWarning('5 minutes left');
                }
                if (minutes < 1 && !warnings[1]) {
                    warnings[1] = true;
                    saveState();
                    showWarning('1 minute left');
                }

                secondsLeft -= 1;
                if (secondsLeft % 5 === 0) {
                    saveState();
                }
            }
            tick();
            intervalId = setInterval(tick, 1000);
        }

        function autoSubmitted() {
            autoSubmittedInput.value = '1';
            setHiddenAnswers();
            form.submit();
        }

        renderQuestion();
        startTimer();
    </script>
</body>
</html>
    <?php
    exit;
}

// Handle POST request - Test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = intval($_POST['application_id'] ?? 0);
    $answers = $_POST['answers'] ?? [];

    if ($app_id <= 0 || !is_array($answers)) {
        show_error_page('Invalid Submission', 'The test submission is invalid. Please start the test again.');
    }

    // Fetch application and internship details (honor student_id/user_id)
    $row = fetch_application_with_internship($conn, $app_id, $current_student_id);
    if (!$row) {
        show_error_page('Application Not Found', 'Application not found or you are not allowed to submit this test.');
    }

    $internship_id = intval($row['internship_id']);
    $student_id = intval($row['student_id'] ?? $row['user_id'] ?? 0);
    $subtype = $row['project_subtype'];
    $difficulty = $row['difficulty_level'];

    // Verify student owns this application
    if ($student_id !== $current_student_id) {
        show_error_page('Unauthorized', 'You do not have permission to submit this test.');
    }

    // Prevent multiple attempts: Check if score already exists
    $check_score_stmt = $conn->prepare('SELECT id FROM student_scores WHERE student_id = ? AND application_id = ? LIMIT 1');
    if (!$check_score_stmt) {
        die('Database error: ' . $conn->error);
    }
    $check_score_stmt->bind_param('ii', $student_id, $app_id);
    $check_score_stmt->execute();
    $score_check = $check_score_stmt->get_result();
    $check_score_stmt->close();

    if ($score_check->num_rows > 0) {
        show_error_page('Test Already Completed', 'You have already completed this test. Multiple attempts are not allowed.');
    }

    // Friendly fallback if subtype missing
    if (empty($subtype)) {
        show_error_page('Project Subtype Missing', 'Project subtype is missing for this internship. Please contact your coordinator.');
    }

    // Get subtype_test_id (latest)
    $test_stmt = $conn->prepare('SELECT id FROM subtype_tests WHERE project_subtype = ? AND status = "active" ORDER BY id DESC LIMIT 1');
    if (!$test_stmt) {
        show_error_page('Database Error', 'A database error occurred. Please try again later.');
    }
    $test_stmt->bind_param('s', $subtype);
    $test_stmt->execute();
    $test_res = $test_stmt->get_result();
    $test_row = $test_res->fetch_assoc();
    $test_stmt->close();

    if (!$test_row) {
        show_error_page('Test Not Available', 'Test for this project subtype has not been generated yet. Please contact your coordinator.');
    }
    $subtype_test_id = $test_row['id'];

    // Fetch correct answers
    $questions = [];
    $correct_map = [];
    $qstmt = $conn->prepare('SELECT id, correct_option FROM subtype_test_questions WHERE subtype_test_id = ?');
    if (!$qstmt) {
        die('Database error: ' . $conn->error);
    }
    $qstmt->bind_param('i', $subtype_test_id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    while ($qrow = $qres->fetch_assoc()) {
        $questions[] = $qrow;
        $correct_map[$qrow['id']] = $qrow['correct_option'];
    }
    $qstmt->close();

    // Calculate score
    $score = 0;
    $total_questions = count($questions);

    // Keep track of evaluated question IDs to avoid adding marks multiple times for the same question
    $evaluated_qids = [];

    foreach ($answers as $qid => $selected_answer) {
        $qid = intval($qid);
        
        // Skip if already evaluated (rule 3: do not add marks multiple times)
        if (in_array($qid, $evaluated_qids)) {
            continue;
        }
        
        if (isset($correct_map[$qid])) {
            $correct_answer = $correct_map[$qid];
            // Rule 2: Score should increase only when answer is correct
            if ($selected_answer === $correct_answer) {
                $score++;
            }
            $evaluated_qids[] = $qid;
        }
    }

    // Rule 6: Add validation
    if ($score > $total_questions) {
        $score = $total_questions;
    }

    // Rule 4: Calculate percentage
    $percentage = $total_questions > 0 ? ($score / $total_questions) * 100 : 0;
    $percentage_decimal = round($percentage, 2);
    
    // For safety, ensure percentage doesn't exceed 100%
    if ($percentage_decimal > 100.00) {
        $percentage_decimal = 100.00;
    }

    // Update application status and test scores
    $percentage_int = intval(round($percentage_decimal));
    $new_status = ($percentage_int >= 60) ? 'HR Review' : 'Rejected';
    $upd = $conn->prepare("UPDATE internship_applications SET status = ?, test_score = ?, test_completed_at = NOW(), test_status = 'Completed', test_submitted_date = NOW() WHERE id = ?");
    if (!$upd) {
        die('Database error: ' . $conn->error);
    }
    $upd->bind_param('sii', $new_status, $percentage_int, $app_id);
    $upd->execute();
    $upd->close();

    // Save to student_scores with application_id included
    $ins_score = $conn->prepare("INSERT INTO student_scores (student_id, internship_id, application_id, test_id, score, total_questions, percentage, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$ins_score) {
        die('Database error: ' . $conn->error);
    }
    $ins_score->bind_param('iiiiiid', $student_id, $internship_id, $app_id, $subtype_test_id, $score, $total_questions, $percentage_decimal);
    $ins_score->execute();
    $ins_score->close();

    // Notify coordinators
    $coord_res = mysqli_query($conn, "SELECT id FROM users WHERE LOWER(role) = 'coordinator'");
    if ($coord_res && mysqli_num_rows($coord_res) > 0) {
        $c_title = 'Test Completed';
        $c_msg = "A student has completed a test with score $score/$total_questions ($percentage_decimal%).";
        $c_type = 'success';
        $link = "coordinator_internships.php?view=" . $internship_id;
        $coord_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'coordinator', ?, ?, ?, ?)");
        if ($coord_stmt) {
            while ($c_row = mysqli_fetch_assoc($coord_res)) {
                $c_id = intval($c_row['id']);
                $coord_stmt->bind_param('issss', $c_id, $c_title, $c_msg, $c_type, $link);
                $coord_stmt->execute();
            }
            $coord_stmt->close();
        }
    }

    // Ensure title is defined before use, prefer internship title from the joined internship row
    $title = !empty($row['title']) ? $row['title'] : 'Internship Test';
    $title = $title ?? 'Internship Test';

    // Notify the student via email
    $student_subject = "Assessment Completed for $title";
    $student_message = "Dear " . htmlspecialchars($_SESSION['full_name'] ?? 'Student') . ",\n\nYour assessment for \"$title\" has been successfully submitted with a score of $score/$total_questions ($percentage_decimal%).\n\nThank you for completing the test. Your internship application will be reviewed shortly.\n\nBest regards,\nIMP Team";
    sendStudentNotification($current_student_id, $_SESSION['full_name'] ?? 'Student', $student_subject, $student_message, [
        'event' => 'Test Completed',
        'score' => "$score/$total_questions",
        'percentage' => "$percentage_decimal%",
        'action_url' => 'http://localhost/IMP/student_dashboard.php',
        'action_label' => 'View Application Status'
    ]);

    // Render result page instead of redirect (Rule 7)
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl p-8 max-w-md w-full border border-slate-100 transition-all hover:shadow-2xl">
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center border border-emerald-100">
                <span class="material-symbols-outlined text-emerald-600 text-5xl">task_alt</span>
            </div>
        </div>
        
        <h2 class="text-3xl font-extrabold text-slate-900 text-center mb-1">Test Completed</h2>
        <p class="text-slate-500 text-center text-sm mb-6">Your responses have been successfully submitted and graded.</p>
        
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4 mb-6">
            <div class="flex justify-between items-center py-2 border-b border-slate-200/50">
                <span class="text-sm font-semibold text-slate-600 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                    Correct Answers
                </span>
                <span class="text-sm font-bold text-slate-800" id="result-correct"><?php echo $score; ?></span>
            </div>
            
            <div class="flex justify-between items-center py-2 border-b border-slate-200/50">
                <span class="text-sm font-semibold text-slate-600 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-500 text-lg">cancel</span>
                    Wrong Answers
                </span>
                <span class="text-sm font-bold text-slate-800" id="result-wrong"><?php echo ($total_questions - $score); ?></span>
            </div>
            
            <div class="flex justify-between items-center py-2 border-b border-slate-200/50">
                <span class="text-sm font-semibold text-slate-600 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500 text-lg">score</span>
                    Score
                </span>
                <span class="text-sm font-bold text-slate-800" id="result-score"><?php echo $score; ?>/<?php echo $total_questions; ?></span>
            </div>
            
            <div class="flex justify-between items-center py-2">
                <span class="text-sm font-semibold text-slate-600 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-500 text-lg">percent</span>
                    Percentage
                </span>
                <span class="text-sm font-bold text-slate-800" id="result-percentage"><?php echo round($percentage_decimal); ?>%</span>
            </div>
        </div>
        
        <a href="student_applications.php" class="block text-center px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all">Back to Applications</a>
    </div>
</body>
</html>
    <?php
    exit;
}
?>

