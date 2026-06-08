<?php

// Live Render + Clever Cloud connection configuration
$host = getenv('DB_HOST') ?: "by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com";
$user = getenv('DB_USER') ?: "ujebqn1hlk9qd98k";
$pass = getenv('DB_PASSWORD') ?: "zqPIiSbk9EU6l3KHrvml";
$db   = getenv('DB_NAME') ?: "by7xxebmaxfwobqrh1ne";
$port = getenv('DB_PORT') ?: 3306;

try {
    $conn = mysqli_connect($host, $user, $pass, $db, $port);

    // Prevent test/setup scripts from executing in production environment
    $is_production = (getenv('APP_ENV') === 'production' || strpos($host, 'clever-cloud.com') !== false || strpos($host, 'render.com') !== false);
    if ($is_production) {
        $current_script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        if (strpos($current_script, 'test_') === 0 || strpos($current_script, 'temp_') === 0 || strpos($current_script, 'check_') === 0 || $current_script === 'setup_database.php') {
            if ($current_script !== 'test_live_db_users.php') {
                die("SECURITY ERROR: Execution of test/setup script '$current_script' is blocked on the production database.");
            }
        }
    }
// Register shutdown function to ensure the DB connection is closed at script termination
register_shutdown_function(function() use (&$conn) {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
});
log_debug('Database connection opened and shutdown handler registered.');
} catch (\mysqli_sql_exception $e) {
    $is_local = (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
    if (!$is_local) {
        http_response_code(503);
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Server Busy - IMP</title>
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #191c1d; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { text-align: center; max-width: 450px; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e1e3e4; }
                h1 { font-size: 24px; font-weight: 600; margin-bottom: 16px; color: #004ac6; }
                p { font-size: 14px; line-height: 22px; color: #434655; margin-bottom: 24px; }
                button { background-color: #004ac6; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; }
                button:hover { background-color: #003ea8; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Server is Busy</h1>
                <p>The system is currently experiencing high traffic (database connections exhausted). Please wait a few seconds and click reload to try again.</p>
                <button onclick='window.location.reload()'>Reload Page</button>
            </div>
        </body>
        </html>";
        exit();
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add `title` column to student_notifications if it doesn't exist yet
$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_notifications LIKE 'title'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE student_notifications
                         ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT ''
                         AFTER user_id");
}
unset($_col_check);

// Add `resume_url` column to student_profiles if it doesn't exist yet
$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'resume_url'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE student_profiles ADD COLUMN resume_url VARCHAR(255) NULL AFTER resume_file");
}
unset($_col_check);

// Add approval columns to internships safely
$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'status'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN status VARCHAR(50) DEFAULT 'Pending Approval'");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'approval_status'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN approval_status VARCHAR(50) DEFAULT 'Pending Approval'");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'approved_by'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN approved_by INT DEFAULT NULL");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'approved_at'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN approved_at DATETIME NULL DEFAULT NULL");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internships LIKE 'admin_remarks'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN admin_remarks TEXT NULL");
}
unset($_col_check);

// Add issues_faced and next_plan columns to daily_logs safely
$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE 'issues_faced'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE daily_logs ADD COLUMN issues_faced TEXT NULL");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM daily_logs LIKE 'next_plan'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE daily_logs ADD COLUMN next_plan TEXT NULL");
}
unset($_col_check);

// Add workflow columns to internship_applications safely
$app_new_cols = [
    'hod_phone' => "VARCHAR(20) DEFAULT NULL",
    'test_result' => "VARCHAR(100) DEFAULT NULL",
    'hr_review_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hod_approval_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hod_approval_sent_at' => "TIMESTAMP NULL DEFAULT NULL",
    'hod_approved_at' => "TIMESTAMP NULL DEFAULT NULL",
    'hod_remarks' => "TEXT DEFAULT NULL",
    'selected_by' => "INT DEFAULT NULL",
    'selected_at' => "TIMESTAMP NULL DEFAULT NULL",
    'hr_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hod_token' => "VARCHAR(255) DEFAULT NULL",
    'assigned_project_id' => "INT DEFAULT NULL",
    'team_id' => "INT DEFAULT NULL",
    'mentor_id' => "INT DEFAULT NULL",
    'confirmation_letter_path' => "VARCHAR(255) DEFAULT NULL",
    'confirmation_letter_sent_at' => "TIMESTAMP NULL DEFAULT NULL",
    'certificate_path' => "VARCHAR(255) DEFAULT NULL"
];
foreach ($app_new_cols as $_col => $_def) {
    $_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$_col'");
    if ($_col_check && mysqli_num_rows($_col_check) === 0) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $_col $_def");
    }
    unset($_col_check);
}

