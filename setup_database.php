<?php
/**
 * setup_database.php
 * One-time database setup/schema check script.
 * Include this or run it manually to set up tables/columns.
 */

// 1. Include db.php to establish the connection
require_once __DIR__ . '/db.php';

// Detect if running in CLI or browser
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>IMP Database Setup</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class='bg-slate-50 p-8'>
    <div class='max-w-2xl mx-auto bg-white rounded-2xl shadow-lg p-8 border border-slate-200'>
        <h1 class='text-2xl font-bold text-slate-900 mb-6'>IMP Database Schema Initialization</h1>
        <div class='space-y-4'>";
} else {
    echo "IMP Database Schema Initialization\n";
    echo "===================================\n";
}

$errors = [];

// Helper function to print/execute query status
function executeSetupQuery($conn, $query, $description, &$errors, $is_cli) {
    if (mysqli_query($conn, $query)) {
        if ($is_cli) {
            echo "[SUCCESS] $description\n";
        } else {
            echo "<div class='p-3 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg flex items-center justify-between'>
                    <span>" . htmlspecialchars($description) . "</span>
                    <span class='font-bold'>[Success]</span>
                  </div>";
        }
    } else {
        $err = mysqli_error($conn);
        $errors[] = "$description: $err";
        if ($is_cli) {
            echo "[FAILED] $description - Error: $err\n";
        } else {
            echo "<div class='p-3 bg-red-50 text-red-800 border border-red-200 rounded-lg flex flex-col'>
                    <div class='flex justify-between font-bold'>
                        <span>" . htmlspecialchars($description) . "</span>
                        <span>[Failed]</span>
                    </div>
                    <span class='text-xs text-red-600 mt-1'>" . htmlspecialchars($err) . "</span>
                  </div>";
        }
    }
}

// 1. Check/Add test columns in internship_applications
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'test_status'");
if (mysqli_num_rows($check_cols) == 0) {
    executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN test_status VARCHAR(50) DEFAULT 'Pending'", "Adding test_status column", $errors, $is_cli);
    executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN test_score INT DEFAULT NULL", "Adding test_score column", $errors, $is_cli);
    executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN test_answers TEXT DEFAULT NULL", "Adding test_answers column", $errors, $is_cli);
} else {
    if ($is_cli) {
        echo "[EXISTS] test_status, test_score, test_answers columns\n";
    } else {
        echo "<div class='p-3 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between'>
                <span>test_status, test_score, test_answers columns</span>
                <span class='font-medium text-slate-500'>[Already exists]</span>
              </div>";
    }
}

// 2. Check/Add smart-form columns in internship_applications
$new_cols = [
    "education_status"    => "VARCHAR(20) DEFAULT NULL",
    "department"          => "VARCHAR(100) DEFAULT NULL",
    "hod_name"            => "VARCHAR(100) DEFAULT NULL",
    "hod_email"           => "VARCHAR(100) DEFAULT NULL",
    "graduation_year"     => "VARCHAR(10) DEFAULT NULL",
    "prev_college_name"   => "VARCHAR(150) DEFAULT NULL",
    "aadhaar_number"      => "VARCHAR(20) DEFAULT NULL",
    "resume_file"         => "VARCHAR(255) DEFAULT NULL",
    "preferred_domain"    => "VARCHAR(100) DEFAULT NULL",
    "project_interests"   => "TEXT DEFAULT NULL",
    "pan_number"          => "VARCHAR(10) DEFAULT NULL",
    "pan_masked"          => "VARCHAR(15) DEFAULT NULL",
    "pan_file"            => "VARCHAR(255) DEFAULT NULL",
    "college_name"        => "VARCHAR(150) DEFAULT NULL",
    "year_of_study"       => "VARCHAR(30) DEFAULT NULL",
    "status"              => "VARCHAR(50) DEFAULT 'Applied'",
    "internship_name"     => "VARCHAR(255) DEFAULT NULL",
    "preferred_duration"  => "VARCHAR(100) DEFAULT NULL",
    "reason_for_applying" => "TEXT DEFAULT NULL",
    "relevant_skills"     => "TEXT DEFAULT NULL",
];
foreach ($new_cols as $col => $definition) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if (mysqli_num_rows($chk) == 0) {
        executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN $col $definition", "Adding $col column", $errors, $is_cli);
    } else {
        if ($is_cli) {
            echo "[EXISTS] $col column\n";
        } else {
            echo "<div class='p-3 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between'>
                    <span>$col column</span>
                    <span class='font-medium text-slate-500'>[Already exists]</span>
                  </div>";
        }
    }
}

