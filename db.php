
<?php

$host = getenv("MYSQLHOST") ?: "localhost";
$user = getenv("MYSQLUSER") ?: "root";
$password = getenv("MYSQLPASSWORD") ?: "";
$database = getenv("MYSQLDATABASE") ?: "imp_db";
$port = getenv("MYSQLPORT") ?: 3306;

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


// Alter table to add test columns if they do not exist
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'test_status'");
if (mysqli_num_rows($check_cols) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN test_status VARCHAR(50) DEFAULT 'Pending'");
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN test_score INT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN test_answers TEXT DEFAULT NULL");
}

// Add new smart-form columns if they do not exist
$new_cols = [
    "education_status"   => "VARCHAR(20) DEFAULT NULL",
    "department"         => "VARCHAR(100) DEFAULT NULL",
    "hod_name"           => "VARCHAR(100) DEFAULT NULL",
    "hod_email"          => "VARCHAR(100) DEFAULT NULL",
    "graduation_year"    => "VARCHAR(10) DEFAULT NULL",
    "prev_college_name"  => "VARCHAR(150) DEFAULT NULL",
    "aadhaar_number"     => "VARCHAR(20) DEFAULT NULL",
    "resume_file"        => "VARCHAR(255) DEFAULT NULL",
    "preferred_domain"   => "VARCHAR(100) DEFAULT NULL",
    "project_interests"  => "TEXT DEFAULT NULL",
    "pan_number"         => "VARCHAR(10) DEFAULT NULL",
    "pan_masked"         => "VARCHAR(15) DEFAULT NULL",
    "pan_file"           => "VARCHAR(255) DEFAULT NULL",
    "college_name"       => "VARCHAR(150) DEFAULT NULL",
    "year_of_study"      => "VARCHAR(30) DEFAULT NULL",
];
foreach ($new_cols as $col => $definition) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $col $definition");
    }
}

// Create application_status_history table for tracking status changes
$status_history_table = "CREATE TABLE IF NOT EXISTS application_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    old_status VARCHAR(100) DEFAULT NULL,
    new_status VARCHAR(100) NOT NULL,
    updated_by_role VARCHAR(50) NOT NULL,
    updated_by_name VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE
)";
mysqli_query($conn, $status_history_table);

// Add test_submitted_date column if it doesn't exist
$test_date_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'test_submitted_date'");
if (mysqli_num_rows($test_date_check) == 0) {
    mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN test_submitted_date TIMESTAMP NULL DEFAULT NULL AFTER test_status");
}

?>