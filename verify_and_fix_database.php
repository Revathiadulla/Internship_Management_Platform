<?php
/**
 * Database Verification and Fix Script
 * Ensures all required tables and columns exist for the status system
 */

include "db.php";

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Verification</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class='bg-slate-50 p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-2xl shadow-lg p-8 mb-6'>
            <h1 class='text-3xl font-extrabold text-slate-900 mb-2'>Database Verification & Fix</h1>
            <p class='text-slate-600'>Checking and fixing database structure for the status system...</p>
        </div>
        <div class='bg-white rounded-xl shadow-sm p-6 border border-slate-200'>";

$fixes_applied = 0;
$checks_passed = 0;
$checks_failed = 0;

// Check 1: internship_applications table exists
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>1. Checking internship_applications table</h2>";
$check = mysqli_query($conn, "SHOW TABLES LIKE 'internship_applications'");
if (mysqli_num_rows($check) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
    
    // Check required columns
    $required_columns = [
        'status' => "VARCHAR(50) DEFAULT 'Applied'",
        'education_status' => "VARCHAR(20) DEFAULT 'Pursuing'",
        'internship_name' => "VARCHAR(255) DEFAULT NULL",
        'hod_phone' => "VARCHAR(20) DEFAULT NULL",
        'test_result' => "VARCHAR(100) DEFAULT NULL",
        'hr_review_status' => "VARCHAR(50) DEFAULT 'Pending'",
        'hod_approval_status' => "VARCHAR(50) DEFAULT 'Pending'",
        'hod_approval_sent_at' => "TIMESTAMP DEFAULT NULL",
        'hod_approved_at' => "TIMESTAMP DEFAULT NULL",
        'hod_remarks' => "TEXT DEFAULT NULL",
        'selected_by' => "INT DEFAULT NULL",
        'selected_at' => "TIMESTAMP DEFAULT NULL",
        'hod_token' => "VARCHAR(255) DEFAULT NULL",
        'exam_link' => "TEXT DEFAULT NULL",
        'exam_link_sent_at' => "DATETIME DEFAULT NULL",
        'exam_status' => "VARCHAR(50) DEFAULT 'Pending'",
        'exam_qualified_at' => "DATETIME DEFAULT NULL",
        'qualified_by_hr' => "INT DEFAULT NULL",
        'confirmation_letter_sent_at' => "DATETIME DEFAULT NULL",
        'exam_name' => "VARCHAR(255) DEFAULT NULL",
        'exam_remarks' => "TEXT DEFAULT NULL"
    ];
    
    foreach ($required_columns as $col => $def) {
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
        if (mysqli_num_rows($col_check) == 0) {
            echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col</p>";
            mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $col $def");
            $fixes_applied++;
        } else {
            echo "<p class='text-slate-600 text-sm ml-4'>✓ Column exists: $col</p>";
        }
    }
    
    // Ensure at least one name-related column exists in internship_applications to prevent SQL crashes
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
        echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing fallback column: full_name</p>";
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN full_name VARCHAR(150) DEFAULT NULL");
        $fixes_applied++;
    } else {
        echo "<p class='text-slate-600 text-sm ml-4'>✓ Name-related column exists in internship_applications</p>";
        $checks_passed++;
    }
} else {
    echo "<p class='text-red-600 font-semibold'>✗ Table does not exist - Please run db.php first</p>";
    $checks_failed++;
}
echo "</div>";

// Check 1b: Checking internships table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>1b. Checking internships table</h2>";
$check_internships = mysqli_query($conn, "SHOW TABLES LIKE 'internships'");
if (mysqli_num_rows($check_internships) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
    
    $required_internship_columns = [
        'approval_status' => "VARCHAR(50) DEFAULT 'Pending Approval'",
        'approved_by' => "INT DEFAULT NULL",
        'approved_at' => "TIMESTAMP DEFAULT NULL",
        'admin_remarks' => "TEXT DEFAULT NULL"
    ];
    
    foreach ($required_internship_columns as $col => $def) {
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE '$col'");
        if (mysqli_num_rows($col_check) == 0) {
            echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column to internships: $col</p>";
            mysqli_query($conn, "ALTER TABLE internships ADD COLUMN $col $def");
            $fixes_applied++;
        } else {
            echo "<p class='text-slate-600 text-sm ml-4'>✓ Column exists: $col</p>";
        }
    }
} else {
    echo "<p class='text-red-600 font-semibold'>✗ Table does not exist</p>";
    $checks_failed++;
}
echo "</div>";


