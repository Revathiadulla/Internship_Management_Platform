<?php
// Prevent redirect headers from failing CLI execution
ob_start();

// Mock session and request environment
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'coordinator';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'save_test';
$_POST['project_type'] = 'Test AutoGen Type';
$_POST['project_subtype'] = 'Test AutoGen Subtype';
$_POST['skills'] = 'PHP, MySQL, PHPUnit';
$_POST['difficulty_level'] = 'Advanced';
$_POST['duration_minutes'] = 40;

$_POST['questions'] = [
    [
        'text' => 'What is the default value of question_bank_id after our fix?',
        'a' => 'NULL',
        'b' => 'Default to 0',
        'c' => '1',
        'd' => 'Not defined',
        'correct' => 'B'
    ],
    [
        'text' => 'Does the Save Test execution succeed now?',
        'a' => 'No, it still fails',
        'b' => 'Yes, both test and questions are saved',
        'c' => 'Only test is saved',
        'd' => 'Only questions are saved',
        'correct' => 'B'
    ]
];

// Register shutdown function to verify database after coordinator_generate_test.php finishes and calls exit()
register_shutdown_function(function() {
    // Access the global database connection established by the parent script
    global $conn;
    
    echo "\n=== SHUTDOWN VERIFICATION ===\n";
    
    // Find the test we just inserted
    $subtype = 'Test AutoGen Subtype';
    $difficulty = 'Advanced';
    
    $res = mysqli_query($conn, "SELECT * FROM subtype_tests WHERE project_subtype = '$subtype' AND difficulty_level = '$difficulty'");
    if ($res && mysqli_num_rows($res) > 0) {
        $test = mysqli_fetch_assoc($res);
        echo "SUCCESS: Found generated subtype test in DB!\n";
        print_r($test);
        
        $tid = intval($test['id']);
        echo "Retrieving questions for test ID $tid:\n";
        $qres = mysqli_query($conn, "SELECT * FROM subtype_test_questions WHERE subtype_test_id = $tid");
        if ($qres) {
            echo "Found " . mysqli_num_rows($qres) . " questions:\n";
            while ($qrow = mysqli_fetch_assoc($qres)) {
                print_r($qrow);
            }
        } else {
            echo "ERROR querying questions: " . mysqli_error($conn) . "\n";
        }
        
        // Clean up the test after verification to avoid polluting the database
        mysqli_query($conn, "DELETE FROM subtype_test_questions WHERE subtype_test_id = $tid");
        mysqli_query($conn, "DELETE FROM subtype_tests WHERE id = $tid");
        echo "Cleaned up test and questions from database.\n";
    } else {
        echo "FAILED: Test was not found in DB.\n";
    }
});

// Include the actual script to trigger the POST action
include __DIR__ . '/../coordinator_generate_test.php';
