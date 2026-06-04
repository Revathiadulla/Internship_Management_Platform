<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$success_msg = "";
$error_msg = "";

// TEMPORARY DEBUG: Check if form is being submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($debug_shown)) {
        error_log("[POST_RECEIVED] action=" . ($_POST['action'] ?? 'NONE'));
        $debug_shown = true;
    }
}

$notif_unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . intval($_SESSION['user_id']) . " AND role = 'coordinator' AND is_read = 0");
$notif_unread_row = mysqli_fetch_assoc($notif_unread_res);
$unread_count = $notif_unread_row['count'] ?? 0;

// AJAX Handler for fetching questions
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $test_id = intval($_GET['test_id'] ?? 0);
    if ($_GET['ajax_action'] === 'get_questions') {
        $stmt = $conn->prepare("SELECT * FROM subtype_test_questions WHERE subtype_test_id = ?");
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $questions = [];
        while ($row = $res->fetch_assoc()) {
            $questions[] = [
                'id' => $row['id'],
                'text' => $row['question_text'],
                'a' => $row['option_a'],
                'b' => $row['option_b'],
                'c' => $row['option_c'],
                'd' => $row['option_d'],
                'correct' => $row['correct_option']
            ];
        }
        $stmt->close();
        echo json_encode($questions);
        exit;
    }
}

// ── Action: Delete Test ──
if (isset($_GET['delete_test_id'])) {
    $test_id = intval($_GET['delete_test_id']);
    try {
        // Delete from subtype_test_questions
        $del_q = $conn->prepare("DELETE FROM subtype_test_questions WHERE subtype_test_id = ?");
        $del_q->bind_param("i", $test_id);
        $del_q->execute();
        $del_q->close();
        
        // Delete from subtype_tests
        $del_t = $conn->prepare("DELETE FROM subtype_tests WHERE id = ?");
        $del_t->bind_param("i", $test_id);
        $del_t->execute();
        $del_t->close();
        
        header("Location: coordinator_generate_test.php?success=" . urlencode("Test and related questions deleted successfully!"));
        exit;
    } catch (Throwable $e) {
        $error_msg = "Failed to delete test: " . $e->getMessage();
    }
}

// Check success query param
if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

$project_types = [];
$project_type_stmt = $conn->prepare("SELECT id, type_name FROM project_types WHERE status = 'Active' ORDER BY type_name ASC");
if ($project_type_stmt) {
    $project_type_stmt->execute();
    $type_result = $project_type_stmt->get_result();
    while ($type_row = mysqli_fetch_assoc($type_result)) {
        $project_types[] = $type_row;
    }
    $project_type_stmt->close();
}

$initial_subtypes = [];
$initial_type_id = 0;
$selected_type_name = $_SESSION['preview_project_type'] ?? '';
if (!empty($selected_type_name)) {
    $type_id_stmt = $conn->prepare("SELECT id FROM project_types WHERE type_name = ? AND status = 'Active' LIMIT 1");
    if ($type_id_stmt) {
        $type_id_stmt->bind_param('s', $selected_type_name);
        $type_id_stmt->execute();
        $type_id_result = $type_id_stmt->get_result();
        if ($type_id_result && $type_id_row = mysqli_fetch_assoc($type_id_result)) {
            $initial_type_id = intval($type_id_row['id']);
        }
        $type_id_stmt->close();
    }
}
if ($initial_type_id === 0 && !empty($project_types)) {
    $initial_type_id = intval($project_types[0]['id']);
}
if ($initial_type_id > 0) {
    $subtype_stmt = $conn->prepare("SELECT subtype_name FROM project_subtypes WHERE project_type_id = ? AND status = 'Active' ORDER BY subtype_name ASC");
    if ($subtype_stmt) {
        $subtype_stmt->bind_param('i', $initial_type_id);
        $subtype_stmt->execute();
        $subtype_result = $subtype_stmt->get_result();
        while ($subtype_row = mysqli_fetch_assoc($subtype_result)) {
            $initial_subtypes[] = $subtype_row;
        }
        $subtype_stmt->close();
    }
}

// ── Action: Edit Test Questions (Submit from Edit Modal) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_test_questions') {
    $test_id = intval($_POST['edit_test_id'] ?? 0);
    $questions_post = $_POST['questions'] ?? [];
    
    try {
        // Delete existing questions for this test_id
        $del = $conn->prepare("DELETE FROM subtype_test_questions WHERE subtype_test_id = ?");
        $del->bind_param("i", $test_id);
        $del->execute();
        $del->close();
        
        // And insert the new/updated ones
        $ins_q = $conn->prepare("INSERT INTO subtype_test_questions (subtype_test_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $saved_count = 0;
        foreach ($questions_post as $q) {
            $q_text = trim($q['text'] ?? '');
            $q_a = trim($q['a'] ?? '');
            $q_b = trim($q['b'] ?? '');
            $q_c = trim($q['c'] ?? '');
            $q_d = trim($q['d'] ?? '');
            $q_corr = trim($q['correct'] ?? '');
            
            if (empty($q_text) || empty($q_a) || empty($q_b) || empty($q_c) || empty($q_d) || empty($q_corr)) {
                continue; // Skip invalid or incomplete questions
            }
            
            $ins_q->bind_param("issssss", $test_id, $q_text, $q_a, $q_b, $q_c, $q_d, $q_corr);
            $ins_q->execute();
            $saved_count++;
        }
        $ins_q->close();
        
        // Update num_questions in subtype_tests table
        $upd = $conn->prepare("UPDATE subtype_tests SET num_questions = ? WHERE id = ?");
        $upd->bind_param("ii", $saved_count, $test_id);
        $upd->execute();
        $upd->close();
        
        $success_msg = "Test questions updated successfully! Total questions: $saved_count.";
    } catch (Throwable $e) {
        $error_msg = "Failed to update test questions: " . $e->getMessage();
    }
}

