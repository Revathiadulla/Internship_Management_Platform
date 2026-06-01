<?php
/*
 * workflow_helper.php
 * Centralized helper functions for internship management workflows.
 * Provides safe DB operations, status logging, notifications, and email utilities.
 */

if (!defined('INCLUDE_CHECK')) {
    exit('Direct access not permitted');
}

require_once __DIR__ . '/db.php'; // $conn
require_once __DIR__ . '/mail_helper.php'; // sendEmailNotification()

/**
 * Generate a secure random token for HOD approval links.
 * @return string Plain token
 */
function generate_hod_token(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Add a notification record.
 */
function add_notification(int $user_id, string $role, string $title, string $message, string $type = 'info') {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, role, title, message, type, is_read, created_at) VALUES (?,?,?,?,0,NOW())');
    $stmt->bind_param('isss', $user_id, $role, $title, $message);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log status changes to a simple audit table.
 */
function log_status_change(string $entity, int $entity_id, string $old_status, string $new_status, $extra = null) {
    global $conn;
    // Ensure audit table exists
    $res = $conn->query("SHOW TABLES LIKE 'status_audit'");
    if ($res->num_rows == 0) {
        $create = "CREATE TABLE status_audit (".
            "id INT AUTO_INCREMENT PRIMARY KEY,".
            "entity VARCHAR(50),".
            "entity_id INT,".
            "old_status VARCHAR(50),".
            "new_status VARCHAR(50),".
            "extra TEXT NULL,".
            "changed_at DATETIME DEFAULT CURRENT_TIMESTAMP".
            ") ENGINE=InnoDB";
        $conn->query($create);
    }
    $stmt = $conn->prepare('INSERT INTO status_audit (entity, entity_id, old_status, new_status, extra) VALUES (?,?,?,?,?)');
    $stmt->bind_param('sisss', $entity, $entity_id, $old_status, $new_status, $extra);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send an email and log it to email_notifications table.
 */
function workflow_send_email(string $to, string $subject, string $html_body) {
    // Use existing helper (which already logs)
    sendEmailNotification($to, $subject, $html_body);
    // No extra logging needed as mail_helper does it.
}

/**
 * Store hashed HOD token and return plain token.
 */
function store_hod_token(int $application_id): string {
    global $conn;
    $token = generate_hod_token();
    $hash = password_hash($token, PASSWORD_DEFAULT);
    // Ensure column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM internship_applications LIKE 'hod_approval_token'");
    if ($colCheck->num_rows == 0) {
        $conn->query("ALTER TABLE internship_applications ADD COLUMN hod_approval_token VARCHAR(255) NULL");
    }
    $stmt = $conn->prepare('UPDATE internship_applications SET hod_approval_token = ?, hod_approval_sent_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $hash, $application_id);
    $stmt->execute();
    $stmt->close();
    return $token;
}

/**
 * Verify provided token against stored hash.
 */
function verify_hod_token(int $application_id, string $token): bool {
    global $conn;
    $stmt = $conn->prepare('SELECT hod_approval_token FROM internship_applications WHERE id = ?');
    $stmt->bind_param('i', $application_id);
    $stmt->execute();
    $stmt->bind_result($hash);
    if ($stmt->fetch() && $hash) {
        $valid = password_verify($token, $hash);
        $stmt->close();
        return $valid;
    }
    $stmt->close();
    return false;
}
?>
