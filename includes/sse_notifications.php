<?php
session_start();
include __DIR__ . '/db.php';
include_once __DIR__ . '/auth.php';

// Disable timeout limits for long running script
set_time_limit(0);

if (!is_logged_in()) {
    header('HTTP/1.1 403 Forbidden');
    echo "Unauthorized";
    exit();
}

$user_id = current_user_id();
$role = current_user_role();

// Identify correct notification table
$table = 'student_notifications';
$owner_column = 'user_id';
if ($role === 'mentor') {
    $table = 'mentor_notifications';
    $owner_column = 'mentor_id';
} elseif ($role === 'hr' || $role === 'admin') {
    $table = 'hr_notifications';
    $owner_column = null;
}

// Get starting offset to only stream FUTURE events
if ($owner_column) {
    $init_res = mysqli_query($conn, "SELECT MAX(id) as max_id FROM $table WHERE $owner_column = $user_id");
} else {
    $init_res = mysqli_query($conn, "SELECT MAX(id) as max_id FROM $table");
}
$init_row = mysqli_fetch_assoc($init_res);
$last_id = intval($init_row['max_id'] ?? 0);

// Close session to release write-lock and allow concurrent HTTP requests
session_write_close();

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering on Nginx/IIS if present

// Close initial database connection to free up resources immediately
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// Loop and check for updates
$interval = 30; // Check database every 30 seconds to reduce load

while (true) {
    if (connection_aborted()) {
        break;
    }
    
    // Connect to database dynamically for this check
    try {
        $conn = mysqli_connect($host, $user, $pass, $db, $port);
    } catch (\mysqli_sql_exception $e) {
        $conn = false;
    }
    
    if ($conn) {
        // Check if new notifications have been inserted
        if ($owner_column) {
            $query = "SELECT id, title, type, message, created_at FROM $table WHERE $owner_column = $user_id AND id > $last_id ORDER BY id ASC";
        } else {
            $query = "SELECT id, title, type, message, created_at FROM $table WHERE id > $last_id ORDER BY id ASC";
        }
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $last_id = intval($row['id']);
                
                // Format title
                $title = $row['title'] ?? 'New Notification';
                
                echo "data: " . json_encode([
                    'id' => $row['id'],
                    'title' => $title,
                    'message' => $row['message'],
                    'type' => $row['type'],
                    'created_at' => $row['created_at']
                ]) . "\n\n";
            }
            ob_flush();
            flush();
        }
        
        mysqli_close($conn);
    }
    
    sleep($interval);
}
?>