// Auto-migration: Create required test tables and ensure all columns exist
try {
    // 1. Create subtype_tests table if not exists
    $create_tests_table = "CREATE TABLE IF NOT EXISTS subtype_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_type VARCHAR(100) NULL,
        project_subtype VARCHAR(100) NULL,
        skills TEXT NULL,
        difficulty_level VARCHAR(50) NULL,
        num_questions INT NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 30,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!mysqli_query($conn, $create_tests_table)) {
        throw new Exception(mysqli_error($conn));
    }

    // 2. Ensure all columns exist in subtype_tests (in case table was created with older schema)
    $tests_cols = [
        'project_type' => 'VARCHAR(100) NULL',
        'project_subtype' => 'VARCHAR(100) NULL',
        'skills' => 'TEXT NULL',
        'difficulty_level' => 'VARCHAR(50) NULL',
        'num_questions' => 'INT NOT NULL',
        'duration_minutes' => 'INT NOT NULL DEFAULT 30',
        'status' => "VARCHAR(20) DEFAULT 'active'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];

    foreach ($tests_cols as $col => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM subtype_tests LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            if (!mysqli_query($conn, "ALTER TABLE subtype_tests ADD COLUMN $col $definition")) {
                throw new Exception(mysqli_error($conn));
            }
        }
    }

    // 3. Create subtype_test_questions table if not exists
    $create_questions_table = "CREATE TABLE IF NOT EXISTS subtype_test_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subtype_test_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option VARCHAR(5) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!mysqli_query($conn, $create_questions_table)) {
        throw new Exception(mysqli_error($conn));
    }

    // 4. Ensure all columns exist in subtype_test_questions (in case table was created with older schema)
    $questions_cols = [
        'subtype_test_id' => 'INT NOT NULL',
        'question_text' => 'TEXT NULL',
        'option_a' => 'VARCHAR(255) NULL',
        'option_b' => 'VARCHAR(255) NULL',
        'option_c' => 'VARCHAR(255) NULL',
        'option_d' => 'VARCHAR(255) NULL',
        'correct_option' => 'VARCHAR(5) NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];

    foreach ($questions_cols as $col => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            if (!mysqli_query($conn, "ALTER TABLE subtype_test_questions ADD COLUMN $col $definition")) {
                throw new Exception(mysqli_error($conn));
            }
        }
    }

    // Ensure correct_option column has type VARCHAR(5)
    $check_type = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'correct_option'");
    if ($check_type && mysqli_num_rows($check_type) > 0) {
        $col_info = mysqli_fetch_assoc($check_type);
        if (strpos(strtolower($col_info['Type']), 'varchar(5)') === false) {
            if (!mysqli_query($conn, "ALTER TABLE subtype_test_questions MODIFY COLUMN correct_option VARCHAR(5) NOT NULL")) {
                throw new Exception(mysqli_error($conn));
            }
        }
    }
} catch (Throwable $e) {
    $error_msg = "Database Migration Error: Could not verify or create required test tables. " . $e->getMessage();
}

// Predefined realistic questions dictionary for popular skills
$predefined_questions = [
    'html' => [
        [
            'text' => 'What does HTML stand for?',
            'a' => 'Hyper Text Markup Language',
            'b' => 'Hyperlinks and Text Markup Language',
            'c' => 'Home Tool Markup Language',
            'd' => 'Hyper Tool Markup Language',
            'correct' => 'A'
        ],
        [
            'text' => 'Who is making the Web standards?',
            'a' => 'Google',
            'b' => 'The World Wide Web Consortium (W3C)',
            'c' => 'Mozilla',
            'd' => 'Microsoft',
            'correct' => 'B'
        ],
        [
            'text' => 'Choose the correct HTML element for the largest heading:',
            'a' => '<h6>',
            'b' => '<heading>',
            'c' => '<h1>',
            'd' => '<head>',
            'correct' => 'C'
        ],
        [
            'text' => 'What is the correct HTML element for inserting a line break?',
            'a' => '<break>',
            'b' => '<br>',
            'c' => '<lb>',
            'd' => '<next>',
            'correct' => 'B'
        ],
        [
            'text' => 'What is the correct HTML for adding a background color?',
            'a' => '<body style="background-color:yellow;">',
            'b' => '<body bg="yellow">',
            'c' => '<background>yellow</background>',
            'd' => '<body color="yellow">',
            'correct' => 'A'
        ]
    ],
    'css' => [
        [
            'text' => 'What does CSS stand for?',
            'a' => 'Creative Style Sheets',
            'b' => 'Cascading Style Sheets',
            'c' => 'Computer Style Sheets',
            'd' => 'Colorful Style Sheets',
            'correct' => 'B'
        ],
        [
            'text' => 'Which HTML attribute is used to define inline styles?',
            'a' => 'class',
            'b' => 'styles',
            'c' => 'style',
            'd' => 'font',
            'correct' => 'C'
        ],
        [
            'text' => 'Which CSS property changes text color?',
            'a' => 'text-color',
            'b' => 'fgcolor',
            'c' => 'color',
            'd' => 'font-color',
            'correct' => 'C'
        ],
        [
            'text' => 'Which CSS property controls the text size?',
            'a' => 'font-style',
            'b' => 'text-size',
            'c' => 'font-size',
            'd' => 'text-style',
            'correct' => 'C'
        ],
        [
            'text' => 'How do you display hyperlinks without an underline?',
            'a' => 'a {text-decoration:none;}',
            'b' => 'a {underline:none;}',
            'c' => 'a {decoration:no-underline;}',
            'd' => 'a {text-decoration:no-underline;}',
            'correct' => 'A'
        ]
    ],
    'javascript' => [
        [
            'text' => 'Which keyword is used to declare a block-scoped local variable in JavaScript?',
            'a' => 'var',
            'b' => 'let',
            'c' => 'def',
            'd' => 'local',
            'correct' => 'B'
        ],
        [
            'text' => 'How do you write "Hello World" in an alert box in JavaScript?',
            'a' => 'msgBox("Hello World");',
            'b' => 'alertBox("Hello World");',
            'c' => 'alert("Hello World");',
            'd' => 'msg("Hello World");',
            'correct' => 'C'
        ],
        [
            'text' => 'How do you create a function in JavaScript?',
            'a' => 'function:myFunction()',
            'b' => 'function myFunction()',
            'c' => 'create myFunction()',
            'd' => 'def myFunction()',
            'correct' => 'B'
        ],
        [
            'text' => 'How do you write an IF statement in JavaScript?',
            'a' => 'if i = 5 then',
            'b' => 'if i == 5 then',
            'c' => 'if (i == 5)',
            'd' => 'if i = 5',
            'correct' => 'C'
        ],
        [
            'text' => 'How does a FOR loop start in JavaScript?',
            'a' => 'for (i <= 5; i++)',
            'b' => 'for (i = 0; i <= 5; i++)',
            'c' => 'for i = 1 to 5',
            'd' => 'for (i = 0; i <= 5)',
            'correct' => 'B'
        ]
    ],
    'python' => [
        [
            'text' => 'What is the correct file extension for Python files?',
            'a' => '.pyt',
            'b' => '.py',
            'c' => '.pyw',
            'd' => '.pt',
            'correct' => 'B'
        ],
        [
            'text' => 'How do you create a variable with the numeric value 5 in Python?',
            'a' => 'x = int(5)',
            'b' => 'x = 5',
            'c' => 'Both x = 5 and x = int(5) are correct',
            'd' => 'x : 5',
            'correct' => 'C'
        ],
        [
            'text' => 'What is the correct syntax to output "Hello World" in Python?',
            'a' => 'p("Hello World")',
            'b' => 'print("Hello World")',
            'c' => 'echo("Hello World")',
            'd' => 'printf("Hello World")',
            'correct' => 'B'
        ],
        [
            'text' => 'Which keyword is used to define a function in Python?',
            'a' => 'def',
            'b' => 'function',
            'c' => 'func',
            'd' => 'define',
            'correct' => 'A'
        ]
    ]
];

