<?php
require_once __DIR__ . '/constants.php';

function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function module_flash(): string {
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">' . e($message) . '</div>';
}

function set_flash(string $message): void {
    $_SESSION['flash'] = $message;
}

function ensure_module_schema(mysqli $conn): void {
    // Ensure at least one name-related column exists in internship_applications to prevent SQL crashes
    $chk_name_cols = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
    $has_name_col = false;
    if ($chk_name_cols) {
        while ($row = mysqli_fetch_assoc($chk_name_cols)) {
            $col_name = strtolower($row['Field']);
            if ($col_name === 'full_name' || $col_name === 'name' || $col_name === 'first_name') {
                $has_name_col = true;
                break;
            }
        }
    }
    if (!$has_name_col) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN full_name VARCHAR(150) DEFAULT NULL");
    }

    module_add_column($conn, 'internship_applications', 'is_deleted', "TINYINT(1) DEFAULT 0");
    module_add_column($conn, 'internship_applications', 'verification_status', "VARCHAR(20) DEFAULT 'Pending'");
    module_add_column($conn, 'internship_applications', 'job_posting_id', "INT DEFAULT NULL");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS application_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        old_status VARCHAR(100) DEFAULT NULL,
        new_status VARCHAR(100) NOT NULL,
        updated_by_role VARCHAR(50) NOT NULL,
        updated_by_name VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS job_postings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        department VARCHAR(120) DEFAULT NULL,
        posting_type VARCHAR(40) DEFAULT 'Internship',
        location VARCHAR(120) DEFAULT NULL,
        openings INT DEFAULT 1,
        description TEXT DEFAULT NULL,
        requirements TEXT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'Active',
        deadline DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(40) DEFAULT NULL,
        college VARCHAR(180) DEFAULT NULL,
        skills TEXT DEFAULT NULL,
        resume_file VARCHAR(255) DEFAULT NULL,
        current_status VARCHAR(80) DEFAULT 'Applied',
        latest_application_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_candidate_user (user_id)
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS workflow_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stage_name VARCHAR(80) NOT NULL UNIQUE,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS workflow_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT DEFAULT NULL,
        candidate_id INT DEFAULT NULL,
        old_status VARCHAR(80) DEFAULT NULL,
        new_status VARCHAR(80) NOT NULL,
        changed_by INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS hr_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        user_id INT NOT NULL,
        author_name VARCHAR(100) NOT NULL,
        note_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES internship_applications(id) ON DELETE CASCADE
    )");

    // Clean old stages and seed new 6 simplified stages
    mysqli_query($conn, "DELETE FROM workflow_stages");
    foreach ([
        'Applied' => 1,
        'Test Completed' => 2,
        'HR Round' => 3,
        'HOD Approved' => 4,
        'Selected' => 5,
        'Rejected' => 6,
    ] as $stage => $order) {
        $stage_safe = mysqli_real_escape_string($conn, $stage);
        mysqli_query($conn, "INSERT INTO workflow_stages (stage_name, sort_order, is_active) VALUES ('$stage_safe', $order, 1)");
    }

    module_add_column($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'Active'");
    module_add_column($conn, 'users', 'permissions', "TEXT DEFAULT NULL");
    module_add_column($conn, 'users', 'reset_required', "TINYINT(1) DEFAULT 0");
    module_add_column($conn, 'users', 'profile_image', "VARCHAR(255) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE users MODIFY role ENUM('student', 'hr', 'recruiter', 'coordinator', 'mentor', 'company', 'admin') NOT NULL");

    module_add_unique_index($conn, 'users', 'email', 'unique_users_email');
    module_add_unique_index($conn, 'candidates', 'email', 'unique_candidates_email');
    module_add_foreign_key(
        $conn,
        'internship_applications',
        'fk_applications_job_posting',
        'job_posting_id',
        'job_postings',
        'id',
        'ON DELETE SET NULL ON UPDATE CASCADE'
    );

    // ── Mentor & Daily Logs Extensions ──────────────────────────────────────────
    module_add_column($conn, 'daily_logs', 'mentor_comment', "TEXT DEFAULT NULL");
    module_add_column($conn, 'daily_logs', 'attachment_path', "VARCHAR(255) DEFAULT NULL");
    module_add_column($conn, 'daily_logs', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    module_add_column($conn, 'daily_logs', 'application_id', "INT DEFAULT NULL");
    module_add_column($conn, 'daily_logs', 'hr_review_status', "VARCHAR(50) DEFAULT 'Pending'");
    module_add_column($conn, 'daily_logs', 'hr_remarks', "TEXT DEFAULT NULL");
    module_add_column($conn, 'daily_logs', 'hr_reviewed_by', "INT DEFAULT NULL");
    module_add_column($conn, 'daily_logs', 'hr_reviewed_at', "TIMESTAMP NULL DEFAULT NULL");

    // Create mentor_assignments table if it doesn't exist
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS mentor_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mentor_id INT NOT NULL,
        student_id INT NOT NULL,
        internship_id INT NULL,
        project_id INT NULL,
        application_id INT NULL,
        assigned_by INT NULL,
        status VARCHAR(50) DEFAULT 'Active',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mentor (mentor_id),
        INDEX idx_student (student_id),
        INDEX idx_application (application_id),
        FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add internship_id and project_id to mentor_assignments table if they are missing
    module_add_column($conn, 'mentor_assignments', 'internship_id', "INT DEFAULT NULL");
    module_add_column($conn, 'mentor_assignments', 'project_id', "INT DEFAULT NULL");

    // Recreate mentor_assignments if needed (checking assigned_by)
    $_chk_asg = mysqli_query($conn, "SHOW COLUMNS FROM mentor_assignments LIKE 'assigned_by'");
    if (!$_chk_asg || mysqli_num_rows($_chk_asg) === 0) {
        mysqli_query($conn, "DROP TABLE IF EXISTS mentor_assignments");
        mysqli_query($conn, "CREATE TABLE mentor_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mentor_id INT NOT NULL,
            student_id INT NOT NULL,
            internship_id INT NULL,
            project_id INT NULL,
            application_id INT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'Active',
            INDEX idx_mentor (mentor_id),
            INDEX idx_student (student_id),
            INDEX idx_application (application_id),
            FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if ($_chk_asg) {
        unset($_chk_asg);
    }

    // Create mentor_notifications
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS mentor_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mentor_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mentor_notif (mentor_id),
        FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create mentor_activity_logs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS mentor_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mentor_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        student_id INT NULL,
        log_id INT NULL,
        details TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mentor_activity (mentor_id),
        FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add log_id and status to mentor_feedback
    module_add_column($conn, 'mentor_feedback', 'log_id', "INT DEFAULT NULL");
    module_add_column($conn, 'mentor_feedback', 'status', "VARCHAR(20) DEFAULT NULL");

    // Index setups for workflow stability and query performance
    module_add_composite_unique_index($conn, 'daily_logs', 'user_id, log_date', 'idx_daily_logs_unique_student_date');
    module_add_index($conn, 'daily_logs', 'status', 'idx_daily_logs_status');
    module_add_index($conn, 'daily_logs', 'application_id', 'idx_daily_logs_application');
    module_add_index($conn, 'daily_logs', 'application_id', 'idx_daily_logs_application_id');
    module_add_index($conn, 'mentor_assignments', 'status', 'idx_mentor_assignments_status');
    module_add_index($conn, 'mentor_notifications', 'is_read', 'idx_mentor_notifications_is_read');
    module_add_index($conn, 'student_notifications', 'user_id', 'idx_student_notifications_user_id');
    module_add_index($conn, 'student_notifications', 'is_read', 'idx_student_notifications_is_read');
    module_add_index($conn, 'mentor_activity_logs', 'student_id', 'idx_mentor_activity_logs_student_id');
    module_add_index($conn, 'mentor_feedback', 'log_id', 'idx_mentor_feedback_log_id');
    module_add_index($conn, 'mentor_feedback', 'status', 'idx_mentor_feedback_status');

    // Create hr_notifications table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS hr_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    module_add_index($conn, 'hr_notifications', 'is_read', 'idx_hr_notifications_is_read');

    // Create hiring_requests table if it doesn't exist
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS hiring_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        openings INT DEFAULT 1,
        description TEXT DEFAULT NULL,
        requirements TEXT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function format_module_date($date_value): string {
    if (empty($date_value) || $date_value === '0000-00-00') {
        return 'Open';
    }
    $timestamp = strtotime($date_value);
    if (!$timestamp || (int) date('Y', $timestamp) < 1970) {
        return 'Open';
    }
    return date('M d, Y', $timestamp);
}

function module_add_column(mysqli $conn, string $table, string $column, string $definition): void {
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table_safe` ADD COLUMN `$column_safe` $definition");
    }
}

function module_index_exists(mysqli $conn, string $table, string $index_name): bool {
    $table_safe = mysqli_real_escape_string($conn, $table);
    $index_safe = mysqli_real_escape_string($conn, $index_name);
    $result = mysqli_query($conn, "SHOW INDEX FROM `$table_safe` WHERE Key_name = '$index_safe'");
    return $result && mysqli_num_rows($result) > 0;
}

function module_add_index(mysqli $conn, string $table, string $columns, string $index_name): void {
    if (module_index_exists($conn, $table, $index_name)) {
        return;
    }
    $table_safe = mysqli_real_escape_string($conn, $table);
    $index_safe = mysqli_real_escape_string($conn, $index_name);
    $cols_arr = explode(',', $columns);
    $parts = array_map(fn($col) => '`' . mysqli_real_escape_string($conn, trim($col)) . '`', $cols_arr);
    $columns_sql = implode(', ', $parts);
    mysqli_query($conn, "ALTER TABLE `$table_safe` ADD INDEX `$index_safe` ($columns_sql)");
}

function module_add_composite_unique_index(mysqli $conn, string $table, string $columns, string $index_name): void {
    if (module_index_exists($conn, $table, $index_name)) {
        return;
    }
    $table_safe = mysqli_real_escape_string($conn, $table);
    $index_safe = mysqli_real_escape_string($conn, $index_name);
    $cols_arr = explode(',', $columns);
    $parts = array_map(fn($col) => '`' . mysqli_real_escape_string($conn, trim($col)) . '`', $cols_arr);
    $columns_sql = implode(', ', $parts);
    
    // Check duplicates before creating unique index
    $duplicate_check = mysqli_query($conn, "SELECT $columns_sql, COUNT(*) AS cnt FROM `$table_safe` GROUP BY $columns_sql HAVING cnt > 1 LIMIT 1");
    if ($duplicate_check && mysqli_num_rows($duplicate_check) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table_safe` ADD UNIQUE KEY `$index_safe` ($columns_sql)");
    }
}

function module_add_unique_index(mysqli $conn, string $table, string $column, string $index_name): void {
    if (module_index_exists($conn, $table, $index_name)) {
        return;
    }
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $duplicate_check = mysqli_query($conn, "SELECT `$column_safe`, COUNT(*) AS cnt FROM `$table_safe` WHERE `$column_safe` IS NOT NULL AND `$column_safe` <> '' GROUP BY `$column_safe` HAVING cnt > 1 LIMIT 1");
    if ($duplicate_check && mysqli_num_rows($duplicate_check) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table_safe` ADD UNIQUE KEY `$index_name` (`$column_safe`)");
    }
}

function module_foreign_key_exists(mysqli $conn, string $table, string $constraint_name): bool {
    $table_safe = mysqli_real_escape_string($conn, $table);
    $constraint_safe = mysqli_real_escape_string($conn, $constraint_name);
    $db_result = mysqli_query($conn, "SELECT DATABASE() AS db_name");
    $db_name = $db_result ? mysqli_fetch_assoc($db_result)['db_name'] : '';
    $db_safe = mysqli_real_escape_string($conn, $db_name);
    $result = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '$db_safe' AND TABLE_NAME = '$table_safe' AND CONSTRAINT_NAME = '$constraint_safe' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function module_add_foreign_key(mysqli $conn, string $table, string $constraint_name, string $column, string $ref_table, string $ref_column, string $rule_sql): void {
    if (module_foreign_key_exists($conn, $table, $constraint_name)) {
        return;
    }
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $ref_table_safe = mysqli_real_escape_string($conn, $ref_table);
    $ref_column_safe = mysqli_real_escape_string($conn, $ref_column);
    $orphan_check = mysqli_query($conn, "SELECT t.`$column_safe` FROM `$table_safe` t LEFT JOIN `$ref_table_safe` r ON t.`$column_safe` = r.`$ref_column_safe` WHERE t.`$column_safe` IS NOT NULL AND r.`$ref_column_safe` IS NULL LIMIT 1");
    if ($orphan_check && mysqli_num_rows($orphan_check) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table_safe` ADD CONSTRAINT `$constraint_name` FOREIGN KEY (`$column_safe`) REFERENCES `$ref_table_safe`(`$ref_column_safe`) $rule_sql");
    }
}

function validate_posting_input(array $input): array {
    $errors = [];
    $title = trim($input['title'] ?? '');
    $posting_type = trim($input['posting_type'] ?? 'Internship');
    $status = trim($input['status'] ?? 'Active');
    $openings = (int) ($input['openings'] ?? 1);
    $deadline = trim($input['deadline'] ?? '');

    if ($title === '') {
        $errors[] = 'Posting title is required.';
    }
    if (!in_array($posting_type, ['Internship', 'Job'], true)) {
        $errors[] = 'Posting type must be Internship or Job.';
    }
    if (!in_array($status, ['Active', 'Closed'], true)) {
        $errors[] = 'Posting status must be Active or Closed.';
    }
    if ($openings < 1) {
        $errors[] = 'Openings must be at least 1.';
    }
    if ($deadline !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $deadline);
        if (!$date || $date->format('Y-m-d') !== $deadline) {
            $errors[] = 'Deadline must be a valid date.';
        }
    }

    return $errors;
}

function sync_candidates_from_applications(mysqli $conn): void {
    // Check internship_applications table columns to dynamically handle name-related columns
    $columns_res = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications");
    $columns = [];
    if ($columns_res) {
        while ($row = mysqli_fetch_assoc($columns_res)) {
            $columns[] = strtolower($row['Field']);
        }
    }

    // 1. Determine full_name expression (Requirement 6: COALESCE(a.full_name, CONCAT(a.first_name, ' ', a.last_name), u.full_name))
    // Constructed dynamically to prevent errors if applications columns are missing
    $app_name_parts = [];
    if (in_array('full_name', $columns, true)) {
        $app_name_parts[] = "a.full_name";
    }
    if (in_array('first_name', $columns, true) && in_array('last_name', $columns, true)) {
        $app_name_parts[] = "CONCAT(a.first_name, ' ', a.last_name)";
    }
    if (in_array('name', $columns, true)) {
        $app_name_parts[] = "a.name";
    }
    $app_name_parts[] = "u.full_name";
    $app_name_coalesce = "COALESCE(" . implode(", ", $app_name_parts) . ")";
    $full_name_expr = "COALESCE(NULLIF(sp.full_name, ''), NULLIF($app_name_coalesce, ''), 'Unknown Candidate')";

    // 2. Determine email expression (Requirements 3, 4: u.email AS email)
    if (in_array('email', $columns, true)) {
        $email_expr = "COALESCE(NULLIF(sp.email, ''), NULLIF(a.email, ''), u.email)";
    } else {
        $email_expr = "COALESCE(NULLIF(sp.email, ''), u.email)";
    }

    // 3. Determine phone expression (Requirement 5: COALESCE(a.phone, u.phone) AS phone)
    if (in_array('phone', $columns, true)) {
        $phone_expr = "COALESCE(NULLIF(sp.phone, ''), COALESCE(a.phone, u.phone))";
    } else {
        $phone_expr = "COALESCE(NULLIF(sp.phone, ''), u.phone)";
    }

    // 4. Determine other columns dynamically to prevent any fatal errors
    $college_name_part = in_array('college_name', $columns, true) ? "NULLIF(a.college_name, '')" : "NULL";
    $prev_college_name_part = in_array('prev_college_name', $columns, true) ? "NULLIF(a.prev_college_name, '')" : "NULL";
    $college_expr = "COALESCE(NULLIF(sp.college_name, ''), $college_name_part, $prev_college_name_part)";

    $relevant_skills_part = in_array('relevant_skills', $columns, true) ? "NULLIF(a.relevant_skills, '')" : "NULL";
    $skills_expr = "COALESCE(NULLIF(sp.skills, ''), $relevant_skills_part)";

    $resume_file_part = in_array('resume_file', $columns, true) ? "NULLIF(a.resume_file, '')" : "NULL";
    $resume_file_expr = "COALESCE(NULLIF(sp.resume_file, ''), $resume_file_part)";

    $status_part = in_array('status', $columns, true) ? "NULLIF(a.status, '')" : "NULL";
    $current_status_expr = "COALESCE($status_part, 'Applied')";

    $order_col = in_array('applied_date', $columns, true) ? 'a.applied_date' : (in_array('created_at', $columns, true) ? 'a.created_at' : 'a.id');

    mysqli_query($conn, "INSERT INTO candidates
        (user_id, full_name, email, phone, college, skills, resume_file, current_status, latest_application_id)
        SELECT
            a.user_id,
            $full_name_expr AS full_name,
            $email_expr AS email,
            $phone_expr AS phone,
            $college_expr AS college,
            $skills_expr AS skills,
            $resume_file_expr AS resume_file,
            $current_status_expr AS current_status,
            a.id AS latest_application_id
        FROM internship_applications a
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.user_id IS NOT NULL
        ORDER BY $order_col DESC
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            email = VALUES(email),
            phone = VALUES(phone),
            college = VALUES(college),
            skills = VALUES(skills),
            resume_file = VALUES(resume_file),
            current_status = VALUES(current_status),
            latest_application_id = VALUES(latest_application_id)");
}

function status_badge(string $status): string {
    $classes = [
        'Active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Closed' => 'bg-slate-50 text-slate-700 border-slate-200',
        'Applied' => 'bg-blue-50 text-blue-700 border-blue-200',
        'Assessment' => 'bg-amber-50 text-amber-700 border-amber-200',
        'Test Completed' => 'bg-purple-50 text-purple-700 border-purple-200',
        'HR Review' => 'bg-purple-50 text-purple-700 border-purple-200',
        'Interview' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
        'Interview Scheduled' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
        'HR Round' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'HOD Approved' => 'bg-teal-50 text-teal-700 border-teal-200',
        'Selected' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Offer Sent' => 'bg-lime-50 text-lime-700 border-lime-200',
        'Onboarding Completed' => 'bg-green-50 text-green-700 border-green-200',
        'Internship Started' => 'bg-green-50 text-green-700 border-green-200',
        'Rejected' => 'bg-red-50 text-red-700 border-red-200',
    ];
    $class = $classes[$status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
    return '<span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ' . $class . '">' . e($status) . '</span>';
}


function page_head(string $title): void {
    echo '<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($title) . ' - IMP</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet">
<style>
.material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24; }

/* Profile & Notification menus — must NOT clip their dropdowns */
.profile-menu { position: relative; overflow: visible !important; }
.notif-menu   { position: relative; overflow: visible !important; }

/* Dropdown panels — click-toggled via .open class on the wrapper */
.profile-menu .dropdown,
.notif-menu .dropdown {
    display: none;
    position: absolute !important;
    right: 0;
    top: calc(100% + 8px);
    z-index: 9999 !important;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(15,23,42,.16);
}
.profile-menu .dropdown { min-width: 190px; padding: 6px; }
.notif-menu .dropdown  { min-width: 320px; }

/* Show when .open is present on the wrapper */
.profile-menu.open .dropdown,
.notif-menu.open .dropdown { display: block !important; }

/* Ensure all dropdown links are always clickable */
.profile-menu .dropdown a,
.notif-menu .dropdown a {
    display: block;
    border-radius: 8px;
    padding: 9px 12px;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    pointer-events: auto !important;
    cursor: pointer !important;
}
.profile-menu .dropdown a:hover { background: #f8fafc; color: #1d4ed8; }
</style>
</head><body class="bg-[#f8f9fa] text-slate-900 antialiased">';
}


function hr_sidebar(string $active): void {
    $visible = [];
    $items = [
        ['Dashboard', 'hr_dashboard.php', 'dashboard', 'dashboard'],
        ['Applications', 'hr_applications.php', 'assignment', 'applications'],
        ['Candidates', 'candidates.php', 'group', 'candidates'],
        ['Student Logs', 'student_logs.php', 'description', 'student_logs'],
        ['Hiring Requests', 'hr_hiring_requests.php', 'handshake', 'hiring_requests'],
        ['Reports', 'hr_reports.php', 'analytics', 'reports'],
        ['Users', 'users.php', 'manage_accounts', 'users'],
    ];
    foreach ($items as $item) {
        if (function_exists('can_access_module') && can_access_module($item[3])) {
            $visible[] = $item;
        }
    }
    echo '<aside class="fixed left-0 top-0 z-50 flex h-screen w-60 flex-col border-r border-gray-200 bg-gray-50 py-6 text-sm font-medium">
<div class="mb-8 px-6"><a href="index.html" class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-blue-600 text-sm font-extrabold text-white">IMP</span><span class="text-xl font-bold text-blue-600">IMP</span></a><p class="ml-1 mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">HR Portal</p></div>
<nav class="flex-1 space-y-1 px-4">';
    foreach ($visible as [$label, $href, $icon, $key]) {
        $is_active = ($active === $key) || ($key === 'applications' && $active === 'archived_applications');
        $class = $is_active
            ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-600'
            : 'text-gray-600 hover:bg-gray-100';
        echo '<a href="' . e($href) . '" class="flex items-center gap-3 rounded-lg px-4 py-3 transition ' . $class . '"><span class="material-symbols-outlined">' . e($icon) . '</span><span>' . e($label) . '</span></a>';
    }
    echo '</nav><div class="mt-auto border-t border-gray-200 px-4 pt-4"><a href="logout.php" class="flex items-center gap-3 rounded-lg px-4 py-3 text-gray-600 hover:bg-gray-100"><span class="material-symbols-outlined">logout</span><span>Logout</span></a></div></aside>';
}

function module_search_config(string $active): array {
    switch ($active) {
        case 'student_logs':
            return ['student_logs.php', 'Search student daily logs by student name...'];
        case 'applications':
            return ['hr_applications.php', 'Search applications by name or email...'];
        case 'archived_applications':
            return ['archived_applications.php', 'Search archived applications by name or email...'];
        case 'candidates':
            return ['candidates.php', 'Search candidates, skills, or colleges...'];
        case 'users':
            return ['users.php', 'Search users by name or email...'];
        default:
            return ['candidates.php', 'Search candidates, skills, or colleges...'];
    }
}

function module_topbar(string $active, string $action_html = '', bool $show_search = true): string {
    global $conn;
    [$search_action, $placeholder] = module_search_config($active);
    $search_value = e($_GET['search'] ?? '');
    $name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User';
    $email = $_SESSION['email'] ?? '';
    $role = ucfirst((string) ($_SESSION['role'] ?? ''));
    $profile_image = '';
    $notification_count = 0;
    
    $is_mentor = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'mentor');
    $recent_notifs = [];
    $interns_count = 0;
    
    if (isset($conn) && $conn instanceof mysqli) {
        $uid = current_user_id();
        if ($uid) {
            $user_stmt = $conn->prepare("SELECT full_name, email, role, profile_image FROM users WHERE id = ? LIMIT 1");
            if ($user_stmt) {
                $user_stmt->bind_param('i', $uid);
                $user_stmt->execute();
                $user_row = $user_stmt->get_result()->fetch_assoc();
                if ($user_row) {
                    $name = $user_row['full_name'] ?: $name;
                    $email = $user_row['email'] ?: $email;
                    $role = ucfirst((string) ($user_row['role'] ?: $role));
                    $profile_image = $user_row['profile_image'] ?: '';
                }
            }
            
            if ($is_mentor) {
                // Mentor specific notifications query
                $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $uid AND is_read = 0");
                if ($notif_res) {
                    $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
                }
                
                $notif_q = mysqli_query($conn, "SELECT id, title, type, message, created_at, is_read FROM notifications WHERE user_id = $uid ORDER BY id DESC LIMIT 5");
                if ($notif_q) {
                    while ($n_row = mysqli_fetch_assoc($notif_q)) {
                        $recent_notifs[] = $n_row;
                    }
                }
                
                $interns_q = mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS total FROM project_team_members ptm JOIN project_teams pt ON ptm.project_team_id = pt.id WHERE pt.mentor_id = $uid AND pt.status = 'Active'");
                if ($interns_q) {
                    $interns_count = (int) mysqli_fetch_assoc($interns_q)['total'];
                }
            } else {
                // HR notifications query (from hr_notifications table)
                $notif_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM hr_notifications WHERE is_read = 0");
                if ($notif_res) {
                    $notification_count = (int) mysqli_fetch_assoc($notif_res)['total'];
                }
 
                $notif_q = mysqli_query($conn, "SELECT id, title, type, message, created_at, is_read FROM hr_notifications ORDER BY id DESC LIMIT 5");
                if ($notif_q) {
                    while ($n_row = mysqli_fetch_assoc($notif_q)) {
                        $recent_notifs[] = $n_row;
                    }
                }
            }
        }
    }
    
    $initial = strtoupper(substr((string) $name, 0, 1)) ?: 'U';
    $avatar_html = $profile_image !== ''
        ? '<img src="' . e($profile_image) . '" alt="' . e($name) . '" class="h-9 w-9 rounded-full object-cover">'
        : '<span class="grid h-9 w-9 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white">' . e($initial) . '</span>';
    
    $search_html = '';
    if ($show_search) {
        $search_html = '<div class="flex min-w-0 flex-1 items-center gap-5">
            <form method="GET" action="' . e($search_action) . '" class="relative w-full max-w-md">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
                <input type="text" name="search" value="' . $search_value . '" placeholder="' . e($placeholder) . '" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
            </form>
        </div>';
    }
    
    $badge_class = $notification_count > 0 ? '' : 'hidden';
    $badge_text = $notification_count > 99 ? '99+' : $notification_count;
    
    // Build notifications dropdown HTML
    $notif_html = '';
    $is_hr = (isset($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['hr', 'admin'], true));
    
    if ($is_mentor || $is_hr) {
        $items_html = '';
        if (!empty($recent_notifs)) {
            foreach ($recent_notifs as $n) {
                $time = date('M d, g:i A', strtotime($n['created_at']));
                $unread_style = !$n['is_read'] ? 'bg-blue-50/50 border-l-4 border-blue-600' : '';
                
                $icon = 'notifications';
                $icon_color = 'text-blue-600 bg-blue-50';
                $ntype = strtolower($n['type'] ?? '');
                
                if ($ntype === 'log_submission' || $ntype === 'log_resubmission') {
                    $icon = 'assignment_turned_in';
                    $icon_color = 'text-purple-600 bg-purple-50';
                } elseif ($ntype === 'intern_assignment' || $ntype === 'new_application' || $ntype === 'application_submitted') {
                    $icon = 'person_add';
                    $icon_color = 'text-green-600 bg-green-50';
                } elseif ($ntype === 'reminder') {
                    $icon = 'warning';
                    $icon_color = 'text-amber-600 bg-amber-50';
                }
                
                $redirect_url = $is_mentor ? 'mentor_notifications.php' : 'hr_dashboard.php';
                if ($is_mentor) {
                    if ($ntype === 'log_submission' || $ntype === 'log_resubmission') {
                        $redirect_url = 'mentor_daily_logs.php';
                    } elseif ($ntype === 'intern_assignment') {
                        $redirect_url = 'mentor_dashboard.php';
                    }
                } else {
                    if ($ntype === 'log_submission' || $ntype === 'log_resubmission') {
                        $redirect_url = 'student_logs.php';
                    } elseif ($ntype === 'new_application' || $ntype === 'application_submitted') {
                        $redirect_url = 'hr_applications.php';
                    }
                }
                $notif_url = 'mark_notification_read.php?id=' . intval($n['id']) . '&redirect=' . urlencode($redirect_url);
 
                $items_html .= '<a href="' . $notif_url . '" class="block px-4 py-3 border-b border-slate-100 hover:bg-slate-50 transition ' . $unread_style . '">
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ' . $icon_color . '">
                            <span class="material-symbols-outlined text-[16px]">' . $icon . '</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-bold text-slate-800 truncate">' . e($n['title']) . '</p>
                            <p class="text-[10px] text-slate-500 mt-0.5 leading-snug line-clamp-2">' . e($n['message']) . '</p>
                            <span class="text-[9px] text-slate-400 font-semibold block mt-1">' . $time . '</span>
                        </div>
                    </div>
                </a>';
            }
        } else {
            $items_html = '<div class="px-4 py-6 text-center text-slate-400 text-xs font-semibold">No alerts available</div>';
        }
        
        $badge_id = $is_mentor ? 'mentor-navbar-badge' : 'hr-notification-badge';
        $view_all_url = $is_mentor ? 'mentor_notifications.php' : 'hr_applications.php';
        
        $notif_html = '<div class="notif-menu" id="imp-notif-menu">
            <button type="button" id="imp-notif-btn" onclick="impToggleNotifMenu(event)" style="cursor:pointer;" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-50" title="Notifications">
                <span class="material-symbols-outlined">notifications</span>
                <span id="' . $badge_id . '" class="' . $badge_class . ' absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">' . e($badge_text) . '</span>
            </button>
            <div class="dropdown">
                <div class="px-4 py-2 border-b border-slate-100 flex items-center justify-between font-bold text-xs text-slate-500 uppercase tracking-wider">
                    <span>Recent Alerts</span>
                    ' . ($notification_count > 0 ? '<button id="btn-mark-all-read-dropdown" onclick="markAllNotificationsRead(event)" class="text-[9px] font-black text-blue-600 hover:underline lowercase tracking-normal">Mark all read</button>' : '') . '
                </div>
                <div class="max-h-64 overflow-y-auto">' . $items_html . '</div>
                <div class="border-t border-slate-100 px-4 py-2 text-center bg-slate-50"><a href="' . $view_all_url . '" class="text-xs font-black text-blue-600 hover:underline">View All Notifications</a></div>
            </div>
        </div>';
 
    }
    
    // Build profile dropdown HTML
    $profile_dropdown_html = '';
    if ($is_mentor) {
        $profile_dropdown_html = '<div class="dropdown">
            <div class="border-b border-slate-100 px-4 py-3 bg-slate-50/50">
                <p class="text-sm font-black text-slate-800">' . e($name) . '</p>
                <p class="truncate text-xs text-slate-400 mt-0.5">' . e($email) . '</p>
                <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 text-[9px] font-extrabold rounded uppercase mt-1.5 tracking-wider">' . e($role) . '</span>
            </div>
            <div class="px-4 py-3 border-b border-slate-100 text-xs text-slate-600 flex items-center justify-between font-semibold">
                <span>Assigned Interns:</span>
                <span class="bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full font-extrabold">' . $interns_count . '</span>
            </div>
            <a href="logout.php" class="block px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 hover:text-red-700 transition">Logout</a>
        </div>';
    } else {
        $profile_dropdown_html = '<div class="dropdown">
            <div class="border-b border-slate-100 px-3 py-3"><p class="text-sm font-bold text-slate-900">' . e($name) . '</p><p class="truncate text-xs text-slate-500">' . e($email) . '</p></div>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php" style="color:#dc2626;">Logout</a>
        </div>';
    }
    
    return '<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">' . $search_html . '<div class="flex items-center gap-3">' . $action_html . $notif_html . '<div class="profile-menu" id="imp-profile-menu">
            <button type="button" id="imp-profile-btn" onclick="impToggleProfileMenu(event)" style="cursor:pointer;" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50 select-none">
                ' . $avatar_html . '
                <span class="hidden text-left lg:block"><span class="block text-sm font-bold text-slate-900">' . e($name) . '</span><span class="block text-xs text-slate-500">' . e($role) . '</span></span>
                <span class="material-symbols-outlined text-slate-400">expand_more</span>
            </button>
            ' . $profile_dropdown_html . '
        </div></div></div>';
}
 
 
function module_search_row(string $active): string {
    [$search_action, $placeholder] = module_search_config($active);
    $search_value = e($_GET['search'] ?? '');
    return '<div class="max-w-xl">'
         . '<form method="GET" action="' . e($search_action) . '" class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">'
         . '<div class="relative">'
         . '<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>'
         . '<input type="search" name="search" value="' . $search_value . '" placeholder="' . e($placeholder) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">'
         . '</div>'
         . '</form>'
         . '</div>';
}
 
function page_shell_start(string $active, string $title, string $subtitle = '', string $action_html = ''): void {
    page_head($title);
    if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'mentor') {
        mentor_sidebar($active);
    } else {
        hr_sidebar($active);
    }
    echo '<main class="min-h-screen pl-60"><header class="sticky top-0 z-40 border-b border-gray-200 bg-white px-8 py-4 shadow-sm">';
    $is_dashboard_style = in_array($active, ['dashboard', 'workflows', 'candidates', 'postings', 'applications', 'archived_applications'], true) && in_array($title, ['Dashboard', 'Workflows', 'Candidates', 'Postings', 'Applications', 'Archived Applications'], true);
    
    echo '<div class="mb-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] items-start">';
    echo '<div><h1 class="text-2xl font-extrabold text-slate-900">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="mt-1 text-sm text-slate-500">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    echo '<div class="flex items-center justify-end gap-4">' . module_topbar($active, $action_html, false) . '</div>';
    echo '</div>';
    echo '</header>';
    
    if ($is_dashboard_style) {
        echo '<div class="mt-4 px-8">' . module_search_row($active) . '</div>';
    }
    echo '<section class="px-8 py-8">';
    echo module_flash();
}
 
function page_shell_end(): void {
    echo '</section></main>
<script>
// ── IMP Profile Dropdown — click-to-toggle, click-outside-to-close ──────────
function impToggleProfileMenu(event) {
    event.stopPropagation();
    var menu = document.getElementById("imp-profile-menu");
    var notif = document.getElementById("imp-notif-menu");
    if (notif) notif.classList.remove("open");
    if (menu) { menu.classList.toggle("open"); }
}
function impToggleNotifMenu(event) {
    event.stopPropagation();
    var menu = document.getElementById("imp-notif-menu");
    var profile = document.getElementById("imp-profile-menu");
    if (profile) profile.classList.remove("open");
    if (menu) { menu.classList.toggle("open"); }
}
document.addEventListener("click", function(e) {
    var profileMenu = document.getElementById("imp-profile-menu");
    var notifMenu   = document.getElementById("imp-notif-menu");
    if (profileMenu && !profileMenu.contains(e.target)) { profileMenu.classList.remove("open"); }
    if (notifMenu   && !notifMenu.contains(e.target))   { notifMenu.classList.remove("open"); }
});
// Make all dropdown links always clickable regardless of parent styles
document.addEventListener("DOMContentLoaded", function() {
    ["imp-profile-menu","imp-notif-menu"].forEach(function(id) {
        var menu = document.getElementById(id);
        if (!menu) return;
        menu.querySelectorAll(".dropdown a").forEach(function(link) {
            link.style.pointerEvents = "auto";
            link.style.cursor = "pointer";
            link.style.display = "block";
        });
    });
});

// ── HR Notification Badge polling ───────────────────────────────────────────
(function() {
    const badge = document.getElementById("hr-notification-badge");
    if (!badge) return;


    
    let lastCount = parseInt(badge.textContent) || 0;
    
    async function checkNotifications() {
        try {
            const response = await fetch("get_notification_count.php");
            if (!response.ok) return;
            const data = await response.json();
            if (data.success) {
                const count = parseInt(data.count) || 0;
                if (count !== lastCount) {
                    badge.textContent = count > 99 ? "99+" : count;
                    if (count > 0) {
                        badge.classList.remove("hidden");
                        // Play a scale/bounce animation if the count increased
                        if (count > lastCount) {
                            badge.animate([
                                { transform: "scale(1)" },
                                { transform: "scale(1.4)" },
                                { transform: "scale(0.9)" },
                                { transform: "scale(1)" }
                            ], {
                                duration: 400,
                                easing: "ease-out"
                            });
                        }
                    } else {
                        badge.classList.add("hidden");
                    }
                    lastCount = count;
                }
            }
        } catch (err) {
            console.error("Error polling notifications:", err);
        }
    }
    
    // Poll every 10 seconds
    setInterval(checkNotifications, 10000);
})();

// Real-time EventSource SSE Notifications Listener
(function() {
    if (typeof EventSource === "undefined") return;
    
    const source = new EventSource("sse_notifications.php");
    
    source.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            showLiveToastNotification(data.title, data.message, data.type);
            
            // Increment corresponding badge counts dynamically
            const badges = ["mentor-notification-badge", "mentor-navbar-badge", "sidebar-badge", "hr-notification-badge"];
            badges.forEach(id => {
                const badge = document.getElementById(id);
                if (badge) {
                    let currentCount = parseInt(badge.textContent) || 0;
                    currentCount++;
                    badge.textContent = currentCount;
                    badge.classList.remove("hidden");
                }
            });
        } catch (e) {
            console.error("Error parsing SSE data:", e);
        }
    };
    
    function showLiveToastNotification(title, message, type) {
        const toast = document.createElement("div");
        toast.className = "fixed bottom-5 right-5 z-[999] max-w-sm w-full bg-white border border-slate-100 rounded-2xl shadow-[0_10px_30px_rgba(15,23,42,0.15)] p-4 flex gap-3 transform translate-y-10 opacity-0 transition-all duration-300 ease-out";
        
        let icon = "notifications";
        let iconColor = "bg-blue-50 text-blue-600";
        
        if (type === "log_submission" || type === "log_resubmission") {
            icon = "assignment_turned_in";
            iconColor = "bg-purple-50 text-purple-600";
        } else if (type === "intern_assignment") {
            icon = "person_add";
            iconColor = "bg-green-50 text-green-700";
        }
        
        toast.innerHTML = `
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ${iconColor}">
                <span class="material-symbols-outlined text-[20px]">${icon}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-black text-slate-800">${title}</p>
                <p class="text-xs text-slate-500 mt-1 font-semibold leading-relaxed">${message}</p>
            </div>
            <button class="text-slate-450 hover:text-slate-600 shrink-0 self-start transition-colors">
                <span class="material-symbols-outlined text-sm font-bold">close</span>
            </button>
        `;
        
        toast.querySelector("button").addEventListener("click", () => {
            toast.classList.remove("translate-y-0", "opacity-100");
            toast.classList.add("translate-y-2", "opacity-0");
            setTimeout(() => toast.remove(), 300);
        });
        
        document.body.appendChild(toast);
        toast.offsetHeight; // trigger reflow
        
        toast.classList.remove("translate-y-10", "opacity-0");
        toast.classList.add("translate-y-0", "opacity-100");
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.remove("translate-y-0", "opacity-100");
                toast.classList.add("translate-y-2", "opacity-0");
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }
})();

async function markAllNotificationsRead(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    if (!confirm("Mark all alerts as read?")) return;
    try {
        const res = await fetch("mark_notification_read.php?action=read_all");
        const data = await res.json();
        if (data.success) {
            const badges = ["mentor-notification-badge", "mentor-navbar-badge", "sidebar-badge", "hr-notification-badge"];
            badges.forEach(id => {
                const b = document.getElementById(id);
                if (b) {
                    b.textContent = "0";
                    b.classList.add("hidden");
                }
            });
            document.querySelectorAll(".notif-menu .dropdown a").forEach(item => {
                item.className = item.className.replace(/bg-blue-50\/50 border-l-4 border-blue-600/g, "");
            });
            const btn = document.getElementById("btn-mark-all-read-dropdown");
            if (btn) btn.remove();
        }
    } catch(err) {
        console.error("Error marking read:", err);
    }
}
</script>
</body></html>';
}

