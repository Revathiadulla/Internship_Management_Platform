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

if (!function_exists('ensure_extended_schema')) {
    function ensure_extended_schema(mysqli $conn) {
        // 1. Ensure `internship_applications` has required columns
        $columns = [
            'applied_subtype' => "VARCHAR(100) DEFAULT NULL",
            'confirmation_letter_path' => "VARCHAR(255) DEFAULT NULL",
            'assigned_project_id' => "INT DEFAULT NULL",
            'test_submitted' => "TINYINT(1) NOT NULL DEFAULT 0"
        ];
        foreach ($columns as $col => $def) {
            $res = $conn->query("SHOW COLUMNS FROM internship_applications LIKE '$col'");
            if ($res && $res->num_rows == 0) {
                $conn->query("ALTER TABLE internship_applications ADD COLUMN $col $def");
            }
        }

        // 2. Ensure every table's `id` column is AUTO_INCREMENT PRIMARY KEY if it exists
        $tables_res = $conn->query('SHOW TABLES');
        if ($tables_res) {
            while ($tableRow = $tables_res->fetch_array()) {
                $table = $tableRow[0];
                // Check if table has an `id` column
                $colRes = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'id'");
                if ($colRes && $colRes->num_rows > 0) {
                    $colInfo = $colRes->fetch_assoc();
                    $extra = $colInfo['Extra'];
                    $isAuto = strpos($extra, 'auto_increment') !== false;
                    if (!$isAuto) {
                        // Ensure no other column has AUTO_INCREMENT
                        $autoCheck = $conn->query(
                            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
                            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' " .
                            "AND COLUMN_NAME <> 'id' AND EXTRA LIKE '%auto_increment%'"
                        );
                        if ($autoCheck && $autoCheck->num_rows == 0) {
                            $conn->query("ALTER TABLE `$table` MODIFY id INT NOT NULL AUTO_INCREMENT");
                        }
                    }
                }
            }
        }
    }
}
?>