// Fallback dynamic mock question generator
function generateMockQuestion($skill, $difficulty, $index) {
    $templates = [
        [
            'text' => "In the context of %skill%, which of the following is considered a primary best practice for a %difficulty% difficulty level project?",
            'a' => "Writing dense, non-modular scripts to minimize file count.",
            'b' => "Structuring components cleanly with descriptive names and optimal validations.",
            'c' => "Hardcoding all operational parameters inside local helper configurations.",
            'd' => "Running continuous deployment pipelines without testing scripts.",
            'correct' => 'B'
        ],
        [
            'text' => "What is the primary role of configuration profiles or definitions in a %skill% environment?",
            'a' => "Obfuscating variables to improve local runtime speed.",
            'b' => "Establishing clear parameters, validation checks, and architectural modularity.",
            'c' => "Maximizing active hardware thread usage during idling states.",
            'd' => "Enforcing legacy compiler settings.",
            'correct' => 'B'
        ],
        [
            'text' => "Which of the following options represents a common tool, library, or framework associated with %skill%?",
            'a' => "The package managers and standard modules designed specifically for %skill%.",
            'b' => "A generic text editor without syntax highlighting support.",
            'c' => "A default database table with no indexing configurations.",
            'd' => "Custom legacy binary converters.",
            'correct' => 'A'
        ],
        [
            'text' => "When managing errors or debugging states in %skill%, what is the recommended starting procedure?",
            'a' => "Overriding error handlers to suppress exceptions.",
            'b' => "Examining debug files, checking framework dependencies, and running unit tests.",
            'c' => "Rebuilding the core system dependencies from scratch.",
            'd' => "Publishing directly to global host instances without verification.",
            'correct' => 'B'
        ]
    ];
    
    $tpl = $templates[$index % count($templates)];
    $text = str_replace(['%skill%', '%difficulty%'], [htmlspecialchars($skill), htmlspecialchars($difficulty)], $tpl['text']);
    return [
        'text' => $text,
        'a' => str_replace(['%skill%'], [htmlspecialchars($skill)], $tpl['a']),
        'b' => str_replace(['%skill%'], [htmlspecialchars($skill)], $tpl['b']),
        'c' => str_replace(['%skill%'], [htmlspecialchars($skill)], $tpl['c']),
        'd' => str_replace(['%skill%'], [htmlspecialchars($skill)], $tpl['d']),
        'correct' => $tpl['correct']
    ];
}

// ── Action: Generate Questions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $project_type = trim($_POST['project_type'] ?? '');
    $project_subtype = trim($_POST['project_subtype'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $difficulty_level = trim($_POST['difficulty_level'] ?? '');
    $num_questions = intval($_POST['num_questions'] ?? 5);
    $duration_minutes = intval($_POST['duration_minutes'] ?? 30);

    if (empty($project_type) || empty($project_subtype) || empty($skills) || empty($difficulty_level) || $num_questions <= 0 || $duration_minutes <= 0) {
        $error_msg = "All fields are required. Number of questions and duration must be greater than 0.";
    } else {
        $valid_category = false;
        $type_check = $conn->prepare("SELECT id FROM project_types WHERE type_name = ? AND status = 'Active' LIMIT 1");
        if ($type_check) {
            $type_check->bind_param('s', $project_type);
            $type_check->execute();
            $type_check->bind_result($selected_type_id);
            if ($type_check->fetch()) {
                $valid_category = false;
                $type_check->close();
                $subtype_check = $conn->prepare("SELECT id FROM project_subtypes WHERE project_type_id = ? AND subtype_name = ? AND status = 'Active' LIMIT 1");
                if ($subtype_check) {
                    $subtype_check->bind_param('is', $selected_type_id, $project_subtype);
                    $subtype_check->execute();
                    $subtype_check->store_result();
                    if ($subtype_check->num_rows > 0) {
                        $valid_category = true;
                    }
                    $subtype_check->close();
                }
            } else {
                $type_check->close();
            }
        }

        if (!$valid_category) {
            $error_msg = "Selected project type and subtype combination is invalid.";
        } else {
            $skills_array = array_filter(array_map('trim', explode(',', $skills)));
            if (empty($skills_array)) {
                $error_msg = "Skills cannot be empty. Please enter at least one skill.";
            } else {
                $_SESSION['preview_project_type'] = $project_type;
                $_SESSION['preview_project_subtype'] = $project_subtype;
                $_SESSION['preview_skills'] = $skills;
                $_SESSION['preview_difficulty_level'] = $difficulty_level;
                $_SESSION['preview_num_questions'] = $num_questions;
                $_SESSION['preview_duration_minutes'] = $duration_minutes;
                
                $generated = [];
            for ($i = 0; $i < $num_questions; $i++) {
                $skill = strtolower($skills_array[$i % count($skills_array)]);
                $skill_title = $skills_array[$i % count($skills_array)];
                
                $picked = null;
                if (isset($predefined_questions[$skill])) {
                    $idx = floor($i / count($skills_array)) % count($predefined_questions[$skill]);
                    $picked = $predefined_questions[$skill][$idx];
                }
                
                if ($picked) {
                    $generated[] = [
                        'text' => $picked['text'],
                        'a' => $picked['a'],
                        'b' => $picked['b'],
                        'c' => $picked['c'],
                        'd' => $picked['d'],
                        'correct' => $picked['correct']
                    ];
                } else {
                    $generated[] = generateMockQuestion($skill_title, $difficulty_level, $i);
                }
            }
            $_SESSION['preview_questions'] = $generated;
            $success_msg = "Generated $num_questions questions automatically! Review them below.";
        }
    }
}
}

// ── Action: Add Question ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    // Preserve current form inputs from POST if available to keep preview up to date
    if (isset($_POST['questions'])) {
        $_SESSION['preview_questions'] = $_POST['questions'];
    }
    
    if (!isset($_SESSION['preview_questions'])) {
        $_SESSION['preview_questions'] = [];
    }
    
    $_SESSION['preview_questions'][] = [
        'text' => '',
        'a' => '',
        'b' => '',
        'c' => '',
        'd' => '',
        'correct' => 'A'
    ];
}

