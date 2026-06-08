<?php
// ensure_extended_schema.php
// This script adds new columns/tables required for the extended internship workflow.
// It is safe to include multiple times; it checks existence before altering.

if (!isset($conn) || !($conn instanceof mysqli)) {
    // Expect a valid mysqli connection from the including script.
    die('Database connection not available');
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
    'team_id'                => "ALTER TABLE internship_applications ADD COLUMN team_id INT DEFAULT NULL"
];
foreach ($extended_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Ensure test score and completion columns exist
$test_app_cols = [
    'test_score' => "ALTER TABLE internship_applications ADD COLUMN test_score DECIMAL(5,2) DEFAULT NULL",
    'test_completed_at' => "ALTER TABLE internship_applications ADD COLUMN test_completed_at DATETIME DEFAULT NULL",
    'test_status' => "ALTER TABLE internship_applications ADD COLUMN test_status VARCHAR(50) DEFAULT NULL",
    'test_result' => "ALTER TABLE internship_applications ADD COLUMN test_result VARCHAR(50) DEFAULT NULL",
    'test_submitted_date' => "ALTER TABLE internship_applications ADD COLUMN test_submitted_date DATETIME DEFAULT NULL",
    'test_answers' => "ALTER TABLE internship_applications ADD COLUMN test_answers TEXT DEFAULT NULL"
];
foreach ($test_app_cols as $col => $sql) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, $sql);
    }
}

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

// FIX: Ensure notifications.id is AUTO_INCREMENT PRIMARY KEY (live DB may lack this)
$_notif_id_check = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'id'");
if ($_notif_id_check && $_notif_id_row = mysqli_fetch_assoc($_notif_id_check)) {
    $hasAutoIncrement = (stripos($_notif_id_row['Extra'] ?? '', 'auto_increment') !== false);
    if (!$hasAutoIncrement) {
        // Ensure id is the primary key first
        $hasPK = false;
        $_pk_check = mysqli_query($conn, "SHOW KEYS FROM notifications WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
        if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
            $hasPK = true;
        }
        if (!$hasPK) {
            // Drop any existing PK first (may fail silently if none exists), then add
            @mysqli_query($conn, "ALTER TABLE notifications DROP PRIMARY KEY");
            @mysqli_query($conn, "ALTER TABLE notifications ADD PRIMARY KEY (id)");
        }
        // Now set AUTO_INCREMENT
        @mysqli_query($conn, "ALTER TABLE notifications MODIFY id INT NOT NULL AUTO_INCREMENT");
    }
}

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

