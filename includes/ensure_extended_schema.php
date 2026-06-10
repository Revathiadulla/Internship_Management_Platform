<?php
/**
 * ensure_extended_schema.php
 *
 * Provides safe, idempotent migrations for the IMP database schema.
 * - Adds new columns to `internship_applications`.
 * - Ensures every table's `id` column is INT NOT NULL AUTO_INCREMENT PRIMARY KEY.
 * - Does not create a second AUTO_INCREMENT column.
 * - All operations are guarded with existence checks.
 */

if (!function_exists('columnExists')) {
    function columnExists(mysqli $conn, string $table, string $column): bool {
        $tableEsc = str_replace('`', '', $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('isAutoIncrement')) {
    function isAutoIncrement(mysqli $conn, string $table, string $column = 'id'): bool {
        if (!columnExists($conn, $table, $column)) {
            return false;
        }

        $tableEsc = str_replace('`', '', $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
        if (!$res || mysqli_num_rows($res) === 0) {
            return false;
        }

        $row = mysqli_fetch_assoc($res);
        $type = strtolower($row['Type'] ?? '');
        $null = strtoupper($row['Null'] ?? 'YES');
        $extra = strtolower($row['Extra'] ?? '');

        return (strpos($type, 'int') !== false) && ($null === 'NO') && (strpos($extra, 'auto_increment') !== false);
    }
}

if (!function_exists('foreignKeyExists')) {
    function foreignKeyExists(mysqli $conn, string $table, string $column = 'id'): bool {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $sql = "SELECT 1
            FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE()
              AND (
                (table_name = '$tableEsc' AND column_name = '$columnEsc' AND referenced_table_name IS NOT NULL)
                OR (referenced_table_name = '$tableEsc' AND referenced_column_name = '$columnEsc')
              )
            LIMIT 1";
        $res = mysqli_query($conn, $sql);
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('ensureAutoIncrementPrimaryKeySafe')) {
    function ensureAutoIncrementPrimaryKeySafe(mysqli $conn, string $table, string $column = 'id'): bool {
        if (!columnExists($conn, $table, $column)) {
            return false;
        }

        if (isAutoIncrement($conn, $table, $column)) {
            return true;
        }

        if (foreignKeyExists($conn, $table, $column)) {
            error_log("ensure_extended_schema: skipping $table.$column because it is referenced by a foreign key constraint.");
            return false;
        }

        $tableEsc = str_replace('`', '', $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $pkRes = mysqli_query($conn, "SHOW KEYS FROM `$tableEsc` WHERE Key_name = 'PRIMARY' AND Column_name = '$columnEsc'");
        $hasPk = $pkRes && mysqli_num_rows($pkRes) > 0;

        $sql = $hasPk
            ? "ALTER TABLE `$tableEsc` MODIFY `$columnEsc` INT NOT NULL AUTO_INCREMENT"
            : "ALTER TABLE `$tableEsc` MODIFY `$columnEsc` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`$columnEsc`)";

        if (!mysqli_query($conn, $sql)) {
            error_log("ensure_extended_schema: failed to update $table.$column: " . mysqli_error($conn));
            return false;
        }

        return true;
    }
}

if (!function_exists('ensure_extended_schema')) {
    function ensure_extended_schema(mysqli $conn) {
        // 1. Ensure `internship_applications` has required columns
        $columns = [
            'applied_subtype' => "VARCHAR(100) DEFAULT NULL",
            'confirmation_letter_path' => "VARCHAR(255) DEFAULT NULL",
            'assigned_project_id' => "INT DEFAULT NULL",
            'test_submitted' => "TINYINT(1) NOT NULL DEFAULT 0",
            'exam_link' => "TEXT DEFAULT NULL",
            'exam_link_sent_at' => "DATETIME DEFAULT NULL",
            'exam_status' => "VARCHAR(50) DEFAULT 'Pending'",
            'exam_qualified_at' => "DATETIME DEFAULT NULL",
            'qualified_by_hr' => "INT DEFAULT NULL",
            'confirmation_letter_sent_at' => "DATETIME DEFAULT NULL",
            'exam_name' => "VARCHAR(255) DEFAULT NULL",
            'exam_remarks' => "TEXT DEFAULT NULL",
            'exam_title' => "VARCHAR(255) DEFAULT NULL",
            'exam_instructions' => "TEXT DEFAULT NULL",
            'exam_attachment' => "VARCHAR(255) DEFAULT NULL",
            'exam_sent_date' => "DATETIME DEFAULT NULL",
            'exam_date' => "DATE DEFAULT NULL",
            'exam_time' => "VARCHAR(100) DEFAULT NULL"
        ];
        foreach ($columns as $col => $def) {
            $res = $conn->query("SHOW COLUMNS FROM internship_applications LIKE '$col'");
            if ($res && $res->num_rows == 0) {
                $conn->query("ALTER TABLE internship_applications ADD COLUMN $col $def");
            }
        }

        // 2. Ensure notifications support attachment metadata for inbox and dashboard views
        $notifications_table_exists = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($notifications_table_exists && $notifications_table_exists->num_rows > 0) {
            $notification_attachment_columns = [
                'attachment_path' => 'VARCHAR(500) DEFAULT NULL',
                'attachment_name' => 'VARCHAR(255) DEFAULT NULL',
                'attachment_type' => 'VARCHAR(100) DEFAULT NULL',
                'attachment_size' => 'INT DEFAULT NULL'
            ];
            foreach ($notification_attachment_columns as $col => $def) {
                if (!columnExists($conn, 'notifications', $col)) {
                    $conn->query("ALTER TABLE notifications ADD COLUMN $col $def");
                }
            }
        }

        // 3. Ensure every table's `id` column is AUTO_INCREMENT PRIMARY KEY if it exists and is safe to modify
        $tables_res = $conn->query('SHOW TABLES');
        if ($tables_res) {
            while ($tableRow = $tables_res->fetch_array()) {
                $table = $tableRow[0];
                if (columnExists($conn, $table, 'id')) {
                    ensureAutoIncrementPrimaryKeySafe($conn, $table, 'id');
                }
            }
        }
    }
}
?>