// ── Action: Delete Question ──
if (isset($_GET['delete_idx'])) {
    $idx = intval($_GET['delete_idx']);
    if (isset($_SESSION['preview_questions'][$idx])) {
        array_splice($_SESSION['preview_questions'], $idx, 1);
        // Sync num_questions count
        $_SESSION['preview_num_questions'] = count($_SESSION['preview_questions']);
        $success_msg = "Question removed from preview.";
    }
}

// ── Action: Save Test ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_test') {
    // Get questions from POST form (edited values)
    $questions_post = isset($_POST['questions']) && is_array($_POST['questions']) ? $_POST['questions'] : [];
    
    // Get metadata from POST first, fallback to session
    $project_type = trim($_POST['project_type'] ?? $_SESSION['preview_project_type'] ?? '');
    $project_subtype = trim($_POST['project_subtype'] ?? $_SESSION['preview_project_subtype'] ?? '');
    $skills = trim($_POST['skills'] ?? $_SESSION['preview_skills'] ?? '');
    $difficulty_level = trim($_POST['difficulty_level'] ?? $_SESSION['preview_difficulty_level'] ?? '');
    $duration_minutes = intval($_POST['duration_minutes'] ?? $_SESSION['preview_duration_minutes'] ?? 30);
    
    $num_questions = count($questions_post);
    error_log("[SAVE_TEST] POST received: q_count=$num_questions, subtype=$project_subtype, diff=$difficulty_level, duration=$duration_minutes");
    
    // Validate metadata and questions
    if (empty($project_type) || empty($project_subtype) || empty($skills) || empty($difficulty_level)) {
        $error_msg = "❌ Missing test metadata (type/subtype/skills/difficulty). Please generate questions first.";
    } elseif ($duration_minutes <= 0) {
        $error_msg = "❌ Test duration must be greater than 0.";
    } elseif ($num_questions <= 0) {
        $error_msg = "❌ No questions received (" . count($questions_post) . " items in POST). Please add questions first.";
    } else {
        try {
            // Validate and prepare questions
            $validated = [];
            foreach ($questions_post as $idx => $q) {
                if (!is_array($q)) continue; // Skip invalid entries
                
                $q_text = trim($q['text'] ?? '');
                $q_a = trim($q['a'] ?? '');
                $q_b = trim($q['b'] ?? '');
                $q_c = trim($q['c'] ?? '');
                $q_d = trim($q['d'] ?? '');
                $q_corr = trim($q['correct'] ?? '');

                if (empty($q_text) || empty($q_a) || empty($q_b) || empty($q_c) || empty($q_d) || empty($q_corr)) {
                    throw new Exception("Q" . ($idx + 1) . ": missing fields (text=" . (empty($q_text) ? "NO" : "OK") . ", options=" . (empty($q_a)||empty($q_b)||empty($q_c)||empty($q_d) ? "NO" : "OK") . ", correct=" . (empty($q_corr) ? "NO" : "OK") . ")");
                }
                
                $validated[] = [
                    'text' => $q_text,
                    'a' => $q_a,
                    'b' => $q_b,
                    'c' => $q_c,
                    'd' => $q_d,
                    'correct' => $q_corr
                ];
            }

            if (empty($validated)) {
                throw new Exception("No valid questions after validation. $num_questions items received but all failed validation.");
            }

            error_log("[SAVE_TEST] Validated " . count($validated) . " questions");

            // Find existing test IDs to delete their questions first (avoid orphaned rows)
            $find_stmt = $conn->prepare("SELECT id FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ?");
            if (!$find_stmt) {
                throw new Exception("SELECT existing tests prepare: " . $conn->error);
            }
            $find_stmt->bind_param("ss", $project_subtype, $difficulty_level);
            if (!$find_stmt->execute()) {
                throw new Exception("SELECT existing tests execute: " . $find_stmt->error);
            }
            $find_res = $find_stmt->get_result();
            $old_ids = [];
            while ($row = $find_res->fetch_assoc()) {
                $old_ids[] = intval($row['id']);
            }
            $find_stmt->close();

            if (!empty($old_ids)) {
                // Delete questions for these tests
                foreach ($old_ids as $oid) {
                    $del_q = $conn->prepare("DELETE FROM subtype_test_questions WHERE subtype_test_id = ?");
                    if ($del_q) {
                        $del_q->bind_param("i", $oid);
                        $del_q->execute();
                        $del_q->close();
                    }
                }
                
                // Delete the tests themselves
                $del_t = $conn->prepare("DELETE FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ?");
                if ($del_t) {
                    $del_t->bind_param("ss", $project_subtype, $difficulty_level);
                    $del_t->execute();
                    $del_t->close();
                }
            }
            error_log("[SAVE_TEST] Deleted old tests and questions for this subtype/difficulty");

            // Insert test record
            $ins_test = $conn->prepare("INSERT INTO subtype_tests (project_type, project_subtype, skills, difficulty_level, num_questions, duration_minutes, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            if (!$ins_test) {
                throw new Exception("INSERT test prepare: " . $conn->error);
            }
            $final_count = count($validated);
            $ins_test->bind_param("ssssii", $project_type, $project_subtype, $skills, $difficulty_level, $final_count, $duration_minutes);
            if (!$ins_test->execute()) {
                throw new Exception("INSERT test execute: " . $ins_test->error);
            }
            $test_id = $ins_test->insert_id;
            $ins_test->close();
            error_log("[SAVE_TEST] Test record inserted with ID=$test_id");

            // Insert questions
            $ins_q = $conn->prepare("INSERT INTO subtype_test_questions (subtype_test_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$ins_q) {
                throw new Exception("INSERT questions prepare: " . $conn->error);
            }

            $q_saved = 0;
            foreach ($validated as $q) {
                $ins_q->bind_param("issssss", $test_id, $q['text'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct']);
                if (!$ins_q->execute()) {
                    throw new Exception("Question " . ($q_saved + 1) . " insert: " . $ins_q->error);
                }
                $q_saved++;
            }
            $ins_q->close();
            error_log("[SAVE_TEST] Saved $q_saved questions");

            // Notify coordinator
            $coord_uid = intval($_SESSION['user_id'] ?? 0);
            if ($coord_uid > 0) {
                $c_title = 'Subtype Test Generated';
                $c_msg = "Test for '$project_subtype' ($difficulty_level) with $q_saved questions saved.";
                $c_type = 'success';
                $c_link = "coordinator_generate_test.php";
                $coord_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type, link) VALUES (?, 'coordinator', ?, ?, ?, ?)");
                if ($coord_stmt) {
                    $coord_stmt->bind_param("issss", $coord_uid, $c_title, $c_msg, $c_type, $c_link);
                    $coord_stmt->execute();
                    $coord_stmt->close();
                }
            }

            // Clear session
            unset($_SESSION['preview_project_type']);
            unset($_SESSION['preview_project_subtype']);
            unset($_SESSION['preview_skills']);
            unset($_SESSION['preview_difficulty_level']);
            unset($_SESSION['preview_num_questions']);
            unset($_SESSION['preview_duration_minutes']);
            unset($_SESSION['preview_questions']);

            $success_msg = "Test saved successfully.";
            
            // Redirect to itself to refresh the page and show the new test in the list
            header("Location: coordinator_generate_test.php?success=" . urlencode($success_msg) . "#subtype-tests");
            exit();
        } catch (Throwable $e) {
            $error_msg = "❌ Save failed: " . $e->getMessage();
            error_log("[SAVE_TEST_ERROR] " . $e->getMessage());
        }
    }
}