function mentor_sidebar(string $active): void {
    global $conn;
    $unread_count = 0;
    if (isset($conn) && $conn instanceof mysqli) {
        $uid = current_user_id();
        if ($uid) {
            $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $uid AND is_read = 0");
            if ($res) {
                $unread_count = (int) mysqli_fetch_assoc($res)['total'];
            }
        }
    }

    echo '<aside class="fixed left-0 top-0 z-50 flex h-screen w-60 flex-col border-r border-gray-200 bg-gray-50 py-6 text-sm font-medium">
<div class="mb-8 px-6"><a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity"><span class="grid h-8 w-8 place-items-center rounded-lg bg-blue-600 text-sm font-extrabold text-white">IMP</span><span class="text-xl font-bold text-blue-600">IMP</span></a><p class="ml-1 mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">Mentor Portal</p></div>
<nav class="flex-1 space-y-1 px-4">';

    $items = [
        ['Dashboard', 'mentor_dashboard.php', 'dashboard', 'dashboard'],
        ['Review Daily Logs', 'mentor_daily_logs.php', 'rate_review', 'review_logs'],
        ['Notifications', 'mentor_notifications.php', 'notifications', 'notifications'],
    ];

    foreach ($items as [$label, $href, $icon, $key]) {
        $is_active = ($active === $key);
        $class = $is_active
            ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-600'
            : 'text-gray-600 hover:bg-gray-100';
        
        $badge = '';
        if ($key === 'notifications') {
            $badge_class = ($unread_count > 0) ? '' : 'hidden';
            $badge = '<span id="mentor-notification-badge" class="ml-auto bg-red-600 text-white py-0.5 px-2 rounded-full text-[10px] font-bold ' . $badge_class . '">' . $unread_count . '</span>';
        }
        
        echo '<a href="' . e($href) . '" class="flex items-center gap-3 rounded-lg px-4 py-3 transition ' . $class . '"><span class="material-symbols-outlined">' . e($icon) . '</span><span>' . e($label) . '</span>' . $badge . '</a>';
    }

    echo '</nav><div class="mt-auto border-t border-gray-200 px-4 pt-4"><a href="logout.php" class="flex items-center gap-3 rounded-lg px-4 py-3 text-gray-600 hover:bg-gray-100"><span class="material-symbols-outlined">logout</span><span>Logout</span></a></div></aside>';
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_token_field')) {
    function csrf_token_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
    }
}