// Check 2: application_status_history table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>2. Checking application_status_history table</h2>";
$check = mysqli_query($conn, "SHOW TABLES LIKE 'application_status_history'");
if (mysqli_num_rows($check) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table...</p>";
    $create_sql = "CREATE TABLE application_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        old_status VARCHAR(50) DEFAULT NULL,
        new_status VARCHAR(50) NOT NULL,
        updated_by_role VARCHAR(50) NOT NULL,
        updated_by_name VARCHAR(100) NOT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_application (application_id),
        INDEX idx_created (created_at)
    )";
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Check 3: student_profiles table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>3. Checking student_profiles table</h2>";
$check = mysqli_query($conn, "SHOW TABLES LIKE 'student_profiles'");
if (mysqli_num_rows($check) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
    
    // Check for required HOD and phone columns
    $required_sp_columns = [
        'phone' => "VARCHAR(20) DEFAULT NULL",
        'hod_name' => "VARCHAR(100) DEFAULT NULL",
        'hod_phone' => "VARCHAR(20) DEFAULT NULL",
        'hod_email' => "VARCHAR(100) DEFAULT NULL"
    ];
    
    foreach ($required_sp_columns as $col => $def) {
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
        if (mysqli_num_rows($col_check) == 0) {
            echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col</p>";
            mysqli_query($conn, "ALTER TABLE student_profiles ADD COLUMN $col $def");
            $fixes_applied++;
        } else {
            echo "<p class='text-slate-600 text-sm ml-4'>✓ Column exists: $col</p>";
        }
    }
} else {
    echo "<p class='text-red-600 font-semibold'>✗ Table does not exist - Please run db.php first</p>";
    $checks_failed++;
}
echo "</div>";

// Check 3b: users table phone column
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>3b. Checking users table</h2>";
$check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($check_users) > 0) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
    if (mysqli_num_rows($col_check) == 0) {
        echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: phone</p>";
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        $fixes_applied++;
    } else {
        echo "<p class='text-slate-600 text-sm ml-4'>✓ Column exists: phone</p>";
        $checks_passed++;
    }
}
echo "</div>";

// Check 4: Update any old statuses to new format
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>4. Migrating old statuses to new format</h2>";

$status_migrations = [
    'HR Screening' => 'Applied',
    'HR Review' => 'HR Round',
    'HR Approved' => 'HR Round',
    'Waiting for HOD Approval' => 'HR Round',
    'Test Pending' => 'Applied',
    'Approved' => 'Selected',
    'Accepted' => 'Selected'
];

$migrated = 0;
foreach ($status_migrations as $old => $new) {
    $update_sql = "UPDATE internship_applications SET status = '$new' WHERE status = '$old'";
    $result = mysqli_query($conn, $update_sql);
    if ($result && mysqli_affected_rows($conn) > 0) {
        echo "<p class='text-blue-600 text-sm'>→ Migrated " . mysqli_affected_rows($conn) . " applications from '$old' to '$new'</p>";
        $migrated += mysqli_affected_rows($conn);
    }
}

if ($migrated > 0) {
    echo "<p class='text-emerald-600 font-semibold mt-2'>✓ Migrated $migrated applications to new status format</p>";
    $fixes_applied++;
} else {
    echo "<p class='text-slate-600 font-semibold'>✓ No status migrations needed</p>";
}
echo "</div>";

// Check 5: Verify status values
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>5. Current status distribution</h2>";
$status_sql = "SELECT status, COUNT(*) as count FROM internship_applications GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);