// Fetch all generated subtype tests for the list
$generated_tests = [];
try {
    $tests_res = mysqli_query($conn, "SELECT * FROM subtype_tests ORDER BY created_at DESC");
    if ($tests_res) {
        while ($row = mysqli_fetch_assoc($tests_res)) {
            $generated_tests[] = $row;
        }
    }
} catch (Throwable $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Automatic Test Generator – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-white border-r border-gray-200 flex flex-col py-6">
    <div class="px-6 mb-8">
        <a href="index.html" class="flex items-center gap-2">
            <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none">
                <rect width="32" height="32" rx="8" fill="currentColor"/>
                <circle cx="16" cy="16" r="3" fill="white"/>
                <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
                <circle cx="16" cy="8" r="1.5" fill="white"/>
                <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
                <line x1="17.8" y1="18.4" x2="20" y2="21.5" stroke="white" stroke-width="1.5"/>
                <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
                <line x1="14.2" y1="18.4" x2="12" y2="21.5" stroke="white" stroke-width="1.5"/>
                <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
                <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
            </svg>
            <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
        </a>
        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2 ml-0.5">Coordinator Portal</p>
    </div>
    <nav class="flex-1 space-y-0.5 px-3">
        <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
        </a>
        <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">work</span> Postings
        </a>
        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">group</span> Candidates
        </a>
        <a href="coordinator_generate_test.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
            <span class="material-symbols-outlined text-[20px]">quiz</span> Generate Test
        </a>
        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
        </a>
        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
        </a>
        <a href="coordinator_teams.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Teams
        </a>
    </nav>
    <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
        <a href="coordinator_profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
        </a>
        <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
            <span class="material-symbols-outlined text-[20px]">help</span> Help Center
        </a>
        <a href="logout.php" class="flex items-center gap-3 text-red-650 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
            <span class="material-symbols-outlined text-[20px] text-red-400">logout</span> Logout
        </a>
    </div>
</aside>

<main class="ml-60 flex flex-col min-h-screen">
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
        <div class="flex items-center gap-4">
            <h1 class="text-base font-bold text-gray-800">Automatic Test Generator</h1>
        </div>
        <div class="flex items-center gap-6">
            <!-- Notifications Bell -->
            <a href="coordinator_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
                <span class="material-symbols-outlined">notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <div class="p-6 space-y-6 flex-1">
        <?php if ($success_msg): ?>
            <div class="p-4 text-sm font-bold text-green-800 rounded-lg bg-green-50 border border-green-300 shadow-sm alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="p-4 text-sm font-bold text-red-800 rounded-lg bg-red-50 border border-red-300 shadow-sm alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- DEBUG: Show POST data on save attempt (only on error or first load) -->
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_test' && !empty($error_msg)): ?>
            <div class="p-4 text-xs bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-900">
                <strong class="block mb-2">⚠️ DEBUG INFO - Save Test POST Data:</strong>
                <table class="w-full text-left">
                    <tr><td class="font-bold pr-4">Questions:</td><td><?php echo count($_POST['questions'] ?? []); ?></td></tr>
                    <tr><td class="font-bold pr-4">Subtype:</td><td><?php echo htmlspecialchars($_POST['project_subtype'] ?? 'EMPTY'); ?></td></tr>
                    <tr><td class="font-bold pr-4">Difficulty:</td><td><?php echo htmlspecialchars($_POST['difficulty_level'] ?? 'EMPTY'); ?></td></tr>
                    <tr><td class="font-bold pr-4">Duration:</td><td><?php echo htmlspecialchars($_POST['duration_minutes'] ?? 'EMPTY'); ?> min</td></tr>
                    <tr><td class="font-bold pr-4">Skills:</td><td><?php echo htmlspecialchars($_POST['skills'] ?? 'EMPTY'); ?></td></tr>
                </table>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Setup Form -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4 lg:col-span-1">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-3">Test Setup</h2>
                
                <form method="POST" action="coordinator_generate_test.php" class="space-y-4">
                    <input type="hidden" name="action" value="generate">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Project Type</label>
                        <select name="project_type" id="gen-project-type" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" required>
                            <?php if (empty($project_types)): ?>
                                <option value="">No project types available</option>
                            <?php else: ?>
                                <?php foreach ($project_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['type_name']); ?>" data-type-id="<?php echo (int)$type['id']; ?>" <?php echo (($selected_type_name ?? '') === $type['type_name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['type_name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Project Subtype</label>
                        <select name="project_subtype" id="gen-project-subtype" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" required>
                            <?php if (empty($initial_subtypes)): ?>
                                <option value="">Select a project type first</option>
                            <?php else: ?>
                                <?php foreach ($initial_subtypes as $subtype): ?>
                                    <option value="<?php echo htmlspecialchars($subtype['subtype_name']); ?>" <?php echo (($_SESSION['preview_project_subtype'] ?? '') === $subtype['subtype_name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subtype['subtype_name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Skills (comma separated)</label>
                        <input type="text" name="skills" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" placeholder="e.g. HTML,CSS,JavaScript" value="<?php echo htmlspecialchars($_SESSION['preview_skills'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Difficulty Level</label>
                        <select name="difficulty_level" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10">
                            <option value="Easy" <?php echo (($_SESSION['preview_difficulty_level'] ?? '') === 'Easy') ? 'selected' : ''; ?>>Easy</option>
                            <option value="Medium" <?php echo (($_SESSION['preview_difficulty_level'] ?? '') === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="Hard" <?php echo (($_SESSION['preview_difficulty_level'] ?? '') === 'Hard') ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Number of Questions</label>
                        <input type="number" name="num_questions" min="1" class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" value="<?php echo intval($_SESSION['preview_num_questions'] ?? 5); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Test Duration (Minutes)</label>
                        <input type="number" name="duration_minutes" min="1" required class="w-full rounded-xl border-gray-200 text-xs py-2 focus:border-blue-600 focus:ring-blue-600/10" value="<?php echo intval($_SESSION['preview_duration_minutes'] ?? 30); ?>">
                    </div>
                    
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-xs font-bold transition-all shadow-sm">
                            Generate Questions
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview and Edit Table -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4 lg:col-span-2">
                <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest">Generated Questions Preview</h2>
                    <?php if (isset($_SESSION['preview_questions']) && count($_SESSION['preview_questions']) > 0): ?>
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-add-question" class="flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-xl text-xs font-bold transition-all">
                                <span class="material-symbols-outlined text-[16px]">add</span> Add Question
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!isset($_SESSION['preview_questions']) || count($_SESSION['preview_questions']) === 0): ?>
                    <div class="py-16 text-center text-gray-400 text-xs">
                        <span class="material-symbols-outlined text-[48px] text-gray-300 block mb-2">quiz</span>
                        No questions generated yet. Set up the details on the left and click <strong>Generate Questions</strong>.
                    </div>
                <?php else: ?>
                    <form method="POST" action="coordinator_generate_test.php" class="space-y-6">
                        <input type="hidden" name="action" value="save_test">
                        <input type="hidden" name="project_type" value="<?php echo htmlspecialchars($_SESSION['preview_project_type'] ?? ''); ?>">
                        <input type="hidden" name="project_subtype" value="<?php echo htmlspecialchars($_SESSION['preview_project_subtype'] ?? ''); ?>">
                        <input type="hidden" name="skills" value="<?php echo htmlspecialchars($_SESSION['preview_skills'] ?? ''); ?>">
                        <input type="hidden" name="difficulty_level" value="<?php echo htmlspecialchars($_SESSION['preview_difficulty_level'] ?? ''); ?>">
                        <input type="hidden" name="duration_minutes" id="preview-duration-minutes" value="<?php echo intval($_SESSION['preview_duration_minutes'] ?? 30); ?>">
                        <input type="hidden" name="num_questions" id="preview-num-questions" value="<?php echo intval($_SESSION['preview_num_questions'] ?? count($_SESSION['preview_questions'] ?? [])); ?>">
                        <div id="preview-questions-container" class="space-y-4 max-h-[50vh] overflow-y-auto pr-2">
                            <?php 
                            $qcount = 0;
                            foreach ($_SESSION['preview_questions'] as $original_idx => $q): 
                            ?>
                                <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3 relative group preview-q-card">
                                    <div class="flex justify-between items-start">
                                        <span class="text-xs font-bold text-slate-500 q-num-lbl">Question <?php echo $qcount + 1; ?></span>
                                        <button type="button" class="btn-delete-preview-q text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-0.5 cursor-pointer">
                                            <span class="material-symbols-outlined text-[16px]">delete</span> Delete
                                        </button>
                                    </div>
                                    <div>
                                        <textarea name="questions[<?php echo $qcount; ?>][text]" rows="2" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Question Text"><?php echo htmlspecialchars($q['text']); ?></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[9px] font-bold text-gray-500 uppercase">Option A</label>
                                            <input type="text" name="questions[<?php echo $qcount; ?>][a]" value="<?php echo htmlspecialchars($q['a']); ?>" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-gray-500 uppercase">Option B</label>
                                            <input type="text" name="questions[<?php echo $qcount; ?>][b]" value="<?php echo htmlspecialchars($q['b']); ?>" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[9px] font-bold text-gray-500 uppercase">Option C</label>
                                            <input type="text" name="questions[<?php echo $qcount; ?>][c]" value="<?php echo htmlspecialchars($q['c']); ?>" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-gray-500 uppercase">Option D</label>
                                            <input type="text" name="questions[<?php echo $qcount; ?>][d]" value="<?php echo htmlspecialchars($q['d']); ?>" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Correct Option</label>
                                        <select name="questions[<?php echo $qcount; ?>][correct]" class="rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer min-w-[120px]">
                                            <option value="A" <?php echo ($q['correct'] === 'A') ? 'selected' : ''; ?>>A</option>
                                            <option value="B" <?php echo ($q['correct'] === 'B') ? 'selected' : ''; ?>>B</option>
                                            <option value="C" <?php echo ($q['correct'] === 'C') ? 'selected' : ''; ?>>C</option>
                                            <option value="D" <?php echo ($q['correct'] === 'D') ? 'selected' : ''; ?>>D</option>
                                        </select>
                                    </div>
                                </div>
                            <?php $qcount++; endforeach; ?>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex gap-3">
                            <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-xs font-bold transition-all shadow-sm">
                                Save Test
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Generated Subtype Tests List -->
        <div id="subtype-tests" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden scroll-mt-20">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest">Active Subtype Tests</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-[10px] tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Project Subtype</th>
                            <th class="px-6 py-4">Skills Filter</th>
                            <th class="px-6 py-4">Difficulty Level</th>
                            <th class="px-6 py-4">Number of Questions</th>
                            <th class="px-6 py-4">Duration (mins)</th>
                            <th class="px-6 py-4">Generated Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-600">
                        <?php if (empty($generated_tests)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400">No subtype tests generated yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($generated_tests as $test): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($test['project_subtype']); ?></td>
                                    <td class="px-6 py-4 text-xs font-medium text-gray-600"><?php echo htmlspecialchars($test['skills'] ?: 'All'); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($test['difficulty_level']); ?></td>
                                    <td class="px-6 py-4"><?php echo intval($test['num_questions']); ?></td>
                                    <td class="px-6 py-4"><?php echo intval($test['duration_minutes'] ?? 30); ?></td>
                                    <td class="px-6 py-4 text-xs"><?php echo htmlspecialchars($test['created_at']); ?></td>
                                    <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                        <button type="button" onclick="viewQuestions(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['project_subtype'], ENT_QUOTES); ?> (<?php echo htmlspecialchars($test['difficulty_level'], ENT_QUOTES); ?>)')" class="text-blue-600 hover:text-blue-800 font-bold text-xs bg-blue-50 px-2.5 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors cursor-pointer">View Questions</button>
                                        <button type="button" onclick="editQuestions(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['project_subtype'], ENT_QUOTES); ?> (<?php echo htmlspecialchars($test['difficulty_level'], ENT_QUOTES); ?>)')" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 px-2.5 py-1.5 rounded-lg border border-indigo-200 hover:bg-indigo-100 transition-colors cursor-pointer">Edit Questions</button>
                                        <a href="coordinator_generate_test.php?delete_test_id=<?php echo $test['id']; ?>" onclick="return confirm('Are you sure you want to delete this test and all its questions?')" class="inline-block text-red-600 hover:text-red-800 font-bold text-xs bg-red-50 px-2.5 py-1.5 rounded-lg border border-red-200 hover:bg-red-100 transition-colors">Delete Test</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Questions Modal -->
    <div id="view-questions-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-lg font-bold text-gray-900 font-sans shadow-sm" id="view-modal-title">View Test Questions</h3>
                <button type="button" onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto text-sm text-left" id="view-questions-list">
                <!-- Populated dynamically -->
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-end">
                <button type="button" onclick="closeViewModal()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Questions Modal -->
    <div id="edit-questions-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <form method="POST" action="coordinator_generate_test.php" id="edit-questions-form">
                <input type="hidden" name="action" value="edit_test_questions">
                <input type="hidden" name="edit_test_id" id="edit-test-id-field">
                
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="text-lg font-bold text-gray-900 font-sans shadow-sm" id="edit-modal-title">Edit Test Questions</h3>
                    <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto text-sm text-left" id="edit-questions-list">
                    <!-- Populated dynamically -->
                </div>
                
                <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <button type="button" onclick="addEditModalQuestion()" class="flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Question
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg text-sm font-medium transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<script>
function getSelectedTypeId(selectElement) {
    const selected = selectElement.selectedOptions[0];
    return selected ? selected.dataset.typeId : '';
}

function updateSubtypes(typeSelectId, subtypeSelectId, defaultSubtype = '') {
    const typeSelect = document.getElementById(typeSelectId);
    const subtypeSelect = document.getElementById(subtypeSelectId);
    const typeId = getSelectedTypeId(typeSelect);
    subtypeSelect.innerHTML = '';

    if (!typeId) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Select a valid project type';
        subtypeSelect.appendChild(opt);
        return;
    }

    fetch('project_category_api.php?action=get_subtypes&type_id=' + encodeURIComponent(typeId))
        .then(response => response.json())
        .then(list => {
            if (!Array.isArray(list) || list.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No subtypes available for this type';
                subtypeSelect.appendChild(opt);
                if (defaultSubtype) {
                    const customOpt = document.createElement('option');
                    customOpt.value = defaultSubtype;
                    customOpt.textContent = defaultSubtype;
                    customOpt.selected = true;
                    subtypeSelect.appendChild(customOpt);
                }
                return;
            }
            list.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.subtype_name;
                opt.textContent = sub.subtype_name;
                subtypeSelect.appendChild(opt);
            });
            if (defaultSubtype && Array.from(subtypeSelect.options).some(opt => opt.value === defaultSubtype)) {
                subtypeSelect.value = defaultSubtype;
            } else if (defaultSubtype) {
                const customOpt = document.createElement('option');
                customOpt.value = defaultSubtype;
                customOpt.textContent = defaultSubtype;
                subtypeSelect.appendChild(customOpt);
                subtypeSelect.value = defaultSubtype;
            } else if (subtypeSelect.options.length > 0) {
                subtypeSelect.value = subtypeSelect.options[0].value;
            }
        })
        .catch(() => {
            subtypeSelect.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Unable to load subtypes';
            subtypeSelect.appendChild(opt);
        });
}

document.getElementById('gen-project-type').addEventListener('change', () => updateSubtypes('gen-project-type', 'gen-project-subtype'));

// Initial populating
updateSubtypes('gen-project-type', 'gen-project-subtype', '<?php echo htmlspecialchars($_SESSION['preview_project_subtype'] ?? ''); ?>');

// Modals management
const viewModal = document.getElementById('view-questions-modal');
const viewTitle = document.getElementById('view-modal-title');
const viewList = document.getElementById('view-questions-list');

const editModal = document.getElementById('edit-questions-modal');
const editTitle = document.getElementById('edit-modal-title');
const editList = document.getElementById('edit-questions-list');
const editTestIdField = document.getElementById('edit-test-id-field');

let editQuestionCounter = 0;

function viewQuestions(testId, testName) {
    viewTitle.textContent = "View Questions – " + testName;
    viewList.innerHTML = '<div class="text-center py-6 text-gray-500">Loading questions...</div>';
    viewModal.classList.remove('hidden');
    
    fetch('coordinator_generate_test.php?ajax_action=get_questions&test_id=' + testId)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                viewList.innerHTML = '<div class="text-center py-6 text-gray-500">No questions found in this test.</div>';
                return;
            }
            viewList.innerHTML = '';
            data.forEach((q, idx) => {
                const card = document.createElement('div');
                card.className = "p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-2";
                
                let corrText = q.correct;
                let optText = q[q.correct.toLowerCase()] || '';
                
                card.innerHTML = `
                    <div class="font-bold text-xs text-blue-600">Question ${idx + 1}</div>
                    <div class="text-xs font-semibold text-gray-800 mb-2">${escapeHtml(q.text)}</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div><span class="font-bold text-gray-400">A:</span> ${escapeHtml(q.a)}</div>
                        <div><span class="font-bold text-gray-400">B:</span> ${escapeHtml(q.b)}</div>
                        <div><span class="font-bold text-gray-400">C:</span> ${escapeHtml(q.c)}</div>
                        <div><span class="font-bold text-gray-400">D:</span> ${escapeHtml(q.d)}</div>
                    </div>
                    <div class="mt-2 text-xs"><span class="font-bold text-emerald-600">Correct Answer:</span> Option ${corrText} (${escapeHtml(optText)})</div>
                `;
                viewList.appendChild(card);
            });
        })
        .catch(err => {
            viewList.innerHTML = '<div class="text-center py-6 text-red-500">Failed to load questions.</div>';
        });
}

