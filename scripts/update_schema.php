<?php
include __DIR__ . '/includes/db.php';

echo "Updating student_profiles table schema...\n";

$cols_to_add = [
    'hod_name' => 'VARCHAR(100) NULL',
    'hod_phone' => 'VARCHAR(20) NULL',
    'hod_email' => 'VARCHAR(100) NULL'
];

foreach ($cols_to_add as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        if (mysqli_query($conn, "ALTER TABLE student_profiles ADD COLUMN $col $def")) {
            echo "Added column $col successfully.\n";
        } else {
            echo "Error adding column $col: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Column $col already exists.\n";
    }
}

// Ensure phone exists in users and student_profiles
$check_user_phone = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
if (mysqli_num_rows($check_user_phone) == 0) {
    if (mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL")) {
        echo "Added column phone to users successfully.\n";
    } else {
        echo "Error adding phone to users: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column phone exists in users.\n";
}

$check_profile_phone = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'phone'");
if (mysqli_num_rows($check_profile_phone) == 0) {
    if (mysqli_query($conn, "ALTER TABLE student_profiles ADD COLUMN phone VARCHAR(20) NULL")) {
        echo "Added column phone to student_profiles successfully.\n";
    } else {
        echo "Error adding phone to student_profiles: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column phone exists in student_profiles.\n";
}

echo "Schema update completed.\n";
