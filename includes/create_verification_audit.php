<?php
/**
 * create_verification_audit.php
 * Script to create the verification_audit table if it does not already exist.
 * Run via CLI: php create_verification_audit.php
 */
require_once __DIR__ . '/db.php';

$create_sql = "CREATE TABLE IF NOT EXISTS verification_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    previous_status VARCHAR(50) NOT NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $create_sql)) {
    echo "verification_audit table ensured successfully.\n";
} else {
    echo "Error creating verification_audit table: " . mysqli_error($conn) . "\n";
}
?>
