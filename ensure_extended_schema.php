<?php
// ensure_extended_schema.php
// This script adds new columns/tables required for the extended internship workflow.
// It is safe to include multiple times; it checks existence before altering.

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log('ensure_extended_schema skipped: database connection not available.');
    return;
}

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
            error_log("ensure_extended_schema: skipping $table.$column because the column does not exist.");
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

// Add new columns to internship_applications if missing
$extended_cols = [
    'education_status'       => "ALTER TABLE internship_applications ADD COLUMN education_status VARCHAR(50) NOT NULL DEFAULT 'pursuing'",
    'hod_email'              => "ALTER TABLE internship_applications ADD COLUMN hod_email VARCHAR(150) DEFAULT NULL",
    'hod_approval_status'    => "ALTER TABLE internship_applications ADD COLUMN hod_approval_status VARCHAR(50) NOT NULL DEFAULT 'Not Required'",
    'final_selection_status' => "ALTER TABLE internship_applications ADD COLUMN final_selection_status VARCHAR(50) NOT NULL DEFAULT 'Pending'",
    'hod_approved_at'        => "ALTER TABLE internship_applications ADD COLUMN hod_approved_at DATETIME NULL",
    'hod_rejected_at'        => "ALTER TABLE internship_applications ADD COLUMN hod_rejected_at DATETIME NULL",
    'hr_reviewed_at'         => "ALTER TABLE internship_applications ADD COLUMN hr_reviewed_at DATETIME NULL",
    'hod_token'              => "ALTER TABLE internship_applications ADD COLUMN hod_token VARCHAR(64) NULL",
    'assigned_project_id'    => "ALTER TABLE internship_applications ADD COLUMN assigned_project_id INT DEFAULT NULL",
    'team_id'                => "ALTER TABLE internship_applications ADD COLUMN team_id INT DEFAULT NULL",
    'exam_link'              => "ALTER TABLE internship_applications ADD COLUMN exam_link TEXT DEFAULT NULL",
    'exam_link_sent_at'      => "ALTER TABLE internship_applications ADD COLUMN exam_link_sent_at DATETIME DEFAULT NULL",
    'exam_status'            => "ALTER TABLE internship_applications ADD COLUMN exam_status VARCHAR(50) DEFAULT 'Pending'",
    'exam_qualified_at'      => "ALTER TABLE internship_applications ADD COLUMN exam_qualified_at DATETIME DEFAULT NULL",
    'qualified_by_hr'        => "ALTER TABLE internship_applications ADD COLUMN qualified_by_hr INT DEFAULT NULL",
    'confirmation_letter_sent_at' => "ALTER TABLE internship_applications ADD COLUMN confirmation_letter_sent_at DATETIME DEFAULT NULL",
    'exam_name'              => "ALTER TABLE internship_applications ADD COLUMN exam_name VARCHAR(255) DEFAULT NULL",
    'exam_remarks'           => "ALTER TABLE internship_applications ADD COLUMN exam_remarks TEXT DEFAULT NULL",
    'exam_title'             => "ALTER TABLE internship_applications ADD COLUMN exam_title VARCHAR(255) DEFAULT NULL",
    'exam_instructions'      => "ALTER TABLE internship_applications ADD COLUMN exam_instructions TEXT DEFAULT NULL",
    'exam_attachment'        => "ALTER TABLE internship_applications ADD COLUMN exam_attachment VARCHAR(255) DEFAULT NULL",
    'exam_sent_date'         => "ALTER TABLE internship_applications ADD COLUMN exam_sent_date DATETIME DEFAULT NULL",
    'exam_date'              => "ALTER TABLE internship_applications ADD COLUMN exam_date DATE DEFAULT NULL",
    'exam_time'              => "ALTER TABLE internship_applications ADD COLUMN exam_time VARCHAR(100) DEFAULT NULL"
];
foreach ($extended_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Legacy test columns are intentionally not re-created; assessment flow is external to IMP.

// Ensure internship_status column exists for discontinuation workflow (if missing)
$check_internship_status = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'internship_status'");
if ($check_internship_status && mysqli_num_rows($check_internship_status) === 0) {
    // Only add if the table exists
    $has_table = mysqli_query($conn, "SHOW TABLES LIKE 'internship_applications'");
    if ($has_table && mysqli_num_rows($has_table) > 0) {
        @mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN internship_status VARCHAR(50) DEFAULT 'Active'");
    }
}

// Add to student_profiles as well
$sp_cols = [
    'aadhaar_verification_status' => "ALTER TABLE student_profiles ADD COLUMN aadhaar_verification_status VARCHAR(20) DEFAULT 'Pending'",
    'pan_verification_status' => "ALTER TABLE student_profiles ADD COLUMN pan_verification_status VARCHAR(20) DEFAULT 'Pending'"
];
foreach ($sp_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Create hods table if not exists (stores HOD contact per internship)
$create_hods = "CREATE TABLE IF NOT EXISTS hods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_hods);

// Create mentors table if not exists
$create_mentors = "CREATE TABLE IF NOT EXISTS mentors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_mentors);

// Create coordinators table if not exists
$create_coordinators = "CREATE TABLE IF NOT EXISTS coordinators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_coordinators);

// Ensure notifications table exists and supports dashboard notification schema
$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    notification_type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_notifications_table);

// Ensure notification_type exists for legacy and unified notification inserts
$check_notification_type = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'notification_type'");
if ($check_notification_type && mysqli_num_rows($check_notification_type) === 0) {
    mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN notification_type VARCHAR(50) DEFAULT 'info'");
}

// Ensure notification attachment columns exist for dashboard and inbox views
$notifications_table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if ($notifications_table_exists && mysqli_num_rows($notifications_table_exists) > 0) {
    $notification_attachment_columns = [
        'attachment_path' => "ALTER TABLE notifications ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL",
        'attachment_name' => "ALTER TABLE notifications ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL",
        'attachment_type' => "ALTER TABLE notifications ADD COLUMN attachment_type VARCHAR(100) DEFAULT NULL",
        'attachment_size' => "ALTER TABLE notifications ADD COLUMN attachment_size INT DEFAULT NULL"
    ];

    foreach ($notification_attachment_columns as $col => $sql) {
        if (!columnExists($conn, 'notifications', $col)) {
            @mysqli_query($conn, $sql);
        }
    }
}

// Ensure notifications.id is AUTO_INCREMENT PRIMARY KEY when safe to do so
ensureAutoIncrementPrimaryKeySafe($conn, 'notifications', 'id');

// Add email_logs table for tracking outbound emails
$create_email_logs = "CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    recipient_email VARCHAR(255) NULL,
    recipient_role VARCHAR(50) NULL,
    sender_id INT NULL,
    sender_role VARCHAR(50) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NULL,
    status VARCHAR(50) NULL,
    error_message TEXT NULL,
    sent_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_email_logs);
