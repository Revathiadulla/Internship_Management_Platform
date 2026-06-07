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

// 2. Verify User Roles and Passwords
$test_users = [
    'test_hr@example.com' => 'hr',
    'test_hod@example.com' => 'hod',
    'test_student@example.com' => 'student'
];

$sql = "SELECT email, role, status, password FROM users WHERE email IN ('" . implode("','", array_keys($test_users)) . "')";
$res_users = mysqli_query($live_conn, $sql);
if ($res_users) {
    $found_count = 0;
    while ($user = mysqli_fetch_assoc($res_users)) {
        $found_count++;
        $email = $user['email'];
        $expected_role = $test_users[$email];
        $actual_role = $user['role'];
        $status = $user['status'];
        
        // Check Role
        if ($actual_role === $expected_role) {
            echo "[PASSED] User $email has correct role: $actual_role\n";
        } else {
            echo "[FAILED] User $email has incorrect role: actual=$actual_role, expected=$expected_role\n";
        }
        
        // Check Status
        if (strtolower($status) === 'active') {
            echo "[PASSED] User $email has correct status: $status\n";
        } else {
            echo "[FAILED] User $email has incorrect status: $status\n";
        }
        
        // Verify Password
        if (password_verify('Imp@2026', $user['password'])) {
            echo "[PASSED] User $email password successfully verified using password_verify().\n";
        } else {
            echo "[FAILED] User $email password failed verification with password_verify().\n";
        }
    }
    if ($found_count !== 3) {
        echo "[FAILED] Expected to find 3 test users, but found $found_count.\n";
    }
} else {
    echo "[FAILED] Select users query failed: " . mysqli_error($live_conn) . "\n";
}
