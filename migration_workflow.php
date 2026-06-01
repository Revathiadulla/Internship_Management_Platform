<?php
// migration_workflow.php
// Run once to add required columns/tables for the new workflow.
require_once __DIR__ . '/db.php';
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
// Add columns to internships safely


function addColumnIfNotExists($table, $colName, $definition) {
    global $conn;
    $checkSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='$colName'";
    $res = mysqli_query($conn, $checkSql);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if (intval($row['cnt']) === 0) {
            run_query("ALTER TABLE $table ADD COLUMN $definition");
        }
    }
}
?>
addColumnIfNotExists('internships', 'title', 'title VARCHAR(255) NOT NULL');
addColumnIfNotExists('internships', 'description', 'description TEXT NOT NULL');
addColumnIfNotExists('internships', 'technology_stack', 'technology_stack VARCHAR(255) NULL');
addColumnIfNotExists('internships', 'required_skills', 'required_skills VARCHAR(255) NULL');
addColumnIfNotExists('internships', 'duration', 'duration VARCHAR(50) NULL');
addColumnIfNotExists('internships', 'mode', "mode ENUM('Remote','Hybrid','Onsite') DEFAULT 'Remote'");
addColumnIfNotExists('internships', 'slots', 'slots INT DEFAULT 1');
addColumnIfNotExists('internships', 'difficulty_level', 'difficulty_level VARCHAR(50) NULL');
addColumnIfNotExists('internships', 'eligibility_criteria', 'eligibility_criteria TEXT NULL');
addColumnIfNotExists('internships', 'start_date', 'start_date DATE NULL');
addColumnIfNotExists('internships', 'end_date', 'end_date DATE NULL');
addColumnIfNotExists('internships', 'test_required', "test_required ENUM('Yes','No') DEFAULT 'No'");
addColumnIfNotExists('internships', 'passing_score', 'passing_score INT DEFAULT 60');
addColumnIfNotExists('internships', 'status', "status ENUM('Pending Approval','Active','Rejected') DEFAULT 'Pending Approval'");
addColumnIfNotExists('internships', 'created_by', 'created_by INT');
addColumnIfNotExists('internships', 'approved_by', 'approved_by INT NULL');
addColumnIfNotExists('internships', 'approved_at', 'approved_at DATETIME NULL');
addColumnIfNotExists('internships', 'admin_remarks', 'admin_remarks TEXT NULL');

// 2. Add columns to internship_applications
// Add columns to internship_applications safely
addColumnIfNotExists('internship_applications', 'test_score', 'test_score INT NULL');
addColumnIfNotExists('internship_applications', 'test_result', "test_result ENUM('Passed','Failed') NULL");
addColumnIfNotExists('internship_applications', 'education_status', "education_status ENUM('Pursuing','Graduated') NULL");
addColumnIfNotExists('internship_applications', 'hod_name', 'hod_name VARCHAR(255) NULL');
addColumnIfNotExists('internship_applications', 'hod_email', 'hod_email VARCHAR(255) NULL');
addColumnIfNotExists('internship_applications', 'hod_phone', 'hod_phone VARCHAR(50) NULL');
addColumnIfNotExists('internship_applications', 'hod_approval_status', "hod_approval_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending'");
addColumnIfNotExists('internship_applications', 'hod_approval_token', 'hod_approval_token VARCHAR(255) NULL');
addColumnIfNotExists('internship_applications', 'hod_approval_sent_at', 'hod_approval_sent_at DATETIME NULL');
addColumnIfNotExists('internship_applications', 'hod_approved_at', 'hod_approved_at DATETIME NULL');
addColumnIfNotExists('internship_applications', 'hod_remarks', 'hod_remarks TEXT NULL');
addColumnIfNotExists('internship_applications', 'selected_by', 'selected_by INT NULL');
addColumnIfNotExists('internship_applications', 'selected_at', 'selected_at DATETIME NULL');
addColumnIfNotExists('internship_applications', 'assigned_by', 'assigned_by INT NULL');
addColumnIfNotExists('internship_applications', 'assigned_at', 'assigned_at DATETIME NULL');

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