function validate_and_upload_file(array $file, array $allowed_exts, array $allowed_mimes, int $max_size_bytes, string $upload_dir, string $prefix = 'file_'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }
    
    $file_tmp = $file['tmp_name'];
    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file['size'] > $max_size_bytes) {
        return ['success' => false, 'error' => 'File size exceeds limit of ' . ($max_size_bytes / (1024 * 1024)) . 'MB.'];
    }
    
    // Check extension
    if (!in_array($file_ext, $allowed_exts, true)) {
        return ['success' => false, 'error' => 'Invalid file extension. Allowed: ' . implode(', ', $allowed_exts)];
    }
    
    // Check MIME type
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file_tmp);
    } else {
        $mime_type = $file['type']; // Fallback
    }
    
    if (!in_array(strtolower($mime_type), $allowed_mimes, true)) {
        // Also check if it's octet-stream for zip/rar because browsers might send octet-stream
        if (!(in_array('application/octet-stream', $allowed_mimes) && ($file_ext === 'zip' || $file_ext === 'rar'))) {
            return ['success' => false, 'error' => 'Invalid file MIME type (' . htmlspecialchars($mime_type) . ').'];
        }
    }
    
    // Prevent PHP/HTML upload bypass
    $blocked_exts = ['php', 'phtml', 'phar', 'php5', 'php7', 'php8', 'html', 'htm', 'js', 'jsp', 'asp', 'aspx', 'exe', 'sh', 'bat'];
    if (in_array($file_ext, $blocked_exts, true)) {
        return ['success' => false, 'error' => 'Malicious file extension blocked.'];
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Create new unique name
    $new_file_name = uniqid($prefix, true) . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($file_tmp, $destination)) {
        return ['success' => true, 'filename' => $new_file_name];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
}