// FIX: Ensure email_logs.id is AUTO_INCREMENT PRIMARY KEY
$_email_logs_id_check = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE 'id'");
if ($_email_logs_id_check && $_email_logs_id_row = mysqli_fetch_assoc($_email_logs_id_check)) {
    $hasAutoIncrement = (stripos($_email_logs_id_row['Extra'] ?? '', 'auto_increment') !== false);
    if (!$hasAutoIncrement) {
        $hasPK = false;
        $_pk_check = mysqli_query($conn, "SHOW KEYS FROM email_logs WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
        if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
            $hasPK = true;
        }
        if (!$hasPK) {
            @mysqli_query($conn, "ALTER TABLE email_logs DROP PRIMARY KEY");
            @mysqli_query($conn, "ALTER TABLE email_logs ADD PRIMARY KEY (id)");
        }
        @mysqli_query($conn, "ALTER TABLE email_logs MODIFY id INT NOT NULL AUTO_INCREMENT");
    }
}

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

    // FIX: Ensure email_notifications_log.id is AUTO_INCREMENT PRIMARY KEY
    $_email_notif_log_id_check = mysqli_query($conn, "SHOW COLUMNS FROM email_notifications_log LIKE 'id'");
    if ($_email_notif_log_id_check && $_email_notif_log_id_row = mysqli_fetch_assoc($_email_notif_log_id_check)) {
        $hasAutoIncrement = (stripos($_email_notif_log_id_row['Extra'] ?? '', 'auto_increment') !== false);
        if (!$hasAutoIncrement) {
            $hasPK = false;
            $_pk_check = mysqli_query($conn, "SHOW KEYS FROM email_notifications_log WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
                $hasPK = true;
            }
            if (!$hasPK) {
                @mysqli_query($conn, "ALTER TABLE email_notifications_log DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE email_notifications_log ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE email_notifications_log MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }
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

// FIX: Ensure manual_messages.id is AUTO_INCREMENT PRIMARY KEY
$_manual_msg_id_check = mysqli_query($conn, "SHOW COLUMNS FROM manual_messages LIKE 'id'");
if ($_manual_msg_id_check && $_manual_msg_id_row = mysqli_fetch_assoc($_manual_msg_id_check)) {
    $hasAutoIncrement = (stripos($_manual_msg_id_row['Extra'] ?? '', 'auto_increment') !== false);
    if (!$hasAutoIncrement) {
        $hasPK = false;
        $_pk_check = mysqli_query($conn, "SHOW KEYS FROM manual_messages WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
        if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
            $hasPK = true;
        }
        if (!$hasPK) {
            @mysqli_query($conn, "ALTER TABLE manual_messages DROP PRIMARY KEY");
            @mysqli_query($conn, "ALTER TABLE manual_messages ADD PRIMARY KEY (id)");
        }
        @mysqli_query($conn, "ALTER TABLE manual_messages MODIFY id INT NOT NULL AUTO_INCREMENT");
    }
}

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

// Create test_questions table if not exists
$create_test_questions = "CREATE TABLE IF NOT EXISTS test_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_test_questions);

// Seed sample questions if the table is empty
$q_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM test_questions");
$q_row = mysqli_fetch_assoc($q_check);
if ($q_row && intval($q_row['count']) === 0) {
    // Insert a few default questions for standard internship IDs (or dynamically map them if needed)
    $sample_questions = [
        [1, "What does 'box-sizing: border-box' do in CSS?", "Includes padding/border in total dimensions", "Excludes padding from width", "Forces border grid", "Adds borders to margins", "A"],
        [1, "What is the primary state hook in React?", "useEffect", "useContext", "useState", "useReducer", "C"],
        [1, "Which JS array method returns a new array of elements passing a test?", "map()", "filter()", "forEach()", "reduce()", "B"],
        [1, "Which HTML attribute makes an input field required before form submission?", "mandatory", "required", "validate", "must", "B"],
        [1, "Which CSS unit is relative to the root element's font size?", "em", "rem", "px", "vh", "B"]
    ];

    $ins_stmt = $conn->prepare("INSERT INTO test_questions (internship_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($ins_stmt) {
        foreach ($sample_questions as $sq) {
            $ins_stmt->bind_param("issssss", $sq[0], $sq[1], $sq[2], $sq[3], $sq[4], $sq[5], $sq[6]);
            $ins_stmt->execute();
        }
        $ins_stmt->close();
    }
}

// Ensure subtype_tests and subtype_test_questions tables are created and migrated
try {
    // 1. Create subtype_tests table if not exists
    $create_subtype_tests = "CREATE TABLE IF NOT EXISTS subtype_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_type VARCHAR(100) NULL,
        project_subtype VARCHAR(100) NULL,
        skills TEXT NULL,
        difficulty_level VARCHAR(50) NULL,
        num_questions INT NOT NULL,
        duration_minutes INT DEFAULT 30,
        status VARCHAR(20) DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_subtype_tests);

    $subtype_tests_cols = [
        'project_type' => 'VARCHAR(100) NULL',
        'project_subtype' => 'VARCHAR(100) NULL',
        'skills' => 'TEXT NULL',
        'difficulty_level' => 'VARCHAR(50) NULL',
        'num_questions' => 'INT NOT NULL',
        'duration_minutes' => 'INT DEFAULT 30',
        'status' => "VARCHAR(20) DEFAULT 'Active'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    foreach ($subtype_tests_cols as $col => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM subtype_tests LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            mysqli_query($conn, "ALTER TABLE subtype_tests ADD COLUMN $col $definition");
        }
    }

    // 2. Create subtype_test_questions table if not exists
    $create_questions_table = "CREATE TABLE IF NOT EXISTS subtype_test_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subtype_test_id INT NOT NULL,
        question_bank_id INT NOT NULL DEFAULT 0,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option VARCHAR(5) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_questions_table);

    $questions_cols = [
        'subtype_test_id' => 'INT NOT NULL',
        'question_bank_id' => 'INT NOT NULL DEFAULT 0',
        'question_text' => 'TEXT NULL',
        'option_a' => 'VARCHAR(255) NULL',
        'option_b' => 'VARCHAR(255) NULL',
        'option_c' => 'VARCHAR(255) NULL',
        'option_d' => 'VARCHAR(255) NULL',
        'correct_option' => 'VARCHAR(5) NULL'
    ];
    foreach ($questions_cols as $col => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            mysqli_query($conn, "ALTER TABLE subtype_test_questions ADD COLUMN $col $definition");
        }
    }

    // Ensure correct_option column has type VARCHAR(5)
    $check_type = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'correct_option'");
    if ($check_type && mysqli_num_rows($check_type) > 0) {
        $col_info = mysqli_fetch_assoc($check_type);
        if (strpos(strtolower($col_info['Type']), 'varchar(5)') === false) {
            mysqli_query($conn, "ALTER TABLE subtype_test_questions MODIFY COLUMN correct_option VARCHAR(5) NOT NULL");
        }
    }

    // FIX: Ensure question_bank_id has a DEFAULT value (prevents INSERT failures)
    $check_qbid = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'question_bank_id'");
    if ($check_qbid && mysqli_num_rows($check_qbid) > 0) {
        $qbid_info = mysqli_fetch_assoc($check_qbid);
        if ($qbid_info['Default'] === null || $qbid_info['Default'] === '') {
            @mysqli_query($conn, "ALTER TABLE subtype_test_questions MODIFY COLUMN question_bank_id INT NOT NULL DEFAULT 0");
        }
    }

    // FIX: Ensure test_questions.id is AUTO_INCREMENT PRIMARY KEY
    $_test_q_id_check = mysqli_query($conn, "SHOW COLUMNS FROM test_questions LIKE 'id'");
    if ($_test_q_id_check && $_test_q_id_row = mysqli_fetch_assoc($_test_q_id_check)) {
        $hasAutoIncrement = (stripos($_test_q_id_row['Extra'] ?? '', 'auto_increment') !== false);
        if (!$hasAutoIncrement) {
            $hasPK = false;
            $_pk_check = mysqli_query($conn, "SHOW KEYS FROM test_questions WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
                $hasPK = true;
            }
            if (!$hasPK) {
                @mysqli_query($conn, "ALTER TABLE test_questions DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE test_questions ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE test_questions MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }

    // FIX: Ensure subtype_tests.id is AUTO_INCREMENT PRIMARY KEY
    $_subtype_t_id_check = mysqli_query($conn, "SHOW COLUMNS FROM subtype_tests LIKE 'id'");
    if ($_subtype_t_id_check && $_subtype_t_id_row = mysqli_fetch_assoc($_subtype_t_id_check)) {
        $hasAutoIncrement = (stripos($_subtype_t_id_row['Extra'] ?? '', 'auto_increment') !== false);
        if (!$hasAutoIncrement) {
            $hasPK = false;
            $_pk_check = mysqli_query($conn, "SHOW KEYS FROM subtype_tests WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
                $hasPK = true;
            }
            if (!$hasPK) {
                @mysqli_query($conn, "ALTER TABLE subtype_tests DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE subtype_tests ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE subtype_tests MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }

    // FIX: Ensure subtype_test_questions.id is AUTO_INCREMENT PRIMARY KEY
    $_subtype_tq_id_check = mysqli_query($conn, "SHOW COLUMNS FROM subtype_test_questions LIKE 'id'");
    if ($_subtype_tq_id_check && $_subtype_tq_id_row = mysqli_fetch_assoc($_subtype_tq_id_check)) {
        $hasAutoIncrement = (stripos($_subtype_tq_id_row['Extra'] ?? '', 'auto_increment') !== false);
        if (!$hasAutoIncrement) {
            $hasPK = false;
            $_pk_check = mysqli_query($conn, "SHOW KEYS FROM subtype_test_questions WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
                $hasPK = true;
            }
            if (!$hasPK) {
                @mysqli_query($conn, "ALTER TABLE subtype_test_questions DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE subtype_test_questions ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE subtype_test_questions MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }

    // FIX: Ensure question_bank.id is AUTO_INCREMENT PRIMARY KEY
    $_qb_id_check = mysqli_query($conn, "SHOW COLUMNS FROM question_bank LIKE 'id'");
    if ($_qb_id_check && $_qb_id_row = mysqli_fetch_assoc($_qb_id_check)) {
        $hasAutoIncrement = (stripos($_qb_id_row['Extra'] ?? '', 'auto_increment') !== false);
        if (!$hasAutoIncrement) {
            $hasPK = false;
            $_pk_check = mysqli_query($conn, "SHOW KEYS FROM question_bank WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if ($_pk_check && mysqli_num_rows($_pk_check) > 0) {
                $hasPK = true;
            }
            if (!$hasPK) {
                @mysqli_query($conn, "ALTER TABLE question_bank DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE question_bank ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE question_bank MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }

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

    // FIX: Ensure notifications.id is AUTO_INCREMENT PRIMARY KEY (live DB may lack this)
    $_notif_id_check2 = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'id'");
    if ($_notif_id_check2 && $_notif_id_row2 = mysqli_fetch_assoc($_notif_id_check2)) {
        if (stripos($_notif_id_row2['Extra'] ?? '', 'auto_increment') === false) {
            $_pk_check2 = mysqli_query($conn, "SHOW KEYS FROM notifications WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if (!$_pk_check2 || mysqli_num_rows($_pk_check2) === 0) {
                @mysqli_query($conn, "ALTER TABLE notifications DROP PRIMARY KEY");
                @mysqli_query($conn, "ALTER TABLE notifications ADD PRIMARY KEY (id)");
            }
            @mysqli_query($conn, "ALTER TABLE notifications MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    }

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
        'related_type' => "VARCHAR(50) DEFAULT NULL"
    ];
    foreach ($notifications_columns as $column => $definition) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE '$column'");
        if ($check_col && mysqli_num_rows($check_col) === 0) {
            mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN $column $definition");
        }
    }

    // Ensure links exist on student_notifications
    $student_notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'student_notifications'");
    if ($student_notif_check && mysqli_num_rows($student_notif_check) > 0) {
        $sn_cols = [
            'link' => "VARCHAR(255) DEFAULT NULL",
            'related_id' => 'INT DEFAULT NULL',
            'related_type' => "VARCHAR(50) DEFAULT NULL"
        ];
        foreach ($sn_cols as $column => $definition) {
            $check_col = mysqli_query($conn, "SHOW COLUMNS FROM student_notifications LIKE '$column'");
            if ($check_col && mysqli_num_rows($check_col) === 0) {
                mysqli_query($conn, "ALTER TABLE student_notifications ADD COLUMN $column $definition");
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
        FOREIGN KEY (test_id) REFERENCES subtype_tests(id) ON UPDATE CASCADE ON DELETE SET NULL,
        UNIQUE KEY uq_student_test (student_id, test_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_student_scores);
    // -------------------------------------------------
    // Ensure student_scores.id has PRIMARY KEY and AUTO_INCREMENT
    // -------------------------------------------------
    $check_pk = mysqli_query($conn, "SHOW INDEX FROM student_scores WHERE Key_name = 'PRIMARY'");
    if ($check_pk && mysqli_num_rows($check_pk) > 0) {
        // Primary key exists, ensure AUTO_INCREMENT
        mysqli_query($conn, "ALTER TABLE student_scores MODIFY id INT NOT NULL AUTO_INCREMENT");
    } else {
        // Add PRIMARY KEY and AUTO_INCREMENT
        mysqli_query($conn, "ALTER TABLE student_scores MODIFY id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
    }

    // Ensure student_scores columns exist and are correct
    $student_scores_cols = [
        'application_id' => 'INT DEFAULT NULL',
        'test_id' => 'INT DEFAULT NULL',
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
} catch (Throwable $e) {
    // Fail silently
}

