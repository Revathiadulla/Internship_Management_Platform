<?php
/**
 * discontinuation_helpers.php
 * Helper functions for discontinuation workflow status tracking and counts.
 */

/**
 * Get internship status counts for dashboard display
 */
function get_internship_status_counts($conn) {
    $counts = [
        'active' => 0,
        'on_hold' => 0,
        'discontinued' => 0,
        'removed' => 0,
        'completed' => 0,
        'total' => 0
    ];

    // Determine which column to use for internship status. Prefer 'internship_status', then 'status', then 'application_status'.
    $status_col = 'internship_status';
    $check_cols = ['internship_status', 'status', 'application_status'];
    $found = null;
    foreach ($check_cols as $col) {
        $safe_col = mysqli_real_escape_string($conn, $col);
        $res = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'internship_applications' AND COLUMN_NAME = '$safe_col'");
        if ($res && mysqli_num_rows($res) > 0) {
            $found = $col;
            break;
        }
    }
    if ($found) {
        $status_col = $found;
    } else {
        // Fallback: use 'status' for compatibility
        $status_col = 'status';
    }

    $statuses = ['Active', 'On Hold', 'Discontinued', 'Removed', 'Completed'];
    foreach ($statuses as $status) {
        $key = strtolower(str_replace(' ', '_', $status));
        $safe_status = mysqli_real_escape_string($conn, $status);
        $sql = "SELECT COUNT(*) as cnt FROM internship_applications WHERE `" . $status_col . "` = '$safe_status'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $counts[$key] = intval($row['cnt'] ?? 0);
            $counts['total'] += $counts[$key];
        }
    }

    return $counts;
}

/**
 * Get pending mentor reports (Reported by Mentor status)
 */
function get_pending_reports_count($conn) {
    // Determine which status column to use (reuse logic)
    $check_cols = ['internship_status', 'status', 'application_status'];
    $status_col = null;
    foreach ($check_cols as $col) {
        $safe_col = mysqli_real_escape_string($conn, $col);
        $res = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'internship_applications' AND COLUMN_NAME = '$safe_col'");
        if ($res && mysqli_num_rows($res) > 0) {
            $status_col = $col;
            break;
        }
    }
    if (!$status_col) {
        $status_col = 'status';
    }
    $safe_status_col = $status_col;
    $sql = "SELECT COUNT(*) as cnt FROM internship_applications WHERE `" . $safe_status_col . "` = 'Reported by Mentor'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return intval($row['cnt'] ?? 0);
    }
    return 0;
}

/**
 * Get status history for an application
 */
function get_status_history($conn, $app_id, $limit = 10) {
    $app_id = intval($app_id);
    $sql = "SELECT * FROM internship_status_history 
            WHERE application_id = $app_id 
            ORDER BY created_at DESC 
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    $history = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
    }
    return $history;
}

/**
 * Get formatted status badge HTML
 */
function get_status_badge($status) {
    $colors = [
        'Active' => ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
        'On Hold' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800'],
        'Discontinued' => ['bg' => 'bg-red-100', 'text' => 'text-red-800'],
        'Removed' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-800'],
        'Completed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'],
        'Reported by Mentor' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
    ];
    
    $style = $colors[$status] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-800'];
    return "<span class='inline-block px-3 py-1 rounded-full text-xs font-bold {$style['bg']} {$style['text']}'>" . 
           htmlspecialchars($status) . 
           "</span>";
}

/**
 * Get report reason icon/color
 */
function get_report_reason_icon($reason) {
    $icons = [
        'No Daily Log Submission' => '📋',
        'Poor Performance' => '📉',
        'Inactive for Long Period' => '⏰',
        'Not Attending Meetings' => '🚫',
        'Requested Withdrawal' => '🚪',
        'Other' => '❓'
    ];
    return $icons[$reason] ?? '❓';
}
?>
