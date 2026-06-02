<?php
// ensure_extended_schema.php
// This script adds new columns/tables required for the extended internship workflow.
// It is safe to include multiple times; it checks existence before altering.

if (!isset($conn)) {
    // Expect $conn from the including script.
    exit('Database connection not available');
}

// Add new columns to internship_applications if missing
$extended_cols = [
    'hod_approval_status' => "ALTER TABLE internship_applications ADD COLUMN hod_approval_status VARCHAR(20) DEFAULT NULL",
    'coordinator_id'      => "ALTER TABLE internship_applications ADD COLUMN coordinator_id INT DEFAULT NULL",
    'mentor_id'           => "ALTER TABLE internship_applications ADD COLUMN mentor_id INT DEFAULT NULL",
    'dropout_status'      => "ALTER TABLE internship_applications ADD COLUMN dropout_status VARCHAR(20) DEFAULT NULL",
    // New test-related columns
    'test_score'          => "ALTER TABLE internship_applications ADD COLUMN test_score INT DEFAULT NULL",
    'test_result'         => "ALTER TABLE internship_applications ADD COLUMN test_result VARCHAR(20) DEFAULT NULL",
    'test_submitted_date'=> "ALTER TABLE internship_applications ADD COLUMN test_submitted_date DATETIME DEFAULT NULL",
    'aadhaar_verification_status' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_verification_status VARCHAR(20) DEFAULT 'Pending'",
    'pan_verification_status' => "ALTER TABLE internship_applications ADD COLUMN pan_verification_status VARCHAR(20) DEFAULT 'Pending'"
];
foreach ($extended_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Add to student_profiles as well
$sp_cols = [
    'aadhaar_verification_status' => "ALTER TABLE student_profiles ADD COLUMN aadhaar_verification_status VARCHAR(20) DEFAULT 'Pending'",
    'pan_verification_status' => "ALTER TABLE student_profiles ADD COLUMN pan_verification_status VARCHAR(20) DEFAULT 'Pending'"
];
foreach ($sp_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Create hods table if not exists (stores HOD contact per internship)
$create_hods = "CREATE TABLE IF NOT EXISTS hods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_hods);

// Create mentors table if not exists
$create_mentors = "CREATE TABLE IF NOT EXISTS mentors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_mentors);

// Create coordinators table if not exists
$create_coordinators = "CREATE TABLE IF NOT EXISTS coordinators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_coordinators);

// Create dropout_requests table to track mentor-initiated dropouts
$create_dropout = "CREATE TABLE IF NOT EXISTS dropout_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    mentor_id INT NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Requested',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_dropout);

// Ensure daily_logs has required columns for mentor dashboard
$dl_needed = [
    'is_reviewed' => "ALTER TABLE daily_logs ADD COLUMN is_reviewed TINYINT(1) DEFAULT 0",
    'review_status' => "ALTER TABLE daily_logs ADD COLUMN review_status VARCHAR(20) DEFAULT NULL",
    'reviewed_at' => "ALTER TABLE daily_logs ADD COLUMN reviewed_at DATETIME NULL",
    'reviewer_remarks' => "ALTER TABLE daily_logs ADD COLUMN reviewer_remarks TEXT NULL"
];
foreach ($dl_needed as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}
// Create mentor_feedback table if not exists
$create_feedback = "CREATE TABLE IF NOT EXISTS mentor_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    student_id INT NOT NULL,
    internship_id INT NOT NULL,
    rating INT NOT NULL,
    comments TEXT NULL,
    phase VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_feedback);