// Ensure admin-managed category tables exist and migrate existing internship category data
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_types_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_subtypes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type_id INT NOT NULL,
    subtype_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_subtypes_name (project_type_id, subtype_name),
    FOREIGN KEY (project_type_id) REFERENCES project_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure skills, mode, and duration columns exist in project_subtypes
foreach (['skills' => "TEXT NULL", 'mode' => "VARCHAR(50) NULL", 'duration' => "VARCHAR(50) NULL"] as $_col => $_def) {
    $_col_check = mysqli_query($conn, "SHOW COLUMNS FROM project_subtypes LIKE '$_col'");
    if ($_col_check && mysqli_num_rows($_col_check) === 0) {
        mysqli_query($conn, "ALTER TABLE project_subtypes ADD COLUMN $_col $_def");
    }
    unset($_col_check);
}

$project_type_count = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM project_types");
if ($project_type_count) {
    $count_row = mysqli_fetch_assoc($project_type_count);
    if (intval($count_row['cnt']) === 0) {
        mysqli_query($conn, "INSERT IGNORE INTO project_types (type_name, status) VALUES ('Development', 'Active'), ('Design', 'Active'), ('Marketing', 'Active')");
    }
}

$type_rows = mysqli_query($conn, "SELECT DISTINCT TRIM(project_type) AS type_name FROM internships WHERE project_type IS NOT NULL AND TRIM(project_type) <> ''");
while ($row = mysqli_fetch_assoc($type_rows)) {
    $type_name = mysqli_real_escape_string($conn, $row['type_name']);
    mysqli_query($conn, "INSERT IGNORE INTO project_types (type_name, status) VALUES ('$type_name', 'Active')");
}

$type_map = [];
$type_res = mysqli_query($conn, "SELECT id, type_name FROM project_types");
while ($row = mysqli_fetch_assoc($type_res)) {
    $type_map[strtolower(trim($row['type_name']))] = intval($row['id']);
}

$subtype_rows = mysqli_query($conn, "SELECT DISTINCT TRIM(project_type) AS type_name, TRIM(project_subtype) AS subtype_name FROM internships WHERE project_type IS NOT NULL AND TRIM(project_type) <> '' AND project_subtype IS NOT NULL AND TRIM(project_subtype) <> ''");
while ($row = mysqli_fetch_assoc($subtype_rows)) {
    $type_name = trim($row['type_name']);
    $subtype_name = trim($row['subtype_name']);
    $type_id = $type_map[strtolower($type_name)] ?? 0;
    if ($type_id > 0 && $subtype_name !== '') {
        $subtype_name_safe = mysqli_real_escape_string($conn, $subtype_name);
        mysqli_query($conn, "INSERT IGNORE INTO project_subtypes (project_type_id, subtype_name, status) VALUES ($type_id, '$subtype_name_safe', 'Active')");
    }
}

if (isset($app_id) && intval($app_id) > 0) {
    $app_id = intval($app_id);
    $sql = "SELECT status, performance_score, mentor_evaluation, certificate_status, in_talent_pool, user_id FROM internship_applications WHERE id = $app_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        error_log("Database query failed in db.php: " . mysqli_error($conn) . " SQL: " . $sql);
    } elseif ($row = mysqli_fetch_assoc($res)) {
        $status = strtolower($row['status']);
        $score = $row['performance_score'];
        $mentor_eval = strtolower($row['mentor_evaluation']);
        $cert_status = strtolower($row['certificate_status']);
        $in_pool = intval($row['in_talent_pool']);

        // Check eligibility conditions:
        // - Internship status is Completed
        $completed_statuses = ['completed', 'certificate issued', 'internship completed', 'project completed', 'evaluated'];
        $is_completed = in_array($status, $completed_statuses);

        // - Performance score is 70 or above
        $score_eligible = ($score !== null && $score >= 70);

        // - Mentor evaluation is Approved
        $mentor_eligible = ($mentor_eval === 'approved');

        // - Certificate is Generated/Completed
        $cert_eligible = in_array($cert_status, ['generated', 'completed']);

        if ($is_completed && $score_eligible && $mentor_eligible && $cert_eligible) {
            $user_id = intval($row['user_id']);
            // Prevent duplicate talent pool entries for the same student (user_id)
            $dup_check = mysqli_query($conn, "SELECT id FROM internship_applications WHERE user_id = $user_id AND in_talent_pool = 1 AND id != $app_id LIMIT 1");
            if ($dup_check && mysqli_num_rows($dup_check) > 0) {
                mysqli_query($conn, "UPDATE internship_applications SET talent_pool_status = 'Yes', in_talent_pool = 0 WHERE id = $app_id");
            } else {
                mysqli_query($conn, "UPDATE internship_applications SET in_talent_pool = 1, talent_pool_status = 'Yes' WHERE id = $app_id");
            }
        } else {
            // If not eligible, set status to No and remove from pool
            mysqli_query($conn, "UPDATE internship_applications SET in_talent_pool = 0, talent_pool_status = 'No' WHERE id = $app_id");
        }
    }
}

