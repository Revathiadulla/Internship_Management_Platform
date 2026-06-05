<?php
/**
 * Migration script: fix_id_columns.php
 *
 * Scans all tables in the current database for a column named `id`.
 * If the `id` column is present but is NOT a PRIMARY KEY and is NOT AUTO_INCREMENT,
 * and the table does NOT already contain another AUTO_INCREMENT column,
 * the script alters the column to become `INT NOT NULL AUTO_INCREMENT` and adds it as the PRIMARY KEY.
 *
 * Tables that already satisfy the requirements or have another AUTO_INCREMENT
 * column are skipped, and a message is printed for each skipped table.
 */

// Adjust the path to your configuration as needed.
require_once __DIR__ . '/config/db_config.php'; // This file should set up a $conn mysqli connection.

if (!isset($conn) || !$conn) {
    die("Database connection not established. Ensure db_config.php defines \$conn.\n");
}

$database = mysqli_query($conn, 'SELECT DATABASE()')->fetch_row()[0];

// Get list of all tables in the current database.
$tablesResult = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$database'");
if (!$tablesResult) {
    die('Failed to retrieve tables: ' . mysqli_error($conn) . "\n");
}

while ($row = mysqli_fetch_assoc($tablesResult)) {
    $table = $row['TABLE_NAME'];
    // Check if there is a column named 'id'.
    $idColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'id'");
    if (!$idColumnResult || mysqli_num_rows($idColumnResult) == 0) {
        echo "Skipped table `$table`: no `id` column.\n";
        continue;
    }
    $idInfo = mysqli_fetch_assoc($idColumnResult);
    $isAutoInc = stripos($idInfo['Extra'] ?? '', 'auto_increment') !== false;
    // Check if `id` is already primary key.
    $pkResult = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME = 'id'");
    $isPrimary = $pkResult && mysqli_num_rows($pkResult) > 0;

    // Detect any other AUTO_INCREMENT column in the table.
    $otherAutoIncResult = mysqli_query($conn, "SHOW COLUMNS FROM `$table` WHERE Extra LIKE '%auto_increment%' AND Field <> 'id'");
    $hasOtherAutoInc = $otherAutoIncResult && mysqli_num_rows($otherAutoIncResult) > 0;

    if ($isPrimary && $isAutoInc) {
        echo "Skipped table `$table`: `id` already PRIMARY KEY and AUTO_INCREMENT.\n";
        continue;
    }
    if ($hasOtherAutoInc) {
        echo "Skipped table `$table`: another column already has AUTO_INCREMENT.\n";
        continue;
    }
    // Build ALTER statement.
    $alterSql = "ALTER TABLE `$table` MODIFY `id` INT NOT NULL AUTO_INCREMENT";
    if (!$isPrimary) {
        $alterSql .= ", ADD PRIMARY KEY (`id`)";
    }
    // Execute alteration.
    if (mysqli_query($conn, $alterSql)) {
        echo "Fixed table `$table`: `id` column now AUTO_INCREMENT" . ($isPrimary ? '' : " and set as PRIMARY KEY") . ".\n";
    } else {
        echo "Error fixing table `$table`: " . mysqli_error($conn) . "\n";
    }
}

mysqli_close($conn);
?>