// Ensure all expected email_logs columns exist for older schema versions
$email_log_columns = [
    'user_id' => 'INT NULL',
    'recipient_email' => 'VARCHAR(255) NULL',
    'recipient_role' => 'VARCHAR(50) NULL',
    'sender_id' => 'INT NULL',
    'sender_role' => 'VARCHAR(50) NULL',
    'subject' => 'VARCHAR(255) NULL',
    'message' => 'TEXT NULL',
    'status' => 'VARCHAR(50) NULL',
    'error_message' => 'TEXT NULL',
    'sent_at' => 'DATETIME NULL'
];
foreach ($email_log_columns as $col => $definition) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE '$col'");
    if ($check && mysqli_num_rows($check) === 0) {
        @mysqli_query($conn, "ALTER TABLE email_logs ADD COLUMN $col $definition");
    }
}

// Ensure email_logs.id is AUTO_INCREMENT PRIMARY KEY when safe to do so
ensureAutoIncrementPrimaryKeySafe($conn, 'email_logs', 'id');

// Ensure email_notifications_log sender columns exist when the table is present
$has_email_notifications_log = mysqli_query($conn, "SHOW TABLES LIKE 'email_notifications_log'");
if ($has_email_notifications_log && mysqli_num_rows($has_email_notifications_log) > 0) {
    $res3 = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_notifications_log' AND COLUMN_NAME = 'sender_id'");
    if (!$res3 || mysqli_num_rows($res3) === 0) {
        @mysqli_query($conn, "ALTER TABLE email_notifications_log ADD COLUMN sender_id INT NULL");
    }
    $res4 = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_notifications_log' AND COLUMN_NAME = 'sender_role'");
    if (!$res4 || mysqli_num_rows($res4) === 0) {
        @mysqli_query($conn, "ALTER TABLE email_notifications_log ADD COLUMN sender_role VARCHAR(50) NULL");
    }

    // Ensure email_notifications_log.id is AUTO_INCREMENT PRIMARY KEY when safe to do so
    ensureAutoIncrementPrimaryKeySafe($conn, 'email_notifications_log', 'id');
}

