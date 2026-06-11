<?php
/**
 * workflow_helper.php – central helpers for status audit, workflow logs, and schema safety.
 */

require_once dirname(__DIR__) . '/db.php'; // assumed existing DB connection $conn

function normalize_education_status($value) {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || strtolower($raw) === 'null' || strtolower($raw) === 'none') {
        return '';
    }

    $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($raw));
    $pursuing_values = ['pursuing', 'currentlypursuing', 'currentstudent', 'currentlystudying', 'pursuingstudent', 'pursuingcurrentstudent'];
    $passed_out_values = ['passedout', 'passedoutstudent', 'graduated', 'graduate', 'graduation', 'completed', 'completedgraduated', 'notpursuing', 'passedoutgraduated'];

    if (in_array($normalized, $pursuing_values, true)) {
        return 'pursuing';
    }

    if (in_array($normalized, $passed_out_values, true)) {
        return 'passed_out';
    }

    return $normalized;
}

function is_pursuing_student($education_status, $student_type = null) {
    $normalized = normalize_education_status($education_status);
    if ($normalized !== '') {
        return $normalized === 'pursuing';
    }

    $student_type_normalized = preg_replace('/[^a-z0-9]+/', '', strtolower((string) ($student_type ?? '')));
    if ($student_type_normalized !== '') {
        $pursuing_types = ['pursuing', 'currentstudent', 'currentlypursuing', 'currentlystudying', 'pursuingstudent', 'pursuingcurrentstudent'];
        $passed_out_types = ['passedout', 'passedoutstudent', 'graduated', 'graduate', 'graduation', 'completed', 'passedoutgraduated'];
        if (in_array($student_type_normalized, $passed_out_types, true)) {
            return false;
        }
        if (in_array($student_type_normalized, $pursuing_types, true)) {
            return true;
        }
    }

    return true;
}

function is_passed_out_student($education_status, $student_type = null) {
    return !is_pursuing_student($education_status, $student_type);
}

function normalize_workflow_status($value) {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || strtolower($raw) === 'null' || strtolower($raw) === 'none') {
        return '';
    }

    $key = preg_replace('/[^a-z0-9]+/', '_', strtolower($raw));
    $map = [
        'applied' => 'applied',
        'hr_review' => 'hr_review',
        'hr_reviews' => 'hr_review',
        'shortlisted' => 'shortlisted',
        'exam_link_sent' => 'exam_sent',
        'exam_sent' => 'exam_sent',
        'exam_mail_sent' => 'exam_mail_sent',
        'exam_mail' => 'exam_mail_sent',
        'exam_sent_mail' => 'exam_mail_sent',
        'exam_qualified' => 'exam_qualified',
        'exam_completed' => 'exam_completed',
        'hod_pending' => 'hod_pending',
        'hod_approval_pending' => 'hod_pending',
        'hod_approval' => 'hod_pending',
        'hod_pending_approval' => 'hod_pending',
        'hod_approved' => 'hod_approved',
        'hod_approval_approved' => 'hod_approved',
        'hod_rejected' => 'hod_rejected',
        'hod_rejection_rejected' => 'hod_rejected',
        'selected' => 'selected',
        'confirmation_letter_sent' => 'selected',
        'offer_sent' => 'selected',
        'rejected' => 'rejected',
    ];

    return $map[$key] ?? $key;
}

function is_status_key($status, $expected) {
    return normalize_workflow_status($status) === normalize_workflow_status($expected);
}

function is_exam_sent_status($status) {
    $normalized = normalize_workflow_status($status);
    return in_array($normalized, ['exam_sent', 'exam_mail_sent'], true);
}

function is_exam_completed_status($status) {
    return normalize_workflow_status($status) === 'exam_completed';
}

function is_hr_review_status($status) {
    return normalize_workflow_status($status) === 'hr_review';
}

function is_hod_pending_status($status) {
    return normalize_workflow_status($status) === 'hod_pending';
}

function is_hod_approved_status($status) {
    return normalize_workflow_status($status) === 'hod_approved';
}

function is_hod_rejected_status($status) {
    return normalize_workflow_status($status) === 'hod_rejected';
}

function is_selected_status($status) {
    return normalize_workflow_status($status) === 'selected';
}

function is_rejected_status($status) {
    $normalized = normalize_workflow_status($status);
    return in_array($normalized, ['rejected', 'hod_rejected'], true);
}

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

    try {
        $existingColumns = [];
        $colsRes = $conn->query("SHOW COLUMNS FROM workflow_logs");
        if ($colsRes) {
            while ($col = $colsRes->fetch_assoc()) {
                $existingColumns[] = $col['Field'];
            }
        }

        $safeName = trim((string) ($name ?? ''));
        $safeAction = trim((string) ($action ?? ''));
        $safeUserId = intval($userId);
        $safeDetails = trim((string) ($details ?? ''));

        if (in_array('workflow_name', $existingColumns, true) && in_array('action', $existingColumns, true) && in_array('performed_by', $existingColumns, true) && in_array('details', $existingColumns, true)) {
            $stmt = $conn->prepare("INSERT INTO workflow_logs (workflow_name, action, performed_by, details) VALUES (?,?,?,?)");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param('ssis', $safeName, $safeAction, $safeUserId, $safeDetails);
            $stmt->execute();
            $stmt->close();
            return true;
        }

        $notes = $safeName . '|' . $safeAction . '|' . $safeDetails;
        $stmt = $conn->prepare("INSERT INTO workflow_logs (application_id, candidate_id, old_status, new_status, changed_by, notes) VALUES (0, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param('issis', $safeUserId, $safeAction, $safeName, $safeUserId, $notes);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Throwable $e) {
        error_log('Workflow log insert failed: ' . $e->getMessage());
        return false;
    }
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