function closeViewModal() {
    viewModal.classList.add('hidden');
}

function editQuestions(testId, testName) {
    editTitle.textContent = "Edit Questions – " + testName;
    editTestIdField.value = testId;
    editList.innerHTML = '<div class="text-center py-6 text-gray-500">Loading questions...</div>';
    editModal.classList.remove('hidden');
    editQuestionCounter = 0;
    
    fetch('coordinator_generate_test.php?ajax_action=get_questions&test_id=' + testId)
        .then(response => response.json())
        .then(data => {
            editList.innerHTML = '';
            if (data.length === 0) {
                addEditModalQuestion();
                return;
            }
            data.forEach(q => {
                addEditModalQuestion(q.text, q.a, q.b, q.c, q.d, q.correct);
            });
        })
        .catch(err => {
            editList.innerHTML = '<div class="text-center py-6 text-red-500">Failed to load questions.</div>';
        });
}

function closeEditModal() {
    editModal.classList.add('hidden');
}

function addEditModalQuestion(text='', a='', b='', c='', d='', correct='A') {
    const idx = editQuestionCounter++;
    const card = document.createElement('div');
    card.className = "p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3 relative edit-q-card";
    card.innerHTML = `
        <div class="flex justify-between items-center">
            <span class="text-xs font-bold text-slate-500 q-num-lbl">Question</span>
            <button type="button" onclick="this.closest('.edit-q-card').remove(); reindexEditModalLabels();" class="text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-0.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">delete</span> Delete
            </button>
        </div>
        <div>
            <textarea name="questions[${idx}][text]" required rows="2" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Question Text">${escapeHtml(text)}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[9px] font-bold text-gray-500 uppercase">Option A</label>
                <input type="text" name="questions[${idx}][a]" required value="${escapeHtml(a)}" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
            </div>
            <div>
                <label class="block text-[9px] font-bold text-gray-500 uppercase">Option B</label>
                <input type="text" name="questions[${idx}][b]" required value="${escapeHtml(b)}" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[9px] font-bold text-gray-500 uppercase">Option C</label>
                <input type="text" name="questions[${idx}][c]" required value="${escapeHtml(c)}" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
            </div>
            <div>
                <label class="block text-[9px] font-bold text-gray-500 uppercase">Option D</label>
                <input type="text" name="questions[${idx}][d]" required value="${escapeHtml(d)}" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10">
            </div>
        </div>
        <div>
            <label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Correct Option</label>
            <select name="questions[${idx}][correct]" class="rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer min-w-[120px]">
                <option value="A" ${correct === 'A' ? 'selected' : ''}>A</option>
                <option value="B" ${correct === 'B' ? 'selected' : ''}>B</option>
                <option value="C" ${correct === 'C' ? 'selected' : ''}>C</option>
                <option value="D" ${correct === 'D' ? 'selected' : ''}>D</option>
            </select>
        </div>
    `;
    editList.appendChild(card);
    reindexEditModalLabels();
}

