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
?>
