<?php
// migration_workflow.php
// Run once to add required columns/tables for the new workflow.
require_once __DIR__ . '/includes/db.php';
global $conn;

function run_query($sql) {
    global $conn;
    if ($conn->query($sql) === TRUE) {
        echo "Success: $sql\n";
    } else {
        echo "Error executing: $sql\n" . $conn->error . "\n";
    }
}

// 1. Add columns to internships
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS description TEXT NOT NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS technology_stack VARCHAR(255) NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS required_skills VARCHAR(255) NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS duration VARCHAR(50) NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS mode ENUM('Remote','Hybrid','Onsite') DEFAULT 'Remote'");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS slots INT DEFAULT 1");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS difficulty_level VARCHAR(50) NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS eligibility_criteria TEXT NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS start_date DATE NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS end_date DATE NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS test_required ENUM('Yes','No') DEFAULT 'No'");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS passing_score INT DEFAULT 60");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS status ENUM('Pending Approval','Active','Rejected') DEFAULT 'Pending Approval'");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS created_by INT");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS approved_by INT NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL");
run_query("ALTER TABLE internships ADD COLUMN IF NOT EXISTS admin_remarks TEXT NULL");

// 2. Add columns to internship_applications
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS test_score INT NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS test_result ENUM('Passed','Failed') NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS education_status ENUM('Pursuing','Graduated') NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_name VARCHAR(255) NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_email VARCHAR(255) NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_phone VARCHAR(50) NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_approval_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending'");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_approval_token VARCHAR(255) NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_approval_sent_at DATETIME NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_approved_at DATETIME NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS hod_remarks TEXT NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS selected_by INT NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS selected_at DATETIME NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS assigned_by INT NULL");
run_query("ALTER TABLE internship_applications ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL");

// 3. Create mentor_assignments if not exists
run_query("CREATE TABLE IF NOT EXISTS mentor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    student_id INT NOT NULL,
    application_id INT NOT NULL,
    internship_id INT NOT NULL,
    status ENUM('Active','Removed') DEFAULT 'Active',
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// 4. Create dropout_requests if not exists
run_query("CREATE TABLE IF NOT EXISTS dropout_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    application_id INT NOT NULL,
    internship_id INT NOT NULL,
    mentor_id INT NOT NULL,
    reason TEXT NOT NULL,
    last_active_date DATE NULL,
    remarks TEXT NULL,
    status ENUM('Pending Admin Action','Approved','Rejected') DEFAULT 'Pending Admin Action',
    admin_remarks TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL
) ENGINE=InnoDB");

// 5. Create status_audit if not exists
run_query("CREATE TABLE IF NOT EXISTS status_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity VARCHAR(50),
    entity_id INT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    extra TEXT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "Migration completed.\n";
?>