function module_pagination(int $total_rows, int $limit, int $page, string $base_url, array $params = []): string {
    $total_pages = (int) ceil($total_rows / $limit);
    if ($total_pages <= 1) {
        return '';
    }
    
    // Filter out page parameter from params
    unset($params['page']);
    $query_string = '';
    if (!empty($params)) {
        $query_string = '&' . http_build_query($params);
    }
    
    $offset = ($page - 1) * $limit;
    $showing_start = $total_rows > 0 ? $offset + 1 : 0;
    $showing_end = min($offset + $limit, $total_rows);
    
    $html = '<div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-6 rounded-2xl shadow-sm">';
    
    // Mobile layout
    $html .= '<div class="flex flex-1 justify-between sm:hidden">';
    if ($page > 1) {
        $html .= '<a href="' . e($base_url) . '?page=' . ($page - 1) . $query_string . '" class="relative inline-flex items-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50">Previous</a>';
    } else {
        $html .= '<div></div>';
    }
    if ($page < $total_pages) {
        $html .= '<a href="' . e($base_url) . '?page=' . ($page + 1) . $query_string . '" class="relative ml-3 inline-flex items-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50">Next</a>';
    } else {
        $html .= '<div></div>';
    }
    $html .= '</div>';
    
    // Desktop layout
    $html .= '<div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">';
    $html .= '<div><p class="text-xs text-slate-500 font-semibold">Showing <span class="font-bold text-slate-800">' . $showing_start . '</span> to <span class="font-bold text-slate-800">' . $showing_end . '</span> of <span class="font-bold text-slate-800">' . $total_rows . '</span> results</p></div>';
    $html .= '<div><nav class="isolate inline-flex -space-x-px rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden" aria-label="Pagination">';
    
    // Previous button
    if ($page > 1) {
        $html .= '<a href="' . e($base_url) . '?page=' . ($page - 1) . $query_string . '" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-600 transition-colors"><span class="material-symbols-outlined text-sm">chevron_left</span></a>';
    }
    
    // Page links
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    for ($p = $start_page; $p <= $end_page; $p++) {
        $is_active = ($p === $page);
        $active_class = $is_active
            ? 'z-10 bg-blue-600 text-white'
            : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 border-l border-gray-100';
        $html .= '<a href="' . e($base_url) . '?page=' . $p . $query_string . '" class="relative inline-flex items-center px-4 py-2 text-xs font-bold transition-colors ' . $active_class . '">' . $p . '</a>';
    }
    
    // Next button
    if ($page < $total_pages) {
        $html .= '<a href="' . e($base_url) . '?page=' . ($page + 1) . $query_string . '" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-600 transition-colors border-l border-gray-100"><span class="material-symbols-outlined text-sm">chevron_right</span></a>';
    }
    
    $html .= '</nav></div></div></div>';
    
    return $html;
}
