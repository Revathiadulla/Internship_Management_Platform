<?php
/**
 * One-time setup: creates the student_notifications table.
 * Run once via browser: http://localhost/IMP/setup_notifications_table.php
 * Delete this file after running.
 */
include "db.php";

$sql = "CREATE TABLE IF NOT EXISTS student_notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(50)  NOT NULL DEFAULT 'info',
    title       VARCHAR(255) NOT NULL DEFAULT '',
    message     TEXT         NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id  (user_id),
    INDEX idx_is_read  (is_read),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "<p style='font-family:sans-serif;color:green;'>✅ student_notifications table created (or already exists).</p>";
    echo "<p style='font-family:sans-serif;'>You can now delete this file.</p>";
} else {
    echo "<p style='font-family:sans-serif;color:red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}
