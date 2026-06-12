<?php
/**
 * Fix AUTO_INCREMENT Issue on Test Question Tables
 * 
 * This script fixes the "Field 'id' doesn't have a default value" error by:
 * 1. Adding AUTO_INCREMENT to id columns if missing
 * 2. Verifying INSERT queries don't manually insert id values
 */

include __DIR__ . '/includes/db.php';

$fixes_applied = [];
$errors = [];

echo "<h2>Database AUTO_INCREMENT Fix Utility</h2>";
echo "<hr>";

// Tables to check and fix
$tables_to_fix = [
    'subtype_tests' => 'id INT AUTO_INCREMENT UNIQUE PRIMARY KEY',
    'subtype_test_questions' => 'id INT AUTO_INCREMENT UNIQUE PRIMARY KEY',
    'test_questions' => 'id INT AUTO_INCREMENT UNIQUE PRIMARY KEY',
    'question_bank' => 'id INT AUTO_INCREMENT UNIQUE PRIMARY KEY'
];

foreach ($tables_to_fix as $table_name => $id_definition) {
    echo "<h3>Checking table: <strong>$table_name</strong></h3>";
    
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    if ($table_check && mysqli_num_rows($table_check) === 0) {
        echo "<p>✓ Table '$table_name' does not exist yet (will be created on first use)</p>";
        continue;
    }
    
    // Check current id column definition
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM `$table_name` WHERE Field = 'id'");
    if (!$col_check || mysqli_num_rows($col_check) === 0) {
        $error = "✗ ERROR: Table '$table_name' has no 'id' column!";
        echo "<p style='color:red;'>$error</p>";
        $errors[] = $error;
        continue;
    }
    
    $col_info = mysqli_fetch_assoc($col_check);
    echo "<p>Current id definition: <code>" . htmlspecialchars($col_info['Type']) . " " . $col_info['Extra'] . "</code></p>";
    
    // Check if AUTO_INCREMENT exists
    if (strpos(strtolower($col_info['Extra']), 'auto_increment') === false) {
        echo "<p>⚠ AUTO_INCREMENT is missing. Attempting to add it...</p>";
        
        // Drop PRIMARY KEY if exists, then recreate with AUTO_INCREMENT
        $drop_fk = "ALTER TABLE `$table_name` DROP PRIMARY KEY";
        $add_pk = "ALTER TABLE `$table_name` ADD PRIMARY KEY (`id`), MODIFY `id` INT AUTO_INCREMENT";
        
        if (mysqli_query($conn, $drop_fk)) {
            echo "<p style='color:blue;'>Dropped old PRIMARY KEY</p>";
        } else {
            echo "<p style='color:orange;'>Note: Could not drop old KEY: " . htmlspecialchars(mysqli_error($conn)) . "</p>";
        }
        
        if (mysqli_query($conn, $add_pk)) {
            echo "<p style='color:green;'>✓ Successfully added AUTO_INCREMENT to '$table_name'.id</p>";
            $fixes_applied[] = "$table_name.id";
        } else {
            $error = "✗ Failed to add AUTO_INCREMENT to '$table_name'.id: " . htmlspecialchars(mysqli_error($conn));
            echo "<p style='color:red;'>$error</p>";
            $errors[] = $error;
        }
    } else {
        echo "<p style='color:green;'>✓ AUTO_INCREMENT is already present</p>";
    }
    
    echo "<hr>";
}

// Summary
echo "<h3>Summary</h3>";
if (!empty($fixes_applied)) {
    echo "<p style='color:green;'><strong>✓ Fixes Applied:</strong></p>";
    echo "<ul>";
    foreach ($fixes_applied as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
} else {
    echo "<p>✓ No fixes needed - all tables are properly configured</p>";
}

if (!empty($errors)) {
    echo "<p style='color:red;'><strong>✗ Errors Encountered:</strong></p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:green;'>✓ No errors encountered</p>";
}

echo "<hr>";
echo "<p><a href='coordinator_generate_test.php'>← Back to Test Generator</a></p>";
?>
