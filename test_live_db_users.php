<?php
// Connect to Clever Cloud DB
$live_conn = @mysqli_connect("by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com", "ujebqn1hlk9qd98k", "zqPIiSbk9EU6l3KHrvml", "by7xxebmaxfwobqrh1ne", 3306);
if (!$live_conn) {
    echo "[FAILED] Connection to Live Clever Cloud DB: " . mysqli_connect_error() . "\n";
    exit(1);
}
echo "[PASSED] Connected to Live Clever Cloud DB successfully.\n";

// 1. Verify Role Enum Contains 'hod'
$res = mysqli_query($live_conn, "DESCRIBE users");
$role_enum_ok = false;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['Field'] === 'role') {
            if (strpos($row['Type'], "'hod'") !== false) {
                $role_enum_ok = true;
                echo "[PASSED] Role Enum in Live DB contains 'hod'. Definition: {$row['Type']}\n";
            } else {
                echo "[FAILED] Role Enum in Live DB does NOT contain 'hod'. Definition: {$row['Type']}\n";
            }
        }
    }
} else {
    echo "[FAILED] Describe users query failed: " . mysqli_error($live_conn) . "\n";
}

// 2. Verify Restored Live Users exist
$check_emails = [
    'revathi@gmail.com' => 'hr',
    'haritha@gmail.com' => 'student',
    'imp.webportal2026@gmail.com' => 'admin'
];

$sql = "SELECT email, role, status FROM users WHERE email IN ('" . implode("','", array_keys($check_emails)) . "')";
$res_users = mysqli_query($live_conn, $sql);
if ($res_users) {
    $found_count = 0;
    while ($user = mysqli_fetch_assoc($res_users)) {
        $found_count++;
        $email = $user['email'];
        $expected_role = $check_emails[$email];
        $actual_role = $user['role'];
        $status = $user['status'];
        
        // Check Role
        if ($actual_role === $expected_role) {
            echo "[PASSED] Restored User $email has correct role: $actual_role\n";
        } else {
            echo "[FAILED] Restored User $email has incorrect role: actual=$actual_role, expected=$expected_role\n";
        }
        
        // Check Status
        if (strtolower($status) === 'active') {
            echo "[PASSED] Restored User $email is Active\n";
        } else {
            echo "[FAILED] Restored User $email status is: $status\n";
        }
    }
    if ($found_count === 0) {
        echo "[FAILED] Restored production users were not found in the live database.\n";
    } else {
        echo "[PASSED] Found $found_count restored production users in the live database.\n";
    }
} else {
    echo "[FAILED] Select users query failed: " . mysqli_error($live_conn) . "\n";
}
