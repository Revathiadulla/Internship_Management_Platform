<?php
$mentor_notifications = __DIR__ . '/../mentor/notifications.php';
if (file_exists($mentor_notifications)) {
    $content = file_get_contents($mentor_notifications);
    $content = str_replace('href="mark_notification_read.php', 'href="../mark_notification_read.php', $content);
    $content = str_replace("fetch('mark_notification_read.php", "fetch('../mark_notification_read.php", $content);
    $content = str_replace('fetch("mark_notification_read.php', 'fetch("../mark_notification_read.php', $content);
    file_put_contents($mentor_notifications, $content);
    echo "Fixed mark_notification_read paths in mentor/notifications.php\n";
}

$mentor_daily_logs = __DIR__ . '/../mentor/daily_logs.php';
if (file_exists($mentor_daily_logs)) {
    $content = file_get_contents($mentor_daily_logs);
    $content = str_replace('href="export_logs.php"', 'href="../export_logs.php"', $content);
    file_put_contents($mentor_daily_logs, $content);
    echo "Fixed export_logs path in mentor/daily_logs.php\n";
}

$student_files = glob(__DIR__ . '/../student/*.php');
foreach ($student_files as $file) {
    $content = file_get_contents($file);
    $newContent = str_replace('"mentor_daily_logs.php?', '"daily_logs.php?', $content);
    $newContent = str_replace("'mentor_daily_logs.php?", "'daily_logs.php?", $newContent);
    if ($content !== $newContent) {
        file_put_contents($file, $newContent);
        echo "Fixed mentor_daily_logs.php links in student/" . basename($file) . "\n";
    }
}

// Update DB notifications
require_once __DIR__ . '/../includes/db.php';
$stmt = $conn->prepare("UPDATE notifications SET link = REPLACE(link, 'mentor_daily_logs.php', 'daily_logs.php') WHERE link LIKE 'mentor_daily_logs.php%'");
if ($stmt) {
    $stmt->execute();
    echo "Updated " . $stmt->affected_rows . " rows in notifications table for daily_logs.\n";
    $stmt->close();
}

$stmt2 = $conn->prepare("UPDATE notifications SET link = REPLACE(link, 'mentor_projects.php', 'projects.php') WHERE link LIKE 'mentor_projects.php%'");
if ($stmt2) {
    $stmt2->execute();
    echo "Updated " . $stmt2->affected_rows . " rows in notifications table for projects.\n";
    $stmt2->close();
}

echo "Done.\n";