function reindexEditModalLabels() {
    const cards = editList.querySelectorAll('.edit-q-card');
    cards.forEach((card, i) => {
        card.querySelector('.q-num-lbl').textContent = "Question " + (i + 1);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Monitor Save Test form submission & handle client-side question add/delete/re-index
document.addEventListener('DOMContentLoaded', function() {
    const btnAddQuestion = document.getElementById('btn-add-question');
    const previewContainer = document.getElementById('preview-questions-container');

    if (btnAddQuestion && previewContainer) {
        btnAddQuestion.addEventListener('click', function() {
            const cards = previewContainer.querySelectorAll('.preview-q-card');
            const idx = cards.length;

            const card = document.createElement('div');
            card.className = "p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3 relative group preview-q-card";
            card.innerHTML = `
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-slate-500 q-num-lbl">Question ${idx + 1}</span>
                    <button type="button" class="btn-delete-preview-q text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-0.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">delete</span> Delete
                    </button>
                </div>
                <div>
                    <textarea name="questions[${idx}][text]" rows="2" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" placeholder="Question Text" required></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] font-bold text-gray-500 uppercase">Option A</label>
                        <input type="text" name="questions[${idx}][a]" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" required>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-gray-500 uppercase">Option B</label>
                        <input type="text" name="questions[${idx}][b]" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] font-bold text-gray-500 uppercase">Option C</label>
                        <input type="text" name="questions[${idx}][c]" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" required>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-gray-500 uppercase">Option D</label>
                        <input type="text" name="questions[${idx}][d]" class="w-full rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10" required>
                    </div>
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Correct Option</label>
                    <select name="questions[${idx}][correct]" class="rounded-lg border-gray-200 text-xs py-1.5 focus:border-blue-600 focus:ring-blue-600/10 cursor-pointer min-w-[120px]">
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
            `;
            previewContainer.appendChild(card);
            reindexPreviewQuestions();
        });

        previewContainer.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.btn-delete-preview-q');
            if (deleteBtn) {
                e.preventDefault();
                const card = deleteBtn.closest('.preview-q-card');
                if (card) {
                    card.remove();
                    reindexPreviewQuestions();
                }
            }
        });
    }

    function reindexPreviewQuestions() {
        if (!previewContainer) return;
        const cards = previewContainer.querySelectorAll('.preview-q-card');
        cards.forEach((card, idx) => {
            const lbl = card.querySelector('.q-num-lbl');
            if (lbl) lbl.textContent = "Question " + (idx + 1);

            const textarea = card.querySelector('textarea');
            if (textarea) textarea.name = `questions[${idx}][text]`;

            const optA = card.querySelector('input[name*="[a]"]');
            if (optA) optA.name = `questions[${idx}][a]`;

            const optB = card.querySelector('input[name*="[b]"]');
            if (optB) optB.name = `questions[${idx}][b]`;

            const optC = card.querySelector('input[name*="[c]"]');
            if (optC) optC.name = `questions[${idx}][c]`;

            const optD = card.querySelector('input[name*="[d]"]');
            if (optD) optD.name = `questions[${idx}][d]`;

            const correct = card.querySelector('select');
            if (correct) correct.name = `questions[${idx}][correct]`;
        });

        const numQuestionsInput = document.getElementById('preview-num-questions');
        if (numQuestionsInput) {
            numQuestionsInput.value = cards.length;
        }
    }

    // Sync Setup form metadata to Save Test form on submit
    const saveForms = document.querySelectorAll('form[action*="coordinator_generate_test.php"]');
    saveForms.forEach(form => {
        const isSaveForm = form.querySelector('input[name="action"][value="save_test"]');
        if (isSaveForm) {
            form.addEventListener('submit', function(e) {
                const typeSelect = document.getElementById('gen-project-type');
                const subtypeSelect = document.getElementById('gen-project-subtype');
                const skillsInput = document.querySelector('form input[name="skills"]');
                const diffSelect = document.querySelector('form select[name="difficulty_level"]');
                const durationInput = document.querySelector('form input[name="duration_minutes"]');

                if (typeSelect) {
                    const hiddenType = form.querySelector('input[name="project_type"]');
                    if (hiddenType) hiddenType.value = typeSelect.value;
                }
                if (subtypeSelect) {
                    const hiddenSubtype = form.querySelector('input[name="project_subtype"]');
                    if (hiddenSubtype) hiddenSubtype.value = subtypeSelect.value;
                }
                if (skillsInput) {
                    const hiddenSkills = form.querySelector('input[name="skills"]');
                    if (hiddenSkills) hiddenSkills.value = skillsInput.value;
                }
                if (diffSelect) {
                    const hiddenDiff = form.querySelector('input[name="difficulty_level"]');
                    if (hiddenDiff) hiddenDiff.value = diffSelect.value;
                }
                if (durationInput) {
                    const hiddenDuration = form.querySelector('input[name="duration_minutes"]');
                    if (hiddenDuration) hiddenDuration.value = durationInput.value;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const origText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');

                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = origText;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }, 5000);
                }
            });
        }
    });
});
</script>
<script src="js/alerts.js"></script>
</body>
</html>
