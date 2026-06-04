<?php
// temp_ui_full_test.php
// Simulate a student clicking Start Test and follow student_test.php logic end-to-end
require_once __DIR__ . '/db.php';
session_start();

function out($s) { echo $s . "\n"; }

out("Starting full UI simulation for Start Test...\n");

// 1) Find an application whose internship has project_subtype and there's a matching subtype_test
// Build available columns list for applications table
$available_cols = [];
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
if ($cols_res) {
    while ($c = mysqli_fetch_assoc($cols_res)) $available_cols[] = $c['Field'];
}

// Iterate internships with subtype present and find one that has a matching subtype_test and an application
$intern_q = mysqli_query($conn, "SELECT id, project_subtype, difficulty_level FROM internships WHERE project_subtype IS NOT NULL AND TRIM(project_subtype) <> '' ORDER BY id ASC LIMIT 50");
if (!$intern_q) { out("ERROR: Could not query internships: " . mysqli_error($conn)); exit(1); }

$found = false;
$test_check_stmt = $conn->prepare('SELECT id FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ? LIMIT 1');
while ($intern = mysqli_fetch_assoc($intern_q)) {
    $proto_sub = $intern['project_subtype'];
    $proto_diff = $intern['difficulty_level'];
    $test_check_stmt->bind_param('ss', $proto_sub, $proto_diff);
    $test_check_stmt->execute();
    $tr = $test_check_stmt->get_result();
    $trow = $tr ? $tr->fetch_assoc() : null;
    if ($trow) {
        // find an application for this internship
        $iid = intval($intern['id']);
        $app_res_q = mysqli_query($conn, "SELECT id, internship_id" . (in_array('user_id', $available_cols, true) ? ', user_id' : '') . (in_array('student_id', $available_cols, true) ? ', student_id' : '') . " FROM internship_applications WHERE internship_id = $iid LIMIT 1");
        if ($app_res_q && ($app_row = mysqli_fetch_assoc($app_res_q))) {
            $row = $app_row;
            $row['project_subtype'] = $proto_sub;
            $row['difficulty_level'] = $proto_diff;
            $found = true;
            break;
        }
    }
}
$test_check_stmt->close();

if (!$found) {
    out("No suitable application found that links to an internship with project_subtype and a matching subtype_test.");
    exit(2);
}
$app_id = intval($row['id'] ?? $row['app_id'] ?? 0);
$internship_id = intval($row['internship_id']);
$app_user_id = isset($row['user_id']) ? intval($row['user_id']) : null;
$app_student_id = isset($row['student_id']) ? intval($row['student_id']) : null;
$project_subtype = $row['project_subtype'];
$difficulty_level = $row['difficulty_level'];

out("Selected application_id={$app_id}, internship_id={$internship_id}, user_id={$app_user_id}, student_id={$app_student_id}");
out("project_subtype={$project_subtype}, difficulty_level={$difficulty_level}");

// Simulate logged-in user (prefer student_id then user_id)
$sim_user = $app_student_id ?? $app_user_id ?? 0;
$_SESSION['user_id'] = $sim_user;
out("Simulating logged-in user_id={$sim_user}\n");

// 2) Fetch application via student_test.php style query
// Build query that supports both student_id and user_id
$has_student = (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'student_id'")) > 0);
$has_user = (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'user_id'")) > 0);

if ($has_student && $has_user) {
    $sql_app = "SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND (a.student_id = ? OR a.user_id = ?) LIMIT 1";
} elseif ($has_student) {
    $sql_app = "SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.student_id = ? LIMIT 1";
} else {
    $sql_app = "SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.user_id = ? LIMIT 1";
}

out("Application fetch SQL (prepared): $sql_app");
$stmt = $conn->prepare($sql_app);
if (!$stmt) { out("ERROR preparing: " . $conn->error); exit(1); }
if ($has_student && $has_user) $stmt->bind_param('iii', $app_id, $sim_user, $sim_user);
else $stmt->bind_param('ii', $app_id, $sim_user);

if (!$stmt->execute()) { out("ERROR executing app stmt: " . $stmt->error); exit(1); }
$app_res = $stmt->get_result();
$app = $app_res ? $app_res->fetch_assoc() : null;
$stmt->close();

if (!$app) {
    out("RESULT: Application lookup failed for application_id={$app_id} and user_id={$sim_user}.");
    out("SQL used (prepared): $sql_app");
    exit(3);
}

out("SUCCESS: Application found. application_id={$app['id']}, internship_id={$app['internship_id']}");
out("project_subtype={$app['project_subtype']}, difficulty_level={$app['difficulty_level']}");

// 3) Find latest subtype_test
$sql_test = "SELECT id FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ? ORDER BY id DESC LIMIT 1";
out("Subtype test SQL (prepared): $sql_test");
$ts = $conn->prepare($sql_test);
if (!$ts) { out("ERROR preparing: " . $conn->error); exit(1); }
$ts->bind_param('ss', $app['project_subtype'], $app['difficulty_level']);
$ts->execute();
$tr = $ts->get_result();
$test_row = $tr ? $tr->fetch_assoc() : null;
$ts->close();

if (empty($test_row)) {
    out("RESULT: No subtype_test found for project_subtype={$app['project_subtype']} difficulty_level={$app['difficulty_level']}.\nSQL: $sql_test");
    exit(4);
}
$subtype_test_id = intval($test_row['id']);
out("SUCCESS: Found subtype_test id={$subtype_test_id}");

// 4) Load questions
$qsql = "SELECT id, question_text, option_a, option_b, option_c, option_d FROM subtype_test_questions WHERE subtype_test_id = ? ORDER BY id ASC";
$out_qsql = $qsql;
$qst = $conn->prepare($qsql);
$qst->bind_param('i', $subtype_test_id);
$qst->execute();
$qr = $qst->get_result();
$questions = [];
while ($qr && ($r = $qr->fetch_assoc())) {
    $questions[] = $r;
}
$qst->close();

if (count($questions) === 0) {
    out("RESULT: No questions found for subtype_test_id={$subtype_test_id}. SQL: $qsql");
    exit(5);
}

out("SUCCESS: Loaded " . count($questions) . " questions for subtype_test_id={$subtype_test_id}");

out("Full UI simulation successful. The student Start Test flow should open the subtype test correctly.");
exit(0);