if (mysqli_num_rows($status_result) > 0) {
    echo "<div class='grid grid-cols-2 gap-3 mt-3'>";
    while ($stat = mysqli_fetch_assoc($status_result)) {
        $valid_statuses = ['Applied', 'Test Completed', 'HR Round', 'HOD Approved', 'Selected', 'Rejected'];
        $is_valid = in_array($stat['status'], $valid_statuses);
        $badge_color = $is_valid ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200';
        
        echo "<div class='flex items-center justify-between p-3 rounded-lg border $badge_color'>";
        echo "<span class='font-semibold'>" . htmlspecialchars($stat['status']) . "</span>";
        echo "<span class='text-2xl font-bold'>" . $stat['count'] . "</span>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p class='text-slate-500'>No applications in database yet.</p>";
}
echo "</div>";

// Check 6: Check/Add status column in daily_logs
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>6. Checking daily_logs status column</h2>";
$check_dl = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE 'status'");
if (mysqli_num_rows($check_dl) == 0) {
    echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: status in daily_logs</p>";
    if (mysqli_query($conn, "ALTER TABLE daily_logs ADD COLUMN status VARCHAR(50) DEFAULT 'Submitted'")) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Column status added successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add status column: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
} else {
    echo "<p class='text-emerald-600 font-semibold'>✓ Column status exists in daily_logs</p>";
    $checks_passed++;
}
echo "</div>";

// Check 6b: Check/Add application_id column in daily_logs
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>6b. Checking daily_logs application_id column</h2>";
$check_dl_app = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE 'application_id'");
if (mysqli_num_rows($check_dl_app) == 0) {
    echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: application_id in daily_logs</p>";
    if (mysqli_query($conn, "ALTER TABLE daily_logs ADD COLUMN application_id INT NULL")) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Column application_id added successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add application_id column: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
} else {
    echo "<p class='text-emerald-600 font-semibold'>✓ Column application_id exists in daily_logs</p>";
    $checks_passed++;
}
echo "</div>";

// Check 6c: Check/Add idx_daily_logs_application index in daily_logs
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>6c. Checking daily_logs application_id indexes</h2>";
$index_check1 = mysqli_query($conn, "SHOW INDEX FROM daily_logs WHERE Key_name = 'idx_daily_logs_application'");
if (mysqli_num_rows($index_check1) == 0) {
    echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing index: idx_daily_logs_application in daily_logs</p>";
    if (mysqli_query($conn, "ALTER TABLE daily_logs ADD INDEX idx_daily_logs_application (application_id)")) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Index idx_daily_logs_application added successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add index: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
} else {
    echo "<p class='text-emerald-600 font-semibold ml-4'>✓ Index idx_daily_logs_application exists</p>";
    $checks_passed++;
}

$index_check2 = mysqli_query($conn, "SHOW INDEX FROM daily_logs WHERE Key_name = 'idx_daily_logs_application_id'");
if (mysqli_num_rows($index_check2) == 0) {
    echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing index: idx_daily_logs_application_id in daily_logs</p>";
    if (mysqli_query($conn, "ALTER TABLE daily_logs ADD INDEX idx_daily_logs_application_id (application_id)")) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Index idx_daily_logs_application_id added successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add index: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
} else {
    echo "<p class='text-emerald-600 font-semibold ml-4'>✓ Index idx_daily_logs_application_id exists</p>";
    $checks_passed++;
}
echo "</div>";

// Check 6d: Checking daily_logs HR review columns
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>6d. Checking daily_logs HR review columns</h2>";
$hr_cols = [
    'hr_review_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hr_remarks' => "TEXT DEFAULT NULL",
    'hr_reviewed_by' => "INT DEFAULT NULL",
    'hr_reviewed_at' => "TIMESTAMP NULL DEFAULT NULL"
];
foreach ($hr_cols as $col => $def) {
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE '$col'");
    if (mysqli_num_rows($check_col) == 0) {
        echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col in daily_logs</p>";
        if (mysqli_query($conn, "ALTER TABLE daily_logs ADD COLUMN $col $def")) {
            echo "<p class='text-emerald-600 text-sm ml-4'>✓ Column $col added successfully</p>";
            $fixes_applied++;
        } else {
            echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add column $col: " . mysqli_error($conn) . "</p>";
            $checks_failed++;
        }
    } else {
        echo "<p class='text-emerald-600 font-semibold ml-4'>✓ Column $col exists</p>";
        $checks_passed++;
    }
}
echo "</div>";

// Check 7: Check/Add team columns in internship_applications
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>7. Checking team assignment columns in internship_applications</h2>";
$team_cols = [
    'team_name' => "VARCHAR(100) DEFAULT NULL",
    'mentor_id' => "INT DEFAULT NULL",
    'team_status' => "VARCHAR(50) DEFAULT 'Active'",
    'project_type' => "VARCHAR(100) DEFAULT NULL",
    'project_subtype' => "VARCHAR(100) DEFAULT NULL"
];
foreach ($team_cols as $col => $def) {
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if (mysqli_num_rows($check_col) == 0) {
        echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col</p>";
        if (mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $col $def")) {
            echo "<p class='text-emerald-600 text-sm ml-4'>✓ Column $col added successfully</p>";
            $fixes_applied++;
        } else {
            echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add column $col: " . mysqli_error($conn) . "</p>";
            $checks_failed++;
        }
    } else {
        echo "<p class='text-emerald-600 font-semibold ml-4'>✓ Column $col exists</p>";
        $checks_passed++;
    }
}
echo "</div>";

// Check 8: Check/Add project posting columns in internships
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>8. Checking project posting columns in internships</h2>";
$internship_cols = [
    'project_title' => "VARCHAR(255) DEFAULT NULL",
    'task_title' => "VARCHAR(255) DEFAULT NULL",
    'description' => "TEXT DEFAULT NULL",
    'project_type' => "VARCHAR(100) DEFAULT NULL",
    'project_subtype' => "VARCHAR(100) DEFAULT NULL",
    'technology_stack' => "TEXT DEFAULT NULL",
    'difficulty_level' => "VARCHAR(50) DEFAULT 'Medium'",
    'assigned_mentor' => "INT DEFAULT NULL",
    'openings' => "INT DEFAULT 1",
    'start_date' => "DATE DEFAULT NULL",
    'end_date' => "DATE DEFAULT NULL",
    'coordinator_id' => "INT DEFAULT NULL",
    'submission_date' => "DATE DEFAULT NULL"
];
foreach ($internship_cols as $col => $def) {
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE '$col'");
    if (mysqli_num_rows($check_col) == 0) {
        echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col</p>";
        if (mysqli_query($conn, "ALTER TABLE internships ADD COLUMN $col $def")) {
            echo "<p class='text-emerald-600 text-sm ml-4'>✓ Column $col added successfully</p>";
            $fixes_applied++;
        } else {
            echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to add column $col: " . mysqli_error($conn) . "</p>";
            $checks_failed++;
        }
    } else {
        echo "<p class='text-emerald-600 font-semibold ml-4'>✓ Column $col exists</p>";
        $checks_passed++;
    }
}
echo "</div>";

// Check 9: internship_phases table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>9. Checking internship_phases table</h2>";
$check = mysqli_query($conn, "SHOW TABLES LIKE 'internship_phases'");
if (mysqli_num_rows($check) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table internship_phases...</p>";
    $create_sql = "CREATE TABLE internship_phases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NOT NULL,
        phase_number INT NOT NULL,
        phase_name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_internship (internship_id),
        INDEX idx_phase (phase_number)
    )";
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Check 10: mentor_assignments table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>10. Checking mentor_assignments table</h2>";
$check = mysqli_query($conn, "SHOW TABLES LIKE 'mentor_assignments'");
if (mysqli_num_rows($check) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
    
    // Check required columns
    $required_columns = [
        'internship_id' => "INT DEFAULT NULL",
        'project_id' => "INT DEFAULT NULL"
    ];
    
    foreach ($required_columns as $col => $def) {
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM mentor_assignments LIKE '$col'");
        if (mysqli_num_rows($col_check) == 0) {
            echo "<p class='text-orange-600 text-sm ml-4'>⚠ Adding missing column: $col</p>";
            mysqli_query($conn, "ALTER TABLE mentor_assignments ADD COLUMN $col $def");
            $fixes_applied++;
        } else {
            echo "<p class='text-slate-600 text-sm ml-4'>✓ Column exists: $col</p>";
        }
    }
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table mentor_assignments...</p>";
    $create_sql = "CREATE TABLE mentor_assignments (
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
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Check 11: Checking hiring_requests table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>11. Checking hiring_requests table</h2>";
$check_hr = mysqli_query($conn, "SHOW TABLES LIKE 'hiring_requests'");
if (mysqli_num_rows($check_hr) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table hiring_requests...</p>";
    $create_sql = "CREATE TABLE hiring_requests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Check 12: confirmation_letter_templates table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>12. Checking confirmation_letter_templates table</h2>";
$check_cl = mysqli_query($conn, "SHOW TABLES LIKE 'confirmation_letter_templates'");
if (mysqli_num_rows($check_cl) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table confirmation_letter_templates...</p>";
    $create_sql = "CREATE TABLE confirmation_letter_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        logo_path VARCHAR(255) NULL,
        signature_name VARCHAR(255) NULL,
        signature_designation VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
        // Insert default template
        $default_content = "Dear {student_name},\n\nWe are pleased to inform you that your application for the internship position \"{project_title}\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
        $ins = mysqli_query($conn, "INSERT INTO confirmation_letter_templates (template_name, subject, content, signature_name, signature_designation, is_active) VALUES ('Default Template', 'Congratulations! You have been selected for the internship', '" . mysqli_real_escape_string($conn, $default_content) . "', 'HR Team', 'IMP Platform', 1)");
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Check 13: certificate_templates table
echo "<div class='mb-6'>";
echo "<h2 class='text-lg font-bold text-slate-800 mb-3'>13. Checking certificate_templates table</h2>";
$check_ct = mysqli_query($conn, "SHOW TABLES LIKE 'certificate_templates'");
if (mysqli_num_rows($check_ct) > 0) {
    echo "<p class='text-emerald-600 font-semibold'>✓ Table exists</p>";
    $checks_passed++;
} else {
    echo "<p class='text-orange-600 font-semibold'>⚠ Creating table certificate_templates...</p>";
    $create_sql = "CREATE TABLE certificate_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        logo_path VARCHAR(255) NULL,
        signature_name VARCHAR(255) NULL,
        signature_designation VARCHAR(255) NULL,
        seal_image VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (mysqli_query($conn, $create_sql)) {
        echo "<p class='text-emerald-600 text-sm ml-4'>✓ Table created successfully</p>";
        $fixes_applied++;
        // Insert default template
        $default_content = "has successfully completed the internship program as a {project_title} at the {company_name}.";
        $ins = mysqli_query($conn, "INSERT INTO certificate_templates (template_name, content, signature_name, signature_designation, is_active) VALUES ('Default Template', '" . mysqli_real_escape_string($conn, $default_content) . "', 'Program Coordinator', 'IMP Platform Director', 1)");
    } else {
        echo "<p class='text-red-600 text-sm ml-4'>✗ Failed to create table: " . mysqli_error($conn) . "</p>";
        $checks_failed++;
    }
}
echo "</div>";

// Summary
echo "<div class='mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200'>";
echo "<h2 class='text-xl font-bold text-slate-900 mb-4'>Summary</h2>";
echo "<div class='grid grid-cols-3 gap-4 text-center'>";
echo "<div>";
echo "<div class='text-3xl font-extrabold text-emerald-600'>$checks_passed</div>";
echo "<div class='text-sm text-slate-600 mt-1'>Checks Passed</div>";
echo "</div>";
echo "<div>";
echo "<div class='text-3xl font-extrabold text-orange-600'>$fixes_applied</div>";
echo "<div class='text-sm text-slate-600 mt-1'>Fixes Applied</div>";
echo "</div>";
echo "<div>";
echo "<div class='text-3xl font-extrabold text-red-600'>$checks_failed</div>";
echo "<div class='text-sm text-slate-600 mt-1'>Checks Failed</div>";
echo "</div>";
echo "</div>";

if ($checks_failed == 0) {
    echo "<div class='mt-6 p-4 bg-emerald-100 border border-emerald-300 rounded-lg'>";
    echo "<p class='text-emerald-800 font-bold text-center'>✓ Database is ready! The status system should work correctly.</p>";
    echo "</div>";
} else {
    echo "<div class='mt-6 p-4 bg-red-100 border border-red-300 rounded-lg'>";
    echo "<p class='text-red-800 font-bold text-center'>⚠ Some checks failed. Please review the errors above.</p>";
    echo "</div>";
}

echo "<div class='mt-6 flex gap-3 justify-center'>";
echo "<a href='test_status_flow.php' class='px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors'>Test Status System</a>";
echo "<a href='student_applications.php' class='px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg transition-colors'>View Applications</a>";
echo "</div>";

echo "</div>";

echo "</div></div></body></html>";
