<?php
/**
 * workflow_helper.php – central helpers for status audit, workflow logs, and schema safety.
 */

require_once dirname(__DIR__) . '/db.php'; // assumed existing DB connection $conn

/**
 * Ensure the `status_audit` table exists and its `id` column is safe.
 */
function ensure_status_audit_schema() {
    global $conn;
    // Check table existence
    $res = $conn->query("SHOW TABLES LIKE 'status_audit'");
    if ($res->num_rows == 0) {
        $create = "CREATE TABLE status_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            old_status VARCHAR(100) NULL,
            new_status VARCHAR(100) NOT NULL,
            changed_by INT NULL,
            changed_by_role VARCHAR(50) NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($create);
        return;
    }
    // Table exists – verify `id` column
    $colInfo = $conn->query("SHOW COLUMNS FROM status_audit LIKE 'id'");
    if ($colInfo->num_rows > 0) {
        $col = $colInfo->fetch_assoc();
        $extra = $col['Extra'];
        if (strpos($extra, 'auto_increment') === false) {
            $pkCheck = $conn->query("SHOW KEYS FROM status_audit WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($pkCheck && $pkCheck->num_rows === 0) {
                @$conn->query("ALTER TABLE status_audit ADD PRIMARY KEY (id)");
            }
            $conn->query("ALTER TABLE status_audit MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
        return;
    }
    // Check if any AUTO_INCREMENT column already exists
    $other = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'status_audit' " .
        "AND EXTRA LIKE '%auto_increment%'"
    );
    if ($other->num_rows > 0) {
        return;
    }
    // Add id column
    $conn->query("ALTER TABLE status_audit ADD COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
}

/**
 * Ensure the `workflow_logs` table exists and its `id` column is safe.
 */
function ensure_workflow_logs_schema() {
    global $conn;
    $res = $conn->query("SHOW TABLES LIKE 'workflow_logs'");
    if ($res->num_rows == 0) {
        $create = "CREATE TABLE workflow_logs (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            workflow_name VARCHAR(100) NOT NULL,
            action VARCHAR(100) NOT NULL,
            performed_by INT NOT NULL,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            details TEXT NULL
        ) ENGINE=InnoDB";
        $conn->query($create);
        return;
    }
    $colInfo = $conn->query("SHOW COLUMNS FROM workflow_logs LIKE 'id'");
    if ($colInfo->num_rows > 0) {
        $col = $colInfo->fetch_assoc();
        $extra = $col['Extra'];
        if (strpos($extra, 'auto_increment') === false) {
            $other = $conn->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_logs' " .
                "AND COLUMN_NAME <> 'id' AND EXTRA LIKE '%auto_increment%'"
            );
            if ($other->num_rows == 0) {
                $conn->query("ALTER TABLE workflow_logs MODIFY id INT NOT NULL AUTO_INCREMENT");
            }
        }
    }
}

/**
 * Ensure `internship_applications` has the new columns and safe `id`.
 */
function ensure_internship_applications_schema() {
    global $conn;
    // Add new columns if they do not exist
    $newCols = [
        'applied_subtype' => "VARCHAR(100) NOT NULL AFTER internship_id",
        'confirmation_letter_path' => "VARCHAR(255) NULL AFTER applied_subtype",
        'assigned_project_id' => "INT NULL AFTER confirmation_letter_path",
        'test_submitted' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_project_id"
    ];
    foreach ($newCols as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM internship_applications LIKE '{$col}'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE internship_applications ADD COLUMN {$col} {$definition}");
        }
    }
    // Ensure id column safe
    $colInfo = $conn->query("SHOW COLUMNS FROM internship_applications LIKE 'id'");
    if ($colInfo->num_rows > 0) {
        $col = $colInfo->fetch_assoc();
        $extra = $col['Extra'];
        if (strpos($extra, 'auto_increment') === false) {
            $other = $conn->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'internship_applications' " .
                "AND COLUMN_NAME <> 'id' AND EXTRA LIKE '%auto_increment%'"
            );
            if ($other->num_rows == 0) {
                $conn->query("ALTER TABLE internship_applications MODIFY id INT NOT NULL AUTO_INCREMENT");
            }
        }
    }
}

/**
 * Central logger for status changes – uses `status_audit`.
 */
function log_status_change($entity, $entity_id, $old_status, $new_status, $extra = null) {
    global $conn;
    ensure_status_audit_schema();
    
    // Check if column 'entity' exists to determine the schema style
    $checkCol = $conn->query("SHOW COLUMNS FROM status_audit LIKE 'entity'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $stmt = $conn->prepare(
            "INSERT INTO status_audit (entity, entity_id, old_status, new_status, extra) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sisss', $entity, $entity_id, $old_status, $new_status, $extra);
    } else {
        $changed_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        $changed_by_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
        
        $stmt = $conn->prepare(
            "INSERT INTO status_audit (application_id, old_status, new_status, changed_by, changed_by_role, remarks) VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('ississ', $entity_id, $old_status, $new_status, $changed_by, $changed_by_role, $extra);
    }
    $stmt->execute();
    $stmt->close();
}

/**
 * Simple wrapper to add a workflow log entry.
 */
function add_workflow_log($name, $action, $userId, $details = null) {
    global $conn;
    ensure_workflow_logs_schema();
    $stmt = $conn->prepare(
        "INSERT INTO workflow_logs (workflow_name, action, performed_by, details) VALUES (?,?,?,?)"
    );
    $stmt->bind_param('ssis', $name, $action, $userId, $details);
    $stmt->execute();
    $stmt->close();
}

/**
 * Ensure required schemas at every request – safe to call repeatedly.
 */
function ensure_all_schemas() {
    ensure_status_audit_schema();
    ensure_workflow_logs_schema();
    ensure_internship_applications_schema();
}

if (!function_exists('add_notification')) {
    function add_notification($userId, $role, $title, $message, $type = 'info') {
        if (function_exists('createNotification')) {
            return createNotification($userId, $role, $title, $message, $type);
        }
        global $conn;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, role, title, message, type) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('issss', $userId, $role, $title, $message, $type);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }
        return false;
    }
}

// Auto‑run on include
ensure_all_schemas();
?>
