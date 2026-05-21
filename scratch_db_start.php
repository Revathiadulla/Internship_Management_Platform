<?php
include 'db.php';

// Find teja@gmail.com user
$user_sql = "SELECT id FROM users WHERE email = 'teja@gmail.com'";
$user_res = mysqli_query($conn, $user_sql);
if ($user_row = mysqli_fetch_assoc($user_res)) {
    $user_id = $user_row['id'];
    
    // Find if there is an application
    $app_sql = "SELECT id FROM internship_applications WHERE user_id = '$user_id' LIMIT 1";
    $app_res = mysqli_query($conn, $app_sql);
    
    if (mysqli_num_rows($app_res) > 0) {
        $app_row = mysqli_fetch_assoc($app_res);
        $app_id = $app_row['id'];
        
        // Update status to 'Started'
        $update_sql = "UPDATE internship_applications SET status = 'Started', test_status = 'Completed' WHERE id = '$app_id'";
        if (mysqli_query($conn, $update_sql)) {
            echo "Successfully updated application ID $app_id for teja@gmail.com to Started!";
        } else {
            echo "Error updating application: " . mysqli_error($conn);
        }
    } else {
        // Create an internship application in 'Started' status
        $intern_sql = "SELECT id FROM internships LIMIT 1";
        $intern_res = mysqli_query($conn, $intern_sql);
        if ($intern_row = mysqli_fetch_assoc($intern_res)) {
            $intern_id = $intern_row['id'];
            $insert_sql = "INSERT INTO internship_applications (user_id, internship_id, status, test_status, test_score, test_answers, reason_for_applying, relevant_skills, preferred_duration) 
                           VALUES ('$user_id', '$intern_id', 'Started', 'Completed', 4, '[]', 'Integration test', 'React', '3 Months')";
            if (mysqli_query($conn, $insert_sql)) {
                echo "Successfully created a new Started internship application for teja@gmail.com!";
            } else {
                echo "Error inserting application: " . mysqli_error($conn);
            }
        } else {
            echo "No internships found to start!";
        }
    }
} else {
    echo "User teja@gmail.com not found!";
}
