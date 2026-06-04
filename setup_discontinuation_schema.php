<?php
/**
 * setup_discontinuation_schema.php
 * Database schema setup for mentor-initiated student discontinuation workflow.
 * 
 * Creates:
 * - Fields in internship_applications table for status tracking and audit
 * - internship_status_history table for audit trail
 * - Safely handles existing columns
 */

include_once __DIR__ . '/db.php';

function add_column_if_not_exists($conn, $table, $column, $definition) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
    if (!$check || mysqli_num_rows($check) === 0) {
        $result = mysqli_query($conn, "ALTER TABLE $table ADD COLUMN $column $definition");
        if ($result) {
            echo "Added column `$column` to `$table`\n";
        } else {
            echo "Error adding column `$column`: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Column `$column` already exists in `$table`\n";
    }
}

function setup_discontinuation_schema($conn) {
    // 1. Add columns to internship_applications table
    add_column_if_not_exists($conn, 'internship_applications', 'internship_status', 
        "VARCHAR(50) DEFAULT 'Active' COMMENT 'Active, On Hold, Discontinued, Removed, Completed'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'report_reason', 
        "VARCHAR(255) COMMENT 'Reason mentor submitted report'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'mentor_remarks', 
        "TEXT COMMENT 'Detailed remarks from mentor'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'reported_by', 
        "INT COMMENT 'User ID of mentor who submitted report'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'reported_date', 
        "TIMESTAMP NULL COMMENT 'Date mentor submitted report'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'admin_decision', 
        "VARCHAR(50) COMMENT 'Admin action taken: Keep Active, On Hold, Discontinued, Removed'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'admin_remarks', 
        "TEXT COMMENT 'Admin comments on the decision'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'approved_by_admin', 
        "INT COMMENT 'User ID of admin who made decision'");
    
    add_column_if_not_exists($conn, 'internship_applications', 'approved_date', 
        "TIMESTAMP NULL COMMENT 'Date admin made decision'");

    // 2. Create audit trail table
    $audit_table_sql = "CREATE TABLE IF NOT EXISTS internship_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        old_status VARCHAR(50) DEFAULT NULL,
        new_status VARCHAR(50) NOT NULL,
        report_reason VARCHAR(255) DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        changed_by INT COMMENT 'User ID who made the change',
        changed_by_role VARCHAR(50) COMMENT 'Role of user who made change (mentor/admin)',
        change_type VARCHAR(50) COMMENT 'Type of change (report/decision)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE,
        INDEX idx_app_id (application_id),
        INDEX idx_created_at (created_at)
    )";
    
    if (mysqli_query($conn, $audit_table_sql)) {
        echo "Audit trail table `internship_status_history` ready\n";
    } else {
        echo "Error creating audit table: " . mysqli_error($conn) . "\n";
    }
    
    echo "Discontinuation schema setup complete.\n";
}

// Auto-run if accessed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    setup_discontinuation_schema($conn);
    echo "<pre>Setup complete. You can now use the discontinuation workflow.</pre>";
}
?>
