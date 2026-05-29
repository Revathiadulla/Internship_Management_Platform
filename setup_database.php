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
// 1b. Check if name-related columns exist in internship_applications
$chk_name_cols = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
$has_name_col = false;
if ($chk_name_cols) {
    while ($row = mysqli_fetch_assoc($chk_name_cols)) {
        $col_name = strtolower($row['Field']);
        if ($col_name === 'full_name' || $col_name === 'name' || $col_name === 'first_name') {
            $has_name_col = true;
            break;
        }
    }
}
if (!$has_name_col) {
    executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN full_name VARCHAR(150) DEFAULT NULL", "Adding fallback full_name column to internship_applications", $errors, $is_cli);
} else {
    if ($is_cli) {
        echo "[EXISTS] name-related column in internship_applications\n";
    } else {
        echo "<div class='p-3 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between'>
                <span>Name-related column in internship_applications</span>
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
    "aadhaar_card_file"   => "VARCHAR(255) DEFAULT NULL",
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
    application_id INT DEFAULT NULL,
    tasks_completed TEXT NOT NULL,
    time_spent DECIMAL(4,2) NOT NULL,
    focus_level VARCHAR(50) NOT NULL,
    issues_faced TEXT DEFAULT NULL,
    next_plan TEXT DEFAULT NULL,
    log_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Submitted',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    hr_review_status VARCHAR(50) DEFAULT 'Pending',
    hr_remarks TEXT DEFAULT NULL,
    hr_reviewed_by INT DEFAULT NULL,
    hr_reviewed_at TIMESTAMP NULL DEFAULT NULL,
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

// 9. Create users table and default admin user
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'hr', 'coordinator', 'mentor', 'company', 'admin') NOT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSetupQuery($conn, $users_table, "Creating users table", $errors, $is_cli);

// Check if phone column needs to be added dynamically to users table (if users table already existed)
$chk_phone = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
if (mysqli_num_rows($chk_phone) == 0) {
    executeSetupQuery($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(15) DEFAULT NULL AFTER role", "Adding phone column to users table", $errors, $is_cli);
}

// Check if profile_photo column needs to be added dynamically to users table
$chk_photo = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_photo'");
if (mysqli_num_rows($chk_photo) == 0) {
    executeSetupQuery($conn, "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER phone", "Adding profile_photo column to users table", $errors, $is_cli);
}

// 9b. Create mentor_assignments table
$mentor_assignments_table = "CREATE TABLE IF NOT EXISTS mentor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    student_id INT NOT NULL,
    internship_id INT NULL,
    project_id INT NULL,
    application_id INT NULL,
    assigned_by INT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mentor (mentor_id),
    INDEX idx_student (student_id),
    INDEX idx_application (application_id),
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSetupQuery($conn, $mentor_assignments_table, "Creating mentor_assignments table", $errors, $is_cli);


// 10. Create password_resets table
$password_resets_table = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(255) NOT NULL,
    send_method VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSetupQuery($conn, $password_resets_table, "Creating password_resets table", $errors, $is_cli);

// 10b. Create company-related and hiring requests tables
$company_profiles_table = "CREATE TABLE IF NOT EXISTS company_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(150) NOT NULL,
    industry_type VARCHAR(100) DEFAULT NULL,
    website VARCHAR(150) DEFAULT NULL,
    company_size VARCHAR(50) DEFAULT NULL,
    plan_selected VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
executeSetupQuery($conn, $company_profiles_table, "Creating company_profiles table", $errors, $is_cli);

$company_shortlists_table = "CREATE TABLE IF NOT EXISTS company_shortlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    candidate_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_candidate (company_id, candidate_id)
)";
executeSetupQuery($conn, $company_shortlists_table, "Creating company_shortlists table", $errors, $is_cli);

$company_contacts_table = "CREATE TABLE IF NOT EXISTS company_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    candidate_id INT NOT NULL,
    message TEXT DEFAULT NULL,
    contacted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_contact (company_id, candidate_id)
)";
executeSetupQuery($conn, $company_contacts_table, "Creating company_contacts table", $errors, $is_cli);

$hiring_requests_table = "CREATE TABLE IF NOT EXISTS hiring_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    openings INT DEFAULT 1,
    description TEXT DEFAULT NULL,
    requirements TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)";
