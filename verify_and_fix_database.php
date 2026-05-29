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
        'preferred_duration' => "VARCHAR(100) DEFAULT NULL",
        'reason_for_applying' => "TEXT DEFAULT NULL",
        'relevant_skills' => "TEXT DEFAULT NULL"
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
} else {
    echo "<p class='text-red-600 font-semibold'>✗ Table does not exist - Please run db.php first</p>";
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
} else {
    echo "<p class='text-red-600 font-semibold'>✗ Table does not exist - Please run db.php first</p>";
    $checks_failed++;
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
