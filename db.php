<?php
// Load .env file if it exists in the root directory
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $val = trim($val, "\"'");
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

$host = getenv("MYSQLHOST") ?: "localhost";
$user = getenv("MYSQLUSER") ?: "root";
$password = getenv("MYSQLPASSWORD") ?: "";
$database = getenv("MYSQLDATABASE") ?: "imp_db";
$port = getenv("MYSQLPORT") ?: 3306;

if (getenv("APP_ENV") === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);
    $conn = @mysqli_connect($host, $user, $password, $database, $port);
    if (!$conn) {
        http_response_code(500);
        die("An unexpected database error occurred. Please try again later.");
    }
} else {
    $conn = mysqli_connect($host, $user, $password, $database, $port);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// ── Schema migrations ────────────────────────────────────────────────────────

// Add `title` column to student_notifications if it doesn't exist yet
$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_notifications LIKE 'title'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE student_notifications
                         ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT ''
                         AFTER user_id");
}
unset($_col_check);