executeSetupQuery($conn, $hiring_requests_table, "Creating hiring_requests table", $errors, $is_cli);

$activity_logs_table = "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";
executeSetupQuery($conn, $activity_logs_table, "Creating activity_logs table", $errors, $is_cli);

$company_notif_table = "CREATE TABLE IF NOT EXISTS company_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)";
executeSetupQuery($conn, $company_notif_table, "Creating company_notifications table", $errors, $is_cli);

$company_views_table = "CREATE TABLE IF NOT EXISTS company_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    candidate_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_candidate_view (company_id, candidate_id)
)";
executeSetupQuery($conn, $company_views_table, "Creating company_views table", $errors, $is_cli);

// 11. Add Talent Pool columns to internship_applications
$talent_pool_cols = [
    'in_talent_pool'        => "TINYINT(1) DEFAULT 0",
    'is_featured'           => "TINYINT(1) DEFAULT 0",
    'placement_status'      => "VARCHAR(100) DEFAULT 'Unplaced'",
    'shortlisted_companies' => "TEXT DEFAULT NULL",
    'performance_score'     => "DECIMAL(5,2) DEFAULT NULL",
    'tech_stack'            => "VARCHAR(255) DEFAULT NULL",
    'skills'                => "TEXT DEFAULT NULL",
    'internship_duration'   => "VARCHAR(50) DEFAULT NULL",
    'talent_pool_status'    => "VARCHAR(20) DEFAULT 'No'",
    'mentor_evaluation'     => "VARCHAR(50) DEFAULT 'Approved'",
    'certificate_status'    => "VARCHAR(50) DEFAULT 'Completed'",
];
foreach ($talent_pool_cols as $col => $def) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        executeSetupQuery($conn, "ALTER TABLE internship_applications ADD COLUMN $col $def", "Adding talent pool column: $col", $errors, $is_cli);
    } else {
        if ($is_cli) {
            echo "[EXISTS] talent pool column: $col\n";
        } else {
            echo "<div class='p-3 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between'>
                    <span>talent pool column: $col</span>
                    <span class='font-medium text-slate-500'>[Already exists]</span>
                  </div>";
        }
    }
}


$admin_email = 'imp.webportal2026@gmail.com';
$check_admin = mysqli_query($conn, "SELECT id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $admin_email) . "'");
if ($check_admin) {
    if (mysqli_num_rows($check_admin) == 0) {
        $admin_password = password_hash('Imp@2026', PASSWORD_DEFAULT);
        $admin_full_name = 'Admin';
        $admin_role = 'admin';
        $insert_admin_query = "INSERT INTO users (full_name, email, password, role) VALUES (
            '" . mysqli_real_escape_string($conn, $admin_full_name) . "',
            '" . mysqli_real_escape_string($conn, $admin_email) . "',
            '" . mysqli_real_escape_string($conn, $admin_password) . "',
            '" . mysqli_real_escape_string($conn, $admin_role) . "'
        )";
        executeSetupQuery($conn, $insert_admin_query, "Inserting default admin user", $errors, $is_cli);
    } else {
        $admin_password = password_hash('Imp@2026', PASSWORD_DEFAULT);
        $admin_full_name = 'Admin';
        $admin_role = 'admin';
        $update_admin_query = "UPDATE users SET 
            full_name = '" . mysqli_real_escape_string($conn, $admin_full_name) . "',
            password = '" . mysqli_real_escape_string($conn, $admin_password) . "',
            role = '" . mysqli_real_escape_string($conn, $admin_role) . "'
            WHERE email = '" . mysqli_real_escape_string($conn, $admin_email) . "'";
        executeSetupQuery($conn, $update_admin_query, "Updating default admin user credentials", $errors, $is_cli);
    }
} else {
    $err = mysqli_error($conn);
    $errors[] = "Checking admin user existence: $err";
    if ($is_cli) {
        echo "[FAILED] Checking admin user existence - Error: $err\n";
    } else {
        echo "<div class='p-3 bg-red-50 text-red-800 border border-red-200 rounded-lg flex flex-col'>
                <div class='flex justify-between font-bold'>
                    <span>Checking admin user existence</span>
                    <span>[Failed]</span>
                </div>
                <span class='text-xs text-red-600 mt-1'>" . htmlspecialchars($err) . "</span>
              </div>";
    }
}

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
