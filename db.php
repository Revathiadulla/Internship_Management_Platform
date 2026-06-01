<?php

if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {

    // Local XAMPP
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "imp_db";
    $port = 3306;

} else {

    // Live Render + Clever Cloud
    $host = "by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com";
    $user = "ujebqn1hlk9qd98k";
    $pass = "zqPIiSbk9EU6l3KHrvml";
    $db   = "by7xxebmaxfwobqrh1ne";
    $port = 3306;
}

try {
    $conn = mysqli_connect($host, $user, $pass, $db, $port);
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
    mysqli_query($conn, "ALTER TABLE internships ADD COLUMN approved_at TIMESTAMP DEFAULT NULL");
}
unset($_col_check);

// Add workflow columns to internship_applications safely
$app_new_cols = [
    'hod_phone' => "VARCHAR(20) DEFAULT NULL",
    'test_result' => "VARCHAR(100) DEFAULT NULL",
    'hr_review_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hod_approval_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'hod_approval_sent_at' => "TIMESTAMP DEFAULT NULL",
    'hod_approved_at' => "TIMESTAMP DEFAULT NULL",
    'hod_remarks' => "TEXT DEFAULT NULL",
    'selected_by' => "INT DEFAULT NULL",
    'selected_at' => "TIMESTAMP DEFAULT NULL",
    'hod_token' => "VARCHAR(255) DEFAULT NULL"
];
foreach ($app_new_cols as $_col => $_def) {
    $_col_check = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$_col'");
    if ($_col_check && mysqli_num_rows($_col_check) === 0) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $_col $_def");
    }
    unset($_col_check);
}


function checkAndAddToTalentPool($conn, $app_id) {
    $app_id = intval($app_id);
    if ($app_id <= 0) return false;

    // Fetch the application details
    $sql = "SELECT id, status, performance_score, mentor_evaluation, certificate_status, in_talent_pool, user_id FROM internship_applications WHERE id = $app_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
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
            return true;
        } else {
            // If not eligible, set status to No and remove from pool
            mysqli_query($conn, "UPDATE internship_applications SET in_talent_pool = 0, talent_pool_status = 'No' WHERE id = $app_id");
            return false;
        }
    }
    return false;
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