// Create manual_messages table for sender-driven communications
$create_manual_messages = "CREATE TABLE IF NOT EXISTS manual_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_role VARCHAR(50) NOT NULL,
    recipient_id INT NOT NULL,
    recipient_role VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_notification TINYINT(1) DEFAULT 1,
    send_email TINYINT(1) DEFAULT 1,
    email_status VARCHAR(20) DEFAULT 'not_selected',
    email_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_manual_messages);

// Ensure manual_messages table has attachment columns
$manual_msg_cols = [
    'attachment_path' => "ALTER TABLE manual_messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL",
    'attachment_name' => "ALTER TABLE manual_messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL",
    'attachment_size' => "ALTER TABLE manual_messages ADD COLUMN attachment_size INT DEFAULT NULL",
    'attachment_type' => "ALTER TABLE manual_messages ADD COLUMN attachment_type VARCHAR(100) DEFAULT NULL"
];
foreach ($manual_msg_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM manual_messages LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Ensure manual_messages.id is AUTO_INCREMENT PRIMARY KEY when safe to do so
ensureAutoIncrementPrimaryKeySafe($conn, 'manual_messages', 'id');

// Create dropout_requests table to track mentor-initiated dropouts
$create_dropout = "CREATE TABLE IF NOT EXISTS dropout_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    mentor_id INT NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Requested',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_dropout);

// Ensure daily_logs has required columns for mentor dashboard
$dl_needed = [
    'is_reviewed' => "ALTER TABLE daily_logs ADD COLUMN is_reviewed TINYINT(1) DEFAULT 0",
    'review_status' => "ALTER TABLE daily_logs ADD COLUMN review_status VARCHAR(20) DEFAULT NULL",
    'reviewed_at' => "ALTER TABLE daily_logs ADD COLUMN reviewed_at DATETIME NULL",
    'reviewer_remarks' => "ALTER TABLE daily_logs ADD COLUMN reviewer_remarks TEXT NULL"
];
foreach ($dl_needed as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}
// Create mentor_feedback table if not exists
$create_feedback = "CREATE TABLE IF NOT EXISTS mentor_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    student_id INT NOT NULL,
    internship_id INT NOT NULL,
    rating INT NOT NULL,
    comments TEXT NULL,
    phase VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_feedback);

// Create mentor_assignments table if not exists
$create_assignments = "CREATE TABLE IF NOT EXISTS mentor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    student_id INT NOT NULL,
    internship_id INT NULL,
    status VARCHAR(50) DEFAULT 'active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (mentor_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_assignments);

// Create project_types table if not exists
$create_project_types = "CREATE TABLE IF NOT EXISTS project_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_types_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_project_types);

// Create project_teams table if not exists (holds created teams)
$create_project_teams = "CREATE TABLE IF NOT EXISTS project_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(255) NOT NULL,
    project_type VARCHAR(255) DEFAULT NULL,
    project_subtype VARCHAR(255) DEFAULT NULL,
    internship_id INT NOT NULL,
    mentor_id INT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_internship (team_name, internship_id),
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_project_teams);

// Create project_team_members table if not exists (links students to teams)
$create_project_team_members = "CREATE TABLE IF NOT EXISTS project_team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_team_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member (project_team_id, student_id),
    FOREIGN KEY (project_team_id) REFERENCES project_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_project_team_members);

// Create project_subtypes table if not exists
$create_project_subtypes = "CREATE TABLE IF NOT EXISTS project_subtypes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type_id INT NOT NULL,
    subtype_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_subtypes_name (project_type_id, subtype_name),
    FOREIGN KEY (project_type_id) REFERENCES project_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_project_subtypes);