// Ensure message_logs table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS message_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_role VARCHAR(50) NOT NULL,
    receiver_id INT NOT NULL,
    receiver_role VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_type ENUM('in-app', 'email', 'both') NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM message_logs LIKE 'team_id'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE message_logs ADD COLUMN team_id INT NULL DEFAULT NULL AFTER receiver_role");
}
unset($_col_check);

$_col_check = mysqli_query($conn, "SHOW COLUMNS FROM message_logs LIKE 'recipient_group'");
if ($_col_check && mysqli_num_rows($_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE message_logs ADD COLUMN recipient_group VARCHAR(100) NULL DEFAULT NULL AFTER team_id");
}
unset($_col_check);

// Add original file name columns if missing
$sp_orig_cols = [
    'resume_original_name' => "ALTER TABLE student_profiles ADD COLUMN resume_original_name VARCHAR(255) NULL DEFAULT NULL",
    'aadhaar_original_name' => "ALTER TABLE student_profiles ADD COLUMN aadhaar_original_name VARCHAR(255) NULL DEFAULT NULL",
    'pan_original_name' => "ALTER TABLE student_profiles ADD COLUMN pan_original_name VARCHAR(255) NULL DEFAULT NULL"
];
foreach ($sp_orig_cols as $col => $sql) {
    $_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE '$col'");
    if ($_check && mysqli_num_rows($_check) === 0) {
        mysqli_query($conn, $sql);
    }
    unset($_check);
}

$ia_orig_cols = [
    'resume_original_name' => "ALTER TABLE internship_applications ADD COLUMN resume_original_name VARCHAR(255) NULL DEFAULT NULL",
    'aadhaar_original_name' => "ALTER TABLE internship_applications ADD COLUMN aadhaar_original_name VARCHAR(255) NULL DEFAULT NULL",
    'pan_original_name' => "ALTER TABLE internship_applications ADD COLUMN pan_original_name VARCHAR(255) NULL DEFAULT NULL"
];
foreach ($ia_orig_cols as $col => $sql) {
    $_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($_check && mysqli_num_rows($_check) === 0) {
        mysqli_query($conn, $sql);
    }
    unset($_check);
}

$_has_apps = mysqli_query($conn, "SHOW TABLES LIKE 'applications'");
if ($_has_apps && mysqli_num_rows($_has_apps) > 0) {
    $_check = mysqli_query($conn, "SHOW COLUMNS FROM applications LIKE 'resume_original_name'");
    if ($_check && mysqli_num_rows($_check) === 0) {
        mysqli_query($conn, "ALTER TABLE applications ADD COLUMN resume_original_name VARCHAR(255) NULL DEFAULT NULL");
    }
    unset($_check);
}
unset($_has_apps);


if (!function_exists('getDocumentViewUrl')) {
    function getDocumentViewUrl($url) {
        if (empty($url)) return '#';
        return $url;
    }
}

function get_resume_view_link($profile) {
    if (!$profile) {
        return '#';
    }
    // Check resume_url
    if (!empty($profile['resume_url']) && (strpos($profile['resume_url'], 'http://') === 0 || strpos($profile['resume_url'], 'https://') === 0)) {
        return $profile['resume_url'];
    }
    // Check resume_file as an absolute URL (for safety)
    if (!empty($profile['resume_file']) && (strpos($profile['resume_file'], 'http://') === 0 || strpos($profile['resume_file'], 'https://') === 0)) {
        return $profile['resume_file'];
    }
    // Check local resume_file
    if (!empty($profile['resume_file'])) {
        return 'resume_serve.php?file=' . urlencode(basename($profile['resume_file'])) . '&mode=view';
    }
    return '#';
}

function get_resume_download_link($profile) {
    if (!$profile) {
        return '#';
    }
    // Check resume_url
    if (!empty($profile['resume_url']) && (strpos($profile['resume_url'], 'http://') === 0 || strpos($profile['resume_url'], 'https://') === 0)) {
        return $profile['resume_url'];
    }
    // Check resume_file as an absolute URL
    if (!empty($profile['resume_file']) && (strpos($profile['resume_file'], 'http://') === 0 || strpos($profile['resume_file'], 'https://') === 0)) {
        return $profile['resume_file'];
    }
    // Check local resume_file
    if (!empty($profile['resume_file'])) {
        return 'resume_serve.php?file=' . urlencode(basename($profile['resume_file'])) . '&mode=download';
    }
    return '#';
}

function log_debug($message) {
    $log_dir = __DIR__ . '/uploads/';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . 'resume_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function resolve_resume_file_path($filename) {
    if (empty($filename)) {
        log_debug("resolve_resume_file_path: Filename is empty.");
        return null;
    }
    $filename = basename($filename);
    $search_dirs = [
        __DIR__ . '/uploads/resumes/',
        __DIR__ . '/uploads/secure/',
        __DIR__ . '/uploads/aadhaar/',
        __DIR__ . '/uploads/pan/',
        __DIR__ . '/uploads/profile/',
        __DIR__ . '/uploads/',
        sys_get_temp_dir() . '/imp_uploads/',
    ];
    $checked_paths = [];
    $resolved = null;
    foreach ($search_dirs as $dir) {
        $candidate = $dir . $filename;
        $checked_paths[] = $candidate;
        $real = realpath($candidate);
        $real_dir = realpath($dir);
        if ($real !== false && $real_dir !== false && strncmp($real, $real_dir, strlen($real_dir)) === 0) {
            if (is_file($real)) {
                $resolved = $real;
                break;
            }
        }
    }
    log_debug("resolve_resume_file_path check for filename '$filename':\n- Database path/filename: $filename\n- Checked paths: " . implode(', ', $checked_paths) . "\n- Resolved path: " . ($resolved ?: 'NONE'));
    return $resolved;
}

function check_resume_exists($profile) {
    if (!$profile) {
        return false;
    }
    // Check if resume_url exists and starts with http/https
    if (!empty($profile['resume_url']) && (strpos($profile['resume_url'], 'http://') === 0 || strpos($profile['resume_url'], 'https://') === 0)) {
        return true;
    }
    // Check if resume_file starts with http/https (e.g. Cloudinary url)
    if (!empty($profile['resume_file']) && (strpos($profile['resume_file'], 'http://') === 0 || strpos($profile['resume_file'], 'https://') === 0)) {
        return true;
    }
    // Check if local file exists
    if (!empty($profile['resume_file'])) {
        $path = resolve_resume_file_path($profile['resume_file']);
        if ($path !== null && is_file($path)) {
            return true;
        }
    }
    return false;
}

function print_resume_not_found_js() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            var resumeLink = e.target.closest('[data-resume-exists]');
            if (resumeLink) {
                var exists = resumeLink.getAttribute('data-resume-exists') === 'true';
                if (!exists) {
                    e.preventDefault();
                    e.stopPropagation();
                    showResumeNotFoundModal();
                }
            }
        });
    });

    function showResumeNotFoundModal() {
        var existing = document.getElementById('resume-not-found-modal');
        if (existing) {
            existing.remove();
        }
        var modal = document.createElement('div');
        modal.id = 'resume-not-found-modal';
        modal.className = 'fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-2xl max-w-md w-full border border-slate-100 shadow-2xl p-8 text-center transform scale-95 transition-all duration-300" id="resume-not-found-content">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Resume Not Found</h3>
                <p class="text-slate-600 mb-6 text-sm">Resume file not found. Please re-upload resume.</p>
                <div class="flex flex-col gap-2">
                    <a href="student_profile_form.php" class="w-full py-2.5 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold block text-center shadow-sm">
                        Re-upload Resume
                    </a>
                    <button onclick="document.getElementById('resume-not-found-modal').remove();" class="w-full py-2.5 px-4 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors text-sm font-semibold">
                        Close
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        setTimeout(function() {
            var content = document.getElementById('resume-not-found-content');
            if (content) {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }
        }, 10);
    }
    </script>
    <?php
}
?>