// 3. Create application_status_history table
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
executeSetupQuery($conn, $status_history_table, "Creating application_status_history table", $errors, $is_cli);

// 4. Add test_submitted_date column if it doesn't exist
$test_date_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'test_submitted_date'");
if (mysqli_num_rows($test_date_check) == 0) {
    executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN test_submitted_date TIMESTAMP NULL DEFAULT NULL AFTER test_status", "Adding test_submitted_date column", $errors, $is_cli);
} else {
    if ($is_cli) {
        echo "[EXISTS] test_submitted_date column\n";
    } else {
        echo "<div class='p-3 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between'>
                <span>test_submitted_date column</span>
                <span class='font-medium text-slate-500'>[Already exists]</span>
              </div>";
    }
}

// 5. Create email_notifications_log table
$email_log_table = "CREATE TABLE IF NOT EXISTS email_notifications_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    message_text TEXT NOT NULL,
    html_body LONGTEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'Sent',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSetupQuery($conn, $email_log_table, "Creating email_notifications_log table", $errors, $is_cli);

// 6. Create daily_logs table
$daily_logs_table = "CREATE TABLE IF NOT EXISTS daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    internship_id INT NOT NULL,
    tasks_completed TEXT NOT NULL,
    time_spent DECIMAL(4,2) NOT NULL,
    focus_level VARCHAR(50) NOT NULL,
    issues_faced TEXT DEFAULT NULL,
    next_plan TEXT DEFAULT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSetupQuery($conn, $daily_logs_table, "Creating daily_logs table", $errors, $is_cli);

// 7. Create student_notifications table
$student_notif_table = "CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0
)";
executeSetupQuery($conn, $student_notif_table, "Creating student_notifications table", $errors, $is_cli);

// 8. Create mentor_feedback table
$mentor_feedback_table = "CREATE TABLE IF NOT EXISTS mentor_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feedback_title VARCHAR(200) DEFAULT NULL,
    given_by VARCHAR(100) DEFAULT NULL,
    comments TEXT DEFAULT NULL,
    rating INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSetupQuery($conn, $mentor_feedback_table, "Creating mentor_feedback table", $errors, $is_cli);

if (!$is_cli) {
    echo "</div>";

    if (empty($errors)) {
        echo "<div class='mt-6 p-4 bg-emerald-100 border border-emerald-300 text-emerald-800 font-bold rounded-lg text-center'>
                ✓ Database setup completed successfully!
              </div>";
    } else {
        echo "<div class='mt-6 p-4 bg-red-100 border border-red-300 text-red-800 font-bold rounded-lg'>
                <p class='text-center'>⚠ Some errors occurred during setup:</p>
                <ul class='list-disc list-inside mt-2 font-normal text-sm'>";
        foreach ($errors as $e) {
            echo "<li>" . htmlspecialchars($e) . "</li>";
        }
        echo "</ul>
              </div>";
    }

    echo "<div class='mt-6 text-center'>
            <a href='login.php' class='px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors inline-block'>Go to Login</a>
          </div>
        </div>
    </body>
    </html>";
} else {
    echo "\n";
    if (empty($errors)) {
        echo "SUCCESS: Database setup completed successfully!\n";
    } else {
        echo "FAILED: Some errors occurred during setup:\n";
        foreach ($errors as $e) {
            echo " - $e\n";
        }
    }
}