// Ensure skills, mode, and duration columns exist in project_subtypes
$sub_cols = [
    'skills' => "ALTER TABLE project_subtypes ADD COLUMN skills TEXT NULL",
    'mode' => "ALTER TABLE project_subtypes ADD COLUMN mode VARCHAR(50) NULL",
    'duration' => "ALTER TABLE project_subtypes ADD COLUMN duration VARCHAR(50) NULL"
];
foreach ($sub_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM project_subtypes LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Legacy assessment tables are intentionally skipped; the assessment flow is external to IMP.
try {
    // Create notifications table if missing
    $create_notifications = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role VARCHAR(50) NOT NULL,
        sender_id INT DEFAULT NULL,
        sender_role VARCHAR(50) DEFAULT NULL,
        receiver_id INT DEFAULT NULL,
        receiver_role VARCHAR(50) DEFAULT NULL,
        batch_key VARCHAR(100) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        notification_type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_notifications);

    // Ensure notification_type exists for legacy and unified notification inserts
    $check_notification_type = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'notification_type'");
    if ($check_notification_type && mysqli_num_rows($check_notification_type) === 0) {
        mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN notification_type VARCHAR(50) DEFAULT 'info'");
    }

    // Ensure notifications.id is AUTO_INCREMENT PRIMARY KEY when safe to do so
    ensureAutoIncrementPrimaryKeySafe($conn, 'notifications', 'id');

    // Ensure notification metadata columns exist for coordinator send/receive flow and universal linking
    $notifications_columns = [
        'sender_id' => 'INT DEFAULT NULL',
        'sender_role' => "VARCHAR(50) DEFAULT NULL",
        'receiver_id' => 'INT DEFAULT NULL',
        'receiver_role' => "VARCHAR(50) DEFAULT NULL",
        'batch_key' => "VARCHAR(100) DEFAULT NULL",
        'notification_type' => "VARCHAR(50) DEFAULT 'info'",
        'link' => "VARCHAR(255) DEFAULT NULL",
        'related_id' => 'INT DEFAULT NULL',
        'related_type' => "VARCHAR(50) DEFAULT NULL",
        'attachment_path' => "VARCHAR(255) NULL",
        'attachment_name' => "VARCHAR(255) NULL",
        'attachment_size' => "INT NULL",
        'attachment_type' => "VARCHAR(100) NULL"
    ];
    foreach ($notifications_columns as $column => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE '$column'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN $column $definition");
        }
    }

    // Ensure student_notifications has required columns and id auto-increment
    $student_notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'student_notifications'");
    if ($student_notif_check && mysqli_num_rows($student_notif_check) > 0) {
        $sn_cols = [
            'title' => "VARCHAR(255) DEFAULT NULL",
            'link' => "VARCHAR(255) DEFAULT NULL",
            'related_id' => 'INT DEFAULT NULL',
            'related_type' => "VARCHAR(50) DEFAULT NULL",
            'attachment_path' => "VARCHAR(255) NULL",
            'attachment_name' => "VARCHAR(255) NULL",
            'attachment_size' => "INT NULL",
            'attachment_type' => "VARCHAR(100) NULL"
        ];
        foreach ($sn_cols as $column => $definition) {
            $check_col = mysqli_query($conn, "SHOW COLUMNS FROM student_notifications LIKE '$column'");
            if ($check_col && mysqli_num_rows($check_col) === 0) {
                mysqli_query($conn, "ALTER TABLE student_notifications ADD COLUMN $column $definition");
            }
        }

        ensureAutoIncrementPrimaryKeySafe($conn, 'student_notifications', 'id');

        $status_res = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'student_notifications'");
        if ($status_res && $status_row = mysqli_fetch_assoc($status_res)) {
            $current_ai = intval($status_row['Auto_increment']);
            $max_res = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM student_notifications");
            if ($max_res && $max_row = mysqli_fetch_assoc($max_res)) {
                $max_id = intval($max_row['max_id']);
                if ($max_id >= $current_ai) {
                    mysqli_query($conn, "ALTER TABLE student_notifications AUTO_INCREMENT = " . ($max_id + 1));
                }
            }
        }
    }

    // Ensure links exist on hr_notifications
    $hr_notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'hr_notifications'");
    if ($hr_notif_check && mysqli_num_rows($hr_notif_check) > 0) {
        $sn_cols = [
            'link' => "VARCHAR(255) DEFAULT NULL",
            'related_id' => 'INT DEFAULT NULL',
            'related_type' => "VARCHAR(50) DEFAULT NULL"
        ];
        foreach ($sn_cols as $column => $definition) {
            $check_col = mysqli_query($conn, "SHOW COLUMNS FROM hr_notifications LIKE '$column'");
            if ($check_col && mysqli_num_rows($check_col) === 0) {
                mysqli_query($conn, "ALTER TABLE hr_notifications ADD COLUMN $column $definition");
            }
        }
    }

    // Ensure links exist on mentor_notifications
    $mentor_notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'mentor_notifications'");
    if ($mentor_notif_check && mysqli_num_rows($mentor_notif_check) > 0) {
        $sn_cols = [
            'link' => "VARCHAR(255) DEFAULT NULL",
            'related_id' => 'INT DEFAULT NULL',
            'related_type' => "VARCHAR(50) DEFAULT NULL"
        ];
        foreach ($sn_cols as $column => $definition) {
            $check_col = mysqli_query($conn, "SHOW COLUMNS FROM mentor_notifications LIKE '$column'");
            if ($check_col && mysqli_num_rows($check_col) === 0) {
                mysqli_query($conn, "ALTER TABLE mentor_notifications ADD COLUMN $column $definition");
            }
        }
    }

    // Create student_scores table if not exists
    $create_student_scores = "CREATE TABLE IF NOT EXISTS student_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        internship_id INT NOT NULL,
        application_id INT DEFAULT NULL,
        test_id INT DEFAULT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE,
        UNIQUE KEY uq_student_test (student_id, test_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_student_scores);
    // Ensure student_scores.id has PRIMARY KEY and AUTO_INCREMENT when safe to do so
    ensureAutoIncrementPrimaryKeySafe($conn, 'student_scores', 'id');

    // Ensure student_scores columns exist and are correct
    $student_scores_cols = [
        'application_id' => 'INT DEFAULT NULL',
        'test_id' => 'INT DEFAULT NULL',
        'attempt_no' => 'INT NOT NULL DEFAULT 0',
        'total_questions' => 'INT NOT NULL DEFAULT 0',
        'percentage' => 'DECIMAL(5,2) DEFAULT 0.00',
        'submitted_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    foreach ($student_scores_cols as $col => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM student_scores LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            mysqli_query($conn, "ALTER TABLE student_scores ADD COLUMN $col $definition");
        }
    }

    // Legacy test attempt history table is intentionally skipped.

    // Create coordinator_assignments table
    $create_coord_assignments = "CREATE TABLE IF NOT EXISTS coordinator_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coordinator_id INT NOT NULL,
        project_type_id INT NOT NULL,
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'Active',
        UNIQUE KEY uq_project_type_assignment (project_type_id),
        FOREIGN KEY (coordinator_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (project_type_id) REFERENCES project_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_coord_assignments);

    // Ensure project_type_id and project_subtype_id columns exist in internships
    $check_type_id = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'project_type_id'");
    if ($check_type_id && mysqli_num_rows($check_type_id) === 0) {
        mysqli_query($conn, "ALTER TABLE internships ADD COLUMN project_type_id INT NULL");
    }
    $check_subtype_id = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'project_subtype_id'");
    if ($check_subtype_id && mysqli_num_rows($check_subtype_id) === 0) {
        mysqli_query($conn, "ALTER TABLE internships ADD COLUMN project_subtype_id INT NULL");
    }
    // Migrate existing internships
    mysqli_query($conn, "UPDATE internships i JOIN project_types pt ON TRIM(i.project_type) = TRIM(pt.type_name) SET i.project_type_id = pt.id WHERE i.project_type_id IS NULL");
    mysqli_query($conn, "UPDATE internships i JOIN project_subtypes ps ON i.project_type_id = ps.project_type_id AND TRIM(i.project_subtype) = TRIM(ps.subtype_name) SET i.project_subtype_id = ps.id WHERE i.project_subtype_id IS NULL");

    // Check hr_notes schema compatibility and recreate if legacy
    $_notes_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'hr_notes'");
    if ($_notes_table_check && mysqli_num_rows($_notes_table_check) > 0) {
        $_col_check = mysqli_query($conn, "SHOW COLUMNS FROM hr_notes LIKE 'student_id'");
        if (!$_col_check || mysqli_num_rows($_col_check) === 0) {
            mysqli_query($conn, "DROP TABLE IF EXISTS hr_notes");
        }
        if ($_col_check) {
            unset($_col_check);
        }
    }
    if ($_notes_table_check) {
        unset($_notes_table_check);
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS hr_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        student_id INT NOT NULL,
        hr_id INT NOT NULL,
        note_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure notifications table has attachment columns
    $notif_cols = [
        'attachment_path' => "ALTER TABLE notifications ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL",
        'attachment_name' => "ALTER TABLE notifications ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL",
        'attachment_size' => "ALTER TABLE notifications ADD COLUMN attachment_size INT DEFAULT NULL",
        'attachment_type' => "ALTER TABLE notifications ADD COLUMN attachment_type VARCHAR(100) DEFAULT NULL"
    ];
    foreach ($notif_cols as $col => $sql) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE '$col'");
        if ($check && mysqli_num_rows($check) == 0) {
            mysqli_query($conn, $sql);
        }
    }
} catch (Throwable $e) {
    // Fail silently
}

