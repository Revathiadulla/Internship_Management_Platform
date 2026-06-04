<?php
// temp_smoke_student_test.php
// Server-side smoke test for student_test.php diagnostics

require_once __DIR__ . '/db.php';
session_start();

function out($s) { echo $s . "\n"; }

out("Starting smoke test...");

// 1) Pick a real application row
// Determine available columns first (some schemas use user_id instead of student_id)
$available_cols = [];
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
if ($cols_res) {
    while ($c = mysqli_fetch_assoc($cols_res)) {
        $available_cols[] = $c['Field'];
    }
} else {
    out("ERROR: Could not read columns from internship_applications: " . mysqli_error($conn));
    exit(1);
}

$select_cols = ['id', 'internship_id'];
if (in_array('student_id', $available_cols, true)) $select_cols[] = 'student_id';
if (in_array('user_id', $available_cols, true)) $select_cols[] = 'user_id';

$sql = 'SELECT ' . implode(', ', $select_cols) . ' FROM internship_applications WHERE id IS NOT NULL LIMIT 1';
$app_q = mysqli_query($conn, $sql);
if (!$app_q) {
    out("ERROR: Could not query internship_applications: " . mysqli_error($conn));
    exit(1);
}
$app_row = mysqli_fetch_assoc($app_q);
if (!$app_row) {
    out("ERROR: No rows found in internship_applications table.");
    exit(1);
}
$app_id = intval($app_row['id']);
$internship_id = isset($app_row['internship_id']) ? intval($app_row['internship_id']) : 0;
$db_student_id = array_key_exists('student_id', $app_row) ? intval($app_row['student_id']) : null;
$db_user_id = array_key_exists('user_id', $app_row) ? intval($app_row['user_id']) : null;

out("Found application row: application_id={$app_id}, internship_id={$internship_id}, student_id=" . var_export($db_student_id, true) . ", user_id=" . var_export($db_user_id, true));

// 2) Set logged-in user id to the applicant (prefer student_id then user_id)
$logged_in = $db_student_id ?? $db_user_id ?? 0;
if ($logged_in <= 0) {
    out("ERROR: No valid student/user id available to simulate login.");
    exit(1);
}
$_SESSION['user_id'] = $logged_in;
out("Simulating logged-in user_id={$logged_in}");

// 3) Check which columns exist in internship_applications
function col_exists($conn, $col) {
    $r = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
    return ($r && mysqli_num_rows($r) > 0);
}
$has_student_col = col_exists($conn, 'student_id');
$has_user_col = col_exists($conn, 'user_id');
out("Column presence: student_id=" . ($has_student_col ? 'yes' : 'no') . ", user_id=" . ($has_user_col ? 'yes' : 'no'));

// 4) Fetch application joined with internships using prepared statement supporting both columns
if ($has_student_col && $has_user_col) {
    $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND (a.student_id = ? OR a.user_id = ?) LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $app_id, $_SESSION['user_id'], $_SESSION['user_id']);
} elseif ($has_student_col) {
    $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.student_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $app_id, $_SESSION['user_id']);
} elseif ($has_user_col) {
    $sql = 'SELECT a.*, i.project_type, i.project_subtype, i.difficulty_level FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.id = ? AND a.user_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $app_id, $_SESSION['user_id']);
} else {
    out('ERROR: Neither student_id nor user_id columns exist in internship_applications.');
    exit(1);
}

if (!$stmt) {
    out('ERROR: Failed to prepare statement: ' . $conn->error);
    exit(1);
}
if (!$stmt->execute()) {
    out('ERROR: Statement execute failed: ' . $stmt->error);
    exit(1);
}
$res = $stmt->get_result();
$app = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$app) {
    out("RESULT: Application lookup failed for application_id={$app_id} and user_id={$_SESSION['user_id']}.\nApplication not found or not owned by user.");
    exit(2);
}

out("SUCCESS: Application found. Details:");
out(" - application_id: " . intval($app['id']));
out(" - logged-in user_id: " . intval($_SESSION['user_id']));
out(" - internship_id: " . intval($app['internship_id']));
out(" - project_subtype: " . ($app['project_subtype'] ?? 'NULL'));
out(" - difficulty_level: " . ($app['difficulty_level'] ?? 'NULL'));

$project_subtype = $app['project_subtype'] ?? null;
$difficulty_level = $app['difficulty_level'] ?? null;
if (empty($project_subtype) || empty($difficulty_level)) {
    out("WARNING: project_subtype or difficulty_level missing on internship record. Cannot locate subtype test.");
    exit(3);
}

// 5) Find latest subtype test
$test_stmt = $conn->prepare('SELECT id FROM subtype_tests WHERE project_subtype = ? AND difficulty_level = ? ORDER BY id DESC LIMIT 1');
if (!$test_stmt) { out('ERROR: Could not prepare subtype_tests query: ' . $conn->error); exit(1); }
$test_stmt->bind_param('ss', $project_subtype, $difficulty_level);
$test_stmt->execute();
$tr = $test_stmt->get_result();
$test_row = $tr ? $tr->fetch_assoc() : null;
$test_stmt->close();

if (!$test_row) {
    out("RESULT: No subtype_test found for project_subtype={$project_subtype} difficulty_level={$difficulty_level}.");
    exit(4);
}
$subtype_test_id = intval($test_row['id']);
out("SUCCESS: Found subtype_test id={$subtype_test_id}");

// 6) Count questions
$qstmt = $conn->prepare('SELECT COUNT(*) as c FROM subtype_test_questions WHERE subtype_test_id = ?');
$qstmt->bind_param('i', $subtype_test_id);
$qstmt->execute();
$qr = $qstmt->get_result();
$qcount = ($qr && ($r = $qr->fetch_assoc())) ? intval($r['c']) : 0;
$qstmt->close();

if ($qcount <= 0) {
    out("RESULT: subtype_test_questions not found for subtype_test_id={$subtype_test_id}.");
    exit(5);
}

out("SUCCESS: subtype_test_questions count={$qcount} for test_id={$subtype_test_id}");

out("Smoke test completed successfully. No failures.");
exit(0);
