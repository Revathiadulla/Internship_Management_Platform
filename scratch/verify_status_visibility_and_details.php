<?php
include "db.php";
include "status_utils.php";

echo "=== Testing status badge formatting helper ===\n";
$test_cases = [
    'hr_review' => 'HR Review',
    'project_assigned' => 'Project Assigned',
    'hod_approved' => 'HOD Approved',
    'confirmation_letter_sent' => 'Confirmation Letter Sent',
    'APPLICATION STATUS: HR REVIEW' => 'HR Review',
    'application status: shortlisted' => 'Shortlisted',
    'selected' => 'Selected'
];

$failed = false;
foreach ($test_cases as $input => $expected) {
    $result = formatStatusLabel($input);
    if ($result === $expected) {
        echo "  [OK] Input: '{$input}' => '{$result}'\n";
    } else {
        echo "  [FAIL] Input: '{$input}' => Expected '{$expected}', Got '{$result}'\n";
        $failed = true;
    }
}

echo "\n=== Testing HR applications visibility exclusion status list ===\n";
$excluded_statuses = ['Project Assigned', 'Team Assigned', 'Internship Started', 'Internship Completed', 'Certificate Issued', 'Archived'];
$excl_str = implode("', '", $excluded_statuses);
$query = "SELECT COUNT(*) as c FROM internship_applications WHERE status IN ('{$excl_str}')";
$res = mysqli_query($conn, $query);
if ($res) {
    $row = mysqli_fetch_assoc($res);
    echo "  [INFO] Number of applications in database with excluded statuses: " . $row['c'] . "\n";
} else {
    echo "  [FAIL] Failed to run database visibility check.\n";
    $failed = true;
}

echo "\n=== Testing project details fetching fallback query ===\n";
// Let's find one application with status 'Project Assigned' to test
$find_app_res = mysqli_query($conn, "SELECT id, user_id FROM internship_applications WHERE status = 'Project Assigned' LIMIT 1");
if ($find_app_res && mysqli_num_rows($find_app_res) > 0) {
    $app_row = mysqli_fetch_assoc($find_app_res);
    $test_app_id = intval($app_row['id']);
    $test_user_id = intval($app_row['user_id']);
    echo "  [INFO] Found assigned student for testing: User ID {$test_user_id}, Application ID {$test_app_id}\n";
    
    // Test direct query mapping like in hr_applicant_detail.php
    $pt_sql = "SELECT ptm.created_at AS assigned_date,
                     t.team_name,
                     t.status AS project_status,
                     t.project_type,
                     t.project_subtype,
                     i.title AS project_title,
                     i.duration AS project_duration,
                     i.mode AS project_mode,
                     COALESCE(i.technology_stack, i.skills, '') AS project_stack,
                     u.full_name AS mentor_name
              FROM project_team_members ptm
              JOIN project_teams t ON ptm.project_team_id = t.id
              LEFT JOIN internships i ON t.internship_id = i.id
              LEFT JOIN users u ON t.mentor_id = u.id
              WHERE ptm.student_id = $test_user_id
              ORDER BY t.id DESC
              LIMIT 1";
    $pt_res = mysqli_query($conn, $pt_sql);
    if ($pt_res) {
        $details = mysqli_fetch_assoc($pt_res);
        if ($details) {
            echo "  [OK] Project details fetched from team assignment tables:\n";
            echo "       Title: " . $details['project_title'] . "\n";
            echo "       Type: " . $details['project_type'] . "\n";
            echo "       Subtype: " . $details['project_subtype'] . "\n";
            echo "       Mentor: " . $details['mentor_name'] . "\n";
            echo "       Team: " . $details['team_name'] . "\n";
            echo "       Date: " . $details['assigned_date'] . "\n";
            echo "       Status: " . formatStatusLabel($details['project_status']) . "\n";
        } else {
            echo "  [WARNING] No team assignment row found for student in project_team_members table. Checking fallback columns in application.\n";
        }
    } else {
        echo "  [FAIL] Query failed: " . mysqli_error($conn) . "\n";
        $failed = true;
    }
} else {
    echo "  [INFO] No students with status 'Project Assigned' found in DB currently.\n";
}

if ($failed) {
    echo "\n=== RESULT: SOME TESTS FAILED ===\n";
    exit(1);
} else {
    echo "\n=== RESULT: ALL TESTS PASSED ===\n";
    exit(0);
}
